<?php

namespace App\Services\V2;

use App\Models\LogSetting;
use Illuminate\Support\Facades\Cache;
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
     * Rate limiting cache key prefix
     */
    private const RATE_LIMIT_PREFIX = 'slack_log_rate_limit:';
    
    /**
     * Default rate limit: max 1 message per minute per log type
     */
    private const DEFAULT_RATE_LIMIT_MINUTES = 1;
    
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
     * @return bool Success status
     */
    public static function post(string $logType, string $level, string $message, array $context = [], bool $allowInLoop = false): bool
    {
        // If in batch mode and not explicitly allowed, collect instead of posting
        if (self::$batchMode && !$allowInLoop) {
            self::collectBatch($logType, $level, $message, $context);
            return true;
        }
        
        try {
            // Find active log setting for this type and level
            $setting = LogSetting::getActiveForType($logType, $level);
            
            // If no setting found, also check keyword-based settings
            if (!$setting) {
                $setting = LogSetting::getActiveForKeywords($message, $level);
            }
            
            // If no matching setting found, don't post to Slack (only log to file)
            if (!$setting || !$setting->is_enabled) {
                // Log to file only (default Laravel log)
                self::logToFile($level, $message, $context);
                return false;
            }
            
            // Check rate limiting
            if (!self::checkRateLimit($logType, $level)) {
                // Still log to file even if rate limited
                self::logToFile($level, $message, $context);
                return false;
            }
            
            // Verify webhook URL exists
            if (empty($setting->webhook_url)) {
                Log::warning("SlackLogService: Webhook URL missing for log setting: {$setting->name}");
                self::logToFile($level, $message, $context);
                return false;
            }
            
            // Format message for Slack using Block Kit for better formatting
            $slackPayload = self::formatSlackBlocks($level, $message, $context, $setting->channel_name, $logType);
            
            // Post to Slack
            $response = Http::timeout(5)
                ->asJson()
                ->post($setting->webhook_url, $slackPayload);
            
            if ($response->successful()) {
                // Update rate limit cache
                self::updateRateLimit($logType, $level);
                // Also log to file for backup
                self::logToFile($level, $message, $context);
                return true;
            } else {
                // If Slack post fails, still log to file
                Log::warning("SlackLogService: Failed to post to Slack channel {$setting->channel_name}. Status: {$response->status()}");
                self::logToFile($level, $message, $context);
                return false;
            }
            
        } catch (\Exception $e) {
            // If any error occurs, log to file and log the error
            Log::error("SlackLogService: Error posting to Slack: " . $e->getMessage(), [
                'log_type' => $logType,
                'level' => $level,
                'message' => $message,
            ]);
            self::logToFile($level, $message, $context);
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
        self::logToFile($level, $message, $context);
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
            
            // Check rate limiting for batch summary
            if (!self::checkRateLimit($logType, $level)) {
                Log::info("SlackLogService: Batch summary rate limited, skipping", [
                    'log_type' => $logType,
                    'level' => $level,
                ]);
                continue;
            }
            
            // Find setting for batch post
            $setting = LogSetting::getActiveForType($logType, $level);
            if (!$setting || !$setting->is_enabled || empty($setting->webhook_url)) {
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
                    self::updateRateLimit($logType, $level);
                    $results[$key] = true;
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
     * Check rate limiting for log type
     */
    private static function checkRateLimit(string $logType, string $level): bool
    {
        $key = self::RATE_LIMIT_PREFIX . $logType . '_' . $level;
        $lastSent = Cache::get($key);
        
        if ($lastSent) {
            // If sent within last minute, rate limit (unless it's a critical error)
            if ($level !== 'error' && now()->diffInSeconds($lastSent) < 60) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Update rate limit cache
     */
    private static function updateRateLimit(string $logType, string $level): void
    {
        $key = self::RATE_LIMIT_PREFIX . $logType . '_' . $level;
        Cache::put($key, now(), now()->addMinutes(self::DEFAULT_RATE_LIMIT_MINUTES));
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
     * Log to file (default Laravel log channel)
     */
    private static function logToFile(string $level, string $message, array $context = []): void
    {
        match(strtolower($level)) {
            'error' => Log::error($message, $context),
            'warning' => Log::warning($message, $context),
            'info' => Log::info($message, $context),
            'debug' => Log::debug($message, $context),
            default => Log::info($message, $context),
        };
    }
}
