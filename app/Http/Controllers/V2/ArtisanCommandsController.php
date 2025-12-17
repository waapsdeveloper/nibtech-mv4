<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

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

        return view('v2.artisan-commands.index', [
            'commands' => $commands,
            'docs' => $docs
        ])->with($data);
    }

    /**
     * Execute an artisan command
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
            // Clean options - remove empty values and ensure proper format for Artisan::call()
            // Artisan::call() expects option names without '--' prefix
            $cleanOptions = [];
            foreach ($options as $key => $value) {
                // Skip empty values, but keep '0' and false values
                if ($value === null || $value === '') {
                    continue;
                }
                
                // Remove '--' prefix if present
                $cleanKey = str_replace('--', '', $key);
                
                // Convert string numbers to integers if the value is numeric
                if (is_numeric($value) && !is_float($value)) {
                    $cleanOptions[$cleanKey] = (int) $value;
                } else {
                    $cleanOptions[$cleanKey] = $value;
                }
            }

            // Execute command and capture output
            $exitCode = Artisan::call($command, $cleanOptions);
            $output = Artisan::output();

            return response()->json([
                'success' => $exitCode === 0,
                'output' => $output ?: 'Command executed successfully (no output)',
                'command' => $commandString,
                'exit_code' => $exitCode
            ]);
        } catch (\Exception $e) {
            Log::error('Artisan command execution failed', [
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
}

