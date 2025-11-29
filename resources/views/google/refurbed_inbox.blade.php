@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Refurbed Gmail Inbox</h4>
                <form method="GET" action="{{ route('google.refurbed_inbox') }}" class="row g-3 align-items-end">
                    <div class="col-lg-6 col-md-12">
                        <label class="form-label">Gmail Query</label>
                        <input type="text" name="query" class="form-control" value="{{ $query }}" placeholder="subject:&quot;refurbed inquiry&quot;">
                        <small class="text-muted">Use Gmail search syntax. Default filters Refurbed inquiry/zendesk alerts.</small>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Labels</label>
                        @php
                            $labelOptions = [
                                'INBOX' => 'Inbox',
                                'UNREAD' => 'Unread',
                                'STARRED' => 'Starred',
                                'IMPORTANT' => 'Important',
                            ];
                        @endphp
                        <select name="labelIds[]" multiple class="form-select">
                            @foreach($labelOptions as $value => $label)
                                <option value="{{ $value }}" {{ in_array($value, $labelIds ?? []) ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Ctrl/Cmd + click for multi-select. Defaults to Inbox.</small>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label">Max Results</label>
                        <input type="number" name="maxResults" class="form-control" value="{{ $maxResults }}" min="1" max="100">
                    </div>
                    <div class="col-lg-1 col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                    @if($pageToken)
                        <input type="hidden" name="pageToken" value="{{ $pageToken }}">
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Latest Messages</h5>
                    <small class="text-muted">Showing {{ count($messages) }} / approx. {{ $resultSizeEstimate ?? 0 }} results</small>
                </div>
                <div class="btn-group">
                    <a href="{{ route('google.refurbed_inbox') }}" class="btn btn-outline-secondary btn-sm">Reset Filters</a>
                    <a href="{{ route('google.refurbed_inbox', request()->except('pageToken')) }}" class="btn btn-outline-secondary btn-sm">Reset Paging</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 20%">Subject</th>
                                <th style="width: 22%">From</th>
                                <th style="width: 15%">Date</th>
                                <th style="width: 22%">Refurbed Ticket</th>
                                <th>Snippet</th>
                                <th style="width: 12%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($messages as $message)
                                @php
                                    $gmailUrl = 'https://mail.google.com/mail/u/0/#inbox/' . $message['id'];
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $message['subject'] ?? 'No Subject' }}</strong>
                                    </td>
                                    <td>{{ $message['from'] ?? 'Unknown Sender' }}</td>
                                    <td>{{ $message['date'] ?? '-' }}</td>
                                    <td>
                                        @if(!empty($message['ticketLink']))
                                            <div class="d-flex flex-column gap-1">
                                                <div class="d-flex flex-wrap gap-2">
                                                    <a href="{{ $message['ticketLink'] }}" class="btn btn-sm btn-outline-success" target="_blank" rel="noopener">Open Ticket</a>
                                                    @if (!empty($message['ticketId']))
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-primary attach-ticket-btn"
                                                            data-ticket-link="{{ $message['ticketLink'] }}"
                                                            data-ticket-id="{{ $message['ticketId'] }}"
                                                            data-email-subject="{{ $message['subject'] ?? '' }}"
                                                            data-email-from="{{ $message['from'] ?? '' }}"
                                                            data-email-snippet="{{ $message['snippet'] ?? '' }}"
                                                        >Attach ticket</button>
                                                    @endif
                                                </div>
                                                <div>
                                                    <small class="text-muted">
                                                        Ticket #{{ $message['ticketId'] ?? 'Unknown' }}
                                                    </small>
                                                </div>
                                                <div>
                                                    <small class="text-muted">{{ parse_url($message['ticketLink'], PHP_URL_PATH) }}</small>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">Not detected</span>
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($message['snippet'] ?? '', 200) }}</td>
                                    <td>
                                        <a href="{{ $gmailUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center p-4">No messages match your filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($nextPageToken)
                <div class="card-footer d-flex justify-content-between">
                    <span>More messages available...</span>
                    <a href="{{ route('google.refurbed_inbox', array_merge(request()->all(), ['pageToken' => $nextPageToken])) }}" class="btn btn-primary btn-sm">Next Page</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('modal')
<div class="modal fade" id="attachTicketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('google.refurbed_inbox.attach_ticket') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Link Refurbed ticket to order item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Zendesk ticket URL</label>
                            <input type="text" class="form-control" id="ticket_link_display" readonly>
                            <input type="hidden" name="ticket_link" id="ticket_link_input">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ticket ID</label>
                            <input type="text" class="form-control" name="ticket_id" id="ticket_id_input" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Order reference ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="order_reference" id="order_reference_input" placeholder="e.g. 123456" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Refurbed order item ID</label>
                            <input type="text" class="form-control" name="order_item_reference" id="order_item_reference_input" placeholder="Marketplace order line ID">
                            <small class="text-muted">Required when the order contains multiple items unless you apply to every item.</small>
                        </div>
                        <div class="col-md-6 align-self-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="apply_to_all_items" name="apply_to_all_items">
                                <label class="form-check-label" for="apply_to_all_items">
                                    Apply to every order item in this order
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email context</label>
                            <textarea class="form-control" rows="3" id="ticket_context_preview" readonly placeholder="Email subject and snippet will appear here"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Link ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalElement = document.getElementById('attachTicketModal');
        if (!modalElement) {
            return;
        }

        const modal = new bootstrap.Modal(modalElement);
        const linkDisplay = document.getElementById('ticket_link_display');
        const linkInput = document.getElementById('ticket_link_input');
        const ticketIdInput = document.getElementById('ticket_id_input');
        const orderReferenceInput = document.getElementById('order_reference_input');
        const orderItemReferenceInput = document.getElementById('order_item_reference_input');
        const contextPreview = document.getElementById('ticket_context_preview');
        const applyAllCheckbox = document.getElementById('apply_to_all_items');

        document.querySelectorAll('.attach-ticket-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                const ticketLink = this.dataset.ticketLink || '';
                const ticketId = this.dataset.ticketId || '';
                const emailSubject = this.dataset.emailSubject || '';
                const emailSnippet = this.dataset.emailSnippet || '';
                const emailFrom = this.dataset.emailFrom || '';

                linkDisplay.value = ticketLink;
                linkInput.value = ticketLink;
                ticketIdInput.value = ticketId;
                orderReferenceInput.value = '';
                orderItemReferenceInput.value = '';
                applyAllCheckbox.checked = false;

                const contextLines = [];
                if (emailSubject) {
                    contextLines.push('Subject: ' + emailSubject);
                }
                if (emailFrom) {
                    contextLines.push('From: ' + emailFrom);
                }
                if (emailSnippet) {
                    contextLines.push('Snippet: ' + emailSnippet);
                }
                contextPreview.value = contextLines.join('\n');

                modal.show();
            });
        });

        modalElement.addEventListener('hidden.bs.modal', function () {
            linkDisplay.value = '';
            linkInput.value = '';
            ticketIdInput.value = '';
            orderReferenceInput.value = '';
            orderItemReferenceInput.value = '';
            applyAllCheckbox.checked = false;
            contextPreview.value = '';
        });
    });
</script>
@endsection
