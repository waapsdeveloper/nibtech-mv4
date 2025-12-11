<?php

namespace App\Http\Livewire;

use App\Http\Controllers\BackMarketAPIController;
use App\Http\Controllers\GoogleController;
use App\Mail\InvoiceMail;
use App\Mail\RefundInvoiceMail;
use App\Models\Admin_model;
use App\Models\Marketplace_model;
use App\Models\Order_item_model;
use App\Models\Order_model;
use App\Models\SupportMessage;
use App\Models\SupportTag;
use App\Models\SupportThread;
use App\Services\Support\MarketplaceOrderActionService;
use App\Services\Support\SupportEmailSender;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class SupportTickets extends Component
{
    use WithPagination;

    public $search = '';
    public $status = '';
    public $marketplace = '';
    public $assigned = '';
    public $perPage = 25;
    public $selectedThreadId;
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
    public $ticketActionStatus = null;
    public $ticketActionError = null;
    public $invoiceActionStatus = null;
    public $invoiceActionError = null;
    public $showPartialRefundModal = false;
    public $selectedOrderItems = [];
    public $partialRefundAmount = '';
    public $syncStatus = null;
    public $syncError = null;
    public $syncLookback = '6';
    public $syncBackmarket = true;
    public $syncRefurbed = true;
    public $careFolderDetails = null;
    public $careFolderMessages = [];
    public $careFolderError = null;
    protected ?int $replyFormThreadId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => ''],
        'marketplace' => ['except' => ''],
        'assigned' => ['except' => ''],
        'perPage' => ['except' => 25],
        'page' => ['except' => 1],
    ];

    protected $listeners = ['supportThreadsUpdated' => '$refresh'];

    public function mount(): void
    {
        $this->perPage = $this->sanitizePerPage($this->perPage);
        $this->syncLookback = $this->sanitizeSyncLookback($this->syncLookback);
    }

    public function updated($property, $value): void
    {
        if ($property === 'perPage') {
            $this->perPage = $this->sanitizePerPage($value);
        }

        if ($property === 'syncLookback') {
            $this->syncLookback = $this->sanitizeSyncLookback($value);
        }

        $filters = ['search', 'status', 'marketplace', 'assigned', 'perPage'];

        if (in_array($property, $filters, true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->marketplace = '';
        $this->assigned = '';
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
        $this->ticketActionStatus = null;
        $this->ticketActionError = null;
        $this->invoiceActionStatus = null;
        $this->invoiceActionError = null;
        $this->syncStatus = null;
        $this->syncError = null;
        $this->syncLookback = '6';
        $this->syncBackmarket = true;
        $this->syncRefurbed = true;
        $this->careState = '';
        $this->carePriority = '';
        $this->careTopic = '';
        $this->careOrderline = '';
        $this->careOrderId = '';
        $this->careLastId = '';
        $this->carePageSize = '50';
        $this->careExtraQuery = '';
        $this->careFolderDetails = null;
        $this->careFolderMessages = [];
        $this->careFolderError = null;
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
        $this->ticketActionStatus = null;
        $this->ticketActionError = null;
        $this->invoiceActionStatus = null;
        $this->invoiceActionError = null;
        $this->syncStatus = null;
        $this->syncError = null;
        $this->careFolderDetails = null;
        $this->careFolderMessages = [];
        $this->careFolderError = null;
        $this->hydrateReplyDefaults();
        $this->hydrateOrderContext();
        $this->hydrateCareFolder();
    }

    public function fetchCareFolder(): void
    {
        $this->hydrateCareFolder();
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
            $this->ticketActionStatus = null;
            $this->ticketActionError = null;
            $this->invoiceActionStatus = null;
            $this->invoiceActionError = null;
            $this->careFolderDetails = null;
            $this->careFolderMessages = [];
            $this->careFolderError = null;
        } elseif (! $this->selectedThreadId || ! $threads->pluck('id')->contains($this->selectedThreadId)) {
            $this->selectedThreadId = $threads->first()->id;
            $this->hydrateReplyDefaults($threads->first());
            $this->orderActionStatus = null;
            $this->orderActionError = null;
            $this->orderActionPayload = null;
            $this->ticketActionStatus = null;
            $this->ticketActionError = null;
            $this->invoiceActionStatus = null;
            $this->invoiceActionError = null;
            $this->syncStatus = null;
            $this->syncError = null;
            $this->hydrateOrderContext($threads->first());
            $this->hydrateCareFolder($threads->first());
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
                    $sub->where('order_reference', 'LIKE', $needle)
                        ->orWhere('buyer_email', 'LIKE', $needle)
                        ->orWhere('buyer_name', 'LIKE', $needle)
                        ->orWhere('external_thread_id', 'LIKE', $needle);
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->marketplace !== '', fn ($query) => $query->where('marketplace_id', $this->marketplace))
            ->when($this->assigned !== '', fn ($query) => $query->where('assigned_to', $this->assigned))
            ->orderByDesc('last_external_activity_at')
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

    public function markThreadSolved(): void
    {
        $this->ticketActionStatus = null;
        $this->ticketActionError = null;

        if (! $this->selectedThreadId) {
            $this->ticketActionError = 'Select a thread before marking as solved.';

            return;
        }

        $thread = $this->selectedThread;

        if (! $thread) {
            $this->ticketActionError = 'Thread not found.';

            return;
        }

        if (strcasecmp((string) $thread->status, 'solved') === 0) {
            $this->ticketActionStatus = 'Ticket is already marked as solved.';

            return;
        }

        $thread->status = 'solved';
        $thread->last_external_activity_at = now();

        if (! $thread->assigned_to && session('user_id')) {
            $thread->assigned_to = session('user_id');
        }

        $thread->save();

        $this->ticketActionStatus = 'Ticket marked as solved.';
        $this->emitSelf('supportThreadsUpdated');
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
            $this->invoiceActionStatus = null;
            $this->invoiceActionError = null;

            return;
        }

        $service = app(MarketplaceOrderActionService::class);
        $this->marketplaceOrderUrl = $service->buildMarketplaceOrderUrl($thread);
        $this->canCancelOrder = $service->supportsCancellation($thread);
    }

    protected function hydrateCareFolder(?SupportThread $thread = null): void
    {
        $thread = $thread ?: $this->selectedThread;

        $this->careFolderDetails = null;
        $this->careFolderMessages = [];
        $this->careFolderError = null;

        if (! $thread || $thread->marketplace_source !== 'backmarket_care') {
            return;
        }

        $folderId = $thread->external_thread_id ?: data_get($thread->metadata, 'id');

        if (! $folderId) {
            $this->careFolderError = 'Care folder id missing on this ticket.';

            return;
        }

        try {
            $controller = app(BackMarketAPIController::class);
            $folder = $controller->getCare($folderId);
        } catch (\Throwable $e) {
            Log::warning('SupportTickets: Care folder fetch failed', [
                'thread_id' => $thread->id,
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
            ]);
            $this->careFolderError = 'Unable to load Back Market Care details.';

            return;
        }

        if (! $folder) {
            $this->careFolderError = 'Care API returned an empty response.';

            return;
        }

        if (isset($folder->results)) {
            $folder = $folder->results;
        }

        $folderArray = $this->convertToArray($folder);

        if (empty($folderArray)) {
            $this->careFolderError = 'Care API response could not be parsed.';

            return;
        }

        $this->careFolderDetails = $this->normalizeCareFolder($folderArray);

        $messages = data_get($folderArray, 'messages', []);
        if (! is_array($messages)) {
            $messages = $this->convertToArray($messages);
        }

        $this->careFolderMessages = collect($messages)
            ->filter()
            ->map(fn ($message) => $this->normalizeCareMessage($this->convertToArray($message)))
            ->values()
            ->all();
    }

    protected function normalizeCareFolder(array $folder): array
    {
        $orderId = $this->preferCareValue([
            data_get($folder, 'order_id'),
            data_get($folder, 'order.order_id'),
            data_get($folder, 'orderline.order_id'),
            data_get($folder, 'orderline.order.order_id'),
            data_get($folder, 'lines.0.order_id'),
        ]);

        $orderline = $this->preferCareValue([
            data_get($folder, 'orderline'),
            data_get($folder, 'orderline.id'),
            data_get($folder, 'orderline.orderline_id'),
            data_get($folder, 'lines.0'),
            data_get($folder, 'lines.0.id'),
        ]);

        $topic = $this->preferCareScalar([
            data_get($folder, 'topic'),
            data_get($folder, 'topic.name'),
            data_get($folder, 'topic.label'),
            data_get($folder, 'lines.0.issues.0.customerIssue'),
        ]);

        $reason = $this->preferCareScalar([
            data_get($folder, 'reason_code'),
            data_get($folder, 'reason'),
            data_get($folder, 'lines.0.issues.0.tag'),
        ]);

        $priority = $this->preferCareScalar([
            data_get($folder, 'priority'),
            data_get($folder, 'priority.level'),
            data_get($folder, 'priority.label'),
        ]);

        $state = $this->preferCareScalar([
            data_get($folder, 'state'),
            data_get($folder, 'status'),
        ]);

        $summary = $this->preferCareScalar([
            data_get($folder, 'summary'),
            data_get($folder, 'subject'),
        ]);

        $createdAt = $this->preferCareScalar([
            data_get($folder, 'created_at'),
            data_get($folder, 'creation_date'),
            data_get($folder, 'date_creation'),
        ]);

        $lastMessageAt = $this->preferCareScalar([
            data_get($folder, 'last_message_date'),
            data_get($folder, 'last_message_at'),
            data_get($folder, 'date_last_message'),
            data_get($folder, 'date_last_message_at'),
        ]);

        $lastModifiedAt = $this->preferCareScalar([
            data_get($folder, 'last_modification_date'),
            data_get($folder, 'last_modification_at'),
            data_get($folder, 'date_modification'),
        ]);

        $firstName = $this->preferCareScalar([
            data_get($folder, 'customer_firstname'),
            data_get($folder, 'client.first_name'),
            data_get($folder, 'order.shipping_address.first_name'),
            data_get($folder, 'order.billing_address.first_name'),
        ]);

        $lastName = $this->preferCareScalar([
            data_get($folder, 'customer_lastname'),
            data_get($folder, 'client.last_name'),
            data_get($folder, 'order.shipping_address.last_name'),
            data_get($folder, 'order.billing_address.last_name'),
        ]);

        $buyerName = trim(($firstName ?: '') . ' ' . ($lastName ?: ''));

        $buyerEmail = $this->preferCareScalar([
            data_get($folder, 'customer_email'),
            data_get($folder, 'client.email'),
            data_get($folder, 'order.shipping_address.email'),
            data_get($folder, 'order.billing_address.email'),
        ]);

        return [
            'id' => $this->stringifyCareValue(data_get($folder, 'id')),
            'order_id' => $orderId,
            'orderline' => $orderline,
            'topic' => $topic,
            'state' => $state,
            'priority' => $priority,
            'summary' => $summary,
            'reason_code' => $reason,
            'buyer_email' => $buyerEmail,
            'buyer_name' => $buyerName !== '' ? $buyerName : null,
            'created_at' => $createdAt,
            'created_at_human' => $this->formatCareDate($createdAt),
            'last_message_at' => $lastMessageAt,
            'last_message_at_human' => $this->formatCareDate($lastMessageAt),
            'last_modification_at' => $lastModifiedAt,
            'last_modification_at_human' => $this->formatCareDate($lastModifiedAt),
            'portal_url' => $this->stringifyCareValue(data_get($folder, 'portal_url')),
            'raw' => $folder,
        ];
    }

    protected function normalizeCareMessage(array $message): array
    {
        $sentAt = data_get($message, 'date')
            ?? data_get($message, 'created_at')
            ?? data_get($message, 'sent_at');
        $bodyHtml = $this->stringifyCareValue(data_get($message, 'body_html'));
        $bodyText = $this->stringifyCareValue(data_get($message, 'body') ?? data_get($message, 'message'));

        return [
            'id' => $this->stringifyCareValue(data_get($message, 'id')),
            'author' => $this->stringifyCareValue(data_get($message, 'author_name') ?? data_get($message, 'author')),
            'author_type' => $this->stringifyCareValue(data_get($message, 'author_type')),
            'direction' => $this->resolveCareDirection($message),
            'internal' => (bool) data_get($message, 'internal', false),
            'body' => $bodyText,
            'body_html' => $bodyHtml,
            'sent_at' => $sentAt,
            'sent_at_human' => $this->formatCareDate($sentAt),
        ];
    }

    protected function resolveCareDirection(array $message): string
    {
        $authorType = strtolower((string) data_get($message, 'author_type'));

        if (in_array($authorType, ['seller', 'merchant'], true)) {
            return 'outbound';
        }

        if (in_array($authorType, ['customer', 'buyer'], true)) {
            return 'inbound';
        }

        return data_get($message, 'internal') ? 'internal' : 'inbound';
    }

    protected function formatCareDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d M Y H:i');
        } catch (\Throwable $e) {
            return $value;
        }
    }

    protected function convertToArray($payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_null($payload)) {
            return [];
        }

        if ($payload instanceof \JsonSerializable) {
            return (array) $payload->jsonSerialize();
        }

        if (is_object($payload)) {
            return json_decode(json_encode($payload), true) ?: [];
        }

        return (array) $payload;
    }

    protected function stringifyCareValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value)) {
            $value = $this->convertToArray($value);
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $encoded !== false ? $encoded : null;
        }

        return null;
    }

    protected function preferCareValue(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            $value = $this->stringifyCareValue($candidate);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function preferCareScalar(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            if (is_string($candidate)) {
                $trimmed = trim($candidate);
                if ($trimmed !== '') {
                    return $trimmed;
                }
            } elseif (is_scalar($candidate)) {
                $string = trim((string) $candidate);
                if ($string !== '') {
                    return $string;
                }
            }
        }

        return null;
    }

    public function sendOrderInvoice(): void
    {
        $this->dispatchInvoice(false);
    }

    public function sendRefundInvoice(): void
    {
        $this->dispatchInvoice(true);
    }

    public function openPartialRefundModal(): void
    {
        $this->showPartialRefundModal = true;
        $this->selectedOrderItems = [];
        $this->partialRefundAmount = '';
        $this->invoiceActionStatus = null;
        $this->invoiceActionError = null;
    }

    public function closePartialRefundModal(): void
    {
        $this->showPartialRefundModal = false;
        $this->selectedOrderItems = [];
        $this->partialRefundAmount = '';
    }

    public function sendPartialRefundInvoice(): void
    {
        $this->invoiceActionStatus = null;
        $this->invoiceActionError = null;

        if (empty($this->selectedOrderItems)) {
            $this->invoiceActionError = 'Please select at least one order item for partial refund.';
            return;
        }

        $this->dispatchInvoice(true, true);
        $this->closePartialRefundModal();
    }

    protected function dispatchInvoice(bool $isRefund, bool $isPartial = false): void
    {
        $this->invoiceActionStatus = null;
        $this->invoiceActionError = null;

        if (! $this->selectedThreadId) {
            $this->invoiceActionError = 'Select a thread before sending invoices.';

            return;
        }

        $thread = $this->selectedThread;

        if (! $thread) {
            $this->invoiceActionError = 'Thread not found.';

            return;
        }

        $order = $thread->order;

        if (! $order) {
            $this->invoiceActionError = 'No internal order is linked to this ticket.';

            return;
        }

        $customer = $order->customer;

        if (! $customer || ! $customer->email) {
            $this->invoiceActionError = 'This order is missing a customer email address.';

            return;
        }

        try {
            $payload = $this->buildInvoicePayload($order, $isPartial);
            $emailHtml = $this->renderInvoiceEmailBody($payload, $isRefund, $isPartial);
            $this->sendInvoiceMail($order, $customer->email, $payload, $isRefund, $isPartial);
            $this->logInvoiceThreadEntry($thread, $order, $customer->email, $isRefund, $emailHtml, $isPartial);
            $this->sendInvoiceNotificationEmail($thread, $order, $customer->email, $isRefund, $isPartial);
        } catch (\Throwable $exception) {
            $this->invoiceActionError = 'Failed to send invoice: ' . $exception->getMessage();
            Log::error('Support invoice dispatch failed', [
                'thread_id' => $thread->id,
                'order_id' => $order->id,
                'refund' => $isRefund,
                'partial' => $isPartial,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $invoiceType = $isPartial ? 'Partial refund invoice sent to ' : ($isRefund ? 'Refund invoice sent to ' : 'Invoice sent to ');
        $this->invoiceActionStatus = $invoiceType . $customer->email . '.';
    }

    public function refreshExternalThreads(): void
    {
        $this->syncStatus = null;
        $this->syncError = null;
        $since = $this->resolveSyncSince();

        $sources = $this->buildSyncSources();

        if (empty($sources)) {
            $this->syncError = 'Select at least one support channel to refresh.';

            return;
        }

        try {
            $params = [];
            if ($since) {
                $params['--since'] = $since;
            }

            if (count($sources) < 2) {
                $params['--source'] = $sources;
            }

            foreach ($this->buildCareCliOptions() as $option => $value) {
                $params[$option] = $value;
            }

            $exitCode = Artisan::call('support:sync', $params);
            $output = trim(Artisan::output() ?? '');
        } catch (\Throwable $exception) {
            Log::error('Support manual sync failed', ['error' => $exception->getMessage()]);
            $this->syncError = 'Failed to refresh tickets: ' . $exception->getMessage();

            return;
        }

        if ($exitCode !== 0) {
            $this->syncError = $output !== '' ? $output : 'Sync command exited with errors.';

            return;
        }

        $summary = $output !== '' ? $output : 'Support channels refreshed.';
        $lookbackLabel = $since ? ($this->syncLookback . 'h window.') : 'Full history.';
        $this->syncStatus = $summary . ' Lookback: ' . $lookbackLabel;
        $this->emitSelf('supportThreadsUpdated');
    }

    protected function resolveSyncSince(): ?string
    {
        if ($this->syncLookback === 'all') {
            return null;
        }

        $hours = $this->sanitizeSyncLookback($this->syncLookback);

        return now()->subHours($hours)->format('Y-m-d-H-i');
    }

    protected function sanitizeSyncLookback($value): string
    {
        if ($value === 'all') {
            return 'all';
        }

        $value = (int) $value;

        if ($value < 1) {
            return '1';
        }

        if ($value > 168) {
            return '168';
        }

        return (string) $value;
    }

    protected function buildInvoicePayload(Order_model $order, bool $isPartial = false): array
    {
        $order->loadMissing([
            'customer',
            'order_items.variation.product',
            'order_items.variation.storage_id',
            'order_items.variation.color_id',
            'order_items.stock',
            'exchange_items',
            'admin',
            'currency_id',
            'marketplace',
        ]);

        $orderItems = $this->resolveInvoiceOrderItems($order->id, $isPartial);

        return [
            'order' => $order,
            'customer' => $order->customer,
            'orderItems' => $orderItems,
            'isPartial' => $isPartial,
            'partialRefundAmount' => $isPartial ? $this->partialRefundAmount : null,
        ];
    }

    protected function resolveInvoiceOrderItems(int $orderId, bool $isPartial = false)
    {
        $query = Order_item_model::with([
            'variation.product',
            'variation.storage_id',
            'variation.color_id',
            'stock',
            'replacement',
        ])->where('order_id', $orderId);

        if ($isPartial && !empty($this->selectedOrderItems)) {
            $query->whereIn('id', $this->selectedOrderItems);
        } else {
            $count = (clone $query)->count();

            if ($count > 1) {
                $query->whereHas('stock', function ($stockQuery) {
                    $stockQuery->where(function ($inner) {
                        $inner->where('status', 2)->orWhereNull('status');
                    });
                });
            }
        }

        return $query->get();
    }

    protected function sendInvoiceMail(Order_model $order, string $recipient, array $data, bool $isRefund, bool $isPartial = false): void
    {
        $subjectOrderRef = $order->reference_id
            ?: ($order->reference ?? ('#' . $order->id));

        $subject = ($isPartial ? 'Partial refund invoice for ' : ($isRefund ? 'Refund invoice for ' : 'Invoice for ')) . $subjectOrderRef;

        if ($isPartial) {
            $mailable = new \App\Mail\PartialRefundInvoiceMail($data);
        } else {
            $mailable = $isRefund ? new RefundInvoiceMail($data) : new InvoiceMail($data);
        }

        try {
            $response = app(GoogleController::class)->sendEmailInvoice($recipient, $subject, $mailable);
        } catch (\Throwable $exception) {
            Log::error('Gmail invoice send failed', [
                'recipient' => $recipient,
                'order_id' => $order->id,
                'refund' => $isRefund,
                'partial' => $isPartial,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if ($response instanceof \Illuminate\Http\RedirectResponse) {
            throw new \RuntimeException('Google account not connected. Please authenticate Gmail.');
        }
    }

    protected function renderInvoiceEmailBody(array $data, bool $isRefund, bool $isPartial = false): string
    {
        if ($isPartial) {
            $view = 'email.partial_refund_invoice';
        } else {
            $view = $isRefund ? 'email.refund_invoice' : 'email.invoice';
        }

        $html = view($view, $data)->render();

        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $matches)) {
            return trim($matches[1]);
        }

        return trim($html);
    }

    protected function logInvoiceThreadEntry(SupportThread $thread, Order_model $order, string $recipient, bool $isRefund, string $bodyHtml, bool $isPartial = false): void
    {
        $author = Admin_model::find(session('user_id'));
        $authorName = $author ? trim($author->first_name . ' ' . $author->last_name) : 'Nib Support';
        $authorEmail = $author->email ?? config('mail.from.address', 'no-reply@nibritaintech.com');
        $plainText = $this->plainTextFromHtml($bodyHtml);
        $orderLabel = $order->reference_id ?? ('#' . $order->id);

        $invoiceType = $isPartial ? 'Partial refund invoice' : ($isRefund ? 'Refund invoice' : 'Invoice');
        $contextLine = sprintf('%s email sent to %s for order %s.', $invoiceType, $recipient, $orderLabel);
        $renderedBody = '<div>' . $bodyHtml . '</div><hr><p style="color:#64748b;font-size:12px;">' . e($contextLine) . '</p>';

        SupportMessage::create([
            'support_thread_id' => $thread->id,
            'direction' => 'outbound',
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'body_text' => $plainText ?: $contextLine,
            'body_html' => $renderedBody,
            'sent_at' => now(),
            'is_internal_note' => false,
            'metadata' => [
                'source' => 'support_portal',
                'invoice_action' => $isPartial ? 'partial_refund' : ($isRefund ? 'refund' : 'order'),
                'customer_email' => $recipient,
                'selected_items' => $isPartial ? $this->selectedOrderItems : null,
                'partial_amount' => $isPartial ? $this->partialRefundAmount : null,
            ],
        ]);

        $thread->last_external_activity_at = now();
        if (! $thread->assigned_to && session('user_id')) {
            $thread->assigned_to = session('user_id');
        }
        $thread->save();

        $this->emitSelf('supportThreadsUpdated');
    }

    protected function sendInvoiceNotificationEmail(SupportThread $thread, Order_model $order, string $recipient, bool $isRefund, bool $isPartial = false): void
    {
        $replyTo = $thread->reply_email ?? $thread->buyer_email;

        if (! $replyTo) {
            Log::warning('Support invoice notification skipped: no reply email', [
                'thread_id' => $thread->id,
                'order_id' => $order->id,
            ]);

            return;
        }

        $orderLabel = $order->reference_id ?? $order->reference ?? ('#' . $order->id);

        $invoiceType = $isPartial ? 'partial refund invoice' : ($isRefund ? 'refund invoice' : 'invoice');
        $subject = ($isPartial ? 'Partial Refund Invoice Sent - ' : ($isRefund ? 'Refund Invoice Sent - ' : 'Invoice Sent - ')) . $orderLabel;

        $body = sprintf(
            "Hello,\n\nYour %s for order %s has been sent to %s.\n\nIf you have any questions, please don't hesitate to reply to this email.\n\nBest regards,\nSupport Team",
            $invoiceType,
            $orderLabel,
            $recipient
        );

        try {
            app(SupportEmailSender::class)->sendHtml($replyTo, $subject, $this->formatReplyHtml($body));

            // Log::info('Support invoice notification sent', [
            //     'thread_id' => $thread->id,
            //     'order_id' => $order->id,
            //     'recipient' => $replyTo,
            //     'refund' => $isRefund,
            // ]);
        } catch (\Throwable $exception) {
            Log::error('Support invoice notification failed', [
                'thread_id' => $thread->id,
                'order_id' => $order->id,
                'recipient' => $replyTo,
                'error' => $exception->getMessage(),
            ]);
        }
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

    protected function buildSyncSources(): array
    {
        $sources = [];

        if ($this->syncBackmarket) {
            $sources[] = 'backmarket';
        }

        if ($this->syncRefurbed) {
            $sources[] = 'refurbed';
        }

        return $sources;
    }

    protected function buildCareCliOptions(): array
    {
        if (! $this->syncBackmarket) {
            return [];
        }

        $options = [];
        $filters = [
            '--care-state' => $this->careState,
            '--care-priority' => $this->carePriority,
            '--care-topic' => $this->careTopic,
            '--care-orderline' => $this->careOrderline,
            '--care-order-id' => $this->careOrderId,
            '--care-last-id' => $this->careLastId,
        ];

        foreach ($filters as $flag => $value) {
            $value = is_string($value) ? trim($value) : $value;

            if ($value === null || $value === '') {
                continue;
            }

            $options[$flag] = (string) $value;
        }

        return $options;
    }

        return (string) $value;
    }
}
