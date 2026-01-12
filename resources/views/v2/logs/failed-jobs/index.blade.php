@extends('layouts.app')

@section('styles')
<style>
    .job-card {
        transition: all 0.3s ease;
    }
    .job-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .exception-preview {
        max-width: 500px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    pre {
        max-height: 400px;
        overflow-y: auto;
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $data['title_page'] ?? 'Failed Jobs' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/logs/failed-jobs') }}">Logs</a></li>
                <li class="breadcrumb-item active" aria-current="page">Failed Jobs</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Total Failed Jobs</h6>
                            <h3 class="mb-0 text-danger">{{ $stats['total'] }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Last 24 Hours</h6>
                            <h3 class="mb-0 text-warning">{{ $stats['last_24h'] }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Last 7 Days</h6>
                            <h3 class="mb-0">{{ $stats['last_7d'] }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <form method="GET" action="{{ url('v2/logs/failed-jobs') }}" class="d-inline-flex gap-2">
                                <select name="queue" class="form-control form-select" style="width: auto;">
                                    <option value="">All Queues</option>
                                    @foreach($queues as $q)
                                        <option value="{{ $q }}" {{ request('queue') == $q ? 'selected' : '' }}>
                                            {{ $q }}
                                        </option>
                                    @endforeach
                                </select>
                                <select name="per_page" class="form-control form-select" style="width: auto;">
                                    <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20 per page</option>
                                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 per page</option>
                                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 per page</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="{{ url('v2/logs/failed-jobs') }}" class="btn btn-secondary">Reset</a>
                            </form>
                        </div>
                        <div>
                            <button type="button" class="btn btn-danger" onclick="clearAllFailedJobs()">
                                <i class="fe fe-trash-2 me-1"></i>Clear All
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Failed Jobs Table -->
            @if($failedJobs->count() > 0)
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Failed Jobs</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Job Class</th>
                                        <th>Command</th>
                                        <th>Queue</th>
                                        <th>Exception</th>
                                        <th>Failed At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($failedJobs as $job)
                                    <tr>
                                        <td>{{ $job->id }}</td>
                                        <td>
                                            <code>{{ $job->job_class ?? 'Unknown' }}</code>
                                        </td>
                                        <td>
                                            @if($job->command)
                                                <code>{{ $job->command }}</code>
                                                @if(!empty($job->options))
                                                    <br><small class="text-muted">
                                                        @foreach($job->options as $key => $value)
                                                            --{{ $key }}=@if(is_bool($value)){{ $value ? 'true' : 'false' }}@else{{ $value }}@endif
                                                        @endforeach
                                                    </small>
                                                @endif
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>{{ $job->queue ?? 'default' }}</td>
                                        <td>
                                            <div class="exception-preview" title="{{ $job->exception_message }}">
                                                <strong>{{ $job->exception_class ?? 'Exception' }}</strong><br>
                                                <small class="text-danger">{{ Str::limit($job->exception_message, 100) }}</small>
                                            </div>
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($job->failed_at)->format('Y-m-d H:i:s') }}</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ url('v2/logs/failed-jobs/' . $job->id) }}" class="btn btn-sm btn-primary" title="View Details">
                                                    <i class="fe fe-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-success" onclick="retryJob({{ $job->id }})" title="Retry Job">
                                                    <i class="fe fe-refresh-cw"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteJob({{ $job->id }})" title="Delete Job">
                                                    <i class="fe fe-trash-2"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $failedJobs->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fe fe-info me-2"></i>No failed jobs found.
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function retryJob(jobId) {
    if (!confirm('Are you sure you want to retry this job?')) {
        return;
    }
    
    fetch('{{ url("v2/logs/failed-jobs") }}/' + jobId + '/retry', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success || response.ok) {
            showAlert('success', 'Job queued for retry');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert('danger', data.error || 'Failed to retry job');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while retrying the job');
    });
}

function deleteJob(jobId) {
    if (!confirm('Are you sure you want to delete this failed job? This action cannot be undone.')) {
        return;
    }
    
    fetch('{{ url("v2/logs/failed-jobs") }}/' + jobId, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message || 'Job deleted successfully');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert('danger', data.error || 'Failed to delete job');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while deleting the job');
    });
}

function clearAllFailedJobs() {
    if (!confirm('Are you sure you want to delete ALL failed jobs? This action cannot be undone.')) {
        return;
    }
    
    fetch('{{ url("v2/logs/failed-jobs") }}', {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (response.ok) {
            showAlert('success', 'All failed jobs cleared');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            return response.json().then(data => {
                showAlert('danger', data.error || 'Failed to clear jobs');
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while clearing jobs');
    });
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + type + ' alert-dismissible fade show position-fixed';
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = message + 
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>
@endsection
