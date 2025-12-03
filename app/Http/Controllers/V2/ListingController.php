<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\VariationListResource;
use App\Services\V2\ListingDataService;
use App\Services\V2\ListingQueryService;
use App\Services\V2\ListingCalculationService;
use App\Services\V2\ListingCacheService;
use App\Models\Process_model;
use App\Models\Variation_model;
use App\Models\Color_model;
use App\Models\Country_model;
use App\Models\Currency_model;
use App\Models\ExchangeRate;
use App\Models\Grade_model;
use App\Models\Marketplace_model;
use App\Models\Storage_model;
use App\Http\Controllers\BackMarketAPIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ListingController extends Controller
{
    protected ListingQueryService $queryService;
    protected ListingDataService $dataService;
    protected ListingCalculationService $calculationService;
    protected ListingCacheService $cacheService;

    public function __construct(
        ListingQueryService $queryService,
        ListingDataService $dataService,
        ListingCalculationService $calculationService,
        ListingCacheService $cacheService
    ) {
        $this->queryService = $queryService;
        $this->dataService = $dataService;
        $this->calculationService = $calculationService;
        $this->cacheService = $cacheService;
    }

    /**
     * Display the V2 listing page
     */
    public function index()
    {
        $data['title_page'] = "Listings V2";
        session()->put('page_title', $data['title_page']);

        if(request('process_id') != null){
            $process = Process_model::where('id', request('process_id'))->where('process_type_id', 22)->first();
            if($process != null){
                $data['process_id'] = $process->id;
                $data['title_page'] = "Listings V2 - Topup - ".$process->reference_id;
            }else{
                $data['process_id'] = null;
            }
        }else{
            $data['process_id'] = null;
        }
        session()->put('page_title', $data['title_page']);
        $data['bm'] = new BackMarketAPIController();
        $data['storages'] = session('dropdown_data')['storages'];
        $data['colors'] = session('dropdown_data')['colors'];
        $data['grades'] = Grade_model::where('id',"<",6)->pluck('name','id')->toArray();
        $data['eur_gbp'] = ExchangeRate::where('target_currency','GBP')->first()->rate;
        $data['exchange_rates'] = ExchangeRate::pluck('rate','target_currency');
        $data['currencies'] = Currency_model::pluck('code','id');
        $data['currency_sign'] = Currency_model::pluck('sign','id');
        $countries = Country_model::all();
        foreach($countries as $country){
            $data['countries'][$country->id] = $country;
        }
        $marketplaces = Marketplace_model::all();
        foreach($marketplaces as $marketplace){
            $data['marketplaces'][$marketplace->id] = $marketplace;
        }

        return view('v2.listing.listing')->with($data);
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
            
            // Get paginated variations with ALL relationships eager loaded (like original)
            // This is faster than lazy loading because everything is loaded in one query
            $variations = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            $lastPage = ceil($total / $perPage);

            // Get exchange rate data for calculations
            $exchangeData = $this->calculationService->getExchangeRateData();
            
            // Pre-calculate stats for all variations (batch processing is faster)
            $variationData = $variations->map(function($variation) use ($exchangeData) {
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
                
                // Calculate marketplace summaries
                $marketplaceSummaries = $this->calculationService->calculateMarketplaceSummaries(
                    $variation->id,
                    $variation->listings ?? collect()
                );
                
                // Calculate sales data (preload it so it displays immediately)
                $salesData = $this->calculationService->calculateSalesData($variation->id);
                
                return [
                    'id' => $variation->id,
                    'variation_data' => $variation->toArray(),
                    'calculated_stats' => [
                        'stats' => $stats,
                        'pricing_info' => $pricingInfo,
                        'average_cost' => $averageCost,
                        'total_orders_count' => $totalOrdersCount,
                        'buybox_listings' => $buyboxListings,
                        'marketplace_summaries' => $marketplaceSummaries,
                        'sales_data' => $salesData, // Preloaded sales data
                    ],
                ];
            })->toArray();

            // Cache all variation data for quick access when rendering items one at a time
            $pageKey = $this->cacheService->generatePageKey($request->all());
            $this->cacheService->cacheVariationData($variationData, $pageKey);

            return response()->json([
                'data' => $variationData,
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
            $singleId = $request->input('variation_id', null);

            // Support both single ID and array of IDs
            if ($singleId) {
                $variationIds = [$singleId];
            }

            if (empty($variationIds)) {
                return response()->json([
                    'html' => '<p class="text-center text-muted">No variations found.</p>'
                ]);
            }

            // Try to get cached data first (much faster - no DB queries)
            $cachedData = $this->cacheService->getCachedVariations($variationIds);
            $variationData = [];
            $missingIds = [];

            // Check which items are cached and which need to be loaded
            foreach ($variationIds as $id) {
                $cached = collect($cachedData)->firstWhere('id', $id);
                if ($cached) {
                    $variationData[] = $cached;
                } else {
                    $missingIds[] = $id;
                }
            }

            // If some items are missing from cache, load them from DB
            if (!empty($missingIds)) {
                // Get exchange rate data (needed for calculations)
                $exchangeData = $this->calculationService->getExchangeRateData();

                // Load missing variations from DB
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
                ->whereIn('id', $missingIds)
                ->get()
                ->keyBy('id');

                // Calculate stats for missing variations
                $missingData = collect($missingIds)->map(function($id) use ($variations, $exchangeData) {
                    $variation = $variations->get($id);
                    if (!$variation) {
                        return null;
                    }

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
                    
                    // Calculate marketplace summaries
                    $marketplaceSummaries = $this->calculationService->calculateMarketplaceSummaries(
                        $variation->id,
                        $variation->listings ?? collect()
                    );
                    
                    // Calculate sales data (preload it so it displays immediately)
                    $salesData = $this->calculationService->calculateSalesData($variation->id);
                    
                    return [
                        'id' => $variation->id,
                        'variation_data' => $variation->toArray(),
                        'calculated_stats' => [
                            'stats' => $stats,
                            'pricing_info' => $pricingInfo,
                            'average_cost' => $averageCost,
                            'total_orders_count' => $totalOrdersCount,
                            'buybox_listings' => $buyboxListings,
                            'marketplace_summaries' => $marketplaceSummaries,
                            'sales_data' => $salesData, // Preloaded sales data
                        ],
                    ];
                })->filter()->values()->toArray();

                // Cache the newly loaded data
                if (!empty($missingData)) {
                    $pageKey = $this->cacheService->generatePageKey($request->all());
                    $this->cacheService->cacheVariationData($missingData, $pageKey);
                }

                // Merge cached and newly loaded data
                $variationData = array_merge($variationData, $missingData);
            }

            // Sort variationData to match the order of variationIds
            $sortedData = [];
            foreach ($variationIds as $id) {
                $found = collect($variationData)->firstWhere('id', $id);
                if ($found) {
                    $sortedData[] = $found;
                }
            }
            $variationData = $sortedData;

            if (empty($variationData)) {
                return response()->json([
                    'html' => '<p class="text-center text-muted">No variations found.</p>'
                ]);
            }

            // Get reference data
            $referenceData = $this->dataService->getReferenceData();
            $exchangeData = $this->calculationService->getExchangeRateData();
            
            // Render ONE item at a time (even if multiple IDs provided, render separately)
            // This allows frontend to display items progressively
            $htmlParts = [];
            
            foreach ($variationData as $index => $variationItem) {
                $component = \Livewire\Livewire::mount('v2.listing.listing-item', [
                    'variationId' => $variationItem['id'],
                    'rowNumber' => $index + 1,
                    'preloadedVariationData' => $variationItem,
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
                
                $htmlParts[] = $component->html();
            }

            // Return HTML for all items (but they were rendered one at a time using cached data)
            return response()->json(['html' => implode('', $htmlParts)]);
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
