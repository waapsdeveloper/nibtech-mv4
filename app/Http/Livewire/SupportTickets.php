<?php

namespace App\Http\Livewire;

use App\Models\Admin_model;
use App\Models\Marketplace_model;
use App\Models\SupportMessage;
use App\Models\SupportTag;
use App\Models\SupportThread;
use App\Services\Support\MarketplaceOrderActionService;
use App\Services\Support\SupportEmailSender;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class SupportTickets extends Component
{
    use WithPagination;

    public $search = '';
    public $status = '';
    public $priority = '';
    public $marketplace = '';
    public $tag = '';
    public $assigned = '';
    public $changeOnly = false;
    public $perPage = 25;
    public $selectedThreadId;
    public $sortField = 'last_external_activity_at';
    public $sortDirection = 'desc';
    public array $messageTranslations = [];
    public array $expandedMessages = [];
    public $replySubject = '';
    public $replyBody = '';
    public $replyRecipient = '';
    public $replyRecipientEmail = '';
    public $replyStatus = null;
    public $replyError = null;
    public $marketplaceOrderUrl = null;
    public $canCancelOrder = false;
    public $orderActionStatus = null;
    public $orderActionError = null;
    public $orderActionPayload = null;
    protected ?int $replyFormThreadId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'priority' => ['except' => ''],
        'marketplace' => ['except' => ''],
        'tag' => ['except' => ''],
        'assigned' => ['except' => ''],
        'changeOnly' => ['except' => false],
        'perPage' => ['except' => 25],
        'sortField' => ['except' => 'last_external_activity_at'],
        'sortDirection' => ['except' => 'desc'],
        'page' => ['except' => 1],
    ];

    protected $listeners = ['supportThreadsUpdated' => '$refresh'];

    public function mount(): void
    {
        $this->perPage = $this->sanitizePerPage($this->perPage);
        $this->sortField = $this->sanitizeSortField($this->sortField);
        $this->sortDirection = $this->sanitizeSortDirection($this->sortDirection);
    }

    public function updated($property, $value): void
    {
        if ($property === 'perPage') {
            $this->perPage = $this->sanitizePerPage($value);
        }

        if ($property === 'sortField') {
            $this->sortField = $this->sanitizeSortField($value);
        }

        if ($property === 'sortDirection') {
            $this->sortDirection = $this->sanitizeSortDirection($value);
        }

        $filters = ['search', 'status', 'priority', 'marketplace', 'tag', 'assigned', 'changeOnly', 'perPage', 'sortField', 'sortDirection'];

        if (in_array($property, $filters, true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->priority = '';
        $this->marketplace = '';
        $this->tag = '';
        $this->assigned = '';
        $this->changeOnly = false;
        $this->sortField = 'last_external_activity_at';
        $this->sortDirection = 'desc';
        $this->perPage = 25;
        $this->selectedThreadId = null;
        $this->messageTranslations = [];
        $this->expandedMessages = [];
        $this->replySubject = '';
        $this->replyBody = '';
        $this->replyRecipient = '';
        $this->replyRecipientEmail = '';
        $this->replyStatus = null;
        $this->replyError = null;
        $this->replyFormThreadId = null;
        $this->marketplaceOrderUrl = null;
        $this->canCancelOrder = false;
        $this->orderActionStatus = null;
        $this->orderActionError = null;
        $this->orderActionPayload = null;
        $this->resetPage();
    }

    public function selectThread(int $threadId): void
    {
        $this->selectedThreadId = $threadId;
        $this->messageTranslations = [];
        $this->expandedMessages = [];
        $this->replyStatus = null;
        $this->replyError = null;
        $this->orderActionStatus = null;
        $this->orderActionError = null;
        $this->orderActionPayload = null;
        $this->hydrateReplyDefaults();
        $this->hydrateOrderContext();
    }

    public function translateMessage(int $messageId, string $target = 'en'): void
    {
        if (! $this->selectedThreadId) {
            return;
        }

        $target = strtolower($target);

        if (isset($this->messageTranslations[$messageId]) && ($this->messageTranslations[$messageId]['target'] ?? null) === $target) {
            return;
        }

        $message = SupportMessage::query()
            ->where('support_thread_id', $this->selectedThreadId)
            ->where('id', $messageId)
            ->first();

        if (! $message) {
            return;
        }

        $existing = data_get($message->metadata, "translations.$target");
        if ($existing) {
            $this->messageTranslations[$messageId] = [
                'target' => $target,
                'text' => $existing,
            ];

            return;
        }

        $payload = $this->prepareTextForTranslation($message);

        if ($payload === '') {
            return;
        }

        try {
            $response = Http::timeout(10)->get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl' => 'auto',
                'tl' => $target,
                'dt' => 't',
                'q' => $payload,
            ]);
        } catch (\Throwable $exception) {
            return;
        }

        if (! $response->ok()) {
            return;
        }

        $translation = $this->extractTranslationText($response->json());

        if (! $translation) {
            return;
        }

        $this->messageTranslations[$messageId] = [
            'target' => $target,
            'text' => $translation,
        ];

        $metadata = $message->metadata ?? [];
        data_set($metadata, "translations.$target", $translation);
        $message->metadata = $metadata;
        $message->save();
    }

    public function clearTranslation(int $messageId): void
    {
        unset($this->messageTranslations[$messageId]);
    }

    public function toggleFullMessage(int $messageId): void
    {
        if (isset($this->expandedMessages[$messageId])) {
            unset($this->expandedMessages[$messageId]);
        } else {
            $this->expandedMessages[$messageId] = true;
        }
    }

    public function render()
    {
        $threads = $this->threads;

        if ($threads->count() === 0) {
            $this->selectedThreadId = null;
            $this->replyFormThreadId = null;
            $this->hydrateOrderContext(null);
        } elseif (! $this->selectedThreadId || ! $threads->pluck('id')->contains($this->selectedThreadId)) {
            $this->selectedThreadId = $threads->first()->id;
            $this->hydrateReplyDefaults($threads->first());
            $this->orderActionStatus = null;
            $this->orderActionError = null;
            $this->orderActionPayload = null;
            $this->hydrateOrderContext($threads->first());
        }

        return view('livewire.support-tickets', [
            'threads' => $threads,
            'selectedThread' => $this->selectedThread,
            'statusOptions' => $this->distinctValues('status'),
            'priorityOptions' => $this->distinctValues('priority'),
            'marketplaceOptions' => Marketplace_model::orderBy('name')->pluck('name', 'id'),
            'tagOptions' => SupportTag::orderBy('name')->get(['id', 'name', 'color']),
            'assigneeOptions' => Admin_model::orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function getThreadsProperty(): LengthAwarePaginator
    {
        $sortable = ['last_external_activity_at', 'priority', 'status', 'created_at'];
        $sortField = in_array($this->sortField, $sortable, true)
            ? $this->sortField
            : 'last_external_activity_at';

        $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return SupportThread::query()
            ->with([
                'tags',
                'order.customer',
                'marketplace',
                'assignee',
                'messages' => function ($query) {
                    $query->latest()->limit(1);
                },
            ])
            ->withCount('messages')
            ->when($this->search !== '', function ($query) {
                $needle = '%' . trim($this->search) . '%';
                $query->where(function ($sub) use ($needle) {
                    $sub->where('order_reference', 'like', $needle)
                        ->orWhere('buyer_email', 'like', $needle)
                        ->orWhere('buyer_name', 'like', $needle)
                        ->orWhere('external_thread_id', 'like', $needle);
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->priority !== '', fn ($query) => $query->where('priority', $this->priority))
            ->when($this->marketplace !== '', fn ($query) => $query->where('marketplace_id', $this->marketplace))
            ->when($this->assigned !== '', fn ($query) => $query->where('assigned_to', $this->assigned))
            ->when($this->changeOnly, fn ($query) => $query->where('change_of_mind', true))
            ->when($this->tag !== '', function ($query) {
                $query->whereHas('tags', fn ($tagQuery) => $tagQuery->where('support_tags.id', $this->tag));
            })
            ->orderBy($sortField, $sortDirection)
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    public function getSelectedThreadProperty(): ?SupportThread
    {
        if (! $this->selectedThreadId) {
            return null;
        }

        return SupportThread::with([
            'messages' => function ($query) {
                $query->reorder('sent_at')->orderBy('id');
            },
            'tags',
            'order.customer',
            'order.order_items.variation',
            'marketplace',
            'assignee',
        ])->find($this->selectedThreadId);
    }

    protected function distinctValues(string $column)
    {
        return SupportThread::query()
            ->select($column)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    }

    protected function sanitizePerPage($value): int
    {
        $value = (int) $value;
        if ($value < 10) {
            return 10;
        }

        if ($value > 100) {
            return 100;
        }

        return $value;
    }

    protected function sanitizeSortField(?string $field): string
    {
        $allowed = ['last_external_activity_at', 'priority', 'status', 'created_at'];

        return in_array($field, $allowed, true) ? $field : 'last_external_activity_at';
    }

    protected function sanitizeSortDirection(?string $direction): string
    {
        return $direction === 'asc' ? 'asc' : 'desc';
    }

    protected function prepareTextForTranslation(SupportMessage $message): string
    {
        $source = '';

        if ($message->clean_body_html !== '') {
            $source = $this->plainTextFromHtml($message->clean_body_html);
        } elseif ($message->body_html) {
            $source = $this->plainTextFromHtml($message->body_html);
        } else {
            $source = $message->body_text ?? '';
        }

        $source = trim(preg_replace('/\s+/', ' ', $source));

        if ($source === '') {
            return '';
        }

        $maxLength = 4500;

        if (mb_strlen($source) > $maxLength) {
            $source = mb_substr($source, 0, $maxLength);
        }

        return $source;
    }

    protected function plainTextFromHtml(string $html): string
    {
        $normalized = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $html);
        $normalized = preg_replace('/<(\/)?(p|div|li|tr|td|h[1-6])[^>]*>/', "\n", $normalized);

        return strip_tags(html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5));
    }

    protected function extractTranslationText($payload): ?string
    {
        if (! is_array($payload) || empty($payload[0])) {
            return null;
        }

        $segments = [];

        foreach ($payload[0] as $part) {
            if (isset($part[0])) {
                $segments[] = $part[0];
            }
        }

        $text = trim(implode('', $segments));

        return $text === '' ? null : $text;
    }

    public function cancelMarketplaceOrder(): void
    {
        $this->orderActionStatus = null;
        $this->orderActionError = null;
        $this->orderActionPayload = null;

        if (! $this->selectedThreadId) {
            $this->orderActionError = 'Select a thread before cancelling an order.';

            return;
        }

        $thread = $this->selectedThread;

        if (! $thread) {
            $this->orderActionError = 'Thread not found.';

            return;
        }

        $service = app(MarketplaceOrderActionService::class);

        if (! $service->supportsCancellation($thread)) {
            $this->orderActionError = 'Marketplace cancellation is not available for this ticket.';

            return;
        }

        $result = $service->cancelOrder($thread);

        if (! ($result['success'] ?? false)) {
            $this->orderActionError = $result['message'] ?? 'Marketplace rejected the cancellation.';
            $this->orderActionPayload = $result;

            return;
        }

        $this->orderActionStatus = $result['message'] ?? 'Marketplace cancellation triggered.';
        $this->orderActionPayload = $result;
    }

    public function sendReply(): void
    {
        $this->replyStatus = null;
        $this->replyError = null;

        if (! $this->selectedThreadId) {
            $this->replyError = 'Select a thread before replying.';

            return;
        }

        $this->validate([
            'replySubject' => ['nullable', 'string', 'max:255'],
            'replyBody' => ['required', 'string', 'min:3'],
        ]);

        $thread = $this->selectedThread;

        if (! $thread) {
            $this->replyError = 'Thread not found.';

            return;
        }

        $recipient = $this->replyRecipientEmail ?: ($thread->reply_email ?? $thread->buyer_email);

        if (! $recipient) {
            $this->replyError = 'This ticket does not have a valid recipient email.';

            return;
        }

        $subject = $this->replySubject ?: $this->defaultReplySubject($thread);
        $body = trim($this->replyBody ?? '');

        if ($body === '') {
            $this->replyError = 'Message body cannot be empty.';

            return;
        }

        try {
            app(SupportEmailSender::class)->sendHtml($recipient, $subject, $this->formatReplyHtml($body));
        } catch (\Throwable $exception) {
            Log::error('Support reply failed', [
                'thread_id' => $thread->id,
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);
            $this->replyError = $exception->getMessage();

            return;
        }

        $author = Admin_model::find(session('user_id'));
        $authorName = $author ? trim($author->first_name . ' ' . $author->last_name) : 'Nib Support';
        $authorEmail = config('mail.from.address', 'no-reply@nibritaintech.com');

        SupportMessage::create([
            'support_thread_id' => $thread->id,
            'direction' => 'outbound',
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'body_text' => $body,
            'body_html' => $this->formatReplyHtml($body),
            'sent_at' => now(),
            'is_internal_note' => false,
            'metadata' => [
                'source' => 'support_portal',
            ],
        ]);

        $thread->last_external_activity_at = now();
        if (! $thread->assigned_to && session('user_id')) {
            $thread->assigned_to = session('user_id');
        }
        $thread->save();

        $this->replyBody = '';
        $this->replyStatus = 'Reply sent via Gmail.';
        $this->messageTranslations = [];
        $this->expandedMessages = [];
        $this->replyFormThreadId = $thread->id;
        $this->emitSelf('supportThreadsUpdated');
    }

    protected function hydrateReplyDefaults(?SupportThread $thread = null): void
    {
        $thread = $thread ?: $this->selectedThread;

        if (! $thread) {
            return;
        }

        if ($this->replyFormThreadId === $thread->id) {
            return;
        }

        $this->replyFormThreadId = $thread->id;
        $this->replyRecipientEmail = $thread->reply_email ?? $thread->buyer_email ?? '';
        $this->replyRecipient = $thread->reply_mailbox_header ?? $this->replyRecipientEmail;
        $this->replySubject = $this->defaultReplySubject($thread);
        $this->replyBody = '';
        $this->replyStatus = null;
        $this->replyError = null;
    }

    protected function hydrateOrderContext(?SupportThread $thread = null): void
    {
        $thread = $thread ?: $this->selectedThread;

        if (! $thread) {
            $this->marketplaceOrderUrl = null;
            $this->canCancelOrder = false;
            $this->orderActionStatus = null;
            $this->orderActionError = null;
            $this->orderActionPayload = null;

            return;
        }

        $service = app(MarketplaceOrderActionService::class);
        $this->marketplaceOrderUrl = $service->buildMarketplaceOrderUrl($thread);
        $this->canCancelOrder = $service->supportsCancellation($thread);
    }

    protected function defaultReplySubject(SupportThread $thread): string
    {
        $base = $thread->order_reference
            ?: ($thread->external_thread_id ? ltrim($thread->external_thread_id, '#') : 'Support Ticket');

        return 'Re: ' . trim($base);
    }

    protected function formatReplyHtml(string $body): string
    {
        $escaped = e($body);
        $withBreaks = nl2br($escaped);

        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.4;">' . $withBreaks . '</div>';
    }
}
