<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\VariationListResource;
use App\Services\V2\ListingDataService;
use App\Services\V2\ListingQueryService;
use App\Services\V2\ListingCalculationService;
use App\Models\Process_model;
use App\Http\Controllers\BackMarketAPIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ListingController extends Controller
{
    protected ListingQueryService $queryService;
    protected ListingDataService $dataService;
    protected ListingCalculationService $calculationService;

    public function __construct(
        ListingQueryService $queryService,
        ListingDataService $dataService,
        ListingCalculationService $calculationService
    ) {
        $this->queryService = $queryService;
        $this->dataService = $dataService;
        $this->calculationService = $calculationService;
    }

    /**
     * Display the V2 listing page
     */
    public function index()
    {
        $data['title_page'] = "Listings V2";
        session()->put('page_title', $data['title_page']);

        // Handle process_id if provided
        if (request('process_id') != null) {
            $process = Process_model::where('id', request('process_id'))
                ->where('process_type_id', 22)
                ->first();
            
            if ($process != null) {
                $data['process_id'] = $process->id;
                $data['title_page'] = "Listings V2 - Topup - " . $process->reference_id;
            } else {
                $data['process_id'] = null;
            }
        } else {
            $data['process_id'] = null;
        }
        
        session()->put('page_title', $data['title_page']);

        // Get reference data using service
        $referenceData = $this->dataService->getReferenceData();
        
        // Get exchange rate data using calculation service
        $exchangeData = $this->calculationService->getExchangeRateData();

        // Merge all data
        $data = array_merge($data, $referenceData, $exchangeData);
        $data['bm'] = new BackMarketAPIController();

        return view('v2.listings')->with($data);
    }

    /**
     * Get variations for listing (returns only IDs for lazy loading)
     */
    public function getVariations(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            
            // Build query using service
            $query = $this->queryService->buildVariationQuery($request);
            
            $page = $request->input('page', 1);
            
            // Get total count
            $total = $query->count();
            
            // Get only IDs for lazy loading (much faster)
            $variationIds = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->pluck('id')
                ->toArray();

            $lastPage = ceil($total / $perPage);

            return response()->json([
                'data' => array_map(fn($id) => ['id' => $id], $variationIds),
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($page * $perPage, $total),
                'prev_page_url' => $page > 1 ? $request->fullUrlWithQuery(['page' => $page - 1]) : null,
                'next_page_url' => $page < $lastPage ? $request->fullUrlWithQuery(['page' => $page + 1]) : null,
                'first_page_url' => $request->fullUrlWithQuery(['page' => 1]),
                'last_page_url' => $request->fullUrlWithQuery(['page' => $lastPage]),
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching variations: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error fetching variations',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Render listing items using Livewire components
     */
    public function renderListingItems(Request $request)
    {
        try {
            $variationIds = $request->input('variation_ids', []);

            if (empty($variationIds)) {
                return response()->json([
                    'html' => '<p class="text-center text-muted">No variations found.</p>'
                ]);
            }

            // Get reference data
            $referenceData = $this->dataService->getReferenceData();
            $exchangeData = $this->calculationService->getExchangeRateData();

            // Mount Livewire component
            $component = \Livewire\Livewire::mount('v2.listing.listing-items', [
                'variationIds' => $variationIds,
                'storages' => $referenceData['storages'],
                'colors' => $referenceData['colors'],
                'grades' => $referenceData['grades'],
                'exchangeRates' => $exchangeData['exchange_rates'],
                'eurGbp' => $exchangeData['eur_gbp'],
                'currencies' => $referenceData['currencies'],
                'currencySign' => $referenceData['currency_sign'],
                'countries' => $referenceData['countries'],
                'marketplaces' => $referenceData['marketplaces'],
                'processId' => $request->input('process_id'),
            ]);

            $html = $component->html();

            return response()->json(['html' => $html]);
        } catch (\Exception $e) {
            Log::error("Error rendering listing items: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'html' => '<p class="text-center text-danger">Error rendering components: ' . 
                    htmlspecialchars($e->getMessage()) . ' Please refresh the page.</p>',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
