@extends('layouts.app')

@section('styles')
<style>
    .job-card {
        transition: all 0.3s ease;
    }
    .job-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $data['title_page'] ?? 'Queue Jobs' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/logs/jobs') }}">Logs</a></li>
                <li class="breadcrumb-item active" aria-current="page">Jobs</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            @if(isset($error))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error:</strong> {{ $error }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif
            
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif
            
            @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Total Jobs</h6>
                            <h3 class="mb-0">{{ $stats['total'] }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Queued</h6>
                            <h3 class="mb-0 text-info">{{ $stats['queued'] }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-1">Processing</h6>
                            <h3 class="mb-0 text-warning">{{ $stats['processing'] }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <form method="GET" action="{{ url('v2/logs/jobs') }}" class="d-inline-flex gap-2">
                                <select name="queue" class="form-control form-select" style="width: auto;">
                                    <option value="">All Queues</option>
                                    @foreach($queues as $q)
                                        <option value="{{ $q }}" {{ request('queue') == $q ? 'selected' : '' }}>
                                            {{ $q }}
                                        </option>
                                    @endforeach
                                </select>
                                <select name="status" class="form-control form-select" style="width: auto;">
                                    <option value="all" {{ request('status') == 'all' || !request('status') ? 'selected' : '' }}>All Status</option>
                                    <option value="queued" {{ request('status') == 'queued' ? 'selected' : '' }}>Queued</option>
                                    <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>Processing</option>
                                </select>
                                <select name="per_page" class="form-control form-select" style="width: auto;">
                                    <option value="20" {{ request('per_page') == 20 ? 'selected' : '' }}>20 per page</option>
                                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 per page</option>
                                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 per page</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="{{ url('v2/logs/jobs') }}" class="btn btn-secondary">Reset</a>
                            </form>
                        </div>
                        <div>
                            <button type="button" class="btn btn-success me-2" onclick="processAllJobs()">
                                <i class="fe fe-play me-1"></i>Process All Queued
                            </button>
                            <button type="button" class="btn btn-danger" onclick="clearAllJobs()">
                                <i class="fe fe-trash-2 me-1"></i>Clear All
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Jobs Table -->
            @if($jobs->count() > 0)
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Queue Jobs</h5>
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
                                        <th>Status</th>
                                        <th>Attempts</th>
                                        <th>Created At</th>
                                        <th>Available At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($jobs as $job)
                                    <tr>
                                        <td><code>{{ $job->id }}</code></td>
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
                                            <span class="badge bg-{{ $job->status_class ?? 'secondary' }}">
                                                {{ $job->status_label ?? 'Unknown' }}
                                            </span>
                                        </td>
                                        <td>{{ $job->attempts ?? 0 }}</td>
                                        <td>{{ \Carbon\Carbon::parse($job->created_at)->format('Y-m-d H:i:s') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($job->available_at)->format('Y-m-d H:i:s') }}</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ url('v2/logs/jobs/' . $job->id) }}" class="btn btn-sm btn-primary" title="View Details">
                                                    <i class="fe fe-eye"></i>
                                                </a>
                                                @if(!$job->reserved_at)
                                                <button type="button" class="btn btn-sm btn-success" onclick="processJob({{ $job->id }})" title="Process Job">
                                                    <i class="fe fe-play"></i>
                                                </button>
                                                @endif
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
                            {{ $jobs->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fe fe-info me-2"></i>No jobs found in queue.
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
function processJob(jobId) {
    if (!confirm('Are you sure you want to process this job now? This will execute it immediately.')) {
        return;
    }
    
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fe fe-loader fa-spin"></i>';
    
    fetch('{{ url("v2/logs/jobs") }}/' + jobId + '/process', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message || 'Job processed successfully');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert('danger', data.error || 'Failed to process job');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while processing the job');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
}

function deleteJob(jobId) {
    if (!confirm('Are you sure you want to delete this job? This action cannot be undone.')) {
        return;
    }
    
    fetch('{{ url("v2/logs/jobs") }}/' + jobId, {
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

function processAllJobs() {
    const queuedCount = {{ $stats['queued'] ?? 0 }};
    
    if (queuedCount === 0) {
        showAlert('info', 'No queued jobs to process');
        return;
    }
    
    if (!confirm(`Are you sure you want to process all ${queuedCount} queued job(s)? This will execute them immediately.`)) {
        return;
    }
    
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fe fe-loader fa-spin me-1"></i>Processing...';
    
    fetch('{{ route("v2.logs.jobs.process-all") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message || 'Jobs processed successfully');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('danger', data.error || 'Failed to process jobs');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while processing jobs');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
}

function clearAllJobs() {
    if (!confirm('Are you sure you want to delete ALL jobs? This action cannot be undone.')) {
        return;
    }
    
    fetch('{{ url("v2/logs/jobs") }}', {
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
            showAlert('success', data.message || 'All jobs cleared successfully');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert('danger', data.error || 'Failed to clear jobs');
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
