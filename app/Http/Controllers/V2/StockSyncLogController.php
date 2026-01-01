<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\StockSyncLog;
use App\Models\Marketplace_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockSyncLogController extends Controller
{
    /**
     * Display stock sync logs
     */
    public function index(Request $request)
    {
        $data['title_page'] = "Stock Sync Logs";
        session()->put('page_title', $data['title_page']);
        
        // Get filter parameters
        $marketplaceId = $request->get('marketplace_id');
        $status = $request->get('status');
        $perPage = $request->get('per_page', 20);
        
        // Build query
        $query = StockSyncLog::with(['marketplace', 'admin'])
            ->orderBy('started_at', 'desc');
        
        if ($marketplaceId) {
            $query->where('marketplace_id', $marketplaceId);
        }
        
        if ($status) {
            $query->where('status', $status);
        }
        
        // Get logs
        $logs = $query->paginate($perPage);
        
        // Get marketplaces for filter
        $marketplaces = Marketplace_model::where('status', 1)
            ->orderBy('name')
            ->get();
        
        // Get statistics
        $stats = [
            'total' => StockSyncLog::count(),
            'running' => StockSyncLog::where('status', 'running')->count(),
            'completed' => StockSyncLog::where('status', 'completed')->count(),
            'failed' => StockSyncLog::where('status', 'failed')->count(),
        ];
        
        return view('v2.logs.stock-sync.index', compact('logs', 'marketplaces', 'stats', 'data'));
    }
    
    /**
     * Show details of a specific log entry
     */
    public function show($id)
    {
        $log = StockSyncLog::with(['marketplace', 'admin'])->findOrFail($id);
        
        $data['title_page'] = "Stock Sync Log Details";
        session()->put('page_title', $data['title_page']);
        
        return view('v2.logs.stock-sync.show', compact('log', 'data'));
    }
}
