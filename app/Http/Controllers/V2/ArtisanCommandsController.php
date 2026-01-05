<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Jobs\ExecuteArtisanCommandJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArtisanCommandsController extends Controller
{
    /**
     * Display artisan commands guide page
     */
    public function index()
    {
        $data['title_page'] = "V2 Artisan Commands Guide";
        session()->put('page_title', $data['title_page']);

        // Get all V2 commands
        $commands = $this->getV2Commands();
        
        // Get documentation files
        $docs = $this->getDocumentationFiles();

        // Get migration status
        $migrationStatus = $this->getMigrationStatus();
        
        // Get running jobs
        $runningJobs = $this->getRunningJobs();

        return view('v2.artisan-commands.index', [
            'commands' => $commands,
            'docs' => $docs,
            'migrationStatus' => $migrationStatus,
            'runningJobs' => $runningJobs
        ])->with($data);
    }

    /**
     * Execute an artisan command (dispatches to queue to prevent timeout)
     */
    public function execute(Request $request)
    {
        $request->validate([
            'command' => 'required|string',
            'options' => 'nullable|array'
        ]);

        $command = $request->input('command');
        $options = $request->input('options', []);

        // Build command string for display
        $commandString = $command;
        foreach ($options as $key => $value) {
            if ($value !== null && $value !== '' && $value !== '0') {
                $commandString .= " --{$key}=" . escapeshellarg($value);
            }
        }

        try {
            // Clean options - Artisan::call() expects options with '--' prefix in array keys
            $cleanOptions = [];
            
            foreach ($options as $key => $value) {
                // Skip empty values (but allow '0' and boolean false)
                if ($value === null || $value === '') {
                    continue;
                }
                
                // Ensure '--' prefix is present for options
                $cleanKey = strpos($key, '--') === 0 ? $key : '--' . $key;
                
                // For numeric options (like marketplace), keep as integer
                // Check if this is a numeric option that should stay as number
                if (is_numeric($value) && !is_float($value) && strpos($value, '.') === false) {
                    $cleanOptions[$cleanKey] = (int) $value;
                }
                // Handle boolean/checkbox values - if value is '1' or true, set as boolean true
                elseif ($value === '1' || $value === 1 || $value === true) {
                    $cleanOptions[$cleanKey] = true;
                } else {
                    $cleanOptions[$cleanKey] = $value;
                }
            }

            // Dispatch job to queue for asynchronous execution
            $job = new ExecuteArtisanCommandJob($command, $cleanOptions);
            $dispatchedJob = dispatch($job);
            
            // Get job ID if available (for database queue)
            $jobId = null;
            if (config('queue.default') === 'database') {
                // For database queue, get the latest job ID
                $latestJob = DB::table('jobs')
                    ->orderBy('id', 'desc')
                    ->first();
                if ($latestJob) {
                    $jobId = $latestJob->id;
                }
            } elseif (method_exists($dispatchedJob, 'getJobId')) {
                $jobId = $dispatchedJob->getJobId();
            } elseif (is_numeric($dispatchedJob)) {
                $jobId = $dispatchedJob;
            }

            Log::info('Artisan command dispatched to queue', [
                'command' => $commandString,
                'options' => $cleanOptions,
                'job_id' => $jobId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Command dispatched to queue. Check logs for output.',
                'command' => $commandString,
                'status' => 'queued',
                'job_id' => $jobId
            ]);
        } catch (\Exception $e) {
            Log::error('Artisan command dispatch failed', [
                'command' => $commandString,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'command' => $commandString
            ], 500);
        }
    }
    
    /**
     * Kill a running command (delete from queue)
     */
    public function killCommand(Request $request)
    {
        $request->validate([
            'job_id' => 'required|string',
            'command' => 'required|string'
        ]);
        
        $jobId = $request->input('job_id');
        $command = $request->input('command');
        
        try {
            // Try to delete the job from the queue
            $deleted = false;
            
            // For database queue, delete from jobs table
            if (config('queue.default') === 'database') {
                $deleted = DB::table('jobs')
                    ->where('id', $jobId)
                    ->delete();
            }
            
            // Also try to delete from failed_jobs if it failed
            DB::table('failed_jobs')
                ->where('uuid', $jobId)
                ->orWhere('id', $jobId)
                ->delete();
            
            Log::info('Artisan command kill requested', [
                'command' => $command,
                'job_id' => $jobId,
                'deleted' => $deleted
            ]);
            
            return response()->json([
                'success' => true,
                'message' => $deleted ? 'Command job deleted from queue' : 'Command job marked for deletion (may have already completed)',
                'deleted' => $deleted
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to kill command', [
                'command' => $command,
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to kill command: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Restart a command (kill current and start new)
     */
    public function restartCommand(Request $request)
    {
        $request->validate([
            'command' => 'required|string',
            'options' => 'nullable|array',
            'job_id' => 'nullable|string'
        ]);
        
        $command = $request->input('command');
        $options = $request->input('options', []);
        $jobId = $request->input('job_id');
        
        try {
            // Kill the existing job if job_id provided
            if ($jobId) {
                try {
                    if (config('queue.default') === 'database') {
                        DB::table('jobs')
                            ->where('id', $jobId)
                            ->delete();
                    }
                } catch (\Exception $e) {
                    // Ignore if job doesn't exist
                    Log::warning('Could not delete job for restart', [
                        'job_id' => $jobId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Clean options
            $cleanOptions = [];
            foreach ($options as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $cleanKey = strpos($key, '--') === 0 ? $key : '--' . $key;
                if (is_numeric($value) && !is_float($value) && strpos($value, '.') === false) {
                    $cleanOptions[$cleanKey] = (int) $value;
                } elseif ($value === '1' || $value === 1 || $value === true) {
                    $cleanOptions[$cleanKey] = true;
                } else {
                    $cleanOptions[$cleanKey] = $value;
                }
            }
            
            // Dispatch new job
            $job = new ExecuteArtisanCommandJob($command, $cleanOptions);
            $dispatchedJob = dispatch($job);
            
            $newJobId = null;
            if (config('queue.default') === 'database') {
                // For database queue, get the latest job ID
                $latestJob = DB::table('jobs')
                    ->orderBy('id', 'desc')
                    ->first();
                if ($latestJob) {
                    $newJobId = $latestJob->id;
                }
            } elseif (method_exists($dispatchedJob, 'getJobId')) {
                $newJobId = $dispatchedJob->getJobId();
            } elseif (is_numeric($dispatchedJob)) {
                $newJobId = $dispatchedJob;
            }
            
            Log::info('Artisan command restarted', [
                'command' => $command,
                'old_job_id' => $jobId,
                'new_job_id' => $newJobId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Command restarted successfully',
                'command' => $command,
                'status' => 'queued',
                'job_id' => $newJobId
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to restart command', [
                'command' => $command,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to restart command: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get documentation content
     */
    public function getDocumentation(Request $request)
    {
        $file = $request->input('file');
        $docsPath = base_path('docs');

        if (!$file || !File::exists($docsPath . '/' . $file)) {
            return response()->json([
                'success' => false,
                'message' => 'Documentation file not found'
            ], 404);
        }

        $content = File::get($docsPath . '/' . $file);

        return response()->json([
            'success' => true,
            'content' => $content,
            'filename' => $file
        ]);
    }

    /**
     * Get all V2 artisan commands with metadata
     */
    private function getV2Commands()
    {
        return [
            [
                'signature' => 'v2:sync-orders',
                'name' => 'Sync Marketplace Orders',
                'description' => 'Unified command to sync orders from marketplace APIs',
                'category' => 'Orders',
                'docs' => [
                    'V2_UNIFIED_ORDER_SYNC_COMMAND.md',
                    'V2_ORDER_REFRESH_COMMANDS_ANALYSIS.md',
                    'V2_SYNC_SCHEDULE.md'
                ],
                'options' => [
                    'type' => [
                        'label' => 'Sync Type',
                        'type' => 'select',
                        'options' => [
                            'all' => 'All (new + modified + care + incomplete)',
                            'new' => 'New Orders',
                            'modified' => 'Modified Orders',
                            'care' => 'Care/Replacement Records',
                            'incomplete' => 'Incomplete Orders'
                        ],
                        'default' => 'all',
                        'required' => false
                    ],
                    'marketplace' => [
                        'label' => 'Marketplace ID',
                        'type' => 'number',
                        'placeholder' => 'Leave empty for all marketplaces',
                        'required' => false
                    ],
                    'page-size' => [
                        'label' => 'Page Size',
                        'type' => 'number',
                        'default' => 50,
                        'required' => false
                    ],
                    'days-back' => [
                        'label' => 'Days Back (for incomplete)',
                        'type' => 'number',
                        'default' => 2,
                        'required' => false
                    ]
                ],
                'examples' => [
                    'php artisan v2:sync-orders --type=new',
                    'php artisan v2:sync-orders --type=modified --marketplace=1',
                    'php artisan v2:sync-orders --type=all --page-size=100'
                ]
            ],
            [
                'signature' => 'v2:marketplace:sync-stock',
                'name' => 'Sync Marketplace Stock',
                'description' => 'Sync stock quantities from marketplace APIs with buffer application',
                'category' => 'Stock',
                'docs' => [
                    'MARKETPLACE_SYNC_BACKGROUND_SETUP.md',
                    'STOCK_SYNC_COMPREHENSIVE_ANALYSIS.md',
                    'CLIENT_STOCK_SYNC_IMPLEMENTATION_PLAN.md'
                ],
                'options' => [
                    'marketplace' => [
                        'label' => 'Marketplace ID',
                        'type' => 'number',
                        'placeholder' => 'Leave empty for all marketplaces',
                        'required' => false
                    ]
                ],
                'examples' => [
                    'php artisan v2:marketplace:sync-stock',
                    'php artisan v2:marketplace:sync-stock --marketplace=1'
                ]
            ],
            [
                'signature' => 'v2:marketplace:sync-stock-bulk',
                'name' => 'Bulk Sync Marketplace Stock (Optimized)',
                'description' => 'Bulk sync stock from BackMarket API using getAllListings (95-98% fewer API calls). Currently supports BackMarket (ID 1) only.',
                'category' => 'Stock',
                'docs' => [
                    'V1_V2_STOCK_SYNC_COMPARISON.md',
                    'BACKMARKET_STOCK_SYNC_ANALYSIS.md'
                ],
                'options' => [
                    'marketplace' => [
                        'label' => 'Marketplace ID',
                        'type' => 'number',
                        'placeholder' => 'Enter marketplace ID (default: 1)',
                        'required' => false,
                        'default' => '1',
                        'description' => 'The marketplace ID to sync stock for (currently only 1 for BackMarket)'
                    ],
                    'force' => [
                        'label' => 'Force Sync',
                        'type' => 'checkbox',
                        'description' => 'Force sync even if last sync was less than 6 hours ago',
                        'required' => false
                    ]
                ],
                'examples' => [
                    'php artisan v2:marketplace:sync-stock-bulk --marketplace=1',
                    'php artisan v2:marketplace:sync-stock-bulk --marketplace=1 --force'
                ]
            ],
            [
                'signature' => 'v2:sync-all-marketplace-stock-from-api',
                'name' => 'Sync All Marketplace Stock from API',
                'description' => 'Fetch stock quantities from marketplace APIs for all variations. Uses bulk fetch for BackMarket (ID 1), individual calls for other marketplaces. Runs in queue for bulk operations.',
                'category' => 'Stock Management',
                'warning' => false,
                'docs' => [
                    'V1_V2_STOCK_SYNC_COMPARISON.md',
                    'BACKMARKET_STOCK_SYNC_ANALYSIS.md'
                ],
                'options' => [
                    'marketplace' => [
                        'label' => 'Marketplace ID',
                        'type' => 'number',
                        'placeholder' => 'Enter marketplace ID (default: 1)',
                        'required' => false,
                        'default' => '1',
                        'description' => 'The marketplace ID to sync stock for (default: 1 for BackMarket)'
                    ]
                ],
                'examples' => [
                    'php artisan v2:sync-all-marketplace-stock-from-api',
                    'php artisan v2:sync-all-marketplace-stock-from-api --marketplace=1'
                ]
            ]
        ];
    }

    /**
     * Get running jobs from queue
     */
    private function getRunningJobs()
    {
        $runningJobs = [];
        
        if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
            try {
                $jobs = DB::table('jobs')
                    ->orderBy('id', 'desc')
                    ->limit(10)
                    ->get();
                
                foreach ($jobs as $job) {
                    try {
                        $payload = json_decode($job->payload, true);
                        if (isset($payload['displayName']) && $payload['displayName'] === 'App\\Jobs\\ExecuteArtisanCommandJob') {
                            $commandData = null;
                            $options = [];
                            
                            // Laravel serializes job data, so we need to unserialize it
                            if (isset($payload['data'])) {
                                $data = $payload['data'];
                                
                                // If data is a string, it's serialized
                                if (is_string($data)) {
                                    $unserialized = @unserialize($data);
                                    if ($unserialized !== false) {
                                        // The unserialized data contains the job properties
                                        if (is_object($unserialized)) {
                                            $commandData = $unserialized->command ?? null;
                                            $options = $unserialized->options ?? [];
                                        } elseif (is_array($unserialized)) {
                                            $commandData = $unserialized['command'] ?? null;
                                            $options = $unserialized['options'] ?? [];
                                        }
                                    }
                                } elseif (is_array($data)) {
                                    // Direct array access
                                    $commandData = $data['command'] ?? null;
                                    $options = $data['options'] ?? [];
                                }
                            }
                            
                            if ($commandData) {
                                $runningJobs[] = [
                                    'id' => $job->id,
                                    'command' => $commandData,
                                    'options' => $options,
                                    'created_at' => $job->created_at,
                                    'queue' => $job->queue ?? 'default'
                                ];
                            }
                        }
                    } catch (\Exception $e) {
                        // Skip jobs that can't be parsed
                        Log::debug('Failed to parse job payload', ['job_id' => $job->id, 'error' => $e->getMessage()]);
                        continue;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get running jobs', ['error' => $e->getMessage()]);
            }
        }
        
        return $runningJobs;
    }

    /**
     * Get documentation files list
     */
    private function getDocumentationFiles()
    {
        $docsPath = base_path('docs');
        $files = File::files($docsPath);
        
        $docs = [];
        foreach ($files as $file) {
            if ($file->getExtension() === 'md') {
                $docs[] = [
                    'filename' => $file->getFilename(),
                    'name' => str_replace('.md', '', $file->getFilename()),
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime()
                ];
            }
        }

        // Sort by name
        usort($docs, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $docs;
    }

    /**
     * Get migration status - last run migration and pending migrations
     */
    private function getMigrationStatus()
    {
        $status = [
            'last_migration' => null,
            'pending_migrations' => [],
            'total_pending' => 0,
            'migrations_table_exists' => false
        ];

        try {
            // Check if migrations table exists
            if (!Schema::hasTable('migrations')) {
                return $status;
            }

            $status['migrations_table_exists'] = true;
            
            // Check which columns exist in migrations table (cache for this method call)
            $migrationColumns = Schema::getColumnListing('migrations');
            $hasTimestamps = in_array('created_at', $migrationColumns) && in_array('updated_at', $migrationColumns);

            // Get all migration files from main directory and subdirectories
            $migrationPath = database_path('migrations');
            $allMigrations = [];
            
            if (File::exists($migrationPath)) {
                // Get files from main directory
                $files = File::files($migrationPath);
                foreach ($files as $file) {
                    $filename = $file->getFilename();
                    // Extract timestamp and name from filename (e.g., 2024_01_01_000000_create_table.php)
                    if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_(.+?)\.php$/', $filename, $matches)) {
                        $allMigrations[] = [
                            'migration' => str_replace('.php', '', $filename),
                            'filename' => $filename,
                            'timestamp' => $matches[1],
                            'name' => $matches[2],
                            'path' => 'migrations',
                            'full_path' => $file->getPathname(),
                            'batch' => null,
                            'ran_at' => null
                        ];
                    }
                }
                
                // Get files from subdirectories (e.g., live_migrations)
                $directories = File::directories($migrationPath);
                foreach ($directories as $directory) {
                    $dirName = basename($directory);
                    $files = File::files($directory);
                    foreach ($files as $file) {
                        $filename = $file->getFilename();
                        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_(.+?)\.php$/', $filename, $matches)) {
                            $allMigrations[] = [
                                'migration' => str_replace('.php', '', $filename),
                                'filename' => $filename,
                                'timestamp' => $matches[1],
                                'name' => $matches[2],
                                'path' => 'migrations/' . $dirName,
                                'full_path' => $file->getPathname(),
                                'batch' => null,
                                'ran_at' => null
                            ];
                        }
                    }
                }
            }

            // Get ran migrations from database
            $ranMigrations = DB::table('migrations')
                ->orderBy('id', 'desc')
                ->get()
                ->keyBy('migration')
                ->toArray();

            // Get last migration
            $lastRan = DB::table('migrations')
                ->orderBy('id', 'desc')
                ->first();

            if ($lastRan) {
                $status['last_migration'] = [
                    'migration' => $lastRan->migration,
                    'batch' => $lastRan->batch,
                    'ran_at' => $hasTimestamps ? ($lastRan->created_at ?? null) : null
                ];
            }

            // Find pending migrations and check if tables exist
            $pending = [];
            foreach ($allMigrations as $migration) {
                $migrationName = $migration['migration'];
                
                // Check if migration is in database
                $ranMigration = $ranMigrations[$migrationName] ?? null;
                
                if (!$ranMigration) {
                    // Migration not in database - check if table might exist anyway
                    $tableExists = $this->checkMigrationTableExists($migration);
                    $migration['table_exists'] = $tableExists;
                    $migration['status'] = $tableExists ? 'table_exists_but_not_recorded' : 'pending';
                    $pending[] = $migration;
                } else {
                    // Migration is recorded - update with batch info
                    $migration['batch'] = $ranMigration->batch ?? null;
                    $migration['ran_at'] = $hasTimestamps ? ($ranMigration->created_at ?? null) : null;
                    $migration['status'] = 'completed';
                }
            }

            // Sort pending by timestamp
            usort($pending, function($a, $b) {
                return strcmp($a['timestamp'], $b['timestamp']);
            });

            $status['pending_migrations'] = $pending;
            $status['total_pending'] = count($pending);

        } catch (\Exception $e) {
            Log::error('Error getting migration status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $status;
    }

    /**
     * Run pending migrations
     */
    public function runMigrations(Request $request)
    {
        try {
            // Dispatch migrate command to queue
            // Add --no-interaction to prevent STDIN errors in web context
            $job = new ExecuteArtisanCommandJob('migrate', [
                '--no-interaction' => true
            ]);
            dispatch($job);

            Log::info('Migration command dispatched to queue');

            return response()->json([
                'success' => true,
                'message' => 'Migration command dispatched to queue. Check logs for output.',
                'status' => 'queued'
            ]);
        } catch (\Exception $e) {
            Log::error('Migration command dispatch failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a table created by a migration exists
     */
    private function checkMigrationTableExists($migration)
    {
        try {
            // Try to extract table name from migration name
            $migrationName = $migration['name'] ?? '';
            
            // Common patterns: create_X_table, add_X_to_Y_table, etc.
            if (preg_match('/create_([a-z0-9_]+)_table/', $migrationName, $matches)) {
                $tableName = $matches[1];
                return Schema::hasTable($tableName);
            }
            
            // For other migration types, we can't easily determine the table
            // Return null to indicate we can't check
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get detailed migration information for debugging
     */
    public function getMigrationDetails(Request $request)
    {
        try {
            $migrationName = $request->input('migration');
            
            if (!Schema::hasTable('migrations')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Migrations table does not exist'
                ], 404);
            }

            // Check if migration is in database
            $migrationRecord = DB::table('migrations')
                ->where('migration', $migrationName)
                ->first();

            // Get all migrations with this name pattern (in case of duplicates)
            $allRecords = DB::table('migrations')
                ->where('migration', 'like', '%' . $migrationName . '%')
                ->get();

            // Check if table exists
            $tableExists = null;
            if (preg_match('/create_([a-z0-9_]+)_table/', $migrationName, $matches)) {
                $tableName = $matches[1];
                $tableExists = Schema::hasTable($tableName);
            }

            // Find the migration file path by searching all migrations
            $migrationPathFull = null;
            $migrationBasePath = database_path('migrations');
            
            // Search in all migrations (from getMigrationStatus logic)
            // Check main migrations directory
            $mainFile = $migrationBasePath . '/' . $migrationName . '.php';
            if (File::exists($mainFile)) {
                $migrationPathFull = 'migrations/' . $migrationName . '.php';
            } else {
                // Check subdirectories recursively
                $foundPath = $this->findMigrationFile($migrationBasePath, $migrationName);
                if ($foundPath) {
                    $migrationPathFull = 'migrations/' . $foundPath;
                }
            }

            return response()->json([
                'success' => true,
                'migration_name' => $migrationName,
                'in_database' => $migrationRecord !== null,
                'migration_record' => $migrationRecord,
                'all_similar_records' => $allRecords,
                'table_exists' => $tableExists,
                'migration_path' => $migrationPathFull,
                'all_migrations_in_db' => DB::table('migrations')
                    ->orderBy('id', 'desc')
                    ->limit(50)
                    ->get()
                    ->pluck('migration')
                    ->toArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually record a migration in the database
     */
    public function recordMigration(Request $request)
    {
        try {
            $request->validate([
                'migration' => 'required|string'
            ]);

            $migrationName = $request->input('migration');

            if (!Schema::hasTable('migrations')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Migrations table does not exist'
                ], 404);
            }

            // Check if migration already exists
            $existing = DB::table('migrations')
                ->where('migration', $migrationName)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Migration already exists in database',
                    'migration' => $existing
                ], 400);
            }

            // Get the highest batch number and add 1, or use 1 if no migrations exist
            $maxBatch = DB::table('migrations')->max('batch') ?? 0;
            $newBatch = $maxBatch + 1;

            // Check which columns exist in the migrations table
            $columns = Schema::getColumnListing('migrations');
            $hasTimestamps = in_array('created_at', $columns) && in_array('updated_at', $columns);

            // Build insert data - only include columns that exist
            $insertData = [
                'migration' => $migrationName,
                'batch' => $newBatch
            ];

            // Only add timestamps if the columns exist
            if ($hasTimestamps) {
                $insertData['created_at'] = now();
                $insertData['updated_at'] = now();
            }

            // Insert the migration record
            DB::table('migrations')->insert($insertData);

            Log::info('Migration manually recorded', [
                'migration' => $migrationName,
                'batch' => $newBatch
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Migration record added successfully',
                'migration' => [
                    'migration' => $migrationName,
                    'batch' => $newBatch
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error recording migration', [
                'migration' => $request->input('migration'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run a single specific migration
     */
    public function runSingleMigration(Request $request)
    {
        try {
            $request->validate([
                'migration' => 'required|string',
                'path' => 'nullable|string'
            ]);

            $migrationName = $request->input('migration');
            $migrationPath = $request->input('path');

            // Build the migration path (relative to database/migrations)
            // Always search dynamically for the migration file - more reliable than parsing paths
            $relativePath = null;
            $migrationBasePath = database_path('migrations');
            
            // Search for the migration file dynamically
            // Check main directory first
            $mainFile = $migrationBasePath . '/' . $migrationName . '.php';
            if (File::exists($mainFile)) {
                $relativePath = $migrationName . '.php';
            } else {
                // Search all subdirectories recursively (handles any folder structure)
                $found = $this->findMigrationFile($migrationBasePath, $migrationName);
                if ($found) {
                    $relativePath = $found;
                }
            }
            
            // If still not found and path was provided, try to use it as fallback
            if (!$relativePath && $migrationPath) {
                // Path provided from frontend - try to extract and verify
                // Format could be: "migrations/live_migrations/filename.php" or "migrations/filename.php"
                // Remove "database/migrations/" if present
                $cleanPath = preg_replace('#^database/migrations/#', '', $migrationPath);
                // Remove "migrations/" prefix if present (but keep subdirectories)
                $cleanPath = preg_replace('#^migrations/#', '', $cleanPath);
                
                // Verify the file exists at this path
                $fullPath = $migrationBasePath . '/' . $cleanPath;
                if (File::exists($fullPath)) {
                    $relativePath = $cleanPath;
                } else {
                    // Log for debugging
                    Log::warning('Migration path provided but file not found, will search dynamically', [
                        'migration' => $migrationName,
                        'provided_path' => $migrationPath,
                        'extracted_path' => $cleanPath,
                        'full_path' => $fullPath
                    ]);
                }
            }

            if (!$relativePath) {
                return response()->json([
                    'success' => false,
                    'error' => 'Migration file not found: ' . $migrationName . '. Searched in main directory and all subdirectories.'
                ], 404);
            }

            // Verify file exists
            $fullPath = $migrationBasePath . '/' . $relativePath;
            if (!File::exists($fullPath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Migration file not found: ' . $migrationName . ' at path: ' . $relativePath
                ], 404);
            }

            // Dispatch job to run the specific migration
            // Use --path with relative path from database/migrations
            // Add --no-interaction to prevent STDIN errors in web context
            $job = new ExecuteArtisanCommandJob('migrate', [
                '--path' => 'database/migrations/' . $relativePath,
                '--no-interaction' => true
            ]);
            dispatch($job);

            Log::info('Single migration command dispatched to queue', [
                'migration' => $migrationName,
                'path' => $fullPath
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Migration command dispatched to queue. Check logs for output.',
                'migration' => $migrationName,
                'path' => $fullPath,
                'status' => 'queued'
            ]);
        } catch (\Exception $e) {
            Log::error('Single migration command dispatch failed', [
                'migration' => $request->input('migration'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check command execution status by looking at recent logs
     */
    public function checkCommandStatus(Request $request)
    {
        try {
            $command = $request->input('command');
            
            if (!$command) {
                return response()->json([
                    'success' => false,
                    'error' => 'Command name required'
                ], 400);
            }

            // For sync-all-marketplace-stock-from-api command, check StockSyncLog table
            if ($command === 'v2:sync-all-marketplace-stock-from-api') {
                $marketplaceId = $request->input('marketplace', 1);
                
                $logEntry = \App\Models\StockSyncLog::where('marketplace_id', $marketplaceId)
                    ->orderBy('started_at', 'desc')
                    ->first();
                
                if ($logEntry) {
                    $status = $logEntry->status; // running, completed, failed, cancelled
                    $startedAt = $logEntry->started_at ? $logEntry->started_at->format('Y-m-d H:i:s') : null;
                    $completedAt = $logEntry->completed_at ? $logEntry->completed_at->format('Y-m-d H:i:s') : null;
                    $exitCode = $status === 'completed' ? 0 : ($status === 'failed' ? 1 : ($status === 'cancelled' ? 1 : null));
                    
                    // Build detailed output message
                    $output = "Status: {$status}\n";
                    if ($logEntry->summary) {
                        $output .= "Summary: {$logEntry->summary}\n";
                    }
                    if ($logEntry->total_records !== null) {
                        $output .= "Total Records: {$logEntry->total_records}\n";
                        $output .= "Synced: {$logEntry->synced_count}\n";
                        $output .= "Skipped: {$logEntry->skipped_count}\n";
                        $output .= "Errors: {$logEntry->error_count}\n";
                    }
                    if ($logEntry->duration_seconds !== null) {
                        $output .= "Duration: {$logEntry->duration_seconds} seconds\n";
                    }
                    if ($logEntry->error_details && is_array($logEntry->error_details) && count($logEntry->error_details) > 0) {
                        $output .= "\nError Details:\n";
                        foreach (array_slice($logEntry->error_details, 0, 10) as $error) {
                            $variationId = $error['variation_id'] ?? 'Unknown';
                            $errorMsg = $error['error'] ?? 'Unknown error';
                            $output .= "  Variation ID {$variationId}: {$errorMsg}\n";
                        }
                    }
                    
                    return response()->json([
                        'success' => true,
                        'status' => $status,
                        'started_at' => $startedAt,
                        'completed_at' => $completedAt,
                        'exit_code' => $exitCode,
                        'summary' => $logEntry->summary,
                        'total_records' => $logEntry->total_records,
                        'synced_count' => $logEntry->synced_count,
                        'skipped_count' => $logEntry->skipped_count,
                        'error_count' => $logEntry->error_count,
                        'duration_seconds' => $logEntry->duration_seconds,
                        'log_id' => $logEntry->id,
                        'output' => $output
                    ]);
                } else {
                    return response()->json([
                        'success' => true,
                        'status' => 'not_found',
                        'message' => 'No sync log found for this marketplace'
                    ]);
                }
            }

            // For other commands, read recent log entries
            $logPath = storage_path('logs/laravel.log');
            $status = 'not_found';
            $lastLogEntry = null;
            $startedAt = null;
            $completedAt = null;
            $exitCode = null;

            if (File::exists($logPath)) {
                // Read last 1000 lines of log file
                $lines = file($logPath);
                $recentLines = array_slice($lines, -1000);
                
                $foundStart = false;
                $foundComplete = false;
                
                // Search backwards through recent lines
                for ($i = count($recentLines) - 1; $i >= 0; $i--) {
                    $line = $recentLines[$i];
                    
                    // Look for command completion
                    if (strpos($line, 'ExecuteArtisanCommandJob: Command completed') !== false && 
                        strpos($line, $command) !== false) {
                        $foundComplete = true;
                        $status = 'completed';
                        $completedAt = $this->extractTimestamp($line);
                        
                        // Try to extract exit code
                        if (preg_match('/"exit_code":\s*(\d+)/', $line, $matches)) {
                            $exitCode = (int)$matches[1];
                        }
                        
                        // Get this as last log entry
                        if (!$lastLogEntry) {
                            $lastLogEntry = trim($line);
                        }
                        break;
                    }
                    
                    // Look for command start
                    if (strpos($line, 'ExecuteArtisanCommandJob: Starting command execution') !== false && 
                        strpos($line, $command) !== false) {
                        $foundStart = true;
                        $startedAt = $this->extractTimestamp($line);
                        
                        if (!$foundComplete) {
                            $status = 'running';
                        }
                        
                        // Get this as last log entry if we haven't found completion
                        if (!$lastLogEntry) {
                            $lastLogEntry = trim($line);
                        }
                    }
                    
                    // Look for command dispatch
                    if (strpos($line, 'Artisan command dispatched to queue') !== false && 
                        strpos($line, $command) !== false) {
                        if (!$foundStart && !$foundComplete) {
                            $status = 'queued';
                            if (!$lastLogEntry) {
                                $lastLogEntry = trim($line);
                            }
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'status' => $status,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'exit_code' => $exitCode,
                'last_log_entry' => $lastLogEntry
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking command status', [
                'command' => $request->input('command'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract timestamp from log line
     */
    private function extractTimestamp($logLine)
    {
        // Laravel log format: [2024-01-01 12:00:00] local.INFO: ...
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $logLine, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Recursively find a migration file in the migrations directory and subdirectories
     * 
     * @param string $basePath Base migrations directory path
     * @param string $migrationName Migration name (without .php extension)
     * @return string|null Relative path from basePath if found, null otherwise
     */
    private function findMigrationFile($basePath, $migrationName)
    {
        $filename = $migrationName . '.php';
        
        // Check main directory
        $mainFile = $basePath . '/' . $filename;
        if (File::exists($mainFile)) {
            return $filename;
        }
        
        // Check all subdirectories
        if (File::isDirectory($basePath)) {
            $directories = File::directories($basePath);
            foreach ($directories as $directory) {
                $dirName = basename($directory);
                $subFile = $directory . '/' . $filename;
                
                if (File::exists($subFile)) {
                    return $dirName . '/' . $filename;
                }
                
                // Recursively check nested subdirectories
                $nestedResult = $this->findMigrationFile($directory, $migrationName);
                if ($nestedResult) {
                    return $dirName . '/' . $nestedResult;
                }
            }
        }
        
        return null;
    }

    /**
     * Get PM2 logs
     */
    public function getPm2Logs(Request $request)
    {
        try {
            $lines = (int) $request->get('lines', 100);
            $lines = max(10, min(1000, $lines)); // Limit between 10 and 1000
            
            // Check if PM2 command is available (try pm2 first, then npx pm2)
            $pm2Command = 'pm2';
            $pm2Check = shell_exec('which pm2 2>&1');
            if (empty($pm2Check) || strpos($pm2Check, 'not found') !== false) {
                // Try Windows path
                $pm2Check = shell_exec('where pm2 2>&1');
                if (empty($pm2Check) || strpos($pm2Check, 'not found') !== false) {
                    // Try npx pm2 (for systems where PM2 is installed via npx)
                    $npxCheck = shell_exec('which npx 2>&1');
                    if (!empty($npxCheck) && strpos($npxCheck, 'not found') === false) {
                        $pm2Command = 'npx pm2';
                    } else {
                        return response()->json([
                            'success' => false,
                            'error' => 'PM2 is not installed or not in PATH. PM2 is typically used on Linux/Unix systems. On Windows, you may need to use PM2 via WSL or install it via npm.',
                            'logs' => ''
                        ]);
                    }
                }
            }
            
            // Get PM2 process list first to check if PM2 is running
            $pm2List = shell_exec("{$pm2Command} list 2>&1");
            
            // Check for common PM2 error messages
            if (strpos($pm2List, 'command not found') !== false || 
                strpos($pm2List, 'not recognized') !== false ||
                strpos($pm2List, 'Cannot find module') !== false) {
                return response()->json([
                    'success' => false,
                    'error' => 'PM2 command not found. Make sure PM2 is installed: npm install -g pm2',
                    'logs' => ''
                ]);
            }
            
            // Check if PM2 daemon is running
            // PM2 list output should contain PM2 table structure or process info
            // Empty list is OK (means no processes, but PM2 is working)
            if (strpos($pm2List, 'command not found') !== false || 
                strpos($pm2List, 'not recognized') !== false ||
                strpos($pm2List, 'Cannot find module') !== false ||
                (strpos($pm2List, 'PM2') === false && strpos($pm2List, 'online') === false && strpos($pm2List, 'stopped') === false && strpos($pm2List, '┌') === false && strpos($pm2List, '│') === false)) {
                // If we get here and it's not an empty table, PM2 might not be working
                // But empty table (just headers) is fine - it means PM2 is running but no processes
                if (strpos($pm2List, '┌') !== false || strpos($pm2List, '│') !== false || strpos($pm2List, 'id') !== false) {
                    // This looks like a PM2 table (even if empty), so PM2 is working
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'PM2 daemon is not running. Start it with: pm2 resurrect or pm2 start <app>',
                        'logs' => ''
                    ]);
                }
            }
            
            // Get PM2 logs (error and output combined)
            // Using --nostream to get historical logs, --lines to limit output
            $pm2Logs = shell_exec("{$pm2Command} logs --lines {$lines} --nostream --format 2>&1");
            
            // If format option doesn't work, try without it
            if (empty($pm2Logs) || (strpos($pm2Logs, 'error') !== false && strpos($pm2Logs, 'PM2') === false)) {
                $pm2Logs = shell_exec("{$pm2Command} logs --lines {$lines} --nostream 2>&1");
            }
            
            // Also try to get error logs specifically
            $pm2ErrorLogs = shell_exec("{$pm2Command} logs --err --lines {$lines} --nostream 2>&1");
            
            // Combine logs if we have both
            $combinedLogs = '';
            if (!empty($pm2Logs) && strpos($pm2Logs, 'command not found') === false) {
                $combinedLogs = $pm2Logs;
            }
            if (!empty($pm2ErrorLogs) && $pm2ErrorLogs !== $pm2Logs && strpos($pm2ErrorLogs, 'command not found') === false) {
                if (!empty($combinedLogs)) {
                    $combinedLogs .= "\n\n=== Error Logs ===\n" . $pm2ErrorLogs;
                } else {
                    $combinedLogs = $pm2ErrorLogs;
                }
            }
            
            // If still empty, try getting logs from PM2 log files directly
            if (empty($combinedLogs) || strpos($combinedLogs, 'No log') !== false) {
                // Try Linux/Unix path
                $pm2Home = getenv('HOME') . '/.pm2';
                if (!is_dir($pm2Home)) {
                    // Try Windows path (if using WSL or similar)
                    $pm2Home = getenv('USERPROFILE') . '/.pm2';
                }
                
                if (is_dir($pm2Home)) {
                    $logFiles = glob($pm2Home . '/logs/*.log');
                    if (!empty($logFiles)) {
                        $combinedLogs = "PM2 log files found:\n";
                        foreach (array_slice($logFiles, 0, 5) as $logFile) {
                            if (File::exists($logFile)) {
                                $fileContent = file_get_contents($logFile);
                                $fileLines = explode("\n", $fileContent);
                                $recentLines = array_slice($fileLines, -$lines);
                                $combinedLogs .= "\n=== " . basename($logFile) . " ===\n";
                                $combinedLogs .= implode("\n", $recentLines);
                            }
                        }
                    }
                }
            }
            
            if (empty($combinedLogs) || strpos($combinedLogs, 'No log') !== false) {
                return response()->json([
                    'success' => true,
                    'logs' => 'No PM2 logs found. Make sure PM2 processes are running. Use "pm2 list" to check running processes.',
                    'lines' => $lines
                ]);
            }
            
            return response()->json([
                'success' => true,
                'logs' => $combinedLogs,
                'lines' => $lines
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching PM2 logs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error fetching PM2 logs: ' . $e->getMessage(),
                'logs' => ''
            ], 500);
        }
    }
}

