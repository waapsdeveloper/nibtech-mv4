<?php

namespace App\Services;

use App\Models\Order_model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RefurbedZendeskTicketLinkService
{
    private const REFURBED_MARKETPLACE_ID = 4;
    private const CACHE_PREFIX = 'refurbed_ticket_gmail_';

    public function __construct(
        protected RefurbedZendeskMailboxService $mailboxService
    ) {
    }

    /**
     * Automatically link Refurbed Zendesk tickets to order items.
     *
     * @param  array  $options
     * @return array<string, mixed>
     */
    public function autoLink(array $options = []): array
    {
        $query = $options['query'] ?? $this->defaultQuery();
        $labelIds = $options['labelIds'] ?? $this->defaultLabelIds();
        $maxResults = (int) ($options['maxResults'] ?? $this->defaultMaxResults());
        $maxAgeMinutes = (int) ($options['max_age_minutes'] ?? $this->defaultMaxAgeMinutes());
        $maxPages = (int) ($options['max_pages'] ?? $this->defaultMaxPages());
        $force = (bool) ($options['force'] ?? false);

        $stats = [
            'processed' => 0,
            'linked' => 0,
            'skipped' => 0,
            'ignored' => 0,
            'details' => [],
        ];

        $pageToken = null;
        $pageCount = 0;
        $lastResultSizeEstimate = null;

        do {
            $result = $this->mailboxService->fetchMessages([
                'labelIds' => $labelIds,
                'maxResults' => $maxResults,
                'query' => $query,
                'pageToken' => $pageToken,
                'include_body' => true,
            ]);

            $pageCount++;
            $messages = $result['messages'] ?? [];
            $lastResultSizeEstimate = $result['resultSizeEstimate'] ?? null;

            foreach ($messages as $message) {
                $stats['processed']++;
                $messageId = $message['id'];

                if (! $force && $this->hasProcessedMessage($messageId)) {
                    $stats['ignored']++;
                    continue;
                }

                $messageAge = $this->minutesSince($message['date'] ?? null);
                if ($maxAgeMinutes > 0 && $messageAge !== null && $messageAge > $maxAgeMinutes) {
                    $this->markProcessedMessage($messageId, 'expired');
                    $stats['skipped']++;
                    continue;
                }

                $ticketId = $message['ticketId'] ?? null;
                if (! $ticketId) {
                    $this->markProcessedMessage($messageId, 'no_ticket');
                    $stats['skipped']++;
                    continue;
                }

                $orderReference = $this->detectOrderReference($message);
                if (! $orderReference) {
                    $this->markProcessedMessage($messageId, 'no_order_reference');
                    $stats['skipped']++;
                    continue;
                }

                $order = Order_model::with('order_items')
                    ->where('reference_id', $orderReference)
                    ->where('marketplace_id', self::REFURBED_MARKETPLACE_ID)
                    ->first();

                if (! $order) {
                    $this->markProcessedMessage($messageId, 'order_not_found', [
                        'order_reference' => $orderReference,
                    ]);
                    $stats['details'][] = [
                        'message_id' => $messageId,
                        'ticket_id' => $ticketId,
                        'order_reference' => $orderReference,
                        'status' => 'order_not_found',
                    ];
                    $stats['skipped']++;
                    continue;
                }

                $orderItemReference = $this->detectOrderItemReference($message);
                $updated = $this->linkTicketToOrderItems($order, $orderItemReference, $ticketId);

                $stats['details'][] = [
                    'message_id' => $messageId,
                    'ticket_id' => $ticketId,
                    'order_reference' => $orderReference,
                    'order_item_reference' => $orderItemReference,
                    'status' => $updated > 0 ? 'linked' : 'no_target',
                    'updated' => $updated,
                ];

                if ($updated > 0) {
                    $stats['linked'] += $updated;
                    $this->markProcessedMessage($messageId, 'linked', [
                        'order_reference' => $orderReference,
                        'order_item_reference' => $orderItemReference,
                        'updated' => $updated,
                    ]);
                } else {
                    $this->markProcessedMessage($messageId, 'no_target', [
                        'order_reference' => $orderReference,
                        'order_item_reference' => $orderItemReference,
                    ]);
                    $stats['skipped']++;
                }
            }

            $pageToken = $result['nextPageToken'] ?? null;
            $shouldContinue = $pageToken !== null && ($maxPages <= 0 || $pageCount < $maxPages);
        } while ($shouldContinue);

        $stats['pages_processed'] = $pageCount;
        $stats['result_size_estimate'] = $lastResultSizeEstimate;
        $stats['max_results_per_page'] = $maxResults;

        // Log::info('Refurbed Zendesk auto-link summary', [
        //     'processed' => $stats['processed'],
        //     'linked' => $stats['linked'],
        //     'skipped' => $stats['skipped'],
        //     'ignored' => $stats['ignored'],
        //     'query' => $query,
        //     'labelIds' => $labelIds,
        //     'maxResults' => $maxResults,
        //     'pages_processed' => $pageCount,
        //     'result_size_estimate' => $lastResultSizeEstimate,
        // ]);

        return $stats;
    }

    protected function detectOrderReference(array $message): ?string
    {
        $text = $this->composeSearchText($message);

        foreach ($this->orderReferencePatterns() as $pattern) {
            if (! preg_match_all($pattern, $text, $matches)) {
                continue;
            }

            foreach ($matches[1] as $candidate) {
                $normalized = $this->normalizeReference($candidate, 5, 5);
                if ($normalized) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    protected function detectOrderItemReference(array $message): ?string
    {
        $text = $this->composeSearchText($message);

        foreach ($this->orderItemReferencePatterns() as $pattern) {
            if (! preg_match_all($pattern, $text, $matches)) {
                continue;
            }

            foreach ($matches[1] as $candidate) {
                $normalized = $this->normalizeReference($candidate, 4, 4);
                if ($normalized) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    protected function linkTicketToOrderItems(Order_model $order, ?string $orderItemReference, string $ticketId): int
    {
        $orderItems = $order->order_items;
        if ($orderItems->isEmpty()) {
            return 0;
        }

        if ($orderItemReference) {
            $items = $orderItems->where('reference_id', $orderItemReference);
            if ($items->isEmpty()) {
                return 0;
            }
        } else {
            if ($orderItems->count() === 1) {
                $items = $orderItems;
            } else {
                $items = $orderItems->filter(function ($item) use ($ticketId) {
                    return empty($item->care_id) || $item->care_id === $ticketId;
                });

                if ($items->count() === 1) {
                    // use the single eligible item
                } else {
                    $items = $items->whereNull('care_id');
                    if ($items->count() !== 1) {
                        return 0; // ambiguous, require manual linking
                    }
                }
            }
        }

        $updated = 0;
        foreach ($items as $item) {
            if ($item->care_id && $item->care_id !== $ticketId) {
                continue;
            }

            if ($item->care_id === $ticketId) {
                continue;
            }

            $item->care_id = $ticketId;
            $item->save();
            $updated++;
        }

        return $updated;
    }

    protected function composeSearchText(array $message): string
    {
        $parts = [
            $message['subject'] ?? '',
            $message['snippet'] ?? '',
            $message['bodyText'] ?? '',
        ];

        if (! empty($message['bodyHtml'])) {
            $parts[] = html_entity_decode(strip_tags($message['bodyHtml']));
        }

        return strtolower(trim(implode("\n", array_filter($parts))));
    }

    protected function normalizeReference(?string $value, int $minDigits, int $minLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        $value = trim($value, "[](){}<>.,;#:");
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        if (strlen($value) < $minLength || strlen($digits) < $minDigits) {
            return null;
        }

        return strtoupper($value);
    }

    protected function minutesSince(?string $dateHeader): ?int
    {
        if (! $dateHeader) {
            return null;
        }

        try {
            $date = Carbon::parse($dateHeader);
        } catch (\Exception $e) {
            return null;
        }

        return $date->diffInMinutes(now());
    }

    protected function hasProcessedMessage(string $messageId): bool
    {
        return Cache::has(self::CACHE_PREFIX . $messageId);
    }

    protected function markProcessedMessage(string $messageId, string $status, array $context = []): void
    {
        Cache::put(self::CACHE_PREFIX . $messageId, array_merge($context, [
            'status' => $status,
            'timestamp' => now()->toDateTimeString(),
        ]), now()->addDays(14));
    }

    protected function defaultQuery(): string
    {
        return config('services.refurbed.gmail_ticket_query', 'subject:"refurbed inquiry" OR from:refurbed-merchant.zendesk.com');
    }

    protected function defaultLabelIds(): array
    {
        return config('services.refurbed.gmail_ticket_labels', ['INBOX']);
    }

    protected function defaultMaxResults(): int
    {
        return (int) config('services.refurbed.gmail_ticket_max_results', 50);
    }

    protected function defaultMaxAgeMinutes(): int
    {
        return (int) config('services.refurbed.gmail_ticket_max_age_minutes', 1440);
    }

    protected function defaultMaxPages(): int
    {
        return (int) config('services.refurbed.gmail_ticket_max_pages', 0);
    }

    protected function orderReferencePatterns(): array
    {
        return [
            '/Refurbed\s+Order\s*(?:ID|Number|No\.|#)?\s*(?:=|:)?\s*([A-Z0-9\-]+)/i',
            '/Order\s*(?:ID|Number|Reference|No\.|#)\s*(?:=|:|#:)?\s*([A-Z0-9\-]+)/i',
            '/order[_-]?id\s*(?:=|:)?\s*([A-Z0-9\-]+)/i',
            '/orderId%3D([A-Z0-9\-]+)/i',
            '/orders\/([A-Z0-9\-]+)/i',
            '/Bestell(?:ung|nummer|nr\.)\s*(?:=|:)?\s*([A-Z0-9\-]+)/i',
        ];
    }

    protected function orderItemReferencePatterns(): array
    {
        return [
            '/Order\s*(?:Item|Line)\s*(?:ID|Number|No\.|#)?\s*(?:=|:|#:)?\s*([A-Z0-9\-]+)/i',
            '/order[_-]?item[_-]?id\s*(?:=|:)?\s*([A-Z0-9\-]+)/i',
            '/orderLine[_-]?id\s*(?:=|:)?\s*([A-Z0-9\-]+)/i',
            '/orderline\s*(?:=|:|#:)?\s*([A-Z0-9\-]+)/i',
            '/Line\s*ID\s*(?:=|:)?\s*([A-Z0-9\-]+)/i',
            '/Bestell(?:position|zeile|nr\.)\s*(?:=|:)?\s*([A-Z0-9\-]+)/i',
            '/Artikel(?:nummer|nr\.)\s*(?:=|:)?\s*([A-Z0-9\-]+)/i',
        ];
    }
}
