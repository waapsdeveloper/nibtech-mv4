@extends('layouts.app')

@section('styles')
<style>
    pre {
        max-height: 500px;
        overflow-y: auto;
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        font-size: 12px;
    }
    .code-block {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        border: 1px solid #dee2e6;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $data['title_page'] ?? 'Failed Job Details' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/logs/failed-jobs') }}">Logs</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/logs/failed-jobs') }}">Failed Jobs</a></li>
                <li class="breadcrumb-item active" aria-current="page">Details</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Failed Job #{{ $job->id }}</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">UUID</th>
                                    <td><code>{{ $job->uuid ?? $job->id ?? 'N/A' }}</code></td>
                                </tr>
                                @if(isset($job->id) && $job->id != $job->uuid)
                                <tr>
                                    <th>ID</th>
                                    <td>{{ $job->id }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <th>Queue</th>
                                    <td>{{ $job->queue ?? 'default' }}</td>
                                </tr>
                                <tr>
                                    <th>Connection</th>
                                    <td>{{ $job->connection ?? 'default' }}</td>
                                </tr>
                                <tr>
                                    <th>Failed At</th>
                                    <td>{{ \Carbon\Carbon::parse($job->failed_at)->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="40%">Job Class</th>
                                    <td><code>{{ $payload['displayName'] ?? 'Unknown' }}</code></td>
                                </tr>
                                @if(isset($payload['data']['commandName']) && $payload['data']['commandName'] === 'App\\Jobs\\ExecuteArtisanCommandJob')
                                    @php
                                        $jobData = null;
                                        try {
                                            if (isset($payload['data']['command'])) {
                                                $jobData = unserialize($payload['data']['command']);
                                            }
                                        } catch (\Exception $e) {
                                            $jobData = null;
                                        }
                                    @endphp
                                    @if($jobData && is_object($jobData) && isset($jobData->command))
                                    <tr>
                                        <th>Command</th>
                                        <td><code>{{ $jobData->command }}</code></td>
                                    </tr>
                                    @if(!empty($jobData->options))
                                    <tr>
                                        <th>Options</th>
                                        <td>
                                            <code>
                                                @foreach($jobData->options as $key => $value)
                                                    --{{ $key }}=@if(is_bool($value)){{ $value ? 'true' : 'false' }}@else{{ $value }}@endif
                                                @endforeach
                                            </code>
                                        </td>
                                    </tr>
                                    @endif
                                    @endif
                                @endif
                            </table>
                        </div>
                    </div>

                    @if($exception)
                    <div class="mb-4">
                        <h6>Exception Details</h6>
                        <div class="card">
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="20%">Exception Class</th>
                                        <td><code>{{ $exception['exception'] ?? 'Unknown' }}</code></td>
                                    </tr>
                                    <tr>
                                        <th>Message</th>
                                        <td class="text-danger"><strong>{{ $exception['message'] ?? 'No message' }}</strong></td>
                                    </tr>
                                    @if(isset($exception['file']))
                                    <tr>
                                        <th>File</th>
                                        <td><code>{{ $exception['file'] }}</code></td>
                                    </tr>
                                    @endif
                                    @if(isset($exception['line']))
                                    <tr>
                                        <th>Line</th>
                                        <td>{{ $exception['line'] }}</td>
                                    </tr>
                                    @endif
                                </table>

                                @if(isset($exception['trace']))
                                <div class="mt-3">
                                    <h6>Stack Trace</h6>
                                    <pre><code>{{ $exception['trace'] }}</code></pre>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="mb-4">
                        <h6>Full Payload</h6>
                        <div class="code-block">
                            <pre><code>{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                        </div>
                    </div>

                    @if($job->exception)
                    <div class="mb-4">
                        <h6>Full Exception</h6>
                        <div class="code-block">
                            <pre><code>{{ $job->exception }}</code></pre>
                        </div>
                    </div>
                    @endif

                    <div class="mt-4 d-flex justify-content-between">
                        <a href="{{ url('v2/logs/failed-jobs') }}" class="btn btn-secondary">
                            <i class="fe fe-arrow-left"></i> Back to Failed Jobs
                        </a>

                        <div>
                            <button type="button" class="btn btn-success me-2" onclick="retryJob('{{ $job->uuid ?? $job->id }}')">
                                <i class="fe fe-refresh-cw"></i> Retry Job
                            </button>
                            <button type="button" class="btn btn-danger" onclick="deleteJob('{{ $job->uuid ?? $job->id }}')">
                                <i class="fe fe-trash-2"></i> Delete Job
                            </button>
                        </div>
                    </div>
                </div>
            </div>
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

    fetch('{{ url("v2/logs/failed-jobs") }}/' + encodeURIComponent(jobId) + '/retry', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (response.ok) {
            showAlert('success', 'Job queued for retry');
            setTimeout(() => {
                window.location.href = '{{ url("v2/logs/failed-jobs") }}';
            }, 1000);
        } else {
            return response.json().then(data => {
                showAlert('danger', data.error || 'Failed to retry job');
            });
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

    fetch('{{ url("v2/logs/failed-jobs") }}/' + encodeURIComponent(jobId), {
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
                window.location.href = '{{ url("v2/logs/failed-jobs") }}';
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
