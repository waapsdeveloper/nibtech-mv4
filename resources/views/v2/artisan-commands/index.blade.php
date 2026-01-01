@extends('layouts.app')

@section('styles')
<style>
    .command-card {
        transition: all 0.3s ease;
        border-left: 4px solid #007bff;
    }
    .command-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .command-output {
        background: #1e1e1e;
        color: #d4d4d4;
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
        max-height: 400px;
        overflow-y: auto;
    }
    .doc-link {
        color: #007bff;
        text-decoration: none;
    }
    .doc-link:hover {
        text-decoration: underline;
    }
    .spin {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">V2 Artisan Commands Guide</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item active" aria-current="page">Artisan Commands</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
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

            {{-- Migration Status Section --}}
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fe fe-database me-2"></i>Database Migration Status
                    </h5>
                </div>
                <div class="card-body">
                    @if($migrationStatus['migrations_table_exists'])
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <strong class="me-2">Last Migration Run:</strong>
                                    @if($migrationStatus['last_migration'])
                                        <span class="badge bg-success">
                                            {{ $migrationStatus['last_migration']['migration'] }}
                                        </span>
                                        <small class="text-muted ms-2">
                                            (Batch: {{ $migrationStatus['last_migration']['batch'] }})
                                        </small>
                                    @else
                                        <span class="text-muted">No migrations run yet</span>
                                    @endif
                                </div>
                                @if($migrationStatus['last_migration'] && $migrationStatus['last_migration']['ran_at'])
                                <div class="small text-muted">
                                    <i class="fe fe-clock me-1"></i>
                                    Ran at: {{ \Carbon\Carbon::parse($migrationStatus['last_migration']['ran_at'])->format('Y-m-d H:i:s') }}
                                </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <strong class="me-2">Pending Migrations:</strong>
                                    @if($migrationStatus['total_pending'] > 0)
                                        <span class="badge bg-warning text-dark">{{ $migrationStatus['total_pending'] }}</span>
                                    @else
                                        <span class="badge bg-success">0</span>
                                    @endif
                                </div>
                                @if($migrationStatus['total_pending'] > 0)
                                <button type="button" class="btn btn-sm btn-primary" id="runMigrationsBtn">
                                    <i class="fe fe-play me-1"></i>Run Pending Migrations
                                </button>
                                @else
                                <span class="text-success small">
                                    <i class="fe fe-check-circle me-1"></i>All migrations are up to date
                                </span>
                                @endif
                            </div>
                        </div>

                        @if($migrationStatus['total_pending'] > 0)
                        <div class="alert alert-warning mb-0">
                            <strong><i class="fe fe-alert-circle me-2"></i>Pending Migrations:</strong>
                            <ul class="mb-0 mt-2">
                                @foreach($migrationStatus['pending_migrations'] as $migration)
                                <li class="mb-2">
                                    <div class="d-flex align-items-center flex-wrap">
                                        <code>{{ $migration['migration'] }}</code>
                                        <small class="text-muted ms-2">({{ $migration['name'] }})</small>
                                        @if(isset($migration['path']) && $migration['path'] !== 'migrations')
                                            <span class="badge bg-info ms-2">{{ $migration['path'] }}</span>
                                        @endif
                                        @if(isset($migration['status']))
                                            @if($migration['status'] === 'table_exists_but_not_recorded')
                                                <span class="badge bg-warning text-dark ms-2" title="Table exists but migration not recorded in database">
                                                    <i class="fe fe-alert-triangle me-1"></i>Table Exists
                                                </span>
                                            @else
                                                <span class="badge bg-danger ms-2">Not Run</span>
                                            @endif
                                        @endif
                                        <button type="button" class="btn btn-sm btn-link p-0 ms-2" onclick="checkMigrationDetails('{{ $migration['migration'] }}')" title="Check migration details">
                                            <i class="fe fe-info"></i>
                                        </button>
                                    </div>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @else
                        <div class="alert alert-success mb-0">
                            <i class="fe fe-check-circle me-2"></i>
                            <strong>All migrations are up to date!</strong> Your database is synchronized with the latest migration files.
                        </div>
                        @endif
                    @else
                        <div class="alert alert-info mb-0">
                            <i class="fe fe-info me-2"></i>
                            <strong>Migrations table not found.</strong> Run <code>php artisan migrate:install</code> first to initialize the migrations table.
                        </div>
                    @endif

                    {{-- Migration Output Area --}}
                    <div class="migration-output-container mt-3" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong class="small">Migration Output:</strong>
                            <button class="btn btn-sm btn-link text-muted" onclick="clearMigrationOutput()">
                                <i class="fe fe-x"></i> Clear
                            </button>
                        </div>
                        <div class="command-output p-3 rounded" id="migrationOutput" style="display: none;"></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fe fe-terminal me-2"></i>V2 Artisan Commands
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fe fe-info me-2"></i>
                        <strong>Guide:</strong> Use these commands to test and manage V2 marketplace synchronization. 
                        Commands can be executed directly from this interface for testing purposes.
                    </div>

                    @foreach($commands as $command)
                    <div class="card command-card mb-4" style="{{ isset($command['warning']) && $command['warning'] ? 'border-left-color: #dc3545;' : '' }}">
                        <div class="card-header {{ isset($command['warning']) && $command['warning'] ? 'bg-danger text-white' : 'bg-light' }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 fw-bold">
                                        <code>{{ $command['signature'] }}</code>
                                    </h6>
                                    <p class="mb-0 {{ isset($command['warning']) && $command['warning'] ? 'text-white' : 'text-muted' }} small">{{ $command['description'] }}</p>
                                </div>
                                <span class="badge {{ isset($command['warning']) && $command['warning'] ? 'bg-warning text-dark' : 'bg-primary' }}">{{ $command['category'] }}</span>
                            </div>
                        </div>
                        <div class="card-body">
                            {{-- Emergency Warning --}}
                            @if(isset($command['warning']) && $command['warning'])
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <h5 class="alert-heading">
                                    <i class="fe fe-alert-triangle me-2"></i>EMERGENCY COMMAND WARNING
                                </h5>
                                <p class="mb-0">
                                    <strong>{{ $command['warning_message'] ?? 'This is a destructive operation that cannot be easily undone!' }}</strong>
                                </p>
                                <hr>
                                <p class="mb-0 small">
                                    <strong>What this command does:</strong><br>
                                    • Syncs parent stock (variation.listed_stock) to Backmarket (marketplace_id = 1)<br>
                                    • Sets all other marketplaces' listed_stock to 0<br>
                                    • This will overwrite existing marketplace stock values
                                </p>
                                <p class="mb-0 mt-2 small">
                                    <strong>Recommendation:</strong> Always run with <code>--dry-run</code> first to see what will be changed!
                                </p>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            @endif
                            {{-- Documentation Links --}}
                            @if(!empty($command['docs']))
                            <div class="mb-3">
                                <strong class="small">Documentation:</strong>
                                @foreach($command['docs'] as $doc)
                                    <a href="javascript:void(0);" class="doc-link ms-2" onclick="showDocumentation('{{ $doc }}')">
                                        <i class="fe fe-file-text me-1"></i>{{ str_replace('.md', '', $doc) }}
                                    </a>
                                @endforeach
                            </div>
                            @endif

                            {{-- Command Options Form --}}
                            <form class="command-form" data-command="{{ $command['signature'] }}">
                                <div class="row mb-3">
                                    @foreach($command['options'] ?? [] as $optionKey => $option)
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label small">{{ $option['label'] }}</label>
                                        @if($option['type'] === 'select')
                                            <select name="{{ $optionKey }}" class="form-control form-control-sm" 
                                                    value="{{ $option['default'] ?? '' }}">
                                                @foreach($option['options'] as $value => $label)
                                                    <option value="{{ $value }}" {{ ($option['default'] ?? '') == $value ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @elseif($option['type'] === 'checkbox')
                                            <div class="form-check">
                                                <input type="checkbox" 
                                                       name="{{ $optionKey }}" 
                                                       class="form-check-input" 
                                                       id="{{ $optionKey }}_{{ $loop->parent->index }}"
                                                       value="1">
                                                <label class="form-check-label small" for="{{ $optionKey }}_{{ $loop->parent->index }}">
                                                    {{ $option['description'] ?? '' }}
                                                </label>
                                            </div>
                                        @else
                                            <input type="{{ $option['type'] }}" 
                                                   name="{{ $optionKey }}" 
                                                   class="form-control form-control-sm" 
                                                   placeholder="{{ $option['placeholder'] ?? '' }}"
                                                   value="{{ $option['default'] ?? '' }}">
                                        @endif
                                    </div>
                                    @endforeach
                                </div>

                                {{-- Examples --}}
                                @if(!empty($command['examples']))
                                <div class="mb-3">
                                    <strong class="small">Examples:</strong>
                                    <div class="mt-2">
                                        @foreach($command['examples'] as $example)
                                        <code class="d-block mb-1 small bg-light p-2">{{ $example }}</code>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fe fe-play me-1"></i>Execute Command
                                </button>
                            </form>

                            {{-- Output Area --}}
                            <div class="command-output-container mt-3" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="small">Output:</strong>
                                    <button class="btn btn-sm btn-link text-muted" onclick="clearOutput(this)">
                                        <i class="fe fe-x"></i> Clear
                                    </button>
                                </div>
                                <div class="command-output p-3 rounded" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Documentation List --}}
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fe fe-book me-2"></i>Documentation Files
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($docs as $doc)
                        <div class="col-md-4 mb-2">
                            <a href="javascript:void(0);" class="doc-link" onclick="showDocumentation('{{ $doc['filename'] }}')">
                                <i class="fe fe-file-text me-1"></i>{{ $doc['name'] }}
                            </a>
                            <small class="text-muted d-block">{{ number_format($doc['size'] / 1024, 2) }} KB</small>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Migration Details Modal --}}
            <div class="modal fade" id="migrationDetailsModal" tabindex="-1" aria-labelledby="migrationDetailsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="migrationDetailsModalLabel">
                                <i class="fe fe-info me-2"></i>Migration Details
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="migrationDetailsContent">
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="runSingleMigrationBtn" style="display: none;" onclick="runSingleMigration()">
                                <i class="fe fe-play me-1"></i>Run This Migration
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Documentation Modal --}}
            <div class="modal fade" id="documentationModal" tabindex="-1" aria-labelledby="documentationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="documentationModalLabel">
                                <i class="fe fe-file-text me-2"></i>Documentation
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="documentationContent" class="markdown-content" style="max-height: 70vh; overflow-y: auto;">
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script>
// Initialize Artisan Commands Config
window.ArtisanCommandsConfig = {
    urls: {
        runMigrations: '{{ url("v2/artisan-commands/run-migrations") }}',
        recordMigration: '{{ url("v2/artisan-commands/record-migration") }}',
        migrationDetails: '{{ url("v2/artisan-commands/migration-details") }}',
        runSingleMigration: '{{ url("v2/artisan-commands/run-single-migration") }}',
        execute: '{{ url("v2/artisan-commands/execute") }}',
        checkCommandStatus: '{{ url("v2/artisan-commands/check-command-status") }}',
        documentation: '{{ url("v2/artisan-commands/documentation") }}',
        kill: '{{ url("v2/artisan-commands/kill") }}',
        restart: '{{ url("v2/artisan-commands/restart") }}',
        stockSyncLog: '{{ url("v2/logs/stock-sync") }}'
    }
};
</script>
<script src="{{ asset('assets/v2/artisan-commands/js/artisan-commands.js') }}"></script>
@endsection
