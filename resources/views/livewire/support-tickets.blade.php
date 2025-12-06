<?php use Illuminate\Support\Str; ?>

<div class="support-shell">
    <div class="support-panel mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-1">Support Hub</h4>
                <p class="text-muted mb-0">Live view of Back Market Care + Refurbed tickets.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-end">
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 small text-muted">Look back</label>
                    <select class="form-select form-select-sm" style="width:auto;" wire:model="syncLookback">
                        @foreach ([6, 12, 24, 48, 72, 120, 168] as $hours)
                            <option value="{{ $hours }}">{{ $hours }}h</option>
                        @endforeach
                        <option value="all">All history</option>
                    </select>
                </div>
                <button wire:click="refreshExternalThreads" type="button" class="btn btn-outline-primary" wire:loading.attr="disabled" wire:target="refreshExternalThreads">
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true" wire:loading wire:target="refreshExternalThreads"></span>
                    Refresh tickets
                </button>
                <button wire:click="resetFilters" type="button" class="btn btn-light border">Reset Filters</button>
            </div>
        </div>

        <div class="bg-light border rounded p-3 mb-3">
            <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                <div>
                    <div class="fw-semibold text-uppercase small text-muted">Manual sync scope</div>
                    <p class="mb-0 text-muted">Pick which support channels to refresh and optionally add Back Market Care filters before running the sync.</p>
                </div>
                <div class="d-flex flex-wrap gap-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="syncBackmarket" wire:model="syncBackmarket">
                        <label class="form-check-label" for="syncBackmarket">
                            Back Market Care
                            <small class="d-block text-muted">Care API with filters</small>
                        </label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="syncRefurbed" wire:model="syncRefurbed">
                        <label class="form-check-label" for="syncRefurbed">
                            Refurbed Mailbox
                            <small class="d-block text-muted">orders@refurbed sync</small>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <div class="fw-semibold small text-uppercase text-muted mb-2">Back Market Care filters</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted">State</label>
                        <input type="text" class="form-control form-control-sm" placeholder="open / waiting_seller" wire:model.lazy="careState" @if (! $syncBackmarket) disabled @endif>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Priority</label>
                        <input type="text" class="form-control form-control-sm" placeholder="low / normal / high" wire:model.lazy="carePriority" @if (! $syncBackmarket) disabled @endif>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Topic</label>
                        <input type="text" class="form-control form-control-sm" placeholder="delivery / quality" wire:model.lazy="careTopic" @if (! $syncBackmarket) disabled @endif>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Order line</label>
                        <input type="text" class="form-control form-control-sm" placeholder="BM orderline" wire:model.lazy="careOrderline" @if (! $syncBackmarket) disabled @endif>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Order ID</label>
                        <input type="text" class="form-control form-control-sm" placeholder="Back Market order id" wire:model.lazy="careOrderId" @if (! $syncBackmarket) disabled @endif>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Cursor / last ID</label>
                        <input type="text" class="form-control form-control-sm" placeholder="Use to resume pagination" wire:model.lazy="careLastId" @if (! $syncBackmarket) disabled @endif>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Page size</label>
                        <input type="number" min="1" max="200" class="form-control form-control-sm" wire:model.lazy="carePageSize" @if (! $syncBackmarket) disabled @endif>
                        <small class="text-muted">Max 200 per pull</small>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label small text-muted">Extra query string</label>
                        <input type="text" class="form-control form-control-sm" placeholder="state=open&priority=high" wire:model.lazy="careExtraQuery" @if (! $syncBackmarket) disabled @endif>
                        <small class="text-muted">Sent as-is to the Care API. Leave blank for defaults.</small>
                    </div>
                </div>
            </div>
        </div>

        @if ($syncError)
            <div class="alert alert-danger mb-3">{{ $syncError }}</div>
        @endif
        @if ($syncStatus)
            <div class="alert alert-success mb-3">{{ $syncStatus }}</div>
        @endif

        <div class="support-filters">
            <div>
                <label class="form-label fw-semibold">Search</label>
                <input type="search" class="form-control" placeholder="Buyer, reference, email" wire:model.debounce.400ms="search">
            </div>
            <div>
                <label class="form-label fw-semibold">Status</label>
                <select class="form-select" wire:model="status">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $option)
                        <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">Priority</label>
                <select class="form-select" wire:model="priority">
                    <option value="">All priorities</option>
                    @foreach ($priorityOptions as $option)
                        <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">Marketplace</label>
                <select class="form-select" wire:model="marketplace">
                    <option value="">All</option>
                    @foreach ($marketplaceOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">Tag</label>
                <select class="form-select" wire:model="tag">
                    <option value="">Any tag</option>
                    @foreach ($tagOptions as $tagOption)
                        <option value="{{ $tagOption->id }}">{{ $tagOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">Assignee</label>
                <select class="form-select" wire:model="assigned">
                    <option value="">Unassigned / anyone</option>
                    @foreach ($assigneeOptions as $admin)
                        <option value="{{ $admin->id }}">{{ trim($admin->first_name . ' ' . $admin->last_name) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">Items / page</label>
                <select class="form-select" wire:model="perPage">
                    @foreach ([10, 25, 50, 75, 100] as $pageSize)
                        <option value="{{ $pageSize }}">{{ $pageSize }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">Sort</label>
                <div class="d-flex gap-2">
                    <select class="form-select" wire:model="sortField">
                        <option value="last_external_activity_at">Last activity</option>
                        <option value="priority">Priority</option>
                        <option value="status">Status</option>
                        <option value="created_at">Created</option>
                    </select>
                    <select class="form-select" wire:model="sortDirection">
                        <option value="desc">Desc</option>
                        <option value="asc">Asc</option>
                    </select>
                </div>
            </div>
            <div class="d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="changeOnly" wire:model="changeOnly">
                    <label class="form-check-label" for="changeOnly">Change-of-mind only</label>
                </div>
            </div>
        </div>
    </div>

    <div class="support-grid">
        <div class="support-panel">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Threads</h5>
                <span class="text-muted small">{{ $threads->total() }} total</span>
            </div>
            <div class="support-list flex-grow-1">
                @forelse ($threads as $thread)
                    <button type="button" class="support-thread {{ $selectedThread && $thread->id === $selectedThread->id ? 'active' : '' }}" wire:click="selectThread({{ $thread->id }})">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="fw-semibold">{{ $thread->order_reference ?? $thread->external_thread_id }}</div>
                            <span class="badge bg-light text-dark text-uppercase">{{ $thread->status ?? 'open' }}</span>
                        </div>
                        <div class="text-muted small mb-1">
                            {{ $thread->buyer_name ?? 'Unknown buyer' }} · {{ $thread->buyer_email ?? 'No email' }}
                        </div>
                        <div class="d-flex flex-wrap gap-1 mb-1">
                            @foreach ($thread->tags as $tag)
                                <span class="tag-chip" style="color: {{ $tag->color ?? '#2563eb' }}">{{ $tag->name }}</span>
                            @endforeach
                            @if ($thread->change_of_mind)
                                <span class="badge bg-warning text-dark">Change of mind</span>
                            @endif
                        </div>
                        <div class="text-muted small">Updated {{ optional($thread->last_external_activity_at)->diffForHumans() ?? 'n/a' }}</div>
                        <div class="text-muted small">Messages: {{ $thread->messages_count }}</div>
                        @if ($thread->messages->first())
                            <div class="mt-1 text-truncate small">
                                @php
                                    $previewMessage = $thread->messages->first();
                                    $previewSource = $previewMessage->clean_body_html !== ''
                                        ? strip_tags($previewMessage->clean_body_html)
                                        : ($previewMessage->body_text ?? strip_tags($previewMessage->body_html ?? ''));
                                @endphp
                                {{ Str::limit($previewSource, 120) }}
                            </div>
                        @endif
                    </button>
                @empty
                    <div class="alert alert-light border">No support threads match your filters.</div>
                @endforelse
            </div>
            <div class="mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="text-muted small">
                    Showing
                    {{ $threads->firstItem() ?? 0 }}
                    –
                    {{ $threads->lastItem() ?? 0 }}
                    of
                    {{ $threads->total() }}
                    tickets
                </div>
                <div class="support-pagination">
                    {{ $threads->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>

        <div class="support-panel">
            @if ($selectedThread)
                @php
                    $isThreadSolved = strtolower($selectedThread->status ?? '') === 'solved';
                @endphp
                <div class="support-detail-header">
                    <div>
                        <h4 class="mb-1">{{ $selectedThread->order_reference ?? $selectedThread->external_thread_id }}</h4>
                        <p class="text-muted mb-0">{{ $selectedThread->buyer_name ?? 'Unknown buyer' }} · {{ $selectedThread->buyer_email ?? 'No email' }}</p>
                    </div>
                    <div class="text-end d-flex flex-column align-items-end gap-2">
                        <div>
                            <span class="badge bg-dark text-uppercase">{{ $selectedThread->status ?? 'open' }}</span>
                            <span class="badge bg-primary text-uppercase">{{ $selectedThread->priority ?? 'normal' }}</span>
                            @if ($selectedThread->change_of_mind)
                                <span class="badge bg-warning text-dark">Change of mind</span>
                            @endif
                        </div>
                        @if ($selectedThread->portal_url)
                            <a href="{{ $selectedThread->portal_url }}" class="btn btn-sm btn-primary" target="_blank" rel="noopener">
                                View in support portal
                            </a>
                        @endif
                        <button type="button" class="btn btn-sm btn-success" wire:click="markThreadSolved" wire:loading.attr="disabled" wire:target="markThreadSolved" @if ($isThreadSolved) disabled @endif>
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true" wire:loading wire:target="markThreadSolved"></span>
                            {{ $isThreadSolved ? 'Solved' : 'Mark as solved' }}
                        </button>
                    </div>
                </div>

                @if ($ticketActionError)
                    <div class="alert alert-danger mt-3">{{ $ticketActionError }}</div>
                @endif
                @if ($ticketActionStatus)
                    <div class="alert alert-success mt-3">{{ $ticketActionStatus }}</div>
                @endif

                <div class="support-meta-grid">
                    <div class="meta-pill">
                        <div class="text-muted small">Marketplace</div>
                        <div class="fw-semibold">{{ optional($selectedThread->marketplace)->name ?? $selectedThread->marketplace_source ?? 'n/a' }}</div>
                    </div>
                    <div class="meta-pill">
                        <div class="text-muted small">Assigned to</div>
                        <div class="fw-semibold">{{ optional($selectedThread->assignee)->first_name ? trim($selectedThread->assignee->first_name . ' ' . $selectedThread->assignee->last_name) : 'Unassigned' }}</div>
                    </div>
                    <div class="meta-pill">
                        <div class="text-muted small">Last activity</div>
                        <div class="fw-semibold">{{ optional($selectedThread->last_external_activity_at)->format('d M Y H:i') ?? 'n/a' }}</div>
                    </div>
                    <div class="meta-pill">
                        <div class="text-muted small">Order link</div>
                        @if ($selectedThread->order_id)
                            <a href="{{ url('order/detail/' . $selectedThread->order_id) }}" class="fw-semibold" target="_blank">Open order</a>
                        @elseif ($selectedThread->order_reference)
                            <a href="{{ url('order') . '?order_id=' . $selectedThread->order_reference }}" class="fw-semibold" target="_blank">Search order</a>
                        @else
                            <div class="fw-semibold">n/a</div>
                        @endif
                    </div>
                </div>

                @php
                    $order = $selectedThread->order;
                    $orderItems = $order?->order_items ?? collect();
                    $orderValue = $order && isset($order->price) ? number_format((float) $order->price, 2) : null;
                    $orderCurrency = $order?->currency ?? null;
                    $customer = $order?->customer;
                    $customerName = $customer ? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) : null;
                    $marketplaceReference = $selectedThread->order_reference
                        ?? ($order->reference_id ?? $order->reference ?? null);
                @endphp

                <div class="support-order-panel mt-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <h6 class="mb-1">Order information</h6>
                            <small class="text-muted">Internal order data synced with marketplace.</small>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($marketplaceOrderUrl)
                                <a href="{{ $marketplaceOrderUrl }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                                    View in marketplace
                                </a>
                            @endif
                            <button type="button" class="btn btn-outline-danger btn-sm" wire:click="cancelMarketplaceOrder" wire:loading.attr="disabled" wire:target="cancelMarketplaceOrder" @if (! $canCancelOrder) disabled @endif>
                                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true" wire:loading wire:target="cancelMarketplaceOrder"></span>
                                Cancel marketplace order
                            </button>
                        </div>
                    </div>

                    @if ($order)
                        @php
                            $customerEmail = $customer?->email;
                            $canSendInvoice = $customerEmail !== null && $customerEmail !== '';
                        @endphp
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" class="btn btn-outline-success btn-sm" wire:click="sendOrderInvoice" wire:loading.attr="disabled" wire:target="sendOrderInvoice" @if (! $canSendInvoice) disabled @endif>
                                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true" wire:loading wire:target="sendOrderInvoice"></span>
                                Send invoice
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" wire:click="sendRefundInvoice" wire:loading.attr="disabled" wire:target="sendRefundInvoice" @if (! $canSendInvoice) disabled @endif>
                                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true" wire:loading wire:target="sendRefundInvoice"></span>
                                Send refund invoice
                            </button>
                        </div>
                        @if (! $canSendInvoice)
                            <small class="text-danger d-block mt-1">Customer email missing for invoice delivery.</small>
                        @endif
                    @endif

                    @if ($orderActionError)
                        <div class="alert alert-danger py-2 px-3 mt-3 mb-0">{{ $orderActionError }}</div>
                    @endif
                    @if ($orderActionStatus)
                        <div class="alert alert-success py-2 px-3 mt-3 mb-0">{{ $orderActionStatus }}</div>
                    @endif
                    @if ($invoiceActionError)
                        <div class="alert alert-danger py-2 px-3 mt-3 mb-0">{{ $invoiceActionError }}</div>
                    @endif
                    @if ($invoiceActionStatus)
                        <div class="alert alert-success py-2 px-3 mt-3 mb-0">{{ $invoiceActionStatus }}</div>
                    @endif

                    @if ($orderActionPayload)
                        @php
                            $payloadJson = is_string($orderActionPayload)
                                ? $orderActionPayload
                                : json_encode($orderActionPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        @endphp
                        <div class="support-order-payload mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold">API response</span>
                                <small class="text-muted">Last cancellation attempt</small>
                            </div>
                            <pre class="mb-0">{{ $payloadJson }}</pre>
                        </div>
                    @endif

                    <div class="support-order-meta mt-3">
                        <div class="meta-pill">
                            <div class="text-muted small">Internal order</div>
                            <div class="fw-semibold">{{ $order ? '#' . $order->id : 'n/a' }}</div>
                        </div>
                        <div class="meta-pill">
                            <div class="text-muted small">Marketplace reference</div>
                            <div class="fw-semibold">{{ $marketplaceReference ?? 'n/a' }}</div>
                        </div>
                        <div class="meta-pill">
                            <div class="text-muted small">Items</div>
                            <div class="fw-semibold">{{ $orderItems->count() }}</div>
                        </div>
                        <div class="meta-pill">
                            <div class="text-muted small">Order value</div>
                            <div class="fw-semibold">{{ $orderValue ? $orderValue . ' ' . ($orderCurrency ?? '') : 'n/a' }}</div>
                        </div>
                        <div class="meta-pill">
                            <div class="text-muted small">Customer</div>
                            <div class="fw-semibold">{{ $customerName ?: ($selectedThread->buyer_name ?? 'n/a') }}</div>
                        </div>
                        <div class="meta-pill">
                            <div class="text-muted small">Status</div>
                            <div class="fw-semibold">{{ optional($order)->status ?? 'n/a' }}</div>
                        </div>
                    </div>

                    @if ($order)
                        @if ($orderItems->count() > 0)
                            <div class="table-responsive mt-3">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">Line</th>
                                            <th scope="col">SKU / Item</th>
                                            <th scope="col" class="text-center">Qty</th>
                                            <th scope="col" class="text-end">Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($orderItems as $item)
                                            <tr>
                                                <td>{{ $item->reference_id ?? ('#' . $item->id) }}</td>
                                                <td>
                                                    <div class="fw-semibold">{{ optional($item->variation)->sku ?? $item->reference ?? 'n/a' }}</div>
                                                    <small class="text-muted">{{ $item->status ?? 'pending' }}</small>
                                                </td>
                                                <td class="text-center">{{ $item->quantity ?? 1 }}</td>
                                                <td class="text-end">
                                                    @if ($item->price !== null)
                                                        {{ number_format((float) $item->price, 2) }} {{ $item->currency ?? $orderCurrency ?? '' }}
                                                    @else
                                                        n/a
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-muted small mt-3">No order items captured for this order.</div>
                        @endif
                    @else
                        <div class="text-muted small mt-3">No internal order is linked to this ticket yet.</div>
                    @endif
                </div>

                @if ($selectedThread && $selectedThread->marketplace_source === 'backmarket_care')
                    @if ($careFolderError)
                        <div class="alert alert-warning mt-3">{{ $careFolderError }}</div>
                    @endif

                    @if ($careFolderDetails)
                        <div class="support-order-panel mt-3">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div>
                                    <h6 class="mb-1">Back Market Care folder #{{ $careFolderDetails['id'] ?? 'n/a' }}</h6>
                                    <small class="text-muted">Live snapshot loaded directly from the Care API.</small>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-dark text-uppercase">{{ $careFolderDetails['state'] ?? 'n/a' }}</span>
                                    <span class="badge bg-primary text-uppercase">{{ $careFolderDetails['priority'] ?? 'n/a' }}</span>
                                </div>
                            </div>

                            @if (! empty($careFolderDetails['summary']))
                                <p class="mt-3 mb-0">{{ $careFolderDetails['summary'] }}</p>
                            @endif

                            <div class="support-order-meta mt-3">
                                <div class="meta-pill">
                                    <div class="text-muted small">Topic</div>
                                    <div class="fw-semibold">{{ $careFolderDetails['topic'] ?? 'n/a' }}</div>
                                </div>
                                <div class="meta-pill">
                                    <div class="text-muted small">Reason</div>
                                    <div class="fw-semibold">{{ $careFolderDetails['reason_code'] ?? 'n/a' }}</div>
                                </div>
                                <div class="meta-pill">
                                    <div class="text-muted small">Order ID</div>
                                    <div class="fw-semibold">{{ $careFolderDetails['order_id'] ?? 'n/a' }}</div>
                                </div>
                                <div class="meta-pill">
                                    <div class="text-muted small">Orderline</div>
                                    <div class="fw-semibold">{{ $careFolderDetails['orderline'] ?? 'n/a' }}</div>
                                </div>
                                <div class="meta-pill">
                                    <div class="text-muted small">Buyer</div>
                                    <div class="fw-semibold">{{ $careFolderDetails['buyer_name'] ?? 'Unknown' }}</div>
                                    <small class="text-muted">{{ $careFolderDetails['buyer_email'] ?? 'n/a' }}</small>
                                </div>
                                <div class="meta-pill">
                                    <div class="text-muted small">Created</div>
                                    <div class="fw-semibold">{{ $careFolderDetails['created_at_human'] ?? ($careFolderDetails['created_at'] ?? 'n/a') }}</div>
                                </div>
                                <div class="meta-pill">
                                    <div class="text-muted small">Last message</div>
                                    <div class="fw-semibold">{{ $careFolderDetails['last_message_at_human'] ?? ($careFolderDetails['last_message_at'] ?? 'n/a') }}</div>
                                </div>
                                <div class="meta-pill">
                                    <div class="text-muted small">Last update</div>
                                    <div class="fw-semibold">{{ $careFolderDetails['last_modification_at_human'] ?? ($careFolderDetails['last_modification_at'] ?? 'n/a') }}</div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (! empty($careFolderMessages))
                        <div class="support-reply-panel mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h6 class="mb-0">Care conversation</h6>
                                    <small class="text-muted">Messages returned from the Care API in real time.</small>
                                </div>
                                <div class="text-muted small">{{ count($careFolderMessages) }} messages</div>
                            </div>
                            <div class="care-message-feed">
                                @foreach ($careFolderMessages as $careMessage)
                                    <div class="care-message border rounded p-3 mb-2 {{ $careMessage['direction'] }}">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="fw-semibold">{{ $careMessage['author'] ?? 'Unknown author' }}</div>
                                                <small class="text-muted text-uppercase">{{ $careMessage['author_type'] ?? 'n/a' }}</small>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted">{{ $careMessage['sent_at_human'] ?? ($careMessage['sent_at'] ?? 'n/a') }}</small>
                                                <div>
                                                    <span class="badge bg-light text-dark text-uppercase">{{ $careMessage['direction'] }}</span>
                                                    @if ($careMessage['internal'])
                                                        <span class="badge bg-warning text-dark">Internal</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            @if (! empty($careMessage['body_html']))
                                                {!! $careMessage['body_html'] !!}
                                            @else
                                                {!! nl2br(e($careMessage['body'] ?? '')) !!}
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @elseif ($careFolderDetails && ! $careFolderError)
                        <small class="text-muted d-block mt-3">No live messages were returned for this Care folder.</small>
                    @endif
                @endif

                <div class="mt-3 d-flex flex-wrap gap-2">
                    @foreach ($selectedThread->tags as $tag)
                        <span class="tag-chip" style="color: {{ $tag->color ?? '#2563eb' }}">{{ $tag->name }}</span>
                    @endforeach
                </div>

                <div class="support-reply-panel mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="mb-0">Compose reply</h6>
                            <small class="text-muted">Message sends through the connected Gmail API</small>
                        </div>
                        <div class="text-muted small" wire:loading wire:target="sendReply">Sending…</div>
                    </div>
                    @if ($replyStatus)
                        <div class="alert alert-success py-2 px-3">{{ $replyStatus }}</div>
                    @endif
                    @if ($replyError)
                        <div class="alert alert-danger py-2 px-3">{{ $replyError }}</div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label">To</label>
                        <input type="text" class="form-control" value="{{ $replyRecipient ?: 'No recipient available' }}" disabled>
                        @if (! $replyRecipientEmail)
                            <small class="text-danger">Recipient email missing for this ticket.</small>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" wire:model.defer="replySubject" placeholder="Subject">
                        @error('replySubject')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" rows="5" wire:model.defer="replyBody" placeholder="Type your reply"></textarea>
                        @error('replyBody')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Replies are logged here and emailed instantly.</small>
                        <button type="button" class="btn btn-primary" wire:click="sendReply" wire:loading.attr="disabled" wire:target="sendReply" @if (! $replyRecipientEmail) disabled @endif>
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true" wire:loading wire:target="sendReply"></span>
                            Send via Gmail
                        </button>
                    </div>
                </div>

                <div class="message-feed">
                    @forelse ($selectedThread->messages as $message)
                        @php
                            $activeTranslation = $messageTranslations[$message->id]['text'] ?? null;
                            $showFullEmail = isset($expandedMessages[$message->id]);
                        @endphp
                        <div class="message {{ $message->is_internal_note ? 'message-note' : ($message->direction === 'outbound' ? 'outbound' : 'inbound') }}">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-semibold">{{ $message->author_name ?? ($message->direction === 'outbound' ? 'Nib Support' : 'Customer') }}</div>
                                <div class="text-muted small">{{ optional($message->sent_at)->format('d M Y H:i') ?? 'n/a' }}</div>
                            </div>
                            <div class="d-flex justify-content-end flex-wrap gap-2 mb-2 translation-controls">
                                <button type="button" class="btn btn-sm btn-outline-dark" wire:click="toggleFullMessage({{ $message->id }})">
                                    {{ $showFullEmail ? 'Hide full email' : 'View full email' }}
                                </button>
                                @if (! $activeTranslation)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="translateMessage({{ $message->id }})" wire:loading.attr="disabled" wire:target="translateMessage({{ $message->id }})">
                                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true" wire:loading wire:target="translateMessage({{ $message->id }})"></span>
                                        Translate to English
                                    </button>
                                @else
                                    <button type="button" class="btn btn-sm btn-light border" wire:click="clearTranslation({{ $message->id }})">
                                        Hide translation
                                    </button>
                                @endif
                            </div>
                            <div class="message-body">
                                @if ($showFullEmail && $message->body_html)
                                    {!! $message->body_html !!}
                                @elseif ($showFullEmail)
                                    {!! nl2br(e($message->body_text ?? '')) !!}
                                @elseif ($message->clean_body_html !== '')
                                    {!! $message->clean_body_html !!}
                                @elseif ($message->body_html)
                                    {!! $message->body_html !!}
                                @else
                                    {!! nl2br(e($message->body_text ?? '')) !!}
                                @endif
                            </div>
                            @if ($activeTranslation)
                                <div class="message-translation mt-2">
                                    <div class="translation-label mb-1">English translation</div>
                                    <div>{!! nl2br(e($activeTranslation)) !!}</div>
                                </div>
                            @endif
                            @if (! empty($message->detected_links))
                                <div class="email-links mt-2">
                                    <div class="translation-label mb-1 text-uppercase">Links in this email</div>
                                    <ul class="mb-0 ps-3">
                                        @foreach ($message->detected_links as $link)
                                            <li>
                                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener">
                                                    {{ $link['label'] }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @if (!empty($message->attachments))
                                <div class="mt-2">
                                    <div class="text-muted small mb-1">Attachments</div>
                                    <ul class="list-unstyled mb-0">
                                        @foreach ($message->attachments as $attachment)
                                            <li>
                                                <a href="{{ $attachment['url'] ?? '#' }}" target="_blank">{{ $attachment['name'] ?? 'Download' }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="alert alert-light border">No conversation history yet.</div>
                    @endforelse
                </div>
            @else
                <div class="text-center text-muted my-auto">
                    <h5>Select a support thread to view details.</h5>
                </div>
            @endif
        </div>
    </div>
</div>
