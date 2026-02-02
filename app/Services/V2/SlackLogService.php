<?php

namespace App\Services\V2;

use App\Models\LogSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackLogService
{
    /**
     * Buffer to collect logs during loops to avoid spamming Slack
     * Structure: ['log_type_level' => ['messages' => [], 'context' => [], 'count' => 0]]
     */
    private static $buffer = [];

    /**
     * Flag to indicate if we're in a batch mode (collecting logs)
     */
    private static $batchMode = false;

    /**
     * In-request cache for LogSetting by logType|level to avoid repeated DB queries
     */
    private static $logSettingCache = [];

    /**
     * Rate limiting cache key prefix
     */
    private const RATE_LIMIT_PREFIX = 'slack_log_rate_limit:';

    /**
     * Default rate limit: max 1 message per minute per log type
     * For info/warning: 1 per 5 seconds (stricter to prevent Slack rate limits)
     * For error: 1 per minute (less strict for critical issues)
     */
    private const DEFAULT_RATE_LIMIT_MINUTES = 1;
    private const INFO_RATE_LIMIT_SECONDS = 5; // Stricter for info/warning to prevent 429

    /**
     * Default threshold: only alert after X occurrences
     */
    private const DEFAULT_ALERT_THRESHOLD = 3;

    /**
     * Post log to Slack based on log type and settings
     * Only posts if a matching log setting exists and is enabled
     *
     * IMPORTANT: If called inside a loop, use collectBatch() and postBatch() instead
     *
     * @param string $logType Log type/category (e.g., 'care_api', 'order_sync', 'listing_api')
     * @param string $level Log level ('error', 'warning', 'info', 'debug')
     * @param string $message Log message
     * @param array $context Additional context data (optional)
     * @param bool $allowInLoop Allow posting even if in batch mode (default: false)
     * @param bool $skipSlack Skip Slack API call but still log to files (default: false)
     * @return bool Success status
     */
    public static function post(string $logType, string $level, string $message, array $context = [], bool $allowInLoop = false, bool $skipSlack = false): bool
    {
        // DEBUG: Log entry attempt
        // Log::debug("SlackLogService::post called", [
        //     'log_type' => $logType,
        //     'level' => $level,
        //     'message_preview' => mb_substr($message, 0, 100),
        //     'batch_mode' => self::$batchMode,
        //     'allow_in_loop' => $allowInLoop
        // ]);

        // Check if Slack logging is disabled via environment variable
        if (env('DISABLE_SLACK_LOGS', false)) {
            Log::debug("SlackLogService: Slack logging disabled via DISABLE_SLACK_LOGS env variable - logging to file only", [
                'log_type' => $logType,
                'level' => $level,
            ]);
            // Still log to file (use logType as default, will be determined later if setting exists)
            $logFileName = $logType;
            try {
                $setting = self::getCachedSettingForType($logType, $level);
                if (!$setting) {
                    $setting = LogSetting::getActiveForKeywords($message, $level);
                }
                if ($setting && $setting->channel_name) {
                    $logFileName = $setting->channel_name;
                }
            } catch (\Exception $e) {
                // Ignore errors when trying to get channel name
            }
            self::logToFile($level, $message, $context, $logFileName);
            return true; // Return true since file logging succeeded
        }

        // If in batch mode and not explicitly allowed, collect instead of posting
        if (self::$batchMode && !$allowInLoop) {
            Log::debug("SlackLogService: Collecting to batch (batch mode active)");
            self::collectBatch($logType, $level, $message, $context);
            return true;
        }

        try {
            // Find active log setting for this type and level (cached to avoid repeated DB queries)
            $setting = self::getCachedSettingForType($logType, $level);

            // DEBUG: Log setting lookup result
            if (!$setting) {
                Log::debug("SlackLogService: No setting found for type '{$logType}' and level '{$level}'");
            } else {
                Log::debug("SlackLogService: Found setting", [
                    'setting_name' => $setting->name,
                    'is_enabled' => $setting->is_enabled,
                    'channel_name' => $setting->channel_name,
                    'has_webhook' => !empty($setting->webhook_url)
                ]);
            }

            // If no setting found, also check keyword-based settings (message-dependent, not cached)
            if (!$setting) {
                $setting = LogSetting::getActiveForKeywords($message, $level);
                if ($setting) {
                    Log::debug("SlackLogService: Found keyword-based setting", [
                        'setting_name' => $setting->name
                    ]);
                }
            }

            // Determine log file name: prioritize channel_name from setting, fallback to logType
            $logFileName = $setting && $setting->channel_name
                ? $setting->channel_name
                : $logType;

            // If skipSlack is true, only log to file and return (used for suppressed messages)
            if ($skipSlack) {
                // Log::debug("SlackLogService: Skipping Slack (skipSlack=true) - logging to file only", [
                //     'log_type' => $logType,
                //     'level' => $level,
                //     'channel_name' => $logFileName
                // ]);
                self::logToFile($level, $message, $context, $logFileName);
                return true; // Return true since file logging succeeded
            }

            // If no matching setting found, don't post to Slack (only log to file)
            if (!$setting || !$setting->is_enabled) {
                // DEBUG: Log why not posting
                if (!$setting) {
                    Log::debug("SlackLogService: No matching setting found - logging to file only", [
                        'log_type' => $logType,
                        'level' => $level,
                        'message' => mb_substr($message, 0, 200)
                    ]);
                } else {
                    Log::debug("SlackLogService: Setting found but disabled - logging to file only", [
                        'setting_name' => $setting->name,
                        'is_enabled' => $setting->is_enabled
                    ]);
                }
                // Log to file (default Laravel log + named log file based on logType or channel_name)
                self::logToFile($level, $message, $context, $logFileName);
                return false;
            }

            // Check rate limiting (per channel to prevent cross-channel interference)
            $channelName = $setting->channel_name ?? $logType;
            if (!self::checkRateLimit($logType, $level, $channelName)) {
                Log::debug("SlackLogService: Rate limited - logging to file only", [
                    'log_type' => $logType,
                    'level' => $level,
                    'channel_name' => $channelName
                ]);
                // Still log to file even if rate limited (use channel_name or logType)
                self::logToFile($level, $message, $context, $logFileName);
                return false;
            }

            // Check if channel is blocked due to 429 error
            $rateLimitBlockedKey = 'slack_rate_limit_blocked:' . $channelName;
            $blockedUntil = Cache::get($rateLimitBlockedKey);
            if ($blockedUntil && now()->lt($blockedUntil)) {
                $secondsRemaining = now()->diffInSeconds($blockedUntil);
                Log::debug("SlackLogService: Channel blocked due to previous 429 error - logging to file only", [
                    'log_type' => $logType,
                    'level' => $level,
                    'channel_name' => $channelName,
                    'blocked_until' => $blockedUntil->toDateTimeString(),
                    'seconds_remaining' => $secondsRemaining
                ]);
                // Still log to file
                self::logToFile($level, $message, $context, $logFileName);
                return false;
            }

            // Verify webhook URL exists
            if (empty($setting->webhook_url)) {
                Log::warning("SlackLogService: Webhook URL missing for log setting: {$setting->name}", [
                    'setting_id' => $setting->id,
                    'setting_name' => $setting->name,
                    'channel_name' => $setting->channel_name
                ]);
                self::logToFile($level, $message, $context, $logFileName);
                return false;
            }

            // Format message for Slack using Block Kit for better formatting
            $slackPayload = self::formatSlackBlocks($level, $message, $context, $setting->channel_name, $logType);

            // DEBUG: Log before posting
            Log::debug("SlackLogService: Posting to Slack", [
                'webhook_url_preview' => mb_substr($setting->webhook_url, 0, 50) . '...',
                'channel_name' => $setting->channel_name,
                'payload_size' => strlen(json_encode($slackPayload))
            ]);

            // Post to Slack
            $response = Http::timeout(5)
                ->asJson()
                ->post($setting->webhook_url, $slackPayload);

            // DEBUG: Log response
            Log::debug("SlackLogService: Slack API response", [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body_preview' => mb_substr($response->body(), 0, 200)
            ]);

            if ($response->successful()) {
                // Update rate limit cache
                self::updateRateLimit($logType, $level, $setting->channel_name);
                // Also log to file for backup (use channel_name or logType)
                self::logToFile($level, $message, $context, $logFileName);
                Log::debug("SlackLogService: Successfully posted to Slack");
                return true;
            } elseif ($response->status() === 429) {
                // Handle rate limit (429) - implement exponential backoff
                $rateLimitKey = 'slack_rate_limit_blocked:' . $setting->channel_name;
                $blockedUntil = Cache::get($rateLimitKey);

                if (!$blockedUntil || now()->gt($blockedUntil)) {
                    // Block this channel for 60 seconds to prevent further 429s
                    Cache::put($rateLimitKey, now()->addSeconds(60), now()->addSeconds(60));
                    Log::warning("SlackLogService: Rate limit (429) hit for channel {$setting->channel_name}. Blocking for 60 seconds.", [
                        'response_body' => $response->body(),
                        'log_type' => $logType,
                        'level' => $level,
                        'channel_name' => $setting->channel_name
                    ]);
                }

                // Still log to file
                self::logToFile($level, $message, $context, $logFileName);
                return false;
            } else {
                // If Slack post fails, still log to file
                Log::warning("SlackLogService: Failed to post to Slack channel {$setting->channel_name}. Status: {$response->status()}", [
                    'response_body' => $response->body(),
                    'log_type' => $logType,
                    'level' => $level
                ]);
                self::logToFile($level, $message, $context, $logFileName);
                return false;
            }

        } catch (\Exception $e) {
            // If any error occurs, log to file and log the error
            // Try to get channel_name from setting if available, otherwise use logType
            $logFileName = $logType; // Default to logType
            try {
                $setting = self::getCachedSettingForType($logType, $level);
                if (!$setting) {
                    $setting = LogSetting::getActiveForKeywords($message, $level);
                }
                if ($setting && $setting->channel_name) {
                    $logFileName = $setting->channel_name;
                }
            } catch (\Exception $ex) {
                // Ignore errors when trying to get channel name, use logType as fallback
            }

            Log::error("SlackLogService: Error posting to Slack: " . $e->getMessage(), [
                'log_type' => $logType,
                'level' => $level,
                'message' => $message,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            self::logToFile($level, $message, $context, $logFileName);
            return false;
        }
    }

    /**
     * Collect log for batch posting (use inside loops to avoid spamming Slack)
     *
     * @param string $logType Log type/category
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function collectBatch(string $logType, string $level, string $message, array $context = []): void
    {
        $key = "{$logType}_{$level}";

        if (!isset(self::$buffer[$key])) {
            self::$buffer[$key] = [
                'log_type' => $logType,
                'level' => $level,
                'messages' => [],
                'contexts' => [],
                'count' => 0,
                'first_occurrence' => now(),
                'last_occurrence' => now(),
            ];
        }

        self::$buffer[$key]['messages'][] = $message;
        if (!empty($context)) {
            self::$buffer[$key]['contexts'][] = $context;
        }
        self::$buffer[$key]['count']++;
        self::$buffer[$key]['last_occurrence'] = now();

        // Always log to file immediately (backup)
        // Try to get channel_name from setting, otherwise use logType (cached)
        $logFileName = $logType; // Default to logType
        try {
            $setting = self::getCachedSettingForType($logType, $level);
            if ($setting && $setting->channel_name) {
                $logFileName = $setting->channel_name;
            }
        } catch (\Exception $e) {
            // Ignore errors, use logType as fallback
        }
        self::logToFile($level, $message, $context, $logFileName);
    }

    /**
     * Get LogSetting for type/level with in-request cache to avoid repeated DB queries
     */
    private static function getCachedSettingForType(string $logType, string $level): ?LogSetting
    {
        $key = $logType . '|' . $level;
        if (!array_key_exists($key, self::$logSettingCache)) {
            self::$logSettingCache[$key] = LogSetting::getActiveForType($logType, $level);
        }
        return self::$logSettingCache[$key];
    }

    /**
     * Start batch mode - logs will be collected instead of posted immediately
     */
    public static function startBatch(): void
    {
        self::$batchMode = true;
        self::$buffer = [];
    }

    /**
     * Post all collected batch logs as meaningful summaries
     * Only posts if threshold is met (default: 3 occurrences)
     *
     * @param int $alertThreshold Minimum occurrences to trigger alert (default: 3)
     * @return array Results of batch postings
     */
    public static function postBatch(int $alertThreshold = self::DEFAULT_ALERT_THRESHOLD): array
    {
        $results = [];

        // Check if Slack logging is disabled via environment variable
        if (env('DISABLE_SLACK_LOGS', false)) {
            Log::debug("SlackLogService: Slack logging disabled via DISABLE_SLACK_LOGS env variable - skipping batch post, clearing buffer", [
                'batch_count' => count(self::$buffer)
            ]);
            // Clear buffer and return (logs were already written to files during collectBatch)
            self::$buffer = [];
            self::$batchMode = false;
            return $results;
        }

        foreach (self::$buffer as $key => $batch) {
            $logType = $batch['log_type'];
            $level = $batch['level'];
            $count = $batch['count'];

            if ($count === 0) {
                continue;
            }

            // Only post if threshold is met (prevent noise from single occurrences)
            if ($count < $alertThreshold && $level !== 'error') {
                // Still log to file even if below threshold
                Log::info("SlackLogService: Batch below threshold ({$count} < {$alertThreshold}), skipping Slack post", [
                    'log_type' => $logType,
                    'level' => $level,
                    'count' => $count,
                ]);
                continue;
            }

            // Find setting for batch post (use cache to avoid repeated DB)
            $setting = self::getCachedSettingForType($logType, $level);
            if (!$setting || !$setting->is_enabled || empty($setting->webhook_url)) {
                continue;
            }

            // Check rate limiting for batch summary (per channel)
            $channelName = $setting->channel_name ?? $logType;
            if (!self::checkRateLimit($logType, $level, $channelName)) {
                Log::info("SlackLogService: Batch summary rate limited, skipping", [
                    'log_type' => $logType,
                    'level' => $level,
                    'channel_name' => $channelName,
                ]);
                continue;
            }

            // Check if channel is blocked due to 429 error
            $rateLimitBlockedKey = 'slack_rate_limit_blocked:' . $channelName;
            $blockedUntil = Cache::get($rateLimitBlockedKey);
            if ($blockedUntil && now()->lt($blockedUntil)) {
                Log::info("SlackLogService: Channel blocked due to previous 429 error - skipping batch summary", [
                    'log_type' => $logType,
                    'level' => $level,
                    'channel_name' => $channelName,
                    'blocked_until' => $blockedUntil->toDateTimeString(),
                ]);
                continue;
            }

            // Create meaningful summary with insights
            $slackPayload = self::formatBatchSummaryBlocks($batch, $setting->channel_name);

            // Post batch summary (bypass batch mode to actually post)
            $oldBatchMode = self::$batchMode;
            self::$batchMode = false;

            try {
                $response = Http::timeout(5)
                    ->asJson()
                    ->post($setting->webhook_url, $slackPayload);

                if ($response->successful()) {
                    self::updateRateLimit($logType, $level, $channelName);
                    $results[$key] = true;
                } elseif ($response->status() === 429) {
                    // Handle rate limit (429) - block channel for 60 seconds
                    $rateLimitKey = 'slack_rate_limit_blocked:' . $channelName;
                    Cache::put($rateLimitKey, now()->addSeconds(60), now()->addSeconds(60));
                    Log::warning("SlackLogService: Rate limit (429) hit for channel {$channelName} during batch post. Blocking for 60 seconds.", [
                        'response_body' => $response->body(),
                        'log_type' => $logType,
                        'level' => $level,
                    ]);
                    $results[$key] = false;
                } else {
                    $results[$key] = false;
                }
            } catch (\Exception $e) {
                Log::error("SlackLogService: Error posting batch summary: " . $e->getMessage());
                $results[$key] = false;
            }

            self::$batchMode = $oldBatchMode;
        }

        // Clear buffer after posting
        self::$buffer = [];
        self::$batchMode = false;

        return $results;
    }

    /**
     * Clear batch buffer without posting
     */
    public static function clearBatch(): void
    {
        self::$buffer = [];
        self::$batchMode = false;
    }

    /**
     * Format message using Slack Block Kit for better structure
     */
    private static function formatSlackBlocks(string $level, string $message, array $context, string $channelName, string $logType): array
    {
        $color = self::getColorForLevel($level);
        $emoji = self::getEmojiForLevel($level);
        $priority = self::getPriorityForLevel($level);

        // Extract actionable information from context
        $actionableInfo = self::extractActionableInfo($context, $logType);

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => "{$emoji} {$priority} Alert: " . self::formatTitle($message, $logType),
                ]
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Message:*\n" . self::formatMessageText($message),
                ]
            ]
        ];

        // Add actionable information if available
        if (!empty($actionableInfo['key_metrics'])) {
            $metricsText = "*Key Metrics:*\n";
            foreach ($actionableInfo['key_metrics'] as $key => $value) {
                $metricsText .= "• *{$key}:* `{$value}`\n";
            }
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $metricsText,
                ]
            ];
        }

        // Add suggested actions if available
        if (!empty($actionableInfo['suggested_actions'])) {
            $actionsText = "*Suggested Actions:*\n";
            foreach ($actionableInfo['suggested_actions'] as $action) {
                $actionsText .= "• {$action}\n";
            }
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $actionsText,
                ]
            ];
        }

        // Add relevant context (filtered and formatted)
        if (!empty($actionableInfo['relevant_context'])) {
            $contextText = "*Details:*\n";
            foreach ($actionableInfo['relevant_context'] as $key => $value) {
                $formattedValue = is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value;
                $contextText .= "• *{$key}:* `{$formattedValue}`\n";
            }
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $contextText,
                ]
            ];
        }

        // Add divider and footer
        $blocks[] = ['type' => 'divider'];

        $blocks[] = [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "📊 Type: `{$logType}` | 📍 Channel: #{$channelName} | ⏰ " . now()->format('Y-m-d H:i:s'),
                ]
            ]
        ];

        return [
            'blocks' => $blocks,
            'username' => 'System Monitor',
            'icon_emoji' => $emoji,
        ];
    }

    /**
     * Format batch summary using Slack Block Kit
     */
    private static function formatBatchSummaryBlocks(array $batch, string $channelName): array
    {
        $logType = $batch['log_type'];
        $level = $batch['level'];
        $count = $batch['count'];
        $color = self::getColorForLevel($level);
        $emoji = self::getEmojiForLevel($level);

        // Calculate time span
        $firstOccurrence = is_string($batch['first_occurrence'])
            ? \Carbon\Carbon::parse($batch['first_occurrence'])
            : $batch['first_occurrence'];
        $lastOccurrence = is_string($batch['last_occurrence'])
            ? \Carbon\Carbon::parse($batch['last_occurrence'])
            : $batch['last_occurrence'];

        $timeSpan = $firstOccurrence->diffInSeconds($lastOccurrence);
        $timeSpanText = $timeSpan < 60 ? "{$timeSpan} seconds" : round($timeSpan / 60, 1) . " minutes";

        // Extract insights from batch
        $insights = self::extractBatchInsights($batch);

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => "{$emoji} Batch Summary: {$count} occurrence(s) of {$logType}",
                ]
            ],
            [
                'type' => 'section',
                'fields' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Total Occurrences:*\n`{$count}`",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Level:*\n`{$level}`",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Time Span:*\n`{$timeSpanText}`",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Severity:*\n" . self::getSeverityBadge($count, $level),
                    ],
                ]
            ]
        ];

        // Add key insights
        if (!empty($insights['patterns'])) {
            $patternsText = "*Patterns Detected:*\n";
            foreach ($insights['patterns'] as $pattern) {
                $patternsText .= "• {$pattern}\n";
            }
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $patternsText,
                ]
            ];
        }

        // Add sample messages (limited to most important)
        $sampleMessages = array_slice(array_unique($batch['messages']), 0, 3);
        if (!empty($sampleMessages)) {
            $messagesText = "*Sample Messages:*\n";
            foreach ($sampleMessages as $index => $msg) {
                $truncated = mb_strlen($msg) > 150 ? mb_substr($msg, 0, 150) . '...' : $msg;
                $messagesText .= ($index + 1) . ". `{$truncated}`\n";
            }
            if ($count > 3) {
                $messagesText .= "_... and " . ($count - 3) . " more_";
            }
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $messagesText,
                ]
            ];
        }

        // Add aggregated metrics if available
        if (!empty($insights['metrics'])) {
            $metricsText = "*Aggregated Metrics:*\n";
            foreach ($insights['metrics'] as $key => $value) {
                $metricsText .= "• *{$key}:* `{$value}`\n";
            }
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $metricsText,
                ]
            ];
        }

        // Add suggested actions
        if (!empty($insights['suggested_actions'])) {
            $actionsText = "*Recommended Actions:*\n";
            foreach ($insights['suggested_actions'] as $action) {
                $actionsText .= "• {$action}\n";
            }
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $actionsText,
                ]
            ];
        }

        // Add divider and footer
        $blocks[] = ['type' => 'divider'];

        $blocks[] = [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "📊 Type: `{$logType}` | 📍 Channel: #{$channelName} | ⏰ " . now()->format('Y-m-d H:i:s'),
                ]
            ]
        ];

        return [
            'blocks' => $blocks,
            'username' => 'System Monitor',
            'icon_emoji' => $emoji,
        ];
    }

    /**
     * Extract actionable information from context
     */
    private static function extractActionableInfo(array $context, string $logType): array
    {
        $info = [
            'key_metrics' => [],
            'relevant_context' => [],
            'suggested_actions' => [],
        ];

        // Extract key metrics based on log type
        $keyFields = self::getKeyFieldsForLogType($logType);
        foreach ($keyFields as $field) {
            if (isset($context[$field])) {
                $info['key_metrics'][str_replace('_', ' ', ucwords($field, '_'))] = $context[$field];
            }
        }

        // Filter relevant context (exclude verbose/unnecessary data)
        $relevantFields = ['endpoint', 'status_code', 'error', 'message', 'count', 'id', 'marketplace_id', 'variation_id'];
        foreach ($relevantFields as $field) {
            if (isset($context[$field]) && !isset($info['key_metrics'][$field])) {
                $info['relevant_context'][str_replace('_', ' ', ucwords($field, '_'))] = $context[$field];
            }
        }

        // Generate suggested actions based on log type and context
        $info['suggested_actions'] = self::generateSuggestedActions($logType, $context);

        return $info;
    }

    /**
     * Extract insights from batch data
     */
    private static function extractBatchInsights(array $batch): array
    {
        $insights = [
            'patterns' => [],
            'metrics' => [],
            'suggested_actions' => [],
        ];

        $count = $batch['count'];
        $level = $batch['level'];

        // Detect patterns
        if ($count >= 10) {
            $insights['patterns'][] = "High frequency: {$count} occurrences detected";
        }
        if ($count >= 50) {
            $insights['patterns'][] = "⚠️ Critical: Very high occurrence rate - possible system issue";
        }

        // Aggregate metrics from contexts
        if (!empty($batch['contexts'])) {
            $allKeys = [];
            foreach ($batch['contexts'] as $ctx) {
                $allKeys = array_merge($allKeys, array_keys($ctx));
            }
            $allKeys = array_unique($allKeys);

            foreach ($allKeys as $key) {
                $values = array_column($batch['contexts'], $key);
                $uniqueCount = count(array_unique($values));
                if ($uniqueCount <= 5 && in_array($key, ['endpoint', 'marketplace_id', 'error'])) {
                    $insights['metrics'][str_replace('_', ' ', ucwords($key, '_'))] = implode(', ', array_unique(array_slice($values, 0, 5)));
                }
            }
        }

        // Generate suggested actions
        $insights['suggested_actions'] = self::generateBatchActions($batch);

        return $insights;
    }

    /**
     * Generate suggested actions based on log type and context
     */
    private static function generateSuggestedActions(string $logType, array $context): array
    {
        $actions = [];

        // API-related actions
        if (strpos($logType, 'api') !== false) {
            if (isset($context['status_code'])) {
                if ($context['status_code'] >= 500) {
                    $actions[] = "Check API server status";
                    $actions[] = "Review API logs for errors";
                } elseif ($context['status_code'] == 429) {
                    $actions[] = "Rate limit exceeded - reduce request frequency";
                }
            }
            if (isset($context['endpoint'])) {
                $actions[] = "Verify endpoint: " . $context['endpoint'];
            }
        }

        // Order sync actions
        if (strpos($logType, 'order') !== false) {
            if (isset($context['order_id'])) {
                $actions[] = "Check order: " . $context['order_id'];
            }
            $actions[] = "Review order sync process";
        }

        // Stock sync actions
        if (strpos($logType, 'stock') !== false) {
            if (isset($context['variation_id'])) {
                $actions[] = "Check variation stock: " . $context['variation_id'];
            }
            $actions[] = "Verify stock sync configuration";
        }

        // Listing actions
        if (strpos($logType, 'listing') !== false) {
            $actions[] = "Review listing sync process";
            if (isset($context['marketplace_id'])) {
                $actions[] = "Check marketplace: " . $context['marketplace_id'];
            }
        }

        return $actions;
    }

    /**
     * Generate batch-level suggested actions
     */
    private static function generateBatchActions(array $batch): array
    {
        $actions = [];
        $count = $batch['count'];
        $level = $batch['level'];

        if ($count >= 10) {
            $actions[] = "Investigate root cause - high occurrence rate detected";
        }
        if ($level === 'error' && $count >= 5) {
            $actions[] = "⚠️ Critical: Multiple errors - immediate review required";
        }
        if ($count >= 50) {
            $actions[] = "Consider implementing rate limiting or circuit breaker";
        }

        // Check for patterns in contexts
        if (!empty($batch['contexts'])) {
            $endpoints = array_column($batch['contexts'], 'endpoint');
            if (!empty($endpoints) && count(array_unique($endpoints)) === 1) {
                $actions[] = "Single endpoint affected: " . $endpoints[0];
            }
        }

        return $actions;
    }

    /**
     * Get key fields to extract based on log type
     */
    private static function getKeyFieldsForLogType(string $logType): array
    {
        $fieldMap = [
            'care_api' => ['endpoint', 'status_code', 'care_id'],
            'order_api' => ['endpoint', 'status_code', 'order_id', 'marketplace_id'],
            'order_sync' => ['order_id', 'marketplace_id', 'error'],
            'listing_api' => ['endpoint', 'status_code', 'marketplace_id', 'page'],
            'stock_sync' => ['variation_id', 'marketplace_id', 'old_stock', 'new_stock'],
            'listing_sync' => ['variation_id', 'marketplace_id', 'sku'],
        ];

        return $fieldMap[$logType] ?? ['endpoint', 'status_code', 'error'];
    }

    /**
     * Check rate limiting for log type and channel
     * Uses stricter limits for info/warning to prevent Slack 429 errors
     */
    private static function checkRateLimit(string $logType, string $level, ?string $channelName = null): bool
    {
        // Use channel name if provided, otherwise use log_type+level
        $identifier = $channelName ?? ($logType . '_' . $level);
        $key = self::RATE_LIMIT_PREFIX . $identifier;
        $lastSent = Cache::get($key);

        if ($lastSent) {
            // Stricter rate limiting for info/warning (5 seconds) to prevent Slack 429 errors
            // Errors can still be sent once per minute (less strict for critical issues)
            $rateLimitSeconds = in_array($level, ['info', 'warning', 'debug'])
                ? self::INFO_RATE_LIMIT_SECONDS
                : 60;

            if (now()->diffInSeconds($lastSent) < $rateLimitSeconds) {
                return false;
            }
        }

        return true;
    }

    /**
     * Update rate limit cache
     */
    private static function updateRateLimit(string $logType, string $level, ?string $channelName = null): void
    {
        // Use channel name if provided, otherwise use log_type+level
        $identifier = $channelName ?? ($logType . '_' . $level);
        $key = self::RATE_LIMIT_PREFIX . $identifier;

        // Use appropriate cache duration based on level
        $cacheDuration = in_array($level, ['info', 'warning', 'debug'])
            ? now()->addSeconds(self::INFO_RATE_LIMIT_SECONDS)
            : now()->addMinutes(self::DEFAULT_RATE_LIMIT_MINUTES);

        Cache::put($key, now(), $cacheDuration);
    }

    /**
     * Format message title
     */
    private static function formatTitle(string $message, string $logType): string
    {
        // Extract meaningful title from message (first sentence or key phrase)
        $title = explode('.', $message)[0];
        $title = explode(':', $title)[0];
        $title = mb_substr($title, 0, 50);
        return $title ?: ucfirst($logType) . ' Alert';
    }

    /**
     * Format message text
     */
    private static function formatMessageText(string $message): string
    {
        // Truncate if too long, but keep important info
        if (mb_strlen($message) > 500) {
            return mb_substr($message, 0, 500) . '...';
        }
        return $message;
    }

    /**
     * Get color for log level (for Slack attachments - legacy support)
     */
    private static function getColorForLevel(string $level): string
    {
        return match(strtolower($level)) {
            'error' => 'danger',
            'warning' => 'warning',
            'info' => 'good',
            'debug' => '#808080',
            default => '#808080',
        };
    }

    /**
     * Get emoji for log level
     */
    private static function getEmojiForLevel(string $level): string
    {
        return match(strtolower($level)) {
            'error' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            'debug' => '🔍',
            default => '📝',
        };
    }

    /**
     * Get priority label for log level
     */
    private static function getPriorityForLevel(string $level): string
    {
        return match(strtolower($level)) {
            'error' => 'CRITICAL',
            'warning' => 'HIGH',
            'info' => 'MEDIUM',
            'debug' => 'LOW',
            default => 'INFO',
        };
    }

    /**
     * Get severity badge based on count and level
     */
    private static function getSeverityBadge(int $count, string $level): string
    {
        if ($level === 'error') {
            if ($count >= 50) return '🔴 CRITICAL';
            if ($count >= 10) return '🟠 HIGH';
            return '🟡 MEDIUM';
        }
        if ($count >= 50) return '🟠 HIGH';
        if ($count >= 10) return '🟡 MEDIUM';
        return '🟢 LOW';
    }

    /**
     * Log to file (default Laravel log channel and named log file)
     * Always uses a named log file - prioritizes channel_name, falls back to logType
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @param string|null $logFileName Optional log file name (channel_name or logType). If null, will not create named file.
     */
    private static function logToFile(string $level, string $message, array $context = [], ?string $logFileName = null): void
    {
        // Always log to default Laravel log file for backward compatibility
        match(strtolower($level)) {
            'error' => Log::error($message, $context),
            'warning' => Log::warning($message, $context),
            'info' => Log::info($message, $context),
            'debug' => Log::debug($message, $context),
            default => Log::info($message, $context),
        };

        // Always log to named file if logFileName is provided (prioritized approach)
        if ($logFileName) {
            self::logToNamedFile($level, $message, $context, $logFileName);
        }
    }

    /**
     * Log to a named log file based on channel name or logType
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @param string $logFileName Log file name (channel_name or logType - will be sanitized for filename)
     */
    private static function logToNamedFile(string $level, string $message, array $context, string $logFileName): void
    {
        try {
            // Sanitize log file name for filename (remove special characters, keep alphanumeric, hyphens, underscores)
            $sanitizedFileName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $logFileName);
            $finalLogFileName = 'slack-' . strtolower($sanitizedFileName) . '.log';
            $logFilePath = storage_path('logs/' . $finalLogFileName);

            // Format log entry similar to Laravel's log format
            $timestamp = now()->format('Y-m-d H:i:s');
            $levelUpper = strtoupper($level);

            // Format message with context
            $contextString = '';
            if (!empty($context)) {
                $contextString = ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $logEntry = "[{$timestamp}] local.{$levelUpper}: {$message}{$contextString}" . PHP_EOL;

            // Append to file (create if doesn't exist)
            File::append($logFilePath, $logEntry);
        } catch (\Exception $e) {
            // If writing to named file fails, log error to default log but don't throw
            // This prevents named log file errors from breaking the main logging flow
            Log::warning("SlackLogService: Failed to write to named log file '{$logFileName}': " . $e->getMessage());
        }
    }
}
