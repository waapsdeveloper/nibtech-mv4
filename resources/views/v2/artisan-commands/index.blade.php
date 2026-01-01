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

<script>
// Handle run migrations button
document.addEventListener('DOMContentLoaded', function() {
    const runMigrationsBtn = document.getElementById('runMigrationsBtn');
    if (runMigrationsBtn) {
        runMigrationsBtn.addEventListener('click', function() {
            const outputContainer = document.querySelector('.migration-output-container');
            const outputDiv = document.getElementById('migrationOutput');
            
            if (outputContainer && outputDiv) {
                outputContainer.style.display = 'block';
                outputDiv.style.display = 'block';
                outputDiv.innerHTML = '<div class="text-info">Running migrations...</div>';
            }

            // Disable button
            this.disabled = true;
            this.innerHTML = '<i class="fe fe-loader me-1 spin"></i>Running...';

            fetch('{{ url("v2/artisan-commands/run-migrations") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.status === 'queued') {
                        outputDiv.innerHTML = '<div class="alert alert-info mb-2">' +
                                             '<i class="fe fe-clock me-2"></i><strong>Migration command queued successfully!</strong><br>' +
                                             '<small class="text-muted">The migration is running in the background. Check the logs for output:<br>' +
                                             '<code>tail -f storage/logs/laravel.log | grep "ExecuteArtisanCommandJob"</code><br><br>' +
                                             '<strong>Note:</strong> Make sure your queue worker is running:<br>' +
                                             '<code>php artisan queue:work</code> or <code>php artisan queue:listen</code><br><br>' +
                                             'After migrations complete, refresh this page to see updated status.' +
                                             '</small></div>';
                    } else {
                        outputDiv.innerHTML = '<div class="text-success mb-2">✓ Migrations executed successfully</div>' +
                                             '<pre class="mb-0">' + escapeHtml(data.output || 'No output') + '</pre>';
                    }
                } else {
                    outputDiv.innerHTML = '<div class="text-danger mb-2">✗ Migration failed</div>' +
                                         '<pre class="mb-0 text-danger">' + escapeHtml(data.error || 'Unknown error') + '</pre>';
                }
                
                // Re-enable button
                this.disabled = false;
                this.innerHTML = '<i class="fe fe-play me-1"></i>Run Pending Migrations';
            })
            .catch(error => {
                outputDiv.innerHTML = '<div class="text-danger">Error: ' + escapeHtml(error.message) + '</div>';
                
                // Re-enable button
                this.disabled = false;
                this.innerHTML = '<i class="fe fe-play me-1"></i>Run Pending Migrations';
            });
        });
    }
});

// Record migration in database
function recordMigration(migrationName) {
    if (!confirm('Are you sure you want to record this migration in the database? This will mark it as completed.')) {
        return;
    }

    const contentDiv = document.getElementById('migrationDetailsContent');
    const originalContent = contentDiv.innerHTML;
    
    contentDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Recording migration...</div></div>';

    fetch('{{ url("v2/artisan-commands/record-migration") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            migration: migrationName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            contentDiv.innerHTML = '<div class="alert alert-success mb-3">' +
                                  '<i class="fe fe-check-circle me-2"></i><strong>Success!</strong> ' +
                                  'Migration <code>' + escapeHtml(migrationName) + '</code> has been recorded in the database (Batch: ' + data.migration.batch + ').' +
                                  '</div>' +
                                  '<div class="alert alert-info mb-0">' +
                                  'Please refresh the page to see the updated migration status.' +
                                  '</div>';
            
            // Optionally auto-refresh after 2 seconds
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            contentDiv.innerHTML = '<div class="alert alert-danger mb-3">' +
                                  '<i class="fe fe-alert-circle me-2"></i><strong>Error:</strong> ' +
                                  escapeHtml(data.message || data.error || 'Unknown error') +
                                  '</div>' +
                                  '<button type="button" class="btn btn-sm btn-secondary" onclick="checkMigrationDetails(\'' + escapeHtml(migrationName) + '\')">' +
                                  'Reload Details' +
                                  '</button>';
        }
    })
    .catch(error => {
        contentDiv.innerHTML = '<div class="alert alert-danger mb-3">' +
                              '<i class="fe fe-alert-circle me-2"></i><strong>Error:</strong> ' +
                              escapeHtml(error.message) +
                              '</div>' +
                              '<button type="button" class="btn btn-sm btn-secondary" onclick="checkMigrationDetails(\'' + escapeHtml(migrationName) + '\')">' +
                              'Reload Details' +
                              '</button>';
    });
}

