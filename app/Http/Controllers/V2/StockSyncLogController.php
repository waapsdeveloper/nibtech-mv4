<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\CommandRunLog;
use Illuminate\Http\Request;

/**
 * Displays last run info for periodic commands (one row per slug, overwritten each run).
 */
class StockSyncLogController extends Controller
{
    /**
     * List command run logs (one row per command slug).
     */
    public function index(Request $request)
    {
        $data['title_page'] = 'Command run logs';
        session()->put('page_title', $data['title_page']);

        $logs = CommandRunLog::orderBy('last_started_at', 'desc')->get();

        return view('v2.logs.stock-sync.index', compact('logs', 'data'));
    }

    /**
     * Show details for one command run log (by id).
     */
    public function show($id)
    {
        $log = CommandRunLog::findOrFail($id);
        $data['title_page'] = 'Command run: ' . $log->slug;
        session()->put('page_title', $data['title_page']);

        return view('v2.logs.stock-sync.show', compact('log', 'data'));
    }

    /**
     * Delete a command run log row (removes slug from list until next run).
     */
    public function destroy($id)
    {
        try {
            $log = CommandRunLog::findOrFail($id);
            $log->delete();

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Log entry deleted successfully',
                ]);
            }

            return redirect()->route('v2.logs.stock-sync')
                ->with('success', 'Log entry deleted successfully');
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to delete: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->route('v2.logs.stock-sync')
                ->with('error', 'Failed to delete: ' . $e->getMessage());
        }
    }

    /**
     * Update status (e.g. mark as completed/failed manually).
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:running,completed,failed,cancelled',
        ]);

        try {
            $log = CommandRunLog::findOrFail($id);
            $log->status = $request->input('status');
            if (in_array($log->status, ['completed', 'failed', 'cancelled']) && !$log->last_completed_at) {
                $log->last_completed_at = now();
            }
            $log->save();

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Status updated successfully',
                ]);
            }

            return redirect()->back()->with('success', 'Status updated');
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to update status: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to update status: ' . $e->getMessage());
        }
    }
}
