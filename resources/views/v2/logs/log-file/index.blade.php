@extends('layouts.app')

@section('styles')
<style>
    .log-content {
        font-family: 'Courier New', monospace;
        font-size: 12px;
        line-height: 1.6;
        background-color: #1e1e1e;
        color: #d4d4d4;
        padding: 15px;
        border-radius: 5px;
        max-height: 70vh;
        overflow-y: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    .log-line {
        margin-bottom: 2px;
    }
    .log-line.error {
        color: #f48771;
    }
    .log-line.warning {
        color: #dcdcaa;
    }
    .log-line.info {
        color: #4ec9b0;
    }
    .log-line.debug {
        color: #9cdcfe;
    }
    .log-timestamp {
        color: #808080;
    }
    .log-level {
        font-weight: bold;
        margin-right: 10px;
    }
    .log-level.ERROR {
        color: #f48771;
    }
    .log-level.WARNING {
        color: #dcdcaa;
    }
    .log-level.INFO {
        color: #4ec9b0;
    }
    .log-level.DEBUG {
        color: #9cdcfe;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $data['title_page'] ?? 'Log File Viewer' }}</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/logs/stock-sync') }}">Logs</a></li>
                <li class="breadcrumb-item active" aria-current="page">Log File</li>
            </ol>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#log-file-tab" role="tab">
                <i class="fe fe-file-text me-1"></i> Log File
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#log-settings-tab" role="tab">
                <i class="fe fe-slack me-1"></i> Slack Settings
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Log File Tab -->
        <div class="tab-pane fade show active" id="log-file-tab" role="tabpanel">
            <div class="row">
                <div class="col-12">
                    <!-- Controls -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <form method="GET" action="{{ url('v2/logs/log-file') }}" class="d-flex gap-2 align-items-center" id="logFileForm">
                                        <label class="form-label mb-0"><strong>Log File:</strong></label>
                                        <select name="file" class="form-control form-select" style="width: auto; min-width: 200px;" onchange="this.form.submit()">
                                            @if(isset($logFiles) && count($logFiles) > 0)
                                                @foreach($logFiles as $logFile)
                                                    <option value="{{ $logFile['name'] }}" {{ $selectedFile == $logFile['name'] ? 'selected' : '' }}>
                                                        {{ $logFile['name'] }}
                                                        @if($logFile['is_slack_log'])
                                                            ({{ $logFile['size_formatted'] }})
                                                        @endif
                                                    </option>
                                                @endforeach
                                            @else
                                                <option value="laravel.log" {{ $selectedFile == 'laravel.log' ? 'selected' : '' }}>laravel.log</option>
                                            @endif
                                        </select>
                                        <input type="hidden" name="page" value="1">
                                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                                    </form>
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="{{ url('v2/logs/log-file/download-all') }}" class="btn btn-primary btn-sm" title="Download all log files as ZIP">
                                        <i class="fe fe-download"></i> Download All Logs
                                    </a>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="clearLogFile()">
                                        <i class="fe fe-trash-2"></i> Clear Log File
                                    </button>
                                    <a href="{{ url('v2/logs/log-file') }}?file={{ $selectedFile }}" class="btn btn-secondary btn-sm">
                                        <i class="fe fe-refresh-cw"></i> Refresh
                                    </a>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="d-flex gap-2 align-items-center">
                                    <form method="GET" action="{{ url('v2/logs/log-file') }}" class="d-flex gap-2 align-items-center">
                                        <label class="form-label mb-0">Lines per page:</label>
                                        <select name="per_page" class="form-control form-select" style="width: auto;" onchange="this.form.submit()">
                                            <option value="500" {{ $perPage == 500 ? 'selected' : '' }}>500</option>
                                            <option value="1000" {{ $perPage == 1000 ? 'selected' : '' }}>1,000</option>
                                            <option value="2000" {{ $perPage == 2000 ? 'selected' : '' }}>2,000</option>
                                            <option value="5000" {{ $perPage == 5000 ? 'selected' : '' }}>5,000</option>
                                        </select>
                                        <input type="hidden" name="page" value="{{ $page }}">
                                        <input type="hidden" name="file" value="{{ $selectedFile }}">
                                    </form>

                                    <span class="text-muted small">
                                        Showing {{ number_format($lineCount) }} of {{ number_format($totalLines) }} lines
                                        @if($totalPages > 1)
                                            (Page {{ $page }} of {{ $totalPages }})
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Log Content -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        Log File: <code>{{ $selectedFile }}</code>
                        @if(isset($logFiles))
                            @foreach($logFiles as $logFile)
                                @if($logFile['name'] == $selectedFile)
                                    <small class="text-muted">({{ $logFile['size_formatted'] }})</small>
                                @endif
                            @endforeach
                        @endif
                    </h5>
                </div>
                <div class="card-body p-0">
                    @if($totalLines > 0)
                        <div class="log-content" id="logContent">
                            @foreach($lines as $line)
                                @php
                                    $lineClass = '';
                                    $level = '';

                                    // Detect log level and apply styling
                                    if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] local\.(ERROR|WARNING|INFO|DEBUG)/', $line, $matches)) {
                                        $level = strtolower($matches[2]);
                                        $lineClass = $level;
                                    } elseif (stripos($line, 'error') !== false || stripos($line, 'exception') !== false) {
                                        $lineClass = 'error';
                                    } elseif (stripos($line, 'warning') !== false) {
                                        $lineClass = 'warning';
                                    } elseif (stripos($line, 'info') !== false) {
                                        $lineClass = 'info';
                                    }
                                @endphp
                                <div class="log-line {{ $lineClass }}">{{ $line }}</div>
                            @endforeach
                        </div>

                        <!-- Pagination -->
                        @if($totalPages > 1)
                        <div class="card-footer">
                            <nav aria-label="Log file pagination">
                                <ul class="pagination mb-0 justify-content-center">
                                    <li class="page-item {{ !$hasPrevPage ? 'disabled' : '' }}">
                                        <a class="page-link" href="{{ url('v2/logs/log-file') }}?file={{ $selectedFile }}&page={{ $page - 1 }}&per_page={{ $perPage }}" {{ !$hasPrevPage ? 'tabindex="-1" aria-disabled="true"' : '' }}>
                                            <i class="fe fe-chevron-left"></i> Previous
                                        </a>
                                    </li>

                                    @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
                                        <li class="page-item {{ $i == $page ? 'active' : '' }}">
                                            <a class="page-link" href="{{ url('v2/logs/log-file') }}?file={{ $selectedFile }}&page={{ $i }}&per_page={{ $perPage }}">{{ $i }}</a>
                                        </li>
                                    @endfor

                                    <li class="page-item {{ !$hasNextPage ? 'disabled' : '' }}">
                                        <a class="page-link" href="{{ url('v2/logs/log-file') }}?file={{ $selectedFile }}&page={{ $page + 1 }}&per_page={{ $perPage }}" {{ !$hasNextPage ? 'tabindex="-1" aria-disabled="true"' : '' }}>
                                            Next <i class="fe fe-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        @endif
                    @else
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fe fe-info me-2"></i>Log file is empty or does not exist.
                            </div>
                        </div>
                    @endif
                </div>
            </div>
                </div>
            </div>
        </div>

        <!-- Log Settings Tab -->
        <div class="tab-pane fade" id="log-settings-tab" role="tabpanel">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Slack Log Settings</h5>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openLogSettingModal()">
                                <i class="fe fe-plus"></i> Add New Setting
                            </button>
                        </div>
                        <div class="card-body">
                            @if(isset($logSettings) && $logSettings->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Channel</th>
                                                <th>Log Type</th>
                                                <th>Log Level</th>
                                                <th>Keywords</th>
                                                <th>Status</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($logSettings as $setting)
                                                <tr>
                                                    <td><strong>{{ $setting->name }}</strong></td>
                                                    <td><code>#{{ $setting->channel_name }}</code></td>
                                                    <td><span class="badge bg-info">{{ $setting->log_type }}</span></td>
                                                    <td><span class="badge bg-{{ $setting->log_level === 'error' ? 'danger' : ($setting->log_level === 'warning' ? 'warning' : 'secondary') }}">{{ ucfirst($setting->log_level) }}</span></td>
                                                    <td>
                                                        @if($setting->keywords && is_array($setting->keywords))
                                                            @foreach($setting->keywords as $keyword)
                                                                <span class="badge bg-light text-dark">{{ $keyword }}</span>
                                                            @endforeach
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($setting->is_enabled)
                                                            <span class="badge bg-success">Enabled</span>
                                                        @else
                                                            <span class="badge bg-secondary">Disabled</span>
                                                        @endif
                                                    </td>
                                                    <td><small class="text-muted">{{ $setting->description ?? '-' }}</small></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-sm btn-primary" onclick="editLogSetting({{ $setting->id }})" title="Edit">
                                                                <i class="fe fe-edit"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-info" onclick="duplicateLogSetting({{ $setting->id }})" title="Duplicate">
                                                                <i class="fe fe-copy"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteLogSetting({{ $setting->id }})" title="Delete">
                                                                <i class="fe fe-trash-2"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-info">
                                    <i class="fe fe-info me-2"></i>No log settings configured yet. Click "Add New Setting" to create one.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Log Setting Modal -->
<div class="modal fade" id="logSettingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logSettingModalTitle">Add Log Setting</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="logSettingForm">
                <div class="modal-body">
                    <input type="hidden" id="log_setting_id" name="id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required placeholder="e.g., care_api_errors">
                            <small class="form-text text-muted">Unique identifier for this setting</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="channel_name" class="form-label">Slack Channel Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="channel_name" name="channel_name" required placeholder="e.g., care-api-logs">
                            <small class="form-text text-muted">Channel name without #</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="webhook_url" class="form-label">Webhook URL <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="webhook_url" name="webhook_url" required placeholder="https://hooks.slack.com/services/...">
                        <small class="form-text text-muted">Slack webhook URL for this channel</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="log_type" class="form-label">Log Type <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="log_type" name="log_type" required placeholder="e.g., care_api, order_sync, listing_api">
                            <small class="form-text text-muted">Category/type of logs (e.g., care_api, order_sync)</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="log_level" class="form-label">Minimum Log Level <span class="text-danger">*</span></label>
                            <select class="form-select" id="log_level" name="log_level" required>
                                <option value="debug">Debug</option>
                                <option value="info" selected>Info</option>
                                <option value="warning">Warning</option>
                                <option value="error">Error</option>
                            </select>
                            <small class="form-text text-muted">Minimum log level to post to Slack</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="keywords" class="form-label">Keywords (Optional)</label>
                        <input type="text" class="form-control" id="keywords" name="keywords" placeholder="keyword1, keyword2, keyword3">
                        <small class="form-text text-muted">Comma-separated keywords to match in log messages (optional)</small>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="2" placeholder="Describe what logs this setting handles..."></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" checked>
                            <label class="form-check-label" for="is_enabled">
                                Enabled
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Setting</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
// Auto-scroll to top on page load (showing newest entries first)
$(document).ready(function() {
    const logContent = document.getElementById('logContent');
    if (logContent) {
        // Scroll to top to show newest entries first
        logContent.scrollTop = 0;
    }
});

function clearLogFile() {
    const selectedFile = document.querySelector('select[name="file"]').value;

    if (!confirm('Are you sure you want to clear the log file "' + selectedFile + '"? This action cannot be undone.')) {
        return;
    }

    fetch('{{ url("v2/logs/log-file") }}?file=' + encodeURIComponent(selectedFile), {
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
            showAlert('success', data.message || 'Log file cleared successfully');
            setTimeout(() => {
                const selectedFile = document.querySelector('select[name="file"]').value;
                window.location.href = '{{ url("v2/logs/log-file") }}?file=' + encodeURIComponent(selectedFile);
            }, 1000);
        } else {
            showAlert('danger', data.error || 'Failed to clear log file');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while clearing the log file');
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

// Log Settings CRUD Functions
function openLogSettingModal(id = null) {
    const modal = new bootstrap.Modal(document.getElementById('logSettingModal'));
    const form = document.getElementById('logSettingForm');
    const modalTitle = document.getElementById('logSettingModalTitle');

    form.reset();
    document.getElementById('log_setting_id').value = '';

    if (id) {
        modalTitle.textContent = 'Edit Log Setting';
        // Load existing setting
        fetch('{{ url("v2/logs/log-settings") }}/' + id, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const setting = data.data;
                document.getElementById('log_setting_id').value = setting.id;
                document.getElementById('name').value = setting.name;
                document.getElementById('channel_name').value = setting.channel_name;
                document.getElementById('webhook_url').value = setting.webhook_url;
                document.getElementById('log_type').value = setting.log_type;
                document.getElementById('log_level').value = setting.log_level;
                document.getElementById('keywords').value = setting.keywords ? setting.keywords.join(', ') : '';
                document.getElementById('description').value = setting.description || '';
                document.getElementById('is_enabled').checked = setting.is_enabled;
            }
        })
        .catch(error => {
            console.error('Error loading log setting:', error);
            showAlert('danger', 'Error loading log setting');
        });
    } else {
        modalTitle.textContent = 'Add Log Setting';
    }

    modal.show();
}

function editLogSetting(id) {
    openLogSettingModal(id);
}

function deleteLogSetting(id) {
    if (!confirm('Are you sure you want to delete this log setting? This action cannot be undone.')) {
        return;
    }

    fetch('{{ url("v2/logs/log-settings") }}/' + id, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message || 'Log setting deleted successfully');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert('danger', data.error || 'Failed to delete log setting');
        }
    })
    .catch(error => {
        console.error('Error deleting log setting:', error);
        showAlert('danger', 'An error occurred while deleting the log setting');
    });
}

