@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $data['title_page'] ?? 'Command run details' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/logs/stock-sync') }}">Logs</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/logs/stock-sync') }}">Command runs</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $log->slug }}</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Command: <code>{{ $log->slug }}</code></h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Status</th>
                                    <td>
                                        @if($log->status == 'running')
                                            <span class="badge bg-warning">Running</span>
                                        @elseif($log->status == 'completed')
                                            <span class="badge bg-success">Completed</span>
                                        @elseif($log->status == 'failed')
                                            <span class="badge bg-danger">Failed</span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($log->status) }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Last started</th>
                                    <td>{{ $log->last_started_at ? $log->last_started_at->format('Y-m-d H:i:s') : '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Last completed</th>
                                    <td>{{ $log->last_completed_at ? $log->last_completed_at->format('Y-m-d H:i:s') : '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Duration</th>
                                    <td>
                                        @if($log->duration_seconds !== null)
                                            {{ gmdate('H:i:s', $log->duration_seconds) }} ({{ $log->duration_seconds }}s)
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Total processed</th>
                                    <td>{{ $log->total_processed }}</td>
                                </tr>
                                <tr>
                                    <th>OK</th>
                                    <td class="text-success">{{ $log->processed_ok }}</td>
                                </tr>
                                <tr>
                                    <th>Failed</th>
                                    <td class="text-danger">{{ $log->processed_failed }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    @if($log->last_note)
                    <div class="mb-4">
                        <h6>Last note</h6>
                        <div class="alert alert-info mb-0" style="white-space: pre-wrap;">{{ $log->last_note }}</div>
                    </div>
                    @endif

                    <div class="mt-4 d-flex justify-content-between">
                        <a href="{{ url('v2/logs/stock-sync') }}" class="btn btn-secondary"><i class="fe fe-arrow-left"></i> Back to list</a>
                        <button type="button" class="btn btn-danger" onclick="deleteLog({{ $log->id }})"><i class="fe fe-trash-2"></i> Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function deleteLog(logId) {
    if (!confirm('Delete this log entry? It will reappear when the command runs again.')) return;
    fetch('{{ url("v2/logs/stock-sync") }}/' + logId, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'Accept': 'application/json', 'Content-Type': 'application/json' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) setTimeout(function() { window.location.href = '{{ url("v2/logs/stock-sync") }}'; }, 500);
        else alert(data.error || 'Failed');
    });
}
</script>
@endsection
