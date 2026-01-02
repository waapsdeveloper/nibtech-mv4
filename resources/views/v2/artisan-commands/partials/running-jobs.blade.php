{{-- Running Jobs Section --}}
<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fe fe-play-circle me-2"></i>Running Commands
            </h5>
            <button type="button" class="btn btn-sm btn-light" onclick="refreshRunningJobs()" title="Refresh">
                <i class="fe fe-refresh-cw"></i>
            </button>
        </div>
    </div>
    <div class="card-body" id="runningJobsContainer">
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

