{{-- Artisan Commands Group (Running Commands & PM2 Logs) --}}
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0">
            <i class="fe fe-terminal me-2"></i>Artisan Commands
        </h5>
    </div>
    <div class="card-body p-0">
        {{-- Running Commands Section --}}
        <div class="border-bottom">
            <div class="d-flex justify-content-between align-items-center p-3 bg-light">
                <h6 class="mb-0">
                    <i class="fe fe-play-circle me-2"></i>Running Commands
                </h6>
                <button type="button" class="btn btn-sm btn-light" onclick="refreshRunningJobs()" title="Refresh">
                    <i class="fe fe-refresh-cw"></i>
                </button>
            </div>
            <div class="p-3" id="runningJobsContainer">
        @if(count($runningJobs) > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Command</th>
                            <th>Options</th>
                            <th>Job ID</th>
                            <th>Started</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($runningJobs as $job)
                        <tr>
                            <td>
                                <code>{{ $job['command'] }}</code>
                            </td>
                            <td>
                                @if(!empty($job['options']))
                                    <small class="text-muted">
                                        @foreach($job['options'] as $key => $value)
                                            <span class="badge bg-secondary me-1">{{ str_replace('--', '', $key) }}: {{ is_bool($value) ? ($value ? 'true' : 'false') : $value }}</span>
                                        @endforeach
                                    </small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <code>{{ $job['id'] }}</code>
                            </td>
                            <td>
                                <small class="text-muted">{{ \Carbon\Carbon::parse($job['created_at'])->diffForHumans() }}</small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-danger" onclick="killCommandFromList('{{ $job['command'] }}', '{{ $job['id'] }}', this)" title="Kill Command">
                                        <i class="fe fe-x-circle"></i> Kill
                                    </button>
                                    <button type="button" class="btn btn-warning" onclick="restartCommandFromList('{{ $job['command'] }}', {{ json_encode($job['options']) }}, '{{ $job['id'] }}', this)" title="Restart Command">
                                        <i class="fe fe-refresh-cw"></i> Restart
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="alert alert-info mb-0">
                <i class="fe fe-info me-2"></i>No running commands found.
            </div>
        @endif
            </div>
        </div>
        
        {{-- PM2 Logs Section --}}
        <div>
            <div class="d-flex justify-content-between align-items-center p-3 bg-light border-bottom">
                <h6 class="mb-0">
                    <i class="fe fe-alert-circle me-2"></i>PM2 Logs
                </h6>
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
            <div class="p-0">
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
    </div>
</div>

