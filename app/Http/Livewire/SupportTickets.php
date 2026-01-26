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
use TCPDF;

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
    public $careState = '';
    public $carePriority = '';
    public $careTopic = '';
    public $careOrderline = '';
    public $careOrderId = '';
    public $careLastId = '';
    public $carePageSize = '50';
    public $careExtraQuery = '';
    public $careFolderDetails = null;
    public $careFolderMessages = [];
    public $careFolderError = null;
    public $careFolderIdInput = '';
    public $careFolderFetchError = null;
    public $careFolderFetchSuccess = null;
    public $careFolderApiRequest = null;
    public $careFolderApiResponse = null;
    public $careReplyRequest = null;
    public $careReplyResponse = null;
    public $careAttachmentRequest = null;
    public $careAttachmentResponse = null;
    public $aiSummary = null;
    public $aiDraft = null;
    public $aiError = null;
    public $includeReplacementItems = false;
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

    public function fetchCareFolderById(): void
    {
        $this->careFolderFetchError = null;
        $this->careFolderFetchSuccess = null;
        $this->careFolderApiRequest = null;
        $this->careFolderApiResponse = null;

        $folderId = trim($this->careFolderIdInput);

        if ($folderId === '') {
            $this->careFolderFetchError = 'Please enter a Care folder ID.';
            return;
        }

        // Capture request details
        $baseUrl = config('services.backmarket.base_url', 'https://www.backmarket.fr/ws/');
        $endpoint = 'sav/' . $folderId;
        $fullUrl = $baseUrl . $endpoint;

        $this->careFolderApiRequest = [
            'method' => 'GET',
            'url' => $fullUrl,
            'endpoint' => $endpoint,
            'folder_id' => $folderId,
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en',
                'Authorization' => 'Basic [REDACTED]',
                'User-Agent' => 'NIB System',
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        Log::info('Back Market Care API Request', [
            'folder_id' => $folderId,
            'url' => $fullUrl,
            'method' => 'GET',
        ]);

        try {
            $controller = app(BackMarketAPIController::class);
            $folder = $controller->getCare($folderId);
        } catch (\Throwable $e) {
            $this->careFolderApiResponse = [
                'status' => 'error',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String(),
            ];

            Log::warning('Manual Care folder fetch failed', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->careFolderFetchError = 'Failed to fetch folder: ' . $e->getMessage();
            return;
        }

        if (!$folder) {
            $this->careFolderFetchError = 'Care API returned empty response for ID: ' . $folderId;
            return;
        }

        if (isset($folder->results)) {
            $folder = $folder->results;
        }

        $folderArray = $this->convertToArray($folder);

        $this->careFolderApiResponse = [
            'status' => 'success',
            'data' => $folderArray,
            'timestamp' => now()->toIso8601String(),
        ];

        Log::info('Back Market Care API Response', [
            'folder_id' => $folderId,
            'response' => $folderArray,
        ]);

        if (empty($folderArray)) {
            $this->careFolderFetchError = 'Could not parse Care API response.';
            return;
        }

        // Prime UI preview data before saving
        $this->careFolderDetails = $this->normalizeCareFolder($folderArray);
        $messages = data_get($folderArray, 'messages', []);
        if (!is_array($messages)) {
            $messages = $this->convertToArray($messages);
        }
        $this->careFolderMessages = collect($messages)
            ->filter()
            ->map(fn ($message) => $this->normalizeCareMessage($this->convertToArray($message)))
            ->values()
            ->all();

        // Persist into support storage (thread + messages)
        try {
            $syncService = app(\App\Services\Support\BackMarketCareSyncService::class);
            $thread = $syncService->importCase($folderArray);
        } catch (\Throwable $e) {
            Log::warning('Care folder save failed', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->careFolderFetchError = 'Fetched folder but failed to save to DB: ' . $e->getMessage();
            return;
        }

        $this->selectThread($thread->id);
        $stateLabel = $this->careFolderDetails['state_label'] ?? ($this->careFolderDetails['state'] ?? 'N/A');
        $orderId = $this->careFolderDetails['order_id'] ?? 'N/A';
        $buyerEmail = $this->careFolderDetails['buyer_email'] ?? 'N/A';

        $this->careFolderFetchSuccess = sprintf(
            'Care folder #%s saved and ticket selected (Order: %s, State: %s, Email: %s).',
            $folderId,
            $orderId,
            $stateLabel,
            $buyerEmail
        );

        $this->careFolderIdInput = '';
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
        $this->aiError = null;
        $this->careReplyRequest = null;
        $this->careReplyResponse = null;

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

        $isCareThread = $thread->marketplace_source === 'backmarket_care';
        $recipient = $this->replyRecipientEmail ?: ($thread->reply_email ?? $thread->buyer_email);

        $subject = $this->replySubject ?: $this->defaultReplySubject($thread);
        $body = trim($this->replyBody ?? '');

        if ($body === '') {
            $this->replyError = 'Message body cannot be empty.';

            return;
        }

        $careFolderId = null;
        $careApiResponse = null;

        if ($isCareThread) {
            $careFolderId = $thread->external_thread_id ?: data_get($thread->metadata, 'id');

            if (! $careFolderId) {
                $this->replyError = 'Back Market Care folder id is missing for this ticket.';

                return;
            }

            try {
                $this->careReplyRequest = [
                    'folder_id' => $careFolderId,
                    'message' => $body,
                ];

                $careMeta = app(BackMarketAPIController::class)
                    ->sendCareMessageMeta($careFolderId, $body);

                $careApiResponse = $this->convertToArray($careMeta['decoded'] ?? []);

                // Care API may return 200/201 with an empty body; treat that as success.
                if ($careApiResponse === [] && ($careMeta['raw'] ?? '') !== '') {
                    $careApiResponse = ['status' => 'accepted'];
                }

                if ($careApiResponse === [] && (($careMeta['raw'] ?? null) === null || ($careMeta['raw'] === ''))) {
                    $this->replyError = 'Care API did not return a valid response.';
                    $this->careReplyResponse = $careMeta;

                    return;
                }

                $this->careReplyResponse = $careMeta;
            } catch (\Throwable $exception) {
                Log::error('Support reply via Care API failed', [
                    'thread_id' => $thread->id,
                    'folder_id' => $careFolderId,
                    'error' => $exception->getMessage(),
                ]);
                $this->replyError = 'Care API rejected the reply: ' . $exception->getMessage();

                return;
            }
        } else {
            if (! $recipient) {
                $this->replyError = 'This ticket does not have a valid recipient email.';

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
                'source' => $isCareThread ? 'backmarket_care_api' : 'support_portal',
                'care_folder_id' => $careFolderId,
                'care_api_response' => $careApiResponse,
            ],
        ]);

        $thread->last_external_activity_at = now();
        if (! $thread->assigned_to && session('user_id')) {
            $thread->assigned_to = session('user_id');
        }
        $thread->save();

        $this->replyBody = '';
        $this->replyStatus = $isCareThread
            ? 'Reply sent via Back Market Care API.'
            : 'Reply sent via Gmail.';
        $this->messageTranslations = [];
        $this->expandedMessages = [];
        $this->replyFormThreadId = $thread->id;
        $this->emitSelf('supportThreadsUpdated');

        if ($isCareThread) {
            $this->hydrateCareFolder($thread);
        }
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

        $this->aiSummary = null;
        $this->aiDraft = null;
        $this->aiError = null;
        $this->marketplaceOrderUrl = null;
        $this->canCancelOrder = false;
        $this->orderActionStatus = null;
        $this->orderActionError = null;
        $this->orderActionPayload = null;
        $this->invoiceActionStatus = null;
        $this->invoiceActionError = null;

        if (! $thread) {
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
            data_get($folder, 'order'),
            data_get($folder, 'order_number'),
            data_get($folder, 'orderNumber'),
            data_get($folder, 'lines.0.order'),
            data_get($folder, 'lines.0.order_number'),
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

        $sellerName = $this->preferCareScalar([
            data_get($folder, 'seller_name'),
            data_get($folder, 'seller.name'),
            data_get($folder, 'merchant_name'),
            data_get($folder, 'merchant.name'),
        ]);

        $trackingNumber = $this->preferCareScalar([
            data_get($folder, 'tracking_number'),
            data_get($folder, 'tracking'),
            data_get($folder, 'shipment.tracking_number'),
        ]);

        $messagesCount = data_get($folder, 'messages_count') ?? data_get($folder, 'message_count') ?? (is_array(data_get($folder, 'messages')) ? count(data_get($folder, 'messages')) : null);

        return [
            'id' => $this->stringifyCareValue(data_get($folder, 'id')),
            'order_id' => $orderId,
            'orderline' => $orderline,
            'orderline_id' => $this->stringifyCareValue(data_get($folder, 'orderline_id') ?? data_get($folder, 'orderline.id') ?? data_get($folder, 'lines.0.id')),
            'topic' => $topic,
            'state' => $state,
            'state_label' => $this->decodeCareState($state),
            'priority' => $priority,
            'summary' => $summary,
            'reason_code' => $reason,
            'buyer_email' => $buyerEmail,
            'buyer_name' => $buyerName !== '' ? $buyerName : null,
            'seller_name' => $sellerName,
            'tracking_number' => $trackingNumber,
            'messages_count' => $messagesCount,
            'type' => $this->stringifyCareValue(data_get($folder, 'type')),
            'source' => $this->stringifyCareValue(data_get($folder, 'source')),
            'channel' => $this->stringifyCareValue(data_get($folder, 'channel')),
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

    protected function decodeCareState(?string $state): string
    {
        if (!$state) {
            return 'Unknown';
        }

        // Back Market Care state codes
        $states = [
            '1' => 'Open',
            '2' => 'In Progress',
            '3' => 'Waiting Customer',
            '4' => 'Waiting Seller',
            '5' => 'Pending',
            '6' => 'Solved',
            '7' => 'Closed',
            '8' => 'Cancelled',
            '9' => 'Waiting Seller Response',
            '10' => 'Escalated',
        ];

        return $states[$state] ?? "State $state";
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

    public function sendReplacementInvoice(): void
    {
        $this->dispatchInvoice(false, false, true);
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

        $this->dispatchInvoice(true, true, false);
        $this->closePartialRefundModal();
    }

    public function sendSplitReturnInvoices(): void
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

        $allItems = Order_item_model::with(['stock', 'replacement.stock', 'replacement.replacement.stock'])
            ->where('order_id', $order->id)
            ->get()
            ->map(fn ($itm) => $this->applyReplacementOverlay($itm));

        $allItemIds = $allItems->pluck('id')->all();
        $linkedReturnIds = Order_item_model::query()
            ->whereIn('linked_id', $allItemIds)
            ->pluck('linked_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $returnedItemIds = $allItems
            ->filter(function ($itm) use ($linkedReturnIds) {
                $itemStatus = (int) ($itm->status ?? 0);
                $stockStatus = (int) (($itm->effective_status ?? optional($itm->stock)->status) ?? 0);

                return $itemStatus === 6
                    || $stockStatus === 1
                    || in_array($itm->id, $linkedReturnIds, true);
            })
            ->pluck('id')
            ->all();

        if (empty($returnedItemIds)) {
            $this->invoiceActionError = 'No returned items detected for this order (order item status: returned/refunded, linked return item, or stock status: available).';

            return;
        }

        $statusMessages = [];

        $this->selectedOrderItems = $returnedItemIds;
        $this->partialRefundAmount = '';
        $this->dispatchInvoice(true, false, false);

        if ($this->invoiceActionError) {
            return;
        }

        if ($this->invoiceActionStatus) {
            $statusMessages[] = 'Refund invoice sent for returned items (' . count($returnedItemIds) . ').';
        }

        $remainingItemIds = $allItems
            ->reject(fn ($itm) => in_array($itm->id, $returnedItemIds, true))
            ->pluck('id')
            ->all();

        if (! empty($remainingItemIds)) {
            // Send a normal invoice (non-refund) for the remaining items; rely on stock status filtering to exclude returned items.
            $this->selectedOrderItems = $remainingItemIds;
            $this->partialRefundAmount = '';
            $this->dispatchInvoice(false, false, false);

            if ($this->invoiceActionError) {
                return;
            }

            if ($this->invoiceActionStatus) {
                $statusMessages[] = 'Invoice sent for remaining items (' . count($remainingItemIds) . ').';
            }
        } else {
            $statusMessages[] = 'No remaining items to invoice.';
        }

        if (! empty($statusMessages)) {
            $this->invoiceActionStatus = implode(' ', $statusMessages);
        }

        $this->selectedOrderItems = [];
        $this->partialRefundAmount = '';
    }

    protected function dispatchInvoice(bool $isRefund, bool $isPartial = false, bool $includeReplacements = false): void
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
            $this->careAttachmentRequest = null;
            $this->careAttachmentResponse = null;

            $payload = $this->buildInvoicePayload($order, $isPartial, $isRefund, $thread, $includeReplacements);
            $emailHtml = $this->renderInvoiceEmailBody($payload, $isRefund, $isPartial);

            $isCareThread = $thread->marketplace_source === 'backmarket_care';

            // For Back Market Care threads, skip all emails and only post to Care API
            if (! $isCareThread) {
                $this->sendInvoiceMail($order, $customer->email, $payload, $isRefund, $isPartial);
                $this->sendInvoiceNotificationEmail($thread, $order, $customer->email, $isRefund, $isPartial);
            }

            $this->logInvoiceThreadEntry($thread, $order, $customer->email, $isRefund, $emailHtml, $isPartial);

            $this->maybeSendBackmarketInvoiceAttachment($thread, $order, $payload, $isRefund, $isPartial);
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

        $isCareThread = $thread->marketplace_source === 'backmarket_care';

        if ($isCareThread) {
            $this->invoiceActionStatus = $invoiceType . 'Back Market Care (folder #' . ($thread->external_thread_id ?: 'unknown') . ') with PDF attachment.';
        } else {
            $this->invoiceActionStatus = $invoiceType . $customer->email . '.';
        }
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

    protected function buildInvoicePayload(Order_model $order, bool $isPartial = false, bool $isRefund = false, ?SupportThread $thread = null, bool $includeReplacements = false): array
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

        $orderItems = $this->resolveInvoiceOrderItems($order, $isPartial, $isRefund, $thread, $includeReplacements);

        return [
            'order' => $order,
            'customer' => $order->customer,
            'orderItems' => $orderItems,
            'isPartial' => $isPartial,
            'partialRefundAmount' => $isPartial ? $this->partialRefundAmount : null,
        ];
    }

    protected function resolveInvoiceOrderItems(Order_model $order, bool $isPartial = false, bool $isRefund = false, ?SupportThread $thread = null, bool $includeReplacements = false)
    {
        $query = Order_item_model::with([
            'variation.product',
            'variation.storage_id',
            'variation.color_id',
            'stock',
            'replacement',
            'replacement.stock',
            'replacement.replacement',
            'replacement.replacement.stock',
        ])->where('order_id', $order->id);

        if ($isRefund && !empty($this->selectedOrderItems)) {
            $query->whereIn('id', $this->selectedOrderItems);
        } elseif ($isPartial && !empty($this->selectedOrderItems)) {
            $query->whereIn('id', $this->selectedOrderItems);
        } elseif ($isRefund) {
            $query->where(function ($itemQuery) {
                $itemQuery->where('status', 6)
                    ->orWhereHas('childs')
                    ->orWhereHas('stock', function ($stockQuery) {
                        $stockQuery->where('status', 1);
                    });
            });
        } elseif (! $includeReplacements) {
            $count = (clone $query)->count();

            if ($count > 1) {
                $query->where(function ($itemQuery) {
                    $itemQuery->whereNull('status')->orWhere('status', '!=', 6);
                });
                $query->whereHas('stock', function ($stockQuery) {
                    $stockQuery->where(function ($inner) {
                        $inner->where('status', 2)->orWhereNull('status');
                    });
                });
            }
        }

        $items = $query->get();

        if ($includeReplacements) {
            $items = $items
                ->map(fn ($itm) => $this->applyReplacementOverlay($itm))
                ->unique(function ($itm) {
                    $imei = trim((string) ($itm->effective_imei ?? ''));
                    $stockId = optional($itm->effective_stock)->id ?? $itm->stock_id;

                    return $imei !== ''
                        ? strtolower($imei)
                        : ($stockId !== null ? 'stock:' . $stockId : 'item:' . $itm->id);
                })
                ->map(function ($itm) {
                    // Ensure each replacement line is treated as a single unit
                    $itm->quantity = 1;
                    return $itm;
                })
                ->values();
        }

        return $this->normalizeBackmarketItemPrices($order, $items, $thread, $includeReplacements);
    }

    protected function normalizeBackmarketItemPrices(Order_model $order, $items, ?SupportThread $thread = null, bool $includeReplacements = false): \Illuminate\Support\Collection
    {
        $collection = $items instanceof \Illuminate\Support\Collection ? $items : collect($items);

        $collection = $collection->map(function ($item) {
            return $this->applyReplacementOverlay($item);
        });

        $orderTotal = (float) ($order->price ?? 0);

        $collection = $this->rebalanceCollectionPrices($collection, $orderTotal);

        if (! $this->isBackmarketOrder($order, $thread)) {
            return $collection;
        }

        if ($orderTotal <= 0) {
            return $collection;
        }

        return $collection->map(function ($item) use ($orderTotal) {
            $qty = (int) (1);
            $qty = $qty > 0 ? $qty : 1;
            $price = (float) ($item->price ?? 0);

            if ($qty > 1 && abs($price - $orderTotal) < 0.01) {
                $unitPrice = round($orderTotal / $qty, 2);
                $item->price = $unitPrice;
                if (! $item->selling_price) {
                    $item->selling_price = $unitPrice;
                }
            }

            return $item;
        });
    }

    protected function rebalanceCollectionPrices($collection, float $orderTotal)
    {
        if ($orderTotal <= 0) {
            return $collection;
        }

        $totalUnits = $collection->reduce(function ($carry, $itm) {
            $qty = (int) (1);
            return $carry + ($qty > 0 ? $qty : 1);
        }, 0);

        if ($totalUnits <= 0) {
            $totalUnits = $collection->count();
        }

        if ($totalUnits <= 0) {
            return $collection;
        }

        $currentSum = $collection->reduce(function ($carry, $itm) {
            $price = $itm->price ?? $itm->selling_price ?? 0;
            $qty = (int) (1);
            $qty = $qty > 0 ? $qty : 1;

            return $carry + ((float) $price * $qty);
        }, 0.0);

        if (abs($currentSum - $orderTotal) < 0.01) {
            return $collection;
        }

        $unit = round($orderTotal / $totalUnits, 2);

        return $collection->map(function ($itm) use ($unit) {
            $itm->price = $unit;
            if ($itm->selling_price === null || $itm->selling_price <= 0) {
                $itm->selling_price = $unit;
            }

            return $itm;
        });
    }

    protected function applyReplacementOverlay($item)
    {
        $effective = $this->finalReplacement($item);

        if ($effective && $effective->id !== $item->id) {
            if ($effective->stock) {
                $item->stock = $effective->stock;
            }
            if (($item->price === null || $item->price <= 0) && $effective->price !== null) {
                $item->price = $effective->price;
            }
            if (($item->selling_price === null || $item->selling_price <= 0) && $effective->selling_price !== null) {
                $item->selling_price = $effective->selling_price;
            }
            $item->reference_id = $item->reference_id ?? $effective->reference_id;
        }

        $item->effective_stock = $item->stock;
        $item->effective_imei = optional($item->stock)->imei ?? optional($item->stock)->serial_number;
        $item->effective_status = optional($item->stock)->status;

        return $item;
    }

    protected function finalReplacement($item)
    {
        $candidate = $item;

        while ($candidate && $candidate->replacement) {
            $candidate = $candidate->replacement;
        }

        return $candidate;
    }

    protected function isBackmarketOrder(Order_model $order, ?SupportThread $thread = null): bool
    {
        $name = strtolower((string) optional($order->marketplace)->name);

        if ($name !== '' && str_contains($name, 'back')) {
            return true;
        }

        $source = strtolower((string) ($thread->marketplace_source ?? ''));

        if ($source !== '' && str_contains($source, 'backmarket')) {
            return true;
        }

        $marketplaceId = (int) ($order->marketplace_id ?? 0);

        if ($marketplaceId === 4) { // refurbed id
            return false;
        }

        return $marketplaceId > 0;
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

    protected function maybeSendBackmarketInvoiceAttachment(SupportThread $thread, Order_model $order, array $payload, bool $isRefund, bool $isPartial): void
    {
        if (! $this->shouldSendBackmarketInvoiceAttachment($thread, $isRefund, $isPartial)) {
            return;
        }

        $folderId = $thread->external_thread_id ?: data_get($thread->metadata, 'id');

        if (! $folderId) {
            Log::warning('Back Market attachment skipped: missing folder id', [
                'thread_id' => $thread->id,
                'order_id' => $order->id,
            ]);

            return;
        }

        $orderLabel = $order->reference_id ?? $order->reference ?? ('#' . $order->id);
        $message = $isPartial
            ? 'Partial refund invoice attached for order ' . $orderLabel . '.'
            : ($isRefund
                ? 'Refund invoice attached for order ' . $orderLabel . '.'
                : 'Invoice attached for order ' . $orderLabel . '.');

        try {
            $pdf = $this->buildInvoicePdf($payload, $isRefund, $isPartial);

            $this->careAttachmentRequest = [
                'folder_id' => $folderId,
                'message' => $message,
                'attachment_name' => $pdf['name'] ?? 'invoice.pdf',
                'attachment_size' => isset($pdf['data']) ? strlen($pdf['data']) : 0,
            ];

            $response = app(BackMarketAPIController::class)->sendCareMessageWithAttachment($folderId, $message, $pdf);

            $this->careAttachmentResponse = $response;

            Log::info('Back Market invoice posted', [
                'thread_id' => $thread->id,
                'order_id' => $order->id,
                'folder_id' => $folderId,
                'response' => $response,
            ]);
        } catch (\Throwable $exception) {
            $this->careAttachmentResponse = [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ];

            Log::warning('Back Market Care attachment failed', [
                'thread_id' => $thread->id,
                'order_id' => $order->id,
                'folder_id' => $folderId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function shouldSendBackmarketInvoiceAttachment(SupportThread $thread, bool $isRefund, bool $isPartial): bool
    {
        // Always send Back Market Care invoices (order, refund, or partial refund) via Care API attachment.
        return $thread->marketplace_source === 'backmarket_care';
    }

    protected function buildInvoicePdf(array $payload, bool $isRefund, bool $isPartial): array
    {
        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);

        $isRefundLike = $isRefund || $isPartial;
        $pdf->SetTitle($isRefundLike ? 'Refund Invoice' : 'Invoice');
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 12);

        $view = $isRefundLike ? 'export.refund_invoice' : 'export.invoice';
        $html = view($view, $payload)->render();
        $pdf->writeHTML($html, true, false, true, false, '');

        $fileName = $isRefundLike ? 'refund-invoice.pdf' : 'invoice.pdf';
        $pdfOutput = $pdf->Output($fileName, 'S');

        return [
            'data' => $pdfOutput,
            'name' => $fileName,
            'mime' => 'application/pdf',
        ];
    }

    public function generateAiAssist(): void
    {
        $this->aiError = null;
        $this->aiSummary = null;
        $this->aiDraft = null;

        if (! $this->selectedThreadId) {
            $this->aiError = 'Select a ticket first.';

            return;
        }

        $thread = $this->selectedThread;

        if (! $thread) {
            $this->aiError = 'Thread not found.';

            return;
        }

        $thread->loadMissing('messages');

        $messages = collect($thread->messages ?? [])
            ->sortByDesc(function ($message) {
                return $message->sent_at ?: $message->created_at ?: $message->id;
            })
            ->values();

        if ($messages->isEmpty()) {
            $this->aiError = 'No messages to summarize yet.';

            return;
        }

        $recent = $messages->take(8);

        $context = $recent->map(function ($message) {
            $role = $message->is_internal_note ? 'Note' : ($message->direction === 'outbound' ? 'Support' : 'Customer');

            $text = $message->body_text
                ?: ($message->clean_body_html ?? '')
                ?: ($message->body_html ?? '');

            if ($text && $message->body_html) {
                $text = $this->plainTextFromHtml($message->body_html);
            }

            $clean = trim(preg_replace('/\s+/', ' ', $text ?? ''));

            return $role . ': ' . $this->shorten($clean ?: '[no content]', 180);
        })->filter()->values();

        if ($context->isEmpty()) {
            $this->aiError = 'Could not derive text from recent messages.';

            return;
        }

        $summary = 'Recent activity — ' . $context->implode(' | ');
        $this->aiSummary = $this->shorten($summary, 480);

        $name = $thread->buyer_name ?: 'there';
        $orderRef = $thread->order_reference
            ?: ($thread->external_thread_id ? ltrim($thread->external_thread_id, '#') : 'your order');
        $channelNote = $thread->marketplace_source === 'backmarket_care'
            ? "We'll post this via Back Market Care."
            : 'We will reply by email.';

        $this->aiDraft = sprintf(
            "Hi %s,\n\nThanks for your message about %s. I reviewed the recent updates: %s\n\nSuggested reply:\n- Acknowledge their concern in one line.\n- Share the current status or the action we just took.\n- Provide the next step and expected timing.\n\n%s\n\nBest regards,\nSupport Team",
            $name,
            $orderRef,
            $this->aiSummary,
            $channelNote
        );
    }

    public function useAiDraft(): void
    {
        if (! $this->aiDraft) {
            $this->aiError = 'Generate a draft first.';

            return;
        }

        $this->replyBody = $this->aiDraft;
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

    protected function shorten(string $text, int $limit = 180): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text));
        if (strlen($clean) <= $limit) {
            return $clean;
        }

        return substr($clean, 0, $limit - 3) . '...';
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

        foreach ($filters as $flag => $raw) {
            if (is_string($raw)) {
                $raw = trim($raw);
            }

            if ($raw === null || $raw === '') {
                continue;
            }

            $options[$flag] = (string) $raw;
        }

        return $options;
    }
}
