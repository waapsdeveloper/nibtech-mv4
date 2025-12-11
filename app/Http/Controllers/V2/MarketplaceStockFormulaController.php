<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Variation_model;
use App\Models\Marketplace_model;
use App\Models\MarketplaceStockModel;
use App\Services\Marketplace\StockDistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MarketplaceStockFormulaController extends Controller
{
    protected $stockDistributionService;

    public function __construct(StockDistributionService $stockDistributionService)
    {
        $this->stockDistributionService = $stockDistributionService;
    }

    /**
     * Display stock formula management page
     */
    public function index(Request $request)
    {
        $user_id = session('user_id');
        if ($user_id == NULL) {
            return redirect()->route('login');
        }

        $data['title_page'] = "Stock Formula Management";
        session()->put('page_title', $data['title_page']);

        // Load all marketplaces
        $data['marketplaces'] = Marketplace_model::orderBy('name', 'ASC')->get();

        // Load reference data for variation display (colors, storages, grades)
        $data['colors'] = session('dropdown_data')['colors'] ?? [];
        $data['storages'] = session('dropdown_data')['storages'] ?? [];
        $data['grades'] = session('dropdown_data')['grades'] ?? [];

        // Get variation if provided
        $variationId = $request->input('variation_id');
        $data['selectedVariation'] = null;
        $data['marketplaceStocks'] = [];
        $data['searchTerm'] = $request->input('search', '');

        if ($variationId) {
            $data['selectedVariation'] = Variation_model::with(['product', 'color_id', 'storage_id', 'grade_id', 'available_stocks', 'pending_orders', 'pending_bm_orders'])
                ->find($variationId);

            if ($data['selectedVariation']) {
                $data['marketplaceStocks'] = $this->loadMarketplaceStocks($variationId, $data['marketplaces']);
            }
        }

        return view('v2.marketplace.stock-formula')->with($data);
    }

    /**
     * Search variations via AJAX
     */
    public function searchVariations(Request $request)
    {
        $searchTerm = trim($request->input('search', ''));

        if (strlen($searchTerm) < 2) {
            return response()->json(['variations' => []]);
        }

        $variations = Variation_model::with(['product', 'color_id', 'storage_id', 'grade_id'])
            ->where(function ($query) use ($searchTerm) {
                $query->where('sku', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('product', function ($q) use ($searchTerm) {
                        $q->where('model', 'like', '%' . $searchTerm . '%');
                    });
            })
            ->limit(20)
            ->get()
            ->map(function ($variation) {
                return [
                    'id' => $variation->id,
                    'sku' => $variation->sku,
                    'model' => $variation->product->model ?? 'N/A',
                    'storage' => $variation->storage_id->name ?? 'N/A',
                    'color' => $variation->color_id->name ?? 'N/A',
                    'grade' => $variation->grade_id->name ?? 'N/A',
                ];
            });

        return response()->json(['variations' => $variations]);
    }

    /**
     * Get marketplace stocks for a variation
     */
    public function getMarketplaceStocks(Request $request, $variationId)
    {
        $marketplaces = Marketplace_model::orderBy('name', 'ASC')->get();
        $marketplaceStocks = $this->loadMarketplaceStocks($variationId, $marketplaces);

        // Convert to array format for JSON response
        $stocksArray = [];
        foreach ($marketplaceStocks as $id => $stock) {
            $stocksArray[$id] = $stock;
        }

        return response()->json(['marketplaceStocks' => $stocksArray]);
    }

    /**
     * Save formula for a marketplace
     */
    public function saveFormula(Request $request, $variationId, $marketplaceId)
    {
        // Prevent saving formula for first marketplace (ID = 1)
        if ($marketplaceId == 1) {
            return response()->json([
                'success' => false,
                'message' => 'Formula cannot be changed for the first marketplace. Remaining stock is automatically allocated here.'
            ], 403);
        }
        
        $request->validate([
            'value' => 'required|numeric|min:0',
            'type' => 'required|in:percentage,fixed',
            'apply_to' => 'required|in:pushed,total',
        ]);

        $formula = [
            'value' => (float)$request->input('value'),
            'type' => $request->input('type'),
            'apply_to' => $request->input('apply_to'),
        ];

        // Get or create marketplace stock record
        $marketplaceStock = MarketplaceStockModel::firstOrCreate(
            [
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
            ],
            [
                'listed_stock' => 0,
                'admin_id' => session('user_id'),
            ]
        );

        // Update formula
        $marketplaceStock->formula = $formula;
        $marketplaceStock->admin_id = session('user_id');
        $marketplaceStock->save();

        return response()->json([
            'success' => true,
            'message' => 'Formula saved successfully',
            'formula' => $formula
        ]);
    }

    /**
     * Delete formula for a marketplace
     */
    public function deleteFormula($variationId, $marketplaceId)
    {
        // Prevent deleting formula for first marketplace (ID = 1)
        if ($marketplaceId == 1) {
            return response()->json([
                'success' => false,
                'message' => 'Formula cannot be deleted for the first marketplace. Remaining stock is automatically allocated here.'
            ], 403);
        }
        
        $marketplaceStock = MarketplaceStockModel::where('variation_id', $variationId)
            ->where('marketplace_id', $marketplaceId)
            ->first();

        if ($marketplaceStock) {
            $marketplaceStock->formula = null;
            $marketplaceStock->save();

            return response()->json([
                'success' => true,
                'message' => 'Formula deleted successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Formula not found'
        ], 404);
    }

    /**
     * Reset stock to exact value for a marketplace
     */
    public function resetStock(Request $request, $variationId, $marketplaceId)
    {
        $request->validate([
            'stock' => 'required|integer|min:0',
        ]);

        $stockValue = (int)$request->input('stock');

        // Get or create marketplace stock record
        $marketplaceStock = MarketplaceStockModel::firstOrCreate(
            [
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
            ],
            [
                'listed_stock' => 0,
                'admin_id' => session('user_id'),
            ]
        );

        // Store old value in reserve
        $marketplaceStock->reserve_old_value = $marketplaceStock->listed_stock;
        $marketplaceStock->listed_stock = $stockValue;
        $marketplaceStock->reserve_new_value = $stockValue;
        $marketplaceStock->admin_id = session('user_id');
        $marketplaceStock->save();

        // Recalculate and update the variation's total stock based on sum of all marketplace stocks
        $variation = Variation_model::find($variationId);
        if ($variation) {
            // Recalculate total from all marketplace stocks
            $totalStock = MarketplaceStockModel::where('variation_id', $variationId)
                ->sum('listed_stock');
            $variation->listed_stock = $totalStock;
            $variation->save();
        } else {
            $totalStock = 0;
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock reset successfully',
            'stock' => $stockValue,
            'total_stock' => $totalStock
        ]);
    }

    /**
     * Load marketplace stocks for a variation
     */
    private function loadMarketplaceStocks($variationId, $marketplaces)
    {
        $stocks = MarketplaceStockModel::where('variation_id', $variationId)
            ->with('marketplace')
            ->get();

        $marketplaceStocks = [];

        foreach ($marketplaces as $marketplace) {
            $stock = $stocks->firstWhere('marketplace_id', $marketplace->id);

            $marketplaceStocks[$marketplace->id] = [
                'marketplace_id' => $marketplace->id,
                'marketplace_name' => $marketplace->name,
                'listed_stock' => $stock ? $stock->listed_stock : 0,
                'formula' => $stock && $stock->formula ? $stock->formula : null,
                'has_formula' => $stock && $stock->formula ? true : false,
                'stock_id' => $stock ? $stock->id : null,
            ];
        }

        return $marketplaceStocks;
    }
}
