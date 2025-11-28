<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\VariationListResource;
use App\Services\V2\ListingDataService;
use App\Services\V2\ListingQueryService;
use App\Services\V2\ListingCalculationService;
use App\Models\Process_model;
use App\Models\Variation_model;
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
            // Increase execution time limit for this operation
            set_time_limit(120);
            
            $perPage = $request->input('per_page', 10);
            
            // Build query using service
            $query = $this->queryService->buildVariationQuery($request);
            
            $page = $request->input('page', 1);
            
            // Get total count - use distinct count if joins might cause duplicates
            $total = $query->count();
            
            // Get only IDs for lazy loading (much faster)
            // Remove any potential duplicates from joins
            $variationIds = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->pluck('id')
                ->unique()
                ->values()
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

            // Load all variations with basic data in one batch query (much faster than individual loads)
            $variations = Variation_model::with([
                'product',
                'storage_id',
                'color_id',
                'grade_id',
                'listings' => function($q) {
                    $q->select('id', 'variation_id', 'country', 'marketplace_id', 'buybox', 'reference_uuid_2');
                },
                'listings.country_id' => function($q) {
                    $q->select('id', 'code', 'market_url', 'market_code');
                },
                'available_stocks' => function($q) {
                    $q->select('id', 'variation_id', 'status');
                },
                'pending_orders' => function($q) {
                    $q->select('id', 'variation_id');
                },
            ])
            ->whereIn('id', $variationIds)
            ->get()
            ->keyBy('id');

            // Preserve order from variationIds array
            $orderedVariations = collect($variationIds)->map(function($id) use ($variations) {
                return $variations->get($id);
            })->filter()->values();

            // Calculate stats for all variations in batch
            $variationData = $orderedVariations->map(function($variation) use ($exchangeData) {
                // Calculate stats using service
                $stats = $this->calculationService->calculateVariationStats($variation);
                
                // Calculate pricing info
                $pricingInfo = $this->calculationService->calculatePricingInfo(
                    $variation->listings ?? collect(),
                    $exchangeData['exchange_rates'],
                    $exchangeData['eur_gbp']
                );
                
                // Calculate average cost
                $averageCost = $this->calculationService->calculateAverageCost(
                    $variation->available_stocks ?? collect()
                );
                
                // Calculate total orders count
                $totalOrdersCount = $this->calculationService->calculateTotalOrdersCount($variation->id);
                
                // Get buybox listings
                $buyboxListings = ($variation->listings ?? collect())
                    ->where('buybox', 1)
                    ->map(function($listing) {
                        $countryId = is_object($listing->country_id) ? $listing->country_id->id : ($listing->country_id ?? null);
                        return [
                            'id' => $listing->id,
                            'country_id' => $countryId,
                            'reference_uuid_2' => $listing->reference_uuid_2 ?? '',
                        ];
                    })
                    ->values()
                    ->toArray();
                
                return [
                    'id' => $variation->id,
                    'variation_data' => $variation->toArray(),
                    'calculated_stats' => [
                        'stats' => $stats,
                        'pricing_info' => $pricingInfo,
                        'average_cost' => $averageCost,
                        'total_orders_count' => $totalOrdersCount,
                        'buybox_listings' => $buyboxListings,
                    ],
                ];
            })->toArray();

            // Get reference data
            $referenceData = $this->dataService->getReferenceData();
            $exchangeData = $this->calculationService->getExchangeRateData();

            // Mount Livewire component
            $component = \Livewire\Livewire::mount('v2.listing.listing-items', [
                'variationData' => $variationData, // Pass pre-loaded variation data
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