function duplicateLogSetting(id) {
    if (!confirm('Are you sure you want to duplicate this log setting? A copy will be created with the same settings but a different name.')) {
        return;
    }

    fetch('{{ url("v2/logs/log-settings") }}/' + id + '/duplicate', {
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
            showAlert('success', data.message || 'Log setting duplicated successfully');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert('danger', data.error || 'Failed to duplicate log setting');
        }
    })
    .catch(error => {
        console.error('Error duplicating log setting:', error);
        showAlert('danger', 'An error occurred while duplicating the log setting');
    });
}

// Handle form submission
document.getElementById('logSettingForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const id = document.getElementById('log_setting_id').value;
    const url = id
        ? '{{ url("v2/logs/log-settings") }}/' + id
        : '{{ url("v2/logs/log-settings") }}';
    const method = id ? 'PUT' : 'POST';

    const data = {};
    formData.forEach((value, key) => {
        if (key === 'is_enabled') {
            data[key] = document.getElementById('is_enabled').checked;
        } else {
            data[key] = value;
        }
    });

    // Remove id from data if creating new
    if (!id) {
        delete data.id;
    }

    fetch(url, {
        method: method,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message || 'Log setting saved successfully');
            const modal = bootstrap.Modal.getInstance(document.getElementById('logSettingModal'));
            modal.hide();
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            let errorMsg = 'Failed to save log setting';
            if (data.errors) {
                const errors = Object.values(data.errors).flat().join(', ');
                errorMsg = errors;
            } else if (data.error) {
                errorMsg = data.error;
            }
            showAlert('danger', errorMsg);
        }
    })
    .catch(error => {
        console.error('Error saving log setting:', error);
        showAlert('danger', 'An error occurred while saving the log setting');
    });
});
</script>
@endsection

