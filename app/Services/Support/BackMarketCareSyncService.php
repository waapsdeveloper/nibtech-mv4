<?php

namespace App\Services\Support;

use App\Http\Controllers\BackMarketAPIController;
use App\Models\Order_model;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class BackMarketCareSyncService
{
    public function __construct(private BackMarketAPIController $api)
    {
    }

    /**
     * Synchronize Back Market Care help requests into local support storage.
     *
     * @param  array{since?: string, params?: array<string, mixed>}  $options
     */
    public function sync(array $options = []): int
    {
        $since = $options['since'] ?? $this->defaultSince();
        $apiParams = $options['params'] ?? [];

        $cases = $this->api->getAllCare($since, $apiParams);

        if (! is_array($cases)) {
            Log::warning('BackMarketCareSyncService: Unexpected payload from Care API', ['since' => $since]);
            return 0;
        }

        $imported = 0;

        foreach ($cases as $case) {
            $case = $this->ensureCaseDetails($case);
            $thread = $this->upsertThread($case);
            $imported += $this->syncMessages($thread, $case);
        }

        return $imported;
    }

    protected function defaultSince(): string
    {
        return now()->subHours(6)->format('Y-m-d-H-i');
    }

    protected function upsertThread(object|array $case): SupportThread
    {
        $caseArr = (array) $case;
        $externalId = (string) data_get($caseArr, 'id');

        $orderReference = (string) data_get($caseArr, 'order_id');
        $order = $orderReference !== ''
            ? Order_model::where('reference_id', $orderReference)->first()
            : null;

        $lastActivity = $this->parseDate(
            data_get($caseArr, 'last_message_date')
            ?? data_get($caseArr, 'last_modification_date')
        );

        $thread = SupportThread::updateOrCreate(
            [
                'marketplace_source' => 'backmarket_care',
                'external_thread_id' => $externalId,
            ],
            [
                'marketplace_id' => 1,
                'order_id' => $order?->id,
                'order_reference' => $orderReference ?: data_get($caseArr, 'orderline'),
                'buyer_name' => $this->buildBuyerName($caseArr),
                'buyer_email' => data_get($caseArr, 'customer_email'),
                'status' => (string) data_get($caseArr, 'state', 'open'),
                'priority' => (string) data_get($caseArr, 'priority'),
                'change_of_mind' => $this->detectChangeOfMind($caseArr),
                'last_external_activity_at' => $lastActivity,
                'last_synced_at' => now(),
                'metadata' => $caseArr,
            ]
        );

        return $thread;
    }

    protected function syncMessages(SupportThread $thread, object|array $case): int
    {
        $messages = Arr::wrap(data_get($case, 'messages', []));

        if (empty($messages)) {
            return 0;
        }

        $imported = 0;

        foreach ($messages as $message) {
            $messageArr = (array) $message;
            $externalId = (string) data_get($messageArr, 'id');

            if ($externalId === '' && SupportMessage::where('support_thread_id', $thread->id)->count() > 200) {
                continue;
            }

            $sentAt = $this->parseDate(data_get($messageArr, 'date') ?? data_get($messageArr, 'created_at'));

            $record = SupportMessage::updateOrCreate(
                [
                    'support_thread_id' => $thread->id,
                    'external_message_id' => $externalId ?: null,
                ],
                [
                    'direction' => $this->resolveDirection($messageArr),
                    'author_name' => data_get($messageArr, 'author_name') ?? data_get($messageArr, 'author'),
                    'author_email' => data_get($messageArr, 'author_email'),
                    'body_text' => data_get($messageArr, 'body') ?? data_get($messageArr, 'message'),
                    'body_html' => data_get($messageArr, 'body_html'),
                    'attachments' => data_get($messageArr, 'attachments'),
                    'sent_at' => $sentAt,
                    'is_internal_note' => (bool) data_get($messageArr, 'internal'),
                    'metadata' => $messageArr,
                ]
            );

            if ($record->wasRecentlyCreated || $record->wasChanged()) {
                $imported++;
            }
        }

        return $imported;
    }

    protected function buildBuyerName(array $case): ?string
    {
        $first = data_get($case, 'customer_firstname') ?? data_get($case, 'customer_firstname', data_get($case, 'firstname'));
        $last = data_get($case, 'customer_lastname') ?? data_get($case, 'customer_lastname', data_get($case, 'lastname'));

        $full = trim($first . ' ' . $last);

        return $full !== '' ? $full : null;
    }

    protected function detectChangeOfMind(array $case): bool
    {
        $reason = strtolower((string) data_get($case, 'reason_code'));
        $topic = strtolower((string) data_get($case, 'topic'));
        $summary = strtolower((string) data_get($case, 'summary'));

        return str_contains($reason, 'change_of_mind')
            || str_contains($reason, 'buyerchange')
            || str_contains($topic, 'change of mind')
            || str_contains($summary, 'change of mind');
    }

    protected function resolveDirection(array $message): string
    {
        $authorType = strtolower((string) data_get($message, 'author_type'));

        if ($authorType === 'seller' || $authorType === 'merchant') {
            return 'outbound';
        }

        if ($authorType === 'customer' || $authorType === 'buyer') {
            return 'inbound';
        }

        return data_get($message, 'internal') ? 'internal' : 'inbound';
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function ensureCaseDetails(object|array $case): object|array
    {
        $messages = Arr::wrap(data_get($case, 'messages', []));
        if (! empty($messages)) {
            return $case;
        }

        $caseId = data_get($case, 'id');
        if (! $caseId) {
            return $case;
        }

        try {
            $detailed = $this->api->getCare($caseId);
            if ($detailed) {
                return $detailed;
            }
        } catch (\Throwable $e) {
            Log::warning('BackMarketCareSyncService: Failed to hydrate Care case', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);
        }

        return $case;
    }
}