// Check migration details
function checkMigrationDetails(migrationName) {
    const modal = new bootstrap.Modal(document.getElementById('migrationDetailsModal'));
    const contentDiv = document.getElementById('migrationDetailsContent');
    
    contentDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"></div></div>';
    modal.show();

    fetch('{{ url("v2/artisan-commands/migration-details") }}?migration=' + encodeURIComponent(migrationName))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<div class="mb-3">';
                html += '<h6 class="mb-3">Migration: <code>' + escapeHtml(data.migration_name) + '</code></h6>';
                html += '<hr class="mb-3">';
                
                // In database status
                html += '<div class="mb-3">';
                html += '<strong>In Database:</strong> ';
                if (data.in_database) {
                    html += '<span class="badge bg-success">Yes</span>';
                    if (data.migration_record) {
                        html += '<div class="mt-2 small">';
                        html += '<strong>Batch:</strong> ' + data.migration_record.batch + '<br>';
                        html += '<strong>Recorded at:</strong> ' + (data.migration_record.created_at || 'N/A');
                        html += '</div>';
                    }
                } else {
                    html += '<span class="badge bg-danger">No</span>';
                }
                html += '</div>';

                // Table exists status
                if (data.table_exists !== null) {
                    html += '<div class="mb-3">';
                    html += '<strong>Table Exists:</strong> ';
                    if (data.table_exists) {
                        html += '<span class="badge bg-warning text-dark">Yes (but migration not recorded!)</span>';
                        html += '<div class="alert alert-warning mt-2 mb-3 small">';
                        html += 'The table exists in the database, but the migration is not recorded. ';
                        html += 'This could mean the migration was run manually or the record was deleted. ';
                        html += 'You can manually record this migration in the database.';
                        html += '</div>';
                        if (!data.in_database) {
                            html += '<div class="d-grid gap-2">';
                            html += '<button type="button" class="btn btn-warning btn-sm" onclick="recordMigration(\'' + escapeHtml(data.migration_name) + '\')">';
                            html += '<i class="fe fe-plus-circle me-1"></i>Record Migration in Database';
                            html += '</button>';
                            html += '</div>';
                        }
                    } else {
                        html += '<span class="badge bg-danger">No</span>';
                        html += '<div class="alert alert-info mt-2 mb-0 small">';
                        html += 'The table does not exist. This migration needs to be run.';
                        html += '</div>';
                    }
                    html += '</div>';
                }

                // Similar records
                if (data.all_similar_records && data.all_similar_records.length > 0) {
                    html += '<div class="mb-3">';
                    html += '<strong>Similar Migration Records:</strong>';
                    html += '<ul class="small mt-2">';
                    data.all_similar_records.forEach(record => {
                        html += '<li><code>' + escapeHtml(record.migration) + '</code> (Batch: ' + record.batch + ')</li>';
                    });
                    html += '</ul>';
                    html += '</div>';
                }

                // Recent migrations
                if (data.all_migrations_in_db && data.all_migrations_in_db.length > 0) {
                    html += '<div class="mb-3">';
                    html += '<strong>Recent Migrations in Database (last 50):</strong>';
                    html += '<div class="small mt-2" style="max-height: 200px; overflow-y: auto;">';
                    data.all_migrations_in_db.forEach(migration => {
                        const isMatch = migration.includes(data.migration_name.split('_').slice(-1)[0]);
                        html += '<div class="' + (isMatch ? 'text-warning fw-bold' : '') + '">';
                        html += escapeHtml(migration);
                        if (isMatch) {
                            html += ' <span class="badge bg-warning text-dark">Similar</span>';
                        }
                        html += '</div>';
                    });
                    html += '</div>';
                    html += '</div>';
                }

                html += '</div>';
                contentDiv.innerHTML = html;
                
                // Show/hide "Run This Migration" button based on migration status
                const runBtn = document.getElementById('runSingleMigrationBtn');
                if (runBtn) {
                    // Show button only if migration is not in database
                    if (!data.in_database) {
                        runBtn.style.display = 'inline-block';
                        runBtn.setAttribute('data-migration', data.migration_name);
                        // Store migration path if available
                        if (data.migration_path) {
                            runBtn.setAttribute('data-migration-path', data.migration_path);
                        }
                    } else {
                        runBtn.style.display = 'none';
                        runBtn.removeAttribute('data-migration');
                        runBtn.removeAttribute('data-migration-path');
                    }
                }
            } else {
                contentDiv.innerHTML = '<div class="alert alert-danger">' + escapeHtml(data.message || data.error || 'Unknown error') + '</div>';
                // Hide button on error
                const runBtn = document.getElementById('runSingleMigrationBtn');
                if (runBtn) {
                    runBtn.style.display = 'none';
                }
            }
        })
        .catch(error => {
            contentDiv.innerHTML = '<div class="alert alert-danger">Error loading migration details: ' + escapeHtml(error.message) + '</div>';
            // Hide button on error
            const runBtn = document.getElementById('runSingleMigrationBtn');
            if (runBtn) {
                runBtn.style.display = 'none';
            }
        });
}

