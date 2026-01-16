<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Listing_stock_comparison_model;
use App\Models\Variation_model;
use App\Models\Marketplace_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListingStockComparisonController extends Controller
{
    /**
     * Display a listing of stock comparisons
     */
    public function index(Request $request)
    {
        $data['title_page'] = "Listing Stock Comparisons";
        session()->put('page_title', $data['title_page']);
        
        $query = Listing_stock_comparison_model::with(['variation', 'marketplace'])
            ->orderBy('compared_at', 'desc');
        
        // Filters
        if ($request->filled('variation_sku')) {
            $query->where('variation_sku', 'like', '%' . $request->variation_sku . '%');
        }
        
        if ($request->filled('marketplace_id')) {
            $query->where('marketplace_id', $request->marketplace_id);
        }
        
        if ($request->filled('country_code')) {
            $query->where('country_code', $request->country_code);
        }
        
        if ($request->filled('status')) {
            switch($request->status) {
                case 'perfect':
                    $query->where('is_perfect', true);
                    break;
                case 'discrepancy':
                    $query->where('has_discrepancy', true);
                    break;
                case 'shortage':
                    $query->where('has_shortage', true);
                    break;
                case 'excess':
                    $query->where('has_excess', true);
                    break;
            }
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('compared_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('compared_at', '<=', $request->date_to);
        }
        
        $data['comparisons'] = $query->paginate(50);
        $data['marketplaces'] = Marketplace_model::all();
        $data['countries'] = DB::table('country')->select('code', 'title')->orderBy('code')->get();
        
        // Statistics
        $latestComparison = Listing_stock_comparison_model::orderBy('compared_at', 'desc')->first();
        $data['latest_comparison_at'] = $latestComparison ? $latestComparison->compared_at : null;
        
        $latestStats = Listing_stock_comparison_model::whereDate('compared_at', $latestComparison ? $latestComparison->compared_at->format('Y-m-d') : today())
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN is_perfect = 1 THEN 1 ELSE 0 END) as perfect_matches,
                SUM(CASE WHEN has_discrepancy = 1 THEN 1 ELSE 0 END) as discrepancies,
                SUM(CASE WHEN has_shortage = 1 THEN 1 ELSE 0 END) as shortages,
                SUM(CASE WHEN has_excess = 1 THEN 1 ELSE 0 END) as excesses
            ')
            ->first();
        
        $data['stats'] = $latestStats ?? (object)[
            'total' => 0,
            'perfect_matches' => 0,
            'discrepancies' => 0,
            'shortages' => 0,
            'excesses' => 0,
        ];
        
        return view('v2.listing-stock-comparisons.index', $data);
    }
    
    /**
     * Display the specified comparison
     */
    public function show($id)
    {
        $data['title_page'] = "Stock Comparison Details";
        session()->put('page_title', $data['title_page']);
        
        $data['comparison'] = Listing_stock_comparison_model::with(['variation', 'marketplace'])->findOrFail($id);
        
        return view('v2.listing-stock-comparisons.show', $data);
    }
    
    /**
     * Remove the specified comparison
     */
    public function destroy($id)
    {
        $comparison = Listing_stock_comparison_model::findOrFail($id);
        $comparison->delete();
        
        return redirect()->route('v2.listing-stock-comparisons.index')
            ->with('success', 'Stock comparison deleted successfully.');
    }
}
