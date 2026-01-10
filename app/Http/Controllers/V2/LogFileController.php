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
        
        $logPath = storage_path('logs/laravel.log');
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
        
        // Get log settings for CRUD interface
        $logSettings = LogSetting::orderBy('created_at', 'desc')->get();
        
        return view('v2.logs.log-file.index', compact('lines', 'lineCount', 'totalLines', 'page', 'perPage', 'totalPages', 'hasNextPage', 'hasPrevPage', 'data', 'logSettings'));
    }
    
    /**
     * Clear the log file
     */
    public function clear(Request $request)
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (File::exists($logPath)) {
            File::put($logPath, '');
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Log file cleared successfully'
                ]);
            }
            
            return redirect()->route('v2.logs.log-file')
                ->with('success', 'Log file cleared successfully');
        }
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => 'Log file not found'
            ], 404);
        }
        
        return redirect()->route('v2.logs.log-file')
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
}

