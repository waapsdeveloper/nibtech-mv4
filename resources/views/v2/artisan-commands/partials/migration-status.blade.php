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

