<?php

namespace App\Services\Support;

use App\Models\Order_model;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Services\RefurbedZendeskMailboxService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class RefurbedMailboxSyncService
{
    public function __construct(private RefurbedZendeskMailboxService $mailbox)
    {
    }

    /**
     * Fetch Gmail messages and persist them as SupportThreads/Messages.
     *
     * @param  array{labelIds?: array<int,string>, maxResults?: int, query?: string}  $options
     */
    public function sync(array $options = []): int
    {
        $result = $this->mailbox->fetchMessages(
            $options + [
                'include_body' => true,
            ]
        );

        $messages = $result['messages'] ?? [];

        $imported = 0;

        foreach ($messages as $message) {
            $message = (array) $message;

            $thread = $this->upsertThread($message);
            $imported += $this->storeMessage($thread, $message);
        }

        return $imported;
    }

    protected function upsertThread(array $message): SupportThread
    {
        $ticketId = $message['ticketId'] ?? null;
        $externalThreadId = $ticketId ?: ($message['threadId'] ?? $message['id']);

        $orderReference = $this->extractOrderReference($message);
        $order = $orderReference ? Order_model::where('reference_id', $orderReference)->first() : null;

        $buyer = $this->splitEmailAddress($message['from'] ?? '');

        $thread = SupportThread::updateOrCreate(
            [
                'marketplace_source' => 'refurbed_mail',
                'external_thread_id' => (string) $externalThreadId,
            ],
            [
                'marketplace_id' => 4,
                'order_id' => $order?->id,
                'order_reference' => $orderReference,
                'buyer_name' => $buyer['name'],
                'buyer_email' => $buyer['email'],
                'status' => 'open',
                'priority' => null,
                'change_of_mind' => $this->detectChangeOfMind($message),
                'last_external_activity_at' => $this->parseDate($message['date'] ?? null),
                'last_synced_at' => now(),
                'metadata' => [
                    'ticket_id' => $ticketId,
                    'gmail_id' => $message['id'] ?? null,
                    'label_ids' => $message['labelIds'] ?? [],
                ],
            ]
        );

        return $thread;
    }

    protected function storeMessage(SupportThread $thread, array $message): int
    {
        $sentAt = $this->parseDate($message['date'] ?? null);

        $record = SupportMessage::updateOrCreate(
            [
                'support_thread_id' => $thread->id,
                'external_message_id' => $message['id'] ?? null,
            ],
            [
                'direction' => $this->inferDirection($message),
                'author_name' => $this->splitEmailAddress($message['from'] ?? '')['name'],
                'author_email' => $this->splitEmailAddress($message['from'] ?? '')['email'],
                'body_text' => $message['bodyText'] ?? null,
                'body_html' => $message['bodyHtml'] ?? null,
                'attachments' => $message['attachments'] ?? null,
                'sent_at' => $sentAt,
                'metadata' => Arr::only($message, ['subject', 'snippet', 'ticketLink']),
            ]
        );

        return ($record->wasRecentlyCreated || $record->wasChanged()) ? 1 : 0;
    }

    protected function extractOrderReference(array $message): ?string
    {
        $subject = strtolower((string) ($message['subject'] ?? ''));
        $snippet = strtolower((string) ($message['snippet'] ?? ''));

        if (preg_match('/ref(erence)?\s*(#|id)?\s*(\d{5,})/', $subject, $matches)) {
            return $matches[3];
        }

        if (preg_match('/order\s*(#|id)?\s*(\d{5,})/', $subject . ' ' . $snippet, $matches)) {
            return $matches[2];
        }

        return null;
    }

    protected function splitEmailAddress(string $raw): array
    {
        if ($raw === '') {
            return ['name' => null, 'email' => null];
        }

        if (preg_match('/^(.*?)<([^>]+)>$/', $raw, $matches)) {
            return [
                'name' => trim($matches[1], '\" '),
                'email' => strtolower(trim($matches[2])),
            ];
        }

        return ['name' => null, 'email' => strtolower(trim($raw))];
    }

    protected function detectChangeOfMind(array $message): bool
    {
        $text = strtolower(($message['bodyText'] ?? '') . ' ' . ($message['subject'] ?? ''));
        return str_contains($text, 'change of mind') || str_contains($text, 'buyer remorse');
    }

    protected function inferDirection(array $message): string
    {
        $from = strtolower($message['from'] ?? '');
        if (str_contains($from, 'refurbed') || str_contains($from, 'zendesk')) {
            return 'inbound';
        }

        if (str_contains($from, 'nibritaintech.com')) {
            return 'outbound';
        }

        return 'inbound';
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            Log::debug('RefurbedMailboxSyncService: Unable to parse date', ['value' => $value]);
            return null;
        }
    }
}
