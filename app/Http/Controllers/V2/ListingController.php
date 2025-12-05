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
use App\Models\Products_model;
use App\Models\Listed_stock_verification_model;
use App\Models\Admin_model;
use App\Http\Controllers\BackMarketAPIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
    public function index(Request $request)
    {
        \Log::info('V2 ListingController index called');
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

        // Get variations using the same logic as original ListingController
        $perPage = $request->input('per_page', 10);
        $variations = $this->buildVariationQuery($request)->paginate($perPage)->appends($request->except('page'));
        
        // Calculate sales data and withoutBuybox HTML for each variation
        foreach($variations as $variation) {
            $variation->sales_data = $this->calculationService->calculateSalesData($variation->id);
            
            // Build withoutBuybox HTML (listings without buybox from marketplace_id == 1)
            $withoutBuybox = '';
            $listingsWithoutBuybox = $variation->listings->where('buybox', '!=', 1)->where('marketplace_id', 1);
            
            foreach($listingsWithoutBuybox as $listing) {
                $country = $listing->country_id ?? null;
                if ($country && is_object($country)) {
                    $countryCode = strtolower($country->code ?? '');
                    $marketUrl = $country->market_url ?? '';
                    $marketCode = $country->market_code ?? '';
                    $referenceUuid2 = $listing->reference_uuid_2 ?? '';
                    
                    if ($countryCode && $marketUrl && $marketCode && $referenceUuid2) {
                        $withoutBuybox .= '<a href="https://www.backmarket.' . $marketUrl . '/' . $marketCode . '/p/gb/' . $referenceUuid2 . '" target="_blank" class="btn btn-link text-danger border border-danger p-1 m-1">';
                        $withoutBuybox .= '<img src="' . asset('assets/img/flags/' . $countryCode . '.svg') . '" height="10">';
                        $withoutBuybox .= strtoupper($country->code ?? '') . '</a>';
                    }
                }
            }
            
            $variation->withoutBuybox = $withoutBuybox;
        }
        
        $data['variations'] = $variations;

        return view('v2.listing.listing')->with($data);
    }

    /**
     * Build variation query using the same logic as original ListingController
     */
    private function buildVariationQuery(Request $request)
    {
        list($productSearch, $storageSearch) = $this->resolveProductAndStorageSearch($request->input('product_name'));

        $query = Variation_model::with([
            'listings',
            'listings.country_id',
            'listings.currency',
            'listings.marketplace',
            'product',
            'available_stocks',
            'pending_orders',
            'pending_bm_orders',
            'storage_id',
            'color_id',
            'grade_id',
        ]);

        $query->when($request->filled('reference_id'), function ($q) use ($request) {
            return $q->where('reference_id', $request->input('reference_id'));
        })
        ->when($request->filled('variation_id'), function ($q) use ($request) {
            return $q->where('id', $request->input('variation_id'));
        })
        ->when($request->filled('category'), function ($q) use ($request) {
            return $q->whereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('category', $request->input('category'));
            });
        })
        ->when($request->filled('brand'), function ($q) use ($request) {
            return $q->whereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('brand', $request->input('brand'));
            });
        })
        ->when($request->filled('marketplace'), function ($q) use ($request) {
            return $q->whereHas('listings', function ($q) {
                $q->where('marketplace_id', request('marketplace'));
            });
        })
        ->when($request->filled('product'), function ($q) use ($request) {
            return $q->where('product_id', $request->input('product'));
        })
        ->when($productSearch->count() > 0, function ($q) use ($productSearch) {
            return $q->whereIn('product_id', $productSearch);
        })
        ->when($storageSearch->count() > 0, function ($q) use ($storageSearch) {
            return $q->whereIn('storage', $storageSearch);
        })
        ->when($request->filled('sku'), function ($q) use ($request) {
            return $q->where('sku', $request->input('sku'));
        })
        ->when($request->filled('color'), function ($q) use ($request) {
            return $q->where('color', $request->input('color'));
        })
        ->when($request->filled('storage'), function ($q) use ($request) {
            return $q->where('storage', $request->input('storage'));
        })
        ->when($request->filled('grade'), function ($q) use ($request) {
            return $q->whereIn('grade', (array) $request->input('grade'));
        })
        ->when($request->filled('topup'), function ($q) use ($request) {
            return $q->whereHas('listed_stock_verifications', function ($verificationQuery) use ($request) {
                $verificationQuery->where('process_id', $request->input('topup'));
            });
        })
        ->when($request->filled('listed_stock'), function ($q) use ($request) {
            if ((int) $request->input('listed_stock') === 1) {
                return $q->where('listed_stock', '>', 0);
            }

            if ((int) $request->input('listed_stock') === 2) {
                return $q->where('listed_stock', '<=', 0);
            }
        })
        ->when($request->filled('available_stock'), function ($q) use ($request) {
            if ((int) $request->input('available_stock') === 1) {
                return $q->whereHas('available_stocks')
                    ->withCount(['available_stocks', 'pending_orders'])
                    ->havingRaw('(available_stocks_count - pending_orders_count) > 0');
            }

            if ((int) $request->input('available_stock') === 2) {
                return $q->whereDoesntHave('available_stocks');
            }
        });

        $state = $request->input('state');
        if ($state === null || $state === '') {
            $query->whereIn('state', [2, 3]);
        } elseif ((int) $state !== 10) {
            $query->where('state', $state);
        }

        $query->when($request->filled('sale_40'), function ($q) {
            return $q->withCount('today_orders as today_orders_count')
                ->having('today_orders_count', '<', DB::raw('listed_stock * 0.05'));
        })
        ->when((int) $request->input('handler_status') === 2, function ($q) use ($request) {
            return $q->whereHas('listings', function ($listingQuery) use ($request) {
                $listingQuery->where('handler_status', $request->input('handler_status'))
                    ->whereIn('country', [73, 199]);
            });
        })
        ->when(in_array((int) $request->input('handler_status'), [1, 3], true), function ($q) use ($request) {
            return $q->whereHas('listings', function ($listingQuery) use ($request) {
                $listingQuery->where('handler_status', $request->input('handler_status'));
            });
        })
        ->when($request->filled('process_id') && $request->input('special') === 'show_only', function ($q) use ($request) {
            return $q->whereHas('process_stocks', function ($processStockQuery) use ($request) {
                $processStockQuery->where('process_id', $request->input('process_id'));
            });
        })
        ->whereNotNull('sku')
        ->when($request->input('sort') == 4, function ($q) {
            return $q->join('products', 'variation.product_id', '=', 'products.id')
                ->orderBy('products.model', 'asc')
                ->orderBy('variation.storage', 'asc')
                ->orderBy('variation.color', 'asc')
                ->orderBy('variation.grade', 'asc')
                ->select('variation.*');
        })
        ->when($request->input('sort') == 3, function ($q) {
            return $q->join('products', 'variation.product_id', '=', 'products.id')
                ->orderBy('products.model', 'desc')
                ->orderBy('variation.storage', 'asc')
                ->orderBy('variation.color', 'asc')
                ->orderBy('variation.grade', 'asc')
                ->select('variation.*');
        })
        ->when($request->input('sort') == 2, function ($q) {
            return $q->orderBy('listed_stock', 'asc')
                ->orderBy('variation.storage', 'asc')
                ->orderBy('variation.color', 'asc')
                ->orderBy('variation.grade', 'asc');
        })
        ->when($request->input('sort') == 1 || $request->input('sort') === null, function ($q) {
            return $q->orderBy('listed_stock', 'desc')
                ->orderBy('variation.storage', 'asc')
                ->orderBy('variation.color', 'asc')
                ->orderBy('variation.grade', 'asc');
        });

        return $query;
    }

    /**
     * Resolve product and storage search from product name
     */
    private function resolveProductAndStorageSearch(?string $productName): array
    {
        if (empty($productName)) {
            return [collect(), collect()];
        }

        $searchTerm = trim($productName);
        $parts = explode(' ', $searchTerm);
        $lastSegment = end($parts);

        $storageSearch = Storage_model::where('name', 'like', $lastSegment . '%')->pluck('id');

        if ($storageSearch->count() > 0) {
            array_pop($parts);
            $searchTerm = trim(implode(' ', $parts));
        } else {
            $storageSearch = collect();
        }

        $productSearch = Products_model::where('model', 'like', '%' . $searchTerm . '%')->pluck('id');

        return [$productSearch, $storageSearch];
    }

    /**
     * Get variations for listing (returns only IDs for lazy loading)
     */
    public function getVariations(Request $request)
    {
        \Log::info('V2 ListingController getVariations called', ['request' => $request->all()]);
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

            // TEMPORARILY DISABLED: Cache all variation data for quick access when rendering items one at a time
            // $pageKey = $this->cacheService->generatePageKey($request->all());
            // $this->cacheService->cacheVariationData($variationData, $pageKey);

            // Generate links array for pagination (matching Laravel paginator format)
            $links = [];
            
            // Previous link
            $links[] = [
                'url' => $page > 1 ? $request->fullUrlWithQuery(['page' => $page - 1]) : null,
                'label' => '&laquo; Previous',
                'active' => false,
            ];
            
            // Page number links (show up to 7 pages around current page)
            $startPage = max(1, $page - 3);
            $endPage = min($lastPage, $page + 3);
            
            if ($startPage > 1) {
                $links[] = [
                    'url' => $request->fullUrlWithQuery(['page' => 1]),
                    'label' => '1',
                    'active' => false,
                ];
                if ($startPage > 2) {
                    $links[] = [
                        'url' => null,
                        'label' => '...',
                        'active' => false,
                    ];
                }
            }
            
            for ($i = $startPage; $i <= $endPage; $i++) {
                $links[] = [
                    'url' => $request->fullUrlWithQuery(['page' => $i]),
                    'label' => (string)$i,
                    'active' => $i == $page,
                ];
            }
            
            if ($endPage < $lastPage) {
                if ($endPage < $lastPage - 1) {
                    $links[] = [
                        'url' => null,
                        'label' => '...',
                        'active' => false,
                    ];
                }
                $links[] = [
                    'url' => $request->fullUrlWithQuery(['page' => $lastPage]),
                    'label' => (string)$lastPage,
                    'active' => false,
                ];
            }
            
            // Next link
            $links[] = [
                'url' => $page < $lastPage ? $request->fullUrlWithQuery(['page' => $page + 1]) : null,
                'label' => 'Next &raquo;',
                'active' => false,
            ];

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
                'links' => $links, // Add links array for pagination
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
        \Log::info('V2 ListingController renderListingItems called', ['variation_ids' => $request->input('variation_ids', [])]);
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

            // TEMPORARILY DISABLED: Try to get cached data first (much faster - no DB queries)
            // $cachedData = $this->cacheService->getCachedVariations($variationIds);
            $variationData = [];
            $missingIds = $variationIds; // Load all from DB (cache disabled for testing)

            // TEMPORARILY DISABLED: Check which items are cached and which need to be loaded
            // foreach ($variationIds as $id) {
            //     $cached = collect($cachedData)->firstWhere('id', $id);
            //     if ($cached) {
            //         $variationData[] = $cached;
            //     } else {
            //         $missingIds[] = $id;
            //     }
            // }

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

                // TEMPORARILY DISABLED: Cache the newly loaded data
                // if (!empty($missingData)) {
                //     $pageKey = $this->cacheService->generatePageKey($request->all());
                //     $this->cacheService->cacheVariationData($missingData, $pageKey);
                // }

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
                    'preloadedVariationData' => null, // TEMPORARILY DISABLED: Set to null to force fresh calculation
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

    /**
     * Clear listing cache (preloaded variation data)
     */
    public function clearCache()
    {
        try {
            $this->cacheService->clearAllCaches();
            return response()->json([
                'success' => true,
                'message' => 'Listing cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Error clearing listing cache: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error clearing cache: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get variation history (duplicated from original ListingController for consistency)
     */
    public function get_variation_history($id)
    {
        $listed_stock_verifications = Listed_stock_verification_model::where('variation_id', $id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $listed_stock_verifications->each(function($verification) {
            $verification->process_ref = Process_model::find($verification->process_id)->reference_id ?? null;
            $verification->admin = Admin_model::find($verification->admin_id)->first_name ?? null;
        });

        return response()->json(['listed_stock_verifications' => $listed_stock_verifications]);
    }
}
