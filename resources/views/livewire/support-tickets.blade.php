{{-- Support Tickets Workspace --}}

@php
    use Illuminate\Support\Str;
@endphp

@section('styles')
<style>
    .support-shell {
        min-height: calc(100vh - 140px);
        background: linear-gradient(135deg, #f5f7fb 0%, #f0f4ff 40%, #f8fbff 100%);
        padding: 1.5rem;
        border-radius: 1.25rem;
    }

    .support-grid {
        display: grid;
        grid-template-columns: 360px 1fr;
        gap: 1.5rem;
        margin-top: 1rem;
    }

    .support-panel {
        background: #fff;
        border-radius: 1.25rem;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .support-filters {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.75rem;
    }

    .support-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        overflow-y: auto;
        padding-right: 0.25rem;
    }

    .support-thread {
        border: 1px solid transparent;
        border-radius: 0.9rem;
        padding: 0.85rem 1rem;
        text-align: left;
        background: #f9fbff;
        transition: border-color 0.2s ease, background 0.2s ease;
    }

    .support-thread.active {
        border-color: #2563eb;
        background: #e8efff;
    }

    .tag-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.15rem 0.65rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        background: rgba(37, 99, 235, 0.08);
    }

    .tag-chip::before {
        content: '';
        width: 0.45rem;
        height: 0.45rem;
        border-radius: 50%;
        background: currentColor;
    }

    .support-detail-header {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: space-between;
        align-items: center;
    }

    .message-feed {
        margin-top: 1.25rem;
        padding: 1rem;
        border-radius: 1rem;
        background: #f6f7fb;
        overflow-y: auto;
        flex: 1;
    }

    .message {
        background: #fff;
        border-radius: 1rem;
        padding: 0.85rem 1rem;
        margin-bottom: 0.85rem;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05);
    }

    .message.outbound {
        border-left: 4px solid #16a34a;
    }

    .message.inbound {
        border-left: 4px solid #2563eb;
    }

    .message-note {
        border-left: 4px solid #f97316;
        background: #fff7ed;
    }

    .support-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .meta-pill {
        background: #eef2ff;
        border-radius: 0.75rem;
        padding: 0.65rem 0.85rem;
        font-size: 0.9rem;
    }

    @media (max-width: 1100px) {
        .support-grid {
            grid-template-columns: 1fr;
        }

        .support-shell {
            padding: 1rem;
        }
    }
</style>
@endsection

<div class="support-shell">
    <div class="support-panel mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-1">Support Hub</h4>
                <p class="text-muted mb-0">Live view of Back Market Care + Refurbed tickets.</p>
            </div>
            <div class="d-flex gap-2">
                <button wire:click="resetFilters" type="button" class="btn btn-light border">Reset Filters</button>
            </div>
        </div>

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
                                    $previewSource = $thread->messages->first()->body_text ?? $thread->messages->first()->body_html ?? '';
                                @endphp
                                {{ Str::limit(strip_tags($previewSource), 120) }}
                            </div>
                        @endif
                    </button>
                @empty
                    <div class="alert alert-light border">No support threads match your filters.</div>
                @endforelse
            </div>
            <div class="mt-2">
                {{ $threads->links() }}
            </div>
        </div>

        <div class="support-panel">
            @if ($selectedThread)
                <div class="support-detail-header">
                    <div>
                        <h4 class="mb-1">{{ $selectedThread->order_reference ?? $selectedThread->external_thread_id }}</h4>
                        <p class="text-muted mb-0">{{ $selectedThread->buyer_name ?? 'Unknown buyer' }} · {{ $selectedThread->buyer_email ?? 'No email' }}</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-dark text-uppercase">{{ $selectedThread->status ?? 'open' }}</span>
                        <span class="badge bg-primary text-uppercase">{{ $selectedThread->priority ?? 'normal' }}</span>
                        @if ($selectedThread->change_of_mind)
                            <span class="badge bg-warning text-dark">Change of mind</span>
                        @endif
                    </div>
                </div>

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

                <div class="mt-3 d-flex flex-wrap gap-2">
                    @foreach ($selectedThread->tags as $tag)
                        <span class="tag-chip" style="color: {{ $tag->color ?? '#2563eb' }}">{{ $tag->name }}</span>
                    @endforeach
                </div>

                <div class="message-feed">
                    @forelse ($selectedThread->messages as $message)
                        <div class="message {{ $message->is_internal_note ? 'message-note' : ($message->direction === 'outbound' ? 'outbound' : 'inbound') }}">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-semibold">{{ $message->author_name ?? ($message->direction === 'outbound' ? 'Nib Support' : 'Customer') }}</div>
                                <div class="text-muted small">{{ optional($message->sent_at)->format('d M Y H:i') ?? 'n/a' }}</div>
                            </div>
                            <div class="message-body">
                                @if ($message->body_html)
                                    {!! $message->body_html !!}
                                @else
                                    {!! nl2br(e($message->body_text ?? '')) !!}
                                @endif
                            </div>
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
