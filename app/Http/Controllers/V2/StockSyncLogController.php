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
    
    /**
     * Delete a log entry
     */
    public function destroy($id)
    {
        try {
            $log = StockSyncLog::findOrFail($id);
            $log->delete();
            
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Log entry deleted successfully'
                ]);
            }
            
            return redirect()->route('v2.logs.stock-sync')
                ->with('success', 'Log entry deleted successfully');
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to delete log entry: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->route('v2.logs.stock-sync')
                ->with('error', 'Failed to delete log entry: ' . $e->getMessage());
        }
    }
    
    /**
     * Update log status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:running,completed,failed,cancelled'
        ]);
        
        try {
            $log = StockSyncLog::findOrFail($id);
            $oldStatus = $log->status;
            $newStatus = $request->input('status');
            
            $log->status = $newStatus;
            
            // If changing to completed or failed, set completed_at if not already set
            if (in_array($newStatus, ['completed', 'failed', 'cancelled']) && !$log->completed_at) {
                $log->completed_at = now();
                
                // Calculate duration if started_at exists
                if ($log->started_at) {
                    $log->duration_seconds = now()->diffInSeconds($log->started_at);
                }
            }
            
            $log->save();
            
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Status updated successfully',
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);
            }
            
            return redirect()->back()
                ->with('success', 'Status updated from ' . ucfirst($oldStatus) . ' to ' . ucfirst($newStatus));
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to update status: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                ->with('error', 'Failed to update status: ' . $e->getMessage());
        }
    }
}
