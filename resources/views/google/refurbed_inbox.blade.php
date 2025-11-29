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
                                <th style="width: 18%">Refurbed Ticket</th>
                                <th>Snippet</th>
                                <th style="width: 10%">Action</th>
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
                                            <a href="{{ $message['ticketLink'] }}" class="btn btn-sm btn-outline-success" target="_blank" rel="noopener">Open Ticket</a>
                                            <div><small class="text-muted">{{ parse_url($message['ticketLink'], PHP_URL_PATH) }}</small></div>
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
                                    <td colspan="5" class="text-center p-4">No messages match your filters.</td>
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