// Run a single specific migration
function runSingleMigration() {
    const runBtn = document.getElementById('runSingleMigrationBtn');
    if (!runBtn) return;
    
    const migrationName = runBtn.getAttribute('data-migration');
    const migrationPath = runBtn.getAttribute('data-migration-path');
    
    if (!migrationName) {
        alert('Migration name not found');
        return;
    }
    
    if (!confirm('Are you sure you want to run this migration?\n\nMigration: ' + migrationName + '\n\nThis will execute the migration and record it in the database.')) {
        return;
    }
    
    // Disable button and show loading
    runBtn.disabled = true;
    const originalHtml = runBtn.innerHTML;
    runBtn.innerHTML = '<i class="fe fe-loader me-1 spin"></i>Running...';
    
    // Show output in modal body
    const contentDiv = document.getElementById('migrationDetailsContent');
    const outputHtml = '<div class="alert alert-info mb-3">' +
                      '<i class="fe fe-clock me-2"></i><strong>Running migration...</strong><br>' +
                      '<small>Migration: <code>' + escapeHtml(migrationName) + '</code></small>' +
                      '</div>' +
                      '<div class="command-output p-3 rounded" id="singleMigrationOutput" style="background: #1e1e1e; color: #d4d4d4; font-family: monospace; font-size: 0.875rem; max-height: 300px; overflow-y: auto;">' +
                      '<div class="text-info">Command queued. Check logs for output...</div>' +
                      '</div>';
    contentDiv.innerHTML = outputHtml;
    
    fetch('{{ url("v2/artisan-commands/run-single-migration") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            migration: migrationName,
            path: migrationPath
        })
    })
    .then(response => response.json())
    .then(data => {
        const outputDiv = document.getElementById('singleMigrationOutput');
        if (data.success) {
            if (data.status === 'queued') {
                outputDiv.innerHTML = '<div class="text-success">✓ Migration command queued successfully!</div>' +
                                    '<div class="text-muted mt-2">The migration is running in the background.</div>' +
                                    '<div class="text-muted small mt-2">Check the logs for output:<br>' +
                                    '<code>tail -f storage/logs/laravel.log | grep "RunSingleMigration"</code></div>' +
                                    '<div class="text-info mt-3">You can close this modal and check back later, or reload the page to see if the migration was recorded.</div>';
            } else {
                outputDiv.innerHTML = '<div class="text-success">✓ ' + (data.message || 'Migration completed') + '</div>';
            }
            
            // Re-enable button but change text
            runBtn.disabled = false;
            runBtn.innerHTML = '<i class="fe fe-check me-1"></i>Migration Queued';
            runBtn.classList.remove('btn-primary');
            runBtn.classList.add('btn-success');
        } else {
            outputDiv.innerHTML = '<div class="text-danger">✗ Error: ' + escapeHtml(data.error || data.message || 'Unknown error') + '</div>';
            runBtn.disabled = false;
            runBtn.innerHTML = originalHtml;
        }
    })
    .catch(error => {
        const outputDiv = document.getElementById('singleMigrationOutput');
        if (outputDiv) {
            outputDiv.innerHTML = '<div class="text-danger">✗ Error: ' + escapeHtml(error.message) + '</div>';
        }
        runBtn.disabled = false;
        runBtn.innerHTML = originalHtml;
    });
}

