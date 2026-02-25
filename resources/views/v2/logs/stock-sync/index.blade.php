@extends('layouts.app')

@section('styles')
<style>
    .status-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
    .log-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $data['title_page'] ?? 'Command run logs' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/logs/stock-sync') }}">Logs</a></li>
                <li class="breadcrumb-item active" aria-current="page">Command runs</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <p class="text-muted">One row per periodic command. Each run overwrites the same slug with last start time, end time, counts and note.</p>

            @if($logs->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Command (slug)</th>
                            <th>Status</th>
                            <th>Last started</th>
                            <th>Last completed</th>
                            <th>Duration</th>
                            <th>Total</th>
                            <th>OK</th>
                            <th>Failed</th>
                            <th>Last note</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        <tr data-log-id="{{ $log->id }}">
                            <td><code>{{ $log->slug }}</code></td>
                            <td>
                                @if($log->status == 'running')
                                    <span class="badge bg-warning status-badge" title="Click to change" onclick="openStatusModal({{ $log->id }}, 'running')" style="cursor: pointer;">Running</span>
                                @elseif($log->status == 'completed')
                                    <span class="badge bg-success status-badge" title="Click to change" onclick="openStatusModal({{ $log->id }}, 'completed')" style="cursor: pointer;">Completed</span>
                                @elseif($log->status == 'failed')
                                    <span class="badge bg-danger status-badge" title="Click to change" onclick="openStatusModal({{ $log->id }}, 'failed')" style="cursor: pointer;">Failed</span>
                                @else
                                    <span class="badge bg-secondary status-badge" title="Click to change" onclick="openStatusModal({{ $log->id }}, '{{ $log->status }}')" style="cursor: pointer;">{{ ucfirst($log->status) }}</span>
                                @endif
                            </td>
                            <td>{{ $log->last_started_at ? $log->last_started_at->format('Y-m-d H:i:s') : '-' }}</td>
                            <td>{{ $log->last_completed_at ? $log->last_completed_at->format('Y-m-d H:i:s') : '-' }}</td>
                            <td>
                                @if($log->duration_seconds !== null)
                                    {{ gmdate('H:i:s', $log->duration_seconds) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $log->total_processed }}</td>
                            <td class="text-success">{{ $log->processed_ok }}</td>
                            <td class="text-danger">{{ $log->processed_failed }}</td>
                            <td class="small text-break" style="max-width: 280px;" title="{{ $log->last_note }}">{{ Str::limit($log->last_note, 60) }}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ url('v2/logs/stock-sync/' . $log->id) }}" class="btn btn-sm btn-primary" title="View details"><i class="fe fe-eye"></i></a>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteLog({{ $log->id }})" title="Delete"><i class="fe fe-trash-2"></i></button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fe fe-info me-2"></i>No command run logs yet. Rows appear when periodic commands (e.g. refresh:new, refresh:orders) run.
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Status change modal -->
<div class="modal fade" id="statusChangeModal" tabindex="-1" aria-labelledby="statusChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusChangeModalLabel">Change status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Current: <span id="currentStatusBadge"></span></p>
                <p class="mb-3">Select new status:</p>
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action" onclick="selectStatus('running'); return false;"><span class="badge bg-warning me-2">Running</span> Running</a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="selectStatus('completed'); return false;"><span class="badge bg-success me-2">Completed</span> Completed</a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="selectStatus('failed'); return false;"><span class="badge bg-danger me-2">Failed</span> Failed</a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="selectStatus('cancelled'); return false;"><span class="badge bg-secondary me-2">Cancelled</span> Cancelled</a>
                </div>
                <input type="hidden" id="selectedLogId" value="">
                <input type="hidden" id="selectedStatus" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmStatusChange()">Change status</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let statusModal;
document.addEventListener('DOMContentLoaded', function() { statusModal = new bootstrap.Modal(document.getElementById('statusChangeModal')); });

function openStatusModal(logId, currentStatus) {
    var badgeClass = 'badge bg-secondary';
    if (currentStatus === 'running') badgeClass = 'badge bg-warning';
    else if (currentStatus === 'completed') badgeClass = 'badge bg-success';
    else if (currentStatus === 'failed') badgeClass = 'badge bg-danger';
    document.getElementById('currentStatusBadge').innerHTML = '<span class="' + badgeClass + '">' + currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1) + '</span>';
    document.getElementById('selectedLogId').value = logId;
    document.getElementById('selectedStatus').value = '';
    document.querySelectorAll('#statusChangeModal .list-group-item').forEach(function(item) { item.classList.remove('active'); });
    statusModal.show();
}

function selectStatus(status) {
    document.getElementById('selectedStatus').value = status;
    document.querySelectorAll('#statusChangeModal .list-group-item').forEach(function(item) {
        item.classList.remove('active');
        if (item.textContent.includes(status.charAt(0).toUpperCase() + status.slice(1))) item.classList.add('active');
    });
}

function confirmStatusChange() {
    var logId = document.getElementById('selectedLogId').value;
    var newStatus = document.getElementById('selectedStatus').value;
    if (!newStatus) { showAlert('warning', 'Please select a status'); return; }
    changeStatus(logId, newStatus);
    statusModal.hide();
}

function deleteLog(logId) {
    if (!confirm('Delete this log entry? The row will reappear when the command runs again.')) return;
    fetch('{{ url("v2/logs/stock-sync") }}/' + logId, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'Accept': 'application/json', 'Content-Type': 'application/json' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showAlert('success', data.message || 'Deleted');
            var row = document.querySelector('tr[data-log-id="' + logId + '"]');
            if (row) row.remove(); else setTimeout(function() { window.location.reload(); }, 1000);
        } else showAlert('danger', data.error || 'Failed to delete');
    })
    .catch(function(err) { console.error(err); showAlert('danger', 'Error deleting'); });
}

function changeStatus(logId, newStatus) {
    fetch('{{ url("v2/logs/stock-sync") }}/' + logId + '/status', {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ status: newStatus })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) { showAlert('success', data.message || 'Status updated'); setTimeout(function() { window.location.reload(); }, 1000); }
        else showAlert('danger', data.error || 'Failed');
    })
    .catch(function(err) { console.error(err); showAlert('danger', 'Error updating status'); });
}

function showAlert(type, message) {
    var d = document.createElement('div');
    d.className = 'alert alert-' + type + ' alert-dismissible fade show position-fixed';
    d.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    d.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    document.body.appendChild(d);
    setTimeout(function() { if (d.parentNode) d.remove(); }, 5000);
}
</script>
@endsection
