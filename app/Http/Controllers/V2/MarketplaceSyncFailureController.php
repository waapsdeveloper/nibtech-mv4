<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceSyncFailure;
use App\Models\Marketplace_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceSyncFailureController extends Controller
{
    /**
     * Display a listing of marketplace sync failures
     */
    public function index(Request $request)
    {
        $data['title_page'] = "Marketplace Sync Failures";
        session()->put('page_title', $data['title_page']);
        
        $query = MarketplaceSyncFailure::with(['variation', 'marketplace'])
            ->orderBy('last_attempted_at', 'desc');
        
        // Filters
        if ($request->filled('sku')) {
            $query->where('sku', 'like', '%' . $request->sku . '%');
        }
        
        if ($request->filled('marketplace_id')) {
            $query->where('marketplace_id', $request->marketplace_id);
        }
        
        if ($request->filled('is_posted')) {
            $query->where('is_posted_on_marketplace', $request->is_posted == '1');
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('last_attempted_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('last_attempted_at', '<=', $request->date_to);
        }
        
        $perPage = $request->get('per_page', 50);
        $data['failures'] = $query->paginate($perPage)->appends($request->query());
        $data['marketplaces'] = Marketplace_model::all();
        
        // Statistics
        $data['total_failures'] = MarketplaceSyncFailure::count();
        $data['today_failures'] = MarketplaceSyncFailure::whereDate('last_attempted_at', today())->count();
        $data['this_week_failures'] = MarketplaceSyncFailure::whereBetween('last_attempted_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $data['posted_failures'] = MarketplaceSyncFailure::where('is_posted_on_marketplace', true)->count();
        
        return view('v2.marketplace-sync-failures.index', $data);
    }
    
    /**
     * Remove the specified failure record
     */
    public function destroy($id)
    {
        $failure = MarketplaceSyncFailure::findOrFail($id);
        $failure->delete();
        
        return redirect()->route('v2.marketplace-sync-failures.index')
            ->with('success', 'Sync failure record deleted successfully.');
    }
    
    /**
     * Truncate all marketplace sync failures
     */
    public function truncate()
    {
        DB::table('marketplace_sync_failures')->truncate();
        
        return redirect()->route('v2.marketplace-sync-failures.index')
            ->with('success', 'All marketplace sync failure records have been truncated successfully.');
    }
}