// Clear migration output
function clearMigrationOutput() {
    const outputDiv = document.getElementById('migrationOutput');
    const outputContainer = document.querySelector('.migration-output-container');
    if (outputDiv) {
        outputDiv.innerHTML = '';
        outputDiv.style.display = 'none';
    }
    if (outputContainer) {
        outputContainer.style.display = 'none';
    }
}

// Handle command form submission
document.querySelectorAll('.command-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const command = this.dataset.command;
        const formData = new FormData(this);
        const options = {};
        
        // Build options object - only include non-empty values
        formData.forEach((value, key) => {
            if (value !== null && value !== '' && value !== '0') {
                // Convert string numbers to actual numbers if needed
                if (!isNaN(value) && value !== '') {
                    options[key] = isNaN(parseFloat(value)) ? value : parseFloat(value);
                } else {
                    options[key] = value;
                }
            }
        });
        
        // Handle checkboxes - unchecked checkboxes won't be in FormData, so we need to check them explicitly
        this.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            if (checkbox.checked) {
                options[checkbox.name] = checkbox.value || '1';
            }
        });

        // Show output container
        const outputContainer = this.closest('.card-body').querySelector('.command-output-container');
        const outputDiv = outputContainer.querySelector('.command-output');
        outputContainer.style.display = 'block';
        outputDiv.style.display = 'block';
        outputDiv.innerHTML = '<div class="text-info">Executing command...</div>';

        // Execute command
        fetch('{{ url("v2/artisan-commands/execute") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                command: command,
                options: options
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.status === 'queued') {
                    let message = '<div class="alert alert-info mb-2">' +
                                 '<i class="fe fe-clock me-2"></i><strong>Command queued successfully!</strong><br>' +
                                 '<small class="text-muted">Command: <code>' + data.command + '</code></small><br><br>' +
                                 '<div class="small">' +
                                 'The command is running in the background. ' +
                                 '<strong>How to check if it\'s done:</strong><br>' +
                                 '1. Check the logs: <code>tail -f storage/logs/laravel.log | grep "ExecuteArtisanCommandJob"</code><br>' +
                                 '2. Look for "Command completed" in the logs<br><br>' +
                                 '<strong>Note:</strong> Make sure your queue worker is running:<br>' +
                                 '<code>php artisan queue:work</code> or <code>php artisan queue:listen</code><br><br>' +
                                 '<em>If using sync queue (default), the command runs immediately.</em>' +
                                 '</div>' +
                                 '</div>';
                    
                    outputDiv.innerHTML = message;
                } else if (data.status === 'completed') {
                    // Synchronous execution completed successfully
                    outputDiv.innerHTML = '<div class="alert alert-success mb-2">' +
                                         '<i class="fe fe-check-circle me-2"></i><strong>✓ Command executed successfully!</strong><br>' +
                                         '<small class="text-muted">Command: <code>' + escapeHtml(data.command) + '</code></small>' +
                                         '</div>' +
                                         (data.output ? '<pre class="mb-0 bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;">' + escapeHtml(data.output) + '</pre>' : '<div class="text-muted">No output</div>');
                } else if (data.status === 'failed') {
                    // Synchronous execution failed
                    outputDiv.innerHTML = '<div class="alert alert-danger mb-2">' +
                                         '<i class="fe fe-x-circle me-2"></i><strong>✗ Command failed</strong><br>' +
                                         '<small class="text-muted">Command: <code>' + escapeHtml(data.command) + '</code></small>' +
                                         '</div>' +
                                         (data.output ? '<pre class="mb-0 bg-light p-3 rounded text-danger" style="max-height: 400px; overflow-y: auto;">' + escapeHtml(data.output) + '</pre>' : '<div class="text-danger">' + escapeHtml(data.message || 'Unknown error') + '</div>');
                } else {
                    // Fallback for other statuses
                    outputDiv.innerHTML = '<div class="text-success mb-2">✓ ' + (data.message || 'Command executed successfully') + '</div>' +
                                         '<div class="text-muted small mb-2">Command: <code>' + escapeHtml(data.command) + '</code></div>' +
                                         (data.output ? '<pre class="mb-0">' + escapeHtml(data.output) + '</pre>' : '');
                }
            } else {
                // Command failed - show error and output if available
                let errorHtml = '<div class="alert alert-danger mb-2">' +
                               '<i class="fe fe-x-circle me-2"></i><strong>✗ Command failed</strong><br>' +
                               '<small class="text-muted">Command: <code>' + escapeHtml(data.command || command) + '</code></small>' +
                               '</div>';
                
                if (data.output) {
                    errorHtml += '<pre class="mb-0 bg-light p-3 rounded text-danger" style="max-height: 400px; overflow-y: auto;">' + escapeHtml(data.output) + '</pre>';
                } else {
                    errorHtml += '<pre class="mb-0 text-danger">' + escapeHtml(data.error || data.message || 'Unknown error') + '</pre>';
                }
                
                outputDiv.innerHTML = errorHtml;
            }
        })
        .catch(error => {
            outputDiv.innerHTML = '<div class="text-danger">Error: ' + escapeHtml(error.message) + '</div>';
        });
    });
});

