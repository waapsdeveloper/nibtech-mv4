<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LogFileController extends Controller
{
    /**
     * Display the Laravel log file
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
            
            // Calculate pagination
            $offset = ($page - 1) * $perPage;
            $lines = array_slice($allLines, $offset, $perPage);
            
            // Reverse order to show newest first
            $lines = array_reverse($lines);
            
            $lineCount = count($lines);
        }
        
        // Calculate pagination info
        $totalPages = $totalLines > 0 ? ceil($totalLines / $perPage) : 1;
        $hasNextPage = $page < $totalPages;
        $hasPrevPage = $page > 1;
        
        return view('v2.logs.log-file.index', compact('lines', 'lineCount', 'totalLines', 'page', 'perPage', 'totalPages', 'hasNextPage', 'hasPrevPage', 'data'));
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
}

