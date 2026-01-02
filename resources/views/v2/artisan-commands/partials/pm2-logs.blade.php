{{-- PM2 Logs Section --}}
<div class="card mb-4">
    <div class="card-header bg-danger text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fe fe-alert-circle me-2"></i>PM2 Logs
            </h5>
            <div class="d-flex align-items-center gap-2">
                <select id="pm2LogsLines" class="form-select form-select-sm" style="width: auto;">
                    <option value="50">50 lines</option>
                    <option value="100" selected>100 lines</option>
                    <option value="200">200 lines</option>
                    <option value="500">500 lines</option>
                    <option value="1000">1000 lines</option>
                </select>
                <button type="button" class="btn btn-sm btn-light" onclick="loadPm2Logs()" title="Refresh PM2 Logs">
                    <i class="fe fe-refresh-cw" id="pm2LogsRefreshIcon"></i> Refresh
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div id="pm2LogsContainer" class="pm2-logs-container">
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading PM2 logs...</p>
            </div>
        </div>
    </div>
</div>

