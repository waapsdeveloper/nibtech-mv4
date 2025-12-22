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

        return view('v2.artisan-commands.index', [
            'commands' => $commands,
            'docs' => $docs,
            'migrationStatus' => $migrationStatus
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
                // Skip empty values
                if ($value === null || $value === '') {
                    continue;
                }
                
                // Ensure '--' prefix is present
                $cleanKey = strpos($key, '--') === 0 ? $key : '--' . $key;
                
                // Convert string numbers to integers for numeric options
                if (is_numeric($value) && !is_float($value) && strpos($value, '.') === false) {
                    $cleanOptions[$cleanKey] = (int) $value;
                } else {
                    $cleanOptions[$cleanKey] = $value;
                }
            }

            // Dispatch job to queue for asynchronous execution
            $job = new ExecuteArtisanCommandJob($command, $cleanOptions);
            dispatch($job);

            Log::info('Artisan command dispatched to queue', [
                'command' => $commandString,
                'options' => $cleanOptions
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Command dispatched to queue. Check logs for output.',
                'command' => $commandString,
                'status' => 'queued'
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
            ]
        ];
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
                    'ran_at' => $lastRan->created_at ?? null
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
                    $migration['ran_at'] = $ranMigration->created_at ?? null;
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
            $job = new ExecuteArtisanCommandJob('migrate', []);
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

            return response()->json([
                'success' => true,
                'migration_name' => $migrationName,
                'in_database' => $migrationRecord !== null,
                'migration_record' => $migrationRecord,
                'all_similar_records' => $allRecords,
                'table_exists' => $tableExists,
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

            // Insert the migration record
            DB::table('migrations')->insert([
                'migration' => $migrationName,
                'batch' => $newBatch,
                'created_at' => now(),
                'updated_at' => now()
            ]);

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
}

