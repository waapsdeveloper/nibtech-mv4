@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $data['title_page'] ?? 'Job Details' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/logs/jobs') }}">Logs</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/logs/jobs') }}">Jobs</a></li>
                <li class="breadcrumb-item active" aria-current="page">Job Details</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Job Details</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Job ID:</strong> <code>{{ $job->id }}</code>
                        </div>
                        <div class="col-md-6">
                            <strong>Queue:</strong> {{ $job->queue ?? 'default' }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Status:</strong>
                            <span class="badge bg-{{ $status === 'processing' ? 'warning' : 'info' }}">
                                {{ $status === 'processing' ? 'Processing' : 'Queued' }}
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong>Attempts:</strong> {{ $job->attempts ?? 0 }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Created At:</strong> {{ \Carbon\Carbon::parse($job->created_at)->format('Y-m-d H:i:s') }}
                        </div>
                        <div class="col-md-6">
                            <strong>Available At:</strong> {{ \Carbon\Carbon::parse($job->available_at)->format('Y-m-d H:i:s') }}
                        </div>
                    </div>

                    @if($job->reserved_at)
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Reserved At:</strong> {{ \Carbon\Carbon::createFromTimestamp($job->reserved_at)->format('Y-m-d H:i:s') }}
                        </div>
                    </div>
                    @endif

                    <hr>

                    <h6 class="mb-3">Job Payload</h6>
                    <pre><code>{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre>

                    <div class="mt-4">
                        <a href="{{ url('v2/logs/jobs') }}" class="btn btn-secondary">Back to Jobs</a>
                        @if(!$job->reserved_at)
                        <button type="button" class="btn btn-success" onclick="processJob({{ $job->id }})">
                            <i class="fe fe-play me-1"></i>Process Job
                        </button>
                        @endif
                        <button type="button" class="btn btn-danger" onclick="deleteJob({{ $job->id }})">
                            <i class="fe fe-trash-2 me-1"></i>Delete Job
                        </button>
                    </div>
                </div>
            </div>
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
    btn.innerHTML = '<i class="fe fe-loader fa-spin"></i> Processing...';

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
            alert(data.message || 'Job processed successfully');
            window.location.reload();
        } else {
            alert(data.error || 'Failed to process job');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while processing the job');
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
            alert(data.message || 'Job deleted successfully');
            window.location.href = '{{ url("v2/logs/jobs") }}';
        } else {
            alert(data.error || 'Failed to delete job');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the job');
    });
}
</script>
@endsection