// Check command status by looking at recent logs
function checkCommandStatus(command, buttonElement) {
    if (!buttonElement) return;
    
    const originalHtml = buttonElement.innerHTML;
    buttonElement.disabled = true;
    buttonElement.innerHTML = '<i class="fe fe-loader me-1 spin"></i>Checking...';
    
    fetch('{{ url("v2/artisan-commands/check-command-status") }}?command=' + encodeURIComponent(command), {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        buttonElement.disabled = false;
        buttonElement.innerHTML = originalHtml;
        
        if (data.success) {
            // Find the output div for this command form
            const commandForm = document.querySelector('[data-command="' + escapeHtml(command) + '"]');
            const outputDiv = commandForm ? commandForm.closest('.card-body').querySelector('.command-output') : null;
            
            if (outputDiv) {
                let statusHtml = '<div class="alert alert-info mb-2 mt-2">' +
                               '<strong>Command Status Check</strong><br>' +
                               '<small>Command: <code>' + escapeHtml(command) + '</code></small><br><br>';
                
                if (data.status === 'completed') {
                    statusHtml += '<div class="alert alert-success">' +
                                 '<i class="fe fe-check-circle me-2"></i><strong>✓ Command Completed!</strong><br>' +
                                 '<small>Completed at: ' + (data.completed_at || 'Recently') + '</small><br>';
                    if (data.exit_code !== null) {
                        statusHtml += '<small>Exit Code: ' + data.exit_code + '</small><br>';
                    }
                    statusHtml += '</div>';
                } else if (data.status === 'running') {
                    statusHtml += '<div class="alert alert-warning">' +
                                 '<i class="fe fe-loader me-2"></i><strong>Command Still Running...</strong><br>' +
                                 '<small>Started at: ' + (data.started_at || 'Recently') + '</small><br>' +
                                 '<small>Please check again in a moment.</small>' +
                                 '</div>';
                } else if (data.status === 'queued') {
                    statusHtml += '<div class="alert alert-secondary">' +
                                 '<i class="fe fe-clock me-2"></i><strong>Command Queued</strong><br>' +
                                 '<small>The command has been queued and is waiting to run.</small>' +
                                 '</div>';
                } else {
                    statusHtml += '<div class="alert alert-secondary">' +
                                 '<i class="fe fe-info me-2"></i><strong>Status Unknown</strong><br>' +
                                 '<small>No recent execution found in logs. The command may not have started yet, or logs may have been cleared.</small>' +
                                 '</div>';
                }
                
                if (data.last_log_entry) {
                    statusHtml += '<div class="mt-2 small">' +
                                 '<strong>Last Log Entry:</strong><br>' +
                                 '<code class="small" style="word-break: break-all;">' + escapeHtml(data.last_log_entry.substring(0, 200)) + (data.last_log_entry.length > 200 ? '...' : '') + '</code>' +
                                 '</div>';
                }
                
                statusHtml += '</div>';
                outputDiv.innerHTML = statusHtml + outputDiv.innerHTML;
            }
        } else {
            alert('Error checking status: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        buttonElement.disabled = false;
        buttonElement.innerHTML = originalHtml;
        alert('Error checking status: ' + error.message);
    });
}

// Show documentation
function showDocumentation(filename) {
    const modal = new bootstrap.Modal(document.getElementById('documentationModal'));
    const contentDiv = document.getElementById('documentationContent');
    
    contentDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"></div></div>';
    modal.show();

    fetch('{{ url("v2/artisan-commands/documentation") }}?file=' + encodeURIComponent(filename))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Convert markdown to HTML
                let html = '<div class="mb-3">';
                html += '<h4 class="mb-3">' + data.filename.replace('.md', '').replace(/_/g, ' ') + '</h4>';
                html += '<hr class="mb-4">';
                html += convertMarkdownToHtml(data.content);
                html += '</div>';
                contentDiv.innerHTML = html;
            } else {
                contentDiv.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            }
        })
        .catch(error => {
            contentDiv.innerHTML = '<div class="alert alert-danger">Error loading documentation: ' + error.message + '</div>';
        });
}

