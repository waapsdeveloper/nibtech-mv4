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
use App\Models\Listing_model;
use App\Models\Order_item_model;
use App\Models\Category_model;
use App\Models\Brand_model;
use App\Models\MarketplaceStockModel;
use App\Http\Controllers\BackMarketAPIController;
use App\Events\VariationStockUpdated;
use App\Services\Marketplace\StockDistributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ListingController extends Controller
{
    protected ListingQueryService $queryService;
    protected ListingDataService $dataService;
    protected ListingCalculationService $calculationService;
    protected ListingCacheService $cacheService;
    protected StockDistributionService $stockDistributionService;

    public function __construct(
        ListingQueryService $queryService,
        ListingDataService $dataService,
        ListingCalculationService $calculationService,
        ListingCacheService $cacheService,
        StockDistributionService $stockDistributionService
    ) {
        $this->queryService = $queryService;
        $this->dataService = $dataService;
        $this->calculationService = $calculationService;
        $this->cacheService = $cacheService;
        $this->stockDistributionService = $stockDistributionService;
    }

    /**
     * Display the V2 listing page
     */
    public function index(Request $request)
    {
        // Log::info('V2 ListingController index called');
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

        // Filter data
        $data['categories'] = Category_model::all();
        $data['brands'] = Brand_model::all();
        $data['products'] = Products_model::all();
        $data['marketplaces_dropdown'] = Marketplace_model::pluck('name','id')->toArray();

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

        // Calculate global marketplace listing counts across all variations
        $globalMarketplaceCounts = [];
        foreach($data['marketplaces'] as $marketplaceId => $marketplace) {
            $globalMarketplaceCounts[$marketplaceId] = [
                'name' => $marketplace->name ?? 'Marketplace ' . $marketplaceId,
                'total_count' => 0
            ];
        }

        // Calculate sales data, withoutBuybox HTML, and marketplace data for each variation
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

            // Calculate marketplace data for each variation
            // Group listings by marketplace and calculate counts
            // Use the same logic as original - filter listings by marketplace_id
            $marketplaceData = [];
            foreach($data['marketplaces'] as $marketplaceId => $marketplace) {
                // Ensure marketplaceId is integer for consistent key matching
                $marketplaceIdInt = (int)$marketplaceId;

                // Filter listings exactly as original does - using strict comparison
                $marketplaceListings = $variation->listings->filter(function($listing) use ($marketplaceIdInt) {
                    // Get marketplace_id from listing
                    $listingMarketplaceId = $listing->marketplace_id;

                    // Only include listings with non-null marketplace_id that exactly matches
                    if ($listingMarketplaceId === null) {
                        return false;
                    }

                    // Use strict integer comparison (same as original JavaScript: listing.marketplace_id == marketplaceId)
                    return (int)$listingMarketplaceId === $marketplaceIdInt;
                })->values(); // Reset keys to ensure proper collection

                $listingCount = $marketplaceListings->count();

                // Log::info('Marketplace listings by id : ', ['marketplace_id' => $marketplaceIdInt, 'listing_count' => $listingCount]);

                // Calculate order summary for this marketplace
                $orderSummary = $this->calculateMarketplaceOrderSummary($variation->id, $marketplaceIdInt);

                // Use integer key to ensure consistent access
                $marketplaceData[$marketplaceIdInt] = [
                    'name' => $marketplace->name ?? 'Marketplace ' . $marketplaceIdInt,
                    'listing_count' => $listingCount,
                    'listings' => $marketplaceListings,
                    'order_summary' => $orderSummary
                ];

                // Add to global counts
                if (isset($globalMarketplaceCounts[$marketplaceIdInt])) {
                    $globalMarketplaceCounts[$marketplaceIdInt]['total_count'] += $listingCount;
                }
            }

            // Attach marketplace data to variation
            $variation->marketplace_data = $marketplaceData;
        }

        $data['variations'] = $variations;
        $data['global_marketplace_counts'] = $globalMarketplaceCounts;

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

    /**
     * Get listings for a variation, optionally filtered by marketplace
     * Similar to original getCompetitors but supports marketplace filtering
     */
    public function get_listings($variationId, Request $request)
    {
        $marketplaceId = $request->input('marketplace_id');

        $query = Listing_model::with(['marketplace', 'country_id', 'currency'])
            ->where('variation_id', $variationId);

        // Filter by marketplace_id if provided
        if ($marketplaceId !== null) {
            $query->where('marketplace_id', $marketplaceId);
        }

        $listings = $query->get();

        return response()->json(['listings' => $listings]);
    }

    /**
     * Calculate order summary for a variation by marketplace
     * Returns format: "Today: €X.XX (count) - Yesterday: €X.XX (count) - 7 days: €X.XX (count) - 14 days: €X.XX (count) - 30 days: €X.XX (count)"
     */
    private function calculateMarketplaceOrderSummary($variationId, $marketplaceId)
    {
        // Helper function to format amount
        $formatAmount = function($amount) {
            if ($amount === null || $amount === '') {
                return '0.00';
            }
            return number_format((float)$amount, 2, '.', '');
        };

        // Calculate today's summary
        $todayAvg = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                $q->whereBetween('created_at', [now()->startOfDay(), now()])
                  ->where('order_type_id', 3)
                  ->where('marketplace_id', $marketplaceId);
            })
            ->avg('price');

        $todayCount = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                $q->whereBetween('created_at', [now()->startOfDay(), now()])
                  ->where('order_type_id', 3)
                  ->where('marketplace_id', $marketplaceId);
            })
            ->count();

        // Calculate yesterday's summary
        $yesterdayAvg = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                $q->whereBetween('created_at', [now()->yesterday()->startOfDay(), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3)
                  ->where('marketplace_id', $marketplaceId);
            })
            ->avg('price');

        $yesterdayCount = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                $q->whereBetween('created_at', [now()->yesterday()->startOfDay(), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3)
                  ->where('marketplace_id', $marketplaceId);
            })
            ->count();

        // Calculate 7 days summary
        $last7DaysAvg = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                $q->whereBetween('created_at', [now()->subDays(7), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3)
                  ->where('marketplace_id', $marketplaceId);
            })
            ->avg('price');

        $last7DaysCount = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                $q->whereBetween('created_at', [now()->subDays(7), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3)
                  ->where('marketplace_id', $marketplaceId);
            })
            ->count();

        // Calculate 14 days summary
        $last14DaysAvg = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                $q->whereBetween('created_at', [now()->subDays(14), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3)
                  ->where('marketplace_id', $marketplaceId);
            })
            ->avg('price');

        $last14DaysCount = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                $q->whereBetween('created_at', [now()->subDays(14), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3)
                  ->where('marketplace_id', $marketplaceId);
            })
            ->count();

        // Calculate 30 days summary
        $last30DaysAvg = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                $q->whereBetween('created_at', [now()->subDays(30), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3)
                  ->where('marketplace_id', $marketplaceId);
            })
            ->avg('price');

        $last30DaysCount = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                $q->whereBetween('created_at', [now()->subDays(30), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3)
                  ->where('marketplace_id', $marketplaceId);
            })
            ->count();

        // Format the summary string with today and yesterday
        return sprintf(
            'Today: €%s (%d) - Yesterday: €%s (%d) - 7 days: €%s (%d) - 14 days: €%s (%d) - 30 days: €%s (%d)',
            $formatAmount($todayAvg),
            $todayCount,
            $formatAmount($yesterdayAvg),
            $yesterdayCount,
            $formatAmount($last7DaysAvg),
            $last7DaysCount,
            $formatAmount($last14DaysAvg),
            $last14DaysCount,
            $formatAmount($last30DaysAvg),
            $last30DaysCount
        );
    }

    /**
     * V2 API endpoint for adding quantity/stock to a variation
     * This is the V2 version of the add_quantity endpoint
     */
    public function add_quantity($id, $stock = 'no', $process_id = null, $listing = false)
    {
        if($stock == 'no'){
            $stock = request('stock');
        }

        // Check if this is an exact stock set request (from stock formula page)
        $setExactStock = request('set_exact_stock', false);
        $exactStockValue = request('exact_stock_value', null);

        if($process_id == null && request('process_id') != null){
            $process = Process_model::where('process_type_id',22)->where('id', request('process_id'))->first();
            if($process != null){
                $process_id = $process->id;
            }else{
                $process_id = null;
            }
        }
        $variation = Variation_model::find($id);
        $bm = new BackMarketAPIController();
        $previous_qty = $variation->update_qty($bm);

        $variation = Variation_model::find($id);

        if(!in_array($variation->state, [0,1,2,3])){
            return response()->json([
                'error' => 'Ad State is not valid for Topup: ' . $variation->state
            ], 400);
        }
        $pending_orders = $variation->pending_orders->sum('quantity');

        // If setting exact stock, use the exact value directly
        if($setExactStock && $exactStockValue !== null){
            $new_quantity = (int)$exactStockValue;
        } else {
            // Normal flow: calculate based on addition
            $check_active_verification = Process_model::where('process_type_id',21)->where('status',1)->where('id', $process_id)->first();
            if($check_active_verification != null){
                $new_quantity = $stock - $pending_orders;
            }else{
                if($process_id != null && $previous_qty < 0 && $pending_orders == 0){
                    $new_quantity = $stock;
                }else{
                    $new_quantity = $stock + $previous_qty;
                }
            }
        }

        $response = $bm->updateOneListing($variation->reference_id,json_encode(['quantity'=>$new_quantity]));
        if(is_string($response) || is_int($response) || is_null($response)){
            Log::error("Error updating quantity for variation ID $id: $response");
            return response()->json([
                'error' => 'Error updating quantity',
                'message' => is_string($response) ? $response : 'Unknown error'
            ], 500);
        }

        // Check if response is valid object and has quantity property
        $responseQuantity = null;
        if($response && is_object($response) && isset($response->quantity)){
            $responseQuantity = $response->quantity;
        } else {
            // If API response doesn't have quantity, use the new_quantity we sent
            $responseQuantity = $new_quantity;
            Log::warning("API response missing quantity property for variation ID $id, using calculated value: $new_quantity", [
                'api_response' => $response,
                'response_type' => gettype($response),
                'calculated_quantity' => $new_quantity,
            ]);
        }

        if($responseQuantity != null){
            $oldStock = $variation->listed_stock;
            $variation->listed_stock = $responseQuantity;
            $variation->save();

            // Calculate stock change
            if($setExactStock && $exactStockValue !== null){
                // For exact stock set: calculate the difference
                $stockChange = $responseQuantity - $oldStock;
            } else {
                // For normal addition: use the stock parameter
                $stockChange = (int)$stock;
            }

            // Distribute stock to marketplaces based on formulas (synchronously)
            if($stockChange != 0){
                // Call distribution service directly to ensure it completes before response
                // Pass flag to ignore remaining stock if it's an exact set
                $this->stockDistributionService->distributeStock(
                    $variation->id,
                    $stockChange,
                    $responseQuantity, // Pass total stock for formulas that use apply_to: total
                    $setExactStock // Pass flag to ignore remaining stock
                );

                // Note: Event listener is disabled to prevent double distribution
                // Distribution is done synchronously above to ensure it completes before response
                // If you need event logging, add it here without triggering distribution
            }

            // Get updated marketplace stocks after distribution
            $marketplaceStocks = MarketplaceStockModel::where('variation_id', $variation->id)
                ->get()
                ->mapWithKeys(function($stock) {
                    return [$stock->marketplace_id => $stock->listed_stock];
                });
        } else {
            $marketplaceStocks = collect();
        }

        $listed_stock_verification = new Listed_stock_verification_model();
        $listed_stock_verification->process_id = $process_id;
        $listed_stock_verification->variation_id = $variation->id;
        $listed_stock_verification->pending_orders = $pending_orders;
        $listed_stock_verification->qty_from = $previous_qty;
        $listed_stock_verification->qty_change = $stock;
        $listed_stock_verification->qty_to = $responseQuantity ?? 0;
        $listed_stock_verification->admin_id = session('user_id');
        $listed_stock_verification->save();

        // Always return JSON response for V2 API
        return response()->json([
            'quantity' => (int)($responseQuantity ?? 0),
            'total_stock' => (int)($responseQuantity ?? 0),
            'marketplace_stocks' => $marketplaceStocks->toArray()
        ]);
    }
}
