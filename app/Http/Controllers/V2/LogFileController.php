<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\LogSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LogFileController extends Controller
{
    /**
     * Display the Laravel log file and log settings
     */
    public function index(Request $request)
    {
        $data['title_page'] = "Log File Viewer";
        session()->put('page_title', $data['title_page']);
        
        // Get selected log file from request (default to laravel.log)
        $selectedFile = $request->get('file', 'laravel.log');
        $logPath = storage_path('logs/' . $selectedFile);
        
        // Security: Only allow log files (prevent directory traversal)
        $selectedFile = basename($selectedFile);
        if (!preg_match('/\.log$/', $selectedFile)) {
            $selectedFile = 'laravel.log';
            $logPath = storage_path('logs/' . $selectedFile);
        }
        
        $logContent = '';
        $lineCount = 0;
        $totalLines = 0;
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 1000);
        $lines = [];
        
        if (File::exists($logPath)) {
            // Read all lines
            $allLines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $totalLines = count($allLines);
            
            // Reverse the entire array to show newest first
            $allLines = array_reverse($allLines);
            
            // Calculate pagination from the beginning of reversed array
            // Page 1 = most recent entries (first in reversed array)
            $offset = ($page - 1) * $perPage;
            $lines = array_slice($allLines, $offset, $perPage);
            
            $lineCount = count($lines);
        } else {
            $totalLines = 0;
        }
        
        // Calculate pagination info
        $totalPages = $totalLines > 0 ? ceil($totalLines / $perPage) : 1;
        // For reversed order: page 1 = newest, higher pages = older
        $hasNextPage = $page < $totalPages; // Next page = older entries
        $hasPrevPage = $page > 1; // Previous page = newer entries
        
        // Get available log files (laravel.log and all slack-*.log files)
        $logFiles = $this->getAvailableLogFiles();
        
        // Get log settings for CRUD interface
        $logSettings = LogSetting::orderBy('created_at', 'desc')->get();
        
        return view('v2.logs.log-file.index', compact('lines', 'lineCount', 'totalLines', 'page', 'perPage', 'totalPages', 'hasNextPage', 'hasPrevPage', 'data', 'logSettings', 'logFiles', 'selectedFile'));
    }
    
    /**
     * Get list of available log files
     */
    private function getAvailableLogFiles(): array
    {
        $logsDirectory = storage_path('logs');
        $files = [];
        
        if (!File::isDirectory($logsDirectory)) {
            return $files;
        }
        
        // Get all .log files
        $logFiles = File::glob($logsDirectory . '/*.log');
        
        foreach ($logFiles as $filePath) {
            $fileName = basename($filePath);
            $fileSize = File::size($filePath);
            $modifiedAt = File::lastModified($filePath);
            
            $files[] = [
                'name' => $fileName,
                'size' => $fileSize,
                'size_formatted' => $this->formatFileSize($fileSize),
                'modified_at' => date('Y-m-d H:i:s', $modifiedAt),
                'is_slack_log' => strpos($fileName, 'slack-') === 0,
            ];
        }
        
        // Sort by modified date (newest first)
        usort($files, function ($a, $b) {
            return strcmp($b['modified_at'], $a['modified_at']);
        });
        
        return $files;
    }
    
    /**
     * Format file size in human-readable format
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Clear the log file
     */
    public function clear(Request $request)
    {
        // Get file from request (default to laravel.log)
        $selectedFile = $request->get('file', 'laravel.log');
        
        // Security: Only allow log files (prevent directory traversal)
        $selectedFile = basename($selectedFile);
        if (!preg_match('/\.log$/', $selectedFile)) {
            $selectedFile = 'laravel.log';
        }
        
        $logPath = storage_path('logs/' . $selectedFile);
        
        if (File::exists($logPath)) {
            File::put($logPath, '');
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Log file cleared successfully'
                ]);
            }
            
            return redirect()->route('v2.logs.log-file', ['file' => $selectedFile])
                ->with('success', 'Log file cleared successfully');
        }
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => 'Log file not found'
            ], 404);
        }
        
        return redirect()->route('v2.logs.log-file', ['file' => $selectedFile])
            ->with('error', 'Log file not found');
    }
    
    /**
     * Store a new log setting
     */
    public function storeLogSetting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:log_settings,name',
            'channel_name' => 'required|string|max:255',
            'webhook_url' => 'required|url',
            'log_level' => 'required|in:error,warning,info,debug',
            'log_type' => 'required|string|max:255',
            'keywords' => 'nullable|string',
            'description' => 'nullable|string',
            'is_enabled' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $keywords = null;
        if ($request->filled('keywords')) {
            $keywordsArray = array_filter(array_map('trim', explode(',', $request->keywords)));
            $keywords = !empty($keywordsArray) ? $keywordsArray : null;
        }
        
        $logSetting = LogSetting::create([
            'name' => $request->name,
            'channel_name' => $request->channel_name,
            'webhook_url' => $request->webhook_url,
            'log_level' => $request->log_level,
            'log_type' => $request->log_type,
            'keywords' => $keywords,
            'description' => $request->description,
            'is_enabled' => $request->has('is_enabled') ? (bool)$request->is_enabled : true,
            'admin_id' => session('user_id'),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Log setting created successfully',
            'data' => $logSetting
        ]);
    }
    
    /**
     * Update an existing log setting
     */
    public function updateLogSetting(Request $request, $id)
    {
        $logSetting = LogSetting::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:log_settings,name,' . $id,
            'channel_name' => 'required|string|max:255',
            'webhook_url' => 'required|url',
            'log_level' => 'required|in:error,warning,info,debug',
            'log_type' => 'required|string|max:255',
            'keywords' => 'nullable|string',
            'description' => 'nullable|string',
            'is_enabled' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $keywords = null;
        if ($request->filled('keywords')) {
            $keywordsArray = array_filter(array_map('trim', explode(',', $request->keywords)));
            $keywords = !empty($keywordsArray) ? $keywordsArray : null;
        }
        
        $logSetting->update([
            'name' => $request->name,
            'channel_name' => $request->channel_name,
            'webhook_url' => $request->webhook_url,
            'log_level' => $request->log_level,
            'log_type' => $request->log_type,
            'keywords' => $keywords,
            'description' => $request->description,
            'is_enabled' => $request->has('is_enabled') ? (bool)$request->is_enabled : $logSetting->is_enabled,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Log setting updated successfully',
            'data' => $logSetting
        ]);
    }
    
    /**
     * Delete a log setting
     */
    public function deleteLogSetting($id)
    {
        $logSetting = LogSetting::findOrFail($id);
        $logSetting->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Log setting deleted successfully'
        ]);
    }
    
    /**
     * Get a single log setting (for editing)
     */
    public function getLogSetting($id)
    {
        $logSetting = LogSetting::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $logSetting
        ]);
    }
    
    /**
     * Duplicate a log setting
     */
    public function duplicateLogSetting($id)
    {
        $originalSetting = LogSetting::findOrFail($id);
        
        // Generate a unique name for the duplicate
        $baseName = $originalSetting->name;
        $newName = $baseName . ' (Copy)';
        $counter = 1;
        
        // Check if name exists and increment counter until we find a unique name
        while (LogSetting::where('name', $newName)->exists()) {
            $counter++;
            $newName = $baseName . ' (Copy ' . $counter . ')';
        }
        
        // Create duplicate with same settings but new name
        $duplicatedSetting = LogSetting::create([
            'name' => $newName,
            'channel_name' => $originalSetting->channel_name,
            'webhook_url' => $originalSetting->webhook_url,
            'log_level' => $originalSetting->log_level,
            'log_type' => $originalSetting->log_type,
            'keywords' => $originalSetting->keywords,
            'description' => $originalSetting->description,
            'is_enabled' => $originalSetting->is_enabled,
            'admin_id' => session('user_id') ?? $originalSetting->admin_id,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Log setting duplicated successfully',
            'data' => $duplicatedSetting
        ]);
    }
}