// Clear output
function clearOutput(btn) {
    const outputDiv = btn.closest('.command-output-container').querySelector('.command-output');
    outputDiv.innerHTML = '';
    outputDiv.style.display = 'none';
    btn.closest('.command-output-container').style.display = 'none';
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Simple markdown to HTML converter
function convertMarkdownToHtml(markdown) {
    let html = markdown;
    
    // Code blocks (do first to avoid conflicts)
    html = html.replace(/```(\w+)?\n([\s\S]*?)```/g, '<pre class="bg-dark text-light p-3 rounded"><code>$2</code></pre>');
    html = html.replace(/```([\s\S]*?)```/g, '<pre class="bg-dark text-light p-3 rounded"><code>$1</code></pre>');
    
    // Inline code
    html = html.replace(/`([^`\n]+)`/g, '<code class="bg-light px-1 rounded">$1</code>');
    
    // Headers
    html = html.replace(/^#### (.*$)/gim, '<h4 class="mt-4 mb-2">$1</h4>');
    html = html.replace(/^### (.*$)/gim, '<h3 class="mt-4 mb-2">$1</h3>');
    html = html.replace(/^## (.*$)/gim, '<h2 class="mt-4 mb-3">$1</h2>');
    html = html.replace(/^# (.*$)/gim, '<h1 class="mt-4 mb-3">$1</h1>');
    
    // Bold and italic
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    
    // Unordered lists
    html = html.replace(/^[\*\-] (.*$)/gim, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)/s, '<ul class="mb-3">$1</ul>');
    
    // Ordered lists
    html = html.replace(/^\d+\. (.*$)/gim, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)/s, '<ol class="mb-3">$1</ol>');
    
    // Links
    html = html.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
    
    // Horizontal rules
    html = html.replace(/^---$/gim, '<hr>');
    html = html.replace(/^\*\*\*$/gim, '<hr>');
    
    // Tables (basic)
    html = html.replace(/\|(.+)\|/g, function(match, content) {
        const cells = content.split('|').map(c => c.trim()).filter(c => c);
        return '<tr>' + cells.map(c => '<td>' + c + '</td>').join('') + '</tr>';
    });
    
    // Line breaks
    html = html.replace(/\n\n/g, '</p><p class="mb-2">');
    html = '<div class="markdown-body"><p class="mb-2">' + html + '</p></div>';
    
    return html;
}
</script>
@endsection

