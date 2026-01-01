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
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/logs/stock-sync') }}">Logs</a></li>
                <li class="breadcrumb-item active" aria-current="page">Log File</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <!-- Controls -->
            <div class="card mb-4">
                <div class="card-body">
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
                            </form>
                            
                            <span class="text-muted small">
                                Showing {{ number_format($lineCount) }} of {{ number_format($totalLines) }} lines
                                @if($totalPages > 1)
                                    (Page {{ $page }} of {{ $totalPages }})
                                @endif
                            </span>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-danger btn-sm" onclick="clearLogFile()">
                                <i class="fe fe-trash-2"></i> Clear Log File
                            </button>
                            <a href="{{ url('v2/logs/log-file') }}" class="btn btn-secondary btn-sm">
                                <i class="fe fe-refresh-cw"></i> Refresh
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Log Content -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Laravel Log File</h5>
                </div>
                <div class="card-body p-0">
                    @if($totalLines > 0)
                        <div class="log-content">
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
                                        <a class="page-link" href="{{ url('v2/logs/log-file') }}?page={{ $page - 1 }}&per_page={{ $perPage }}" {{ !$hasPrevPage ? 'tabindex="-1" aria-disabled="true"' : '' }}>
                                            <i class="fe fe-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                    
                                    @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
                                        <li class="page-item {{ $i == $page ? 'active' : '' }}">
                                            <a class="page-link" href="{{ url('v2/logs/log-file') }}?page={{ $i }}&per_page={{ $perPage }}">{{ $i }}</a>
                                        </li>
                                    @endfor
                                    
                                    <li class="page-item {{ !$hasNextPage ? 'disabled' : '' }}">
                                        <a class="page-link" href="{{ url('v2/logs/log-file') }}?page={{ $page + 1 }}&per_page={{ $perPage }}" {{ !$hasNextPage ? 'tabindex="-1" aria-disabled="true"' : '' }}>
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
@endsection

@section('scripts')
<script>
function clearLogFile() {
    if (!confirm('Are you sure you want to clear the entire log file? This action cannot be undone.')) {
        return;
    }
    
    fetch('{{ url("v2/logs/log-file") }}', {
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
                window.location.reload();
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
</script>
@endsection

