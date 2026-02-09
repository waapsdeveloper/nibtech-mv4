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
use App\Models\V2\MarketplaceStockModel;
use App\Models\ListingMarketplaceState;
use App\Models\ListingMarketplaceHistory;
use App\Http\Controllers\BackMarketAPIController;
use App\Events\VariationStockUpdated;
use App\Services\Marketplace\StockDistributionService;
use App\Services\V2\MarketplaceAPIService;
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
    protected MarketplaceAPIService $marketplaceAPIService;

    public function __construct(
        ListingQueryService $queryService,
        ListingDataService $dataService,
        ListingCalculationService $calculationService,
        ListingCacheService $cacheService,
        StockDistributionService $stockDistributionService,
        MarketplaceAPIService $marketplaceAPIService
    ) {
        $this->queryService = $queryService;
        $this->dataService = $dataService;
        $this->calculationService = $calculationService;
        $this->cacheService = $cacheService;
        $this->stockDistributionService = $stockDistributionService;
        $this->marketplaceAPIService = $marketplaceAPIService;
    }

    /**
     * Display the V2 listing page
     */
    public function index(Request $request)
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
        })
        ->when($request->filled('stock_mismatch'), function ($q) {
            // Show only variations where listed stock ≠ available stock (Backmarket or total)
            return $q->withCount('available_stocks')
                ->havingRaw('variation.listed_stock != available_stocks_count OR (SELECT COALESCE(ms.listed_stock, 0) FROM marketplace_stock ms WHERE ms.variation_id = variation.id AND ms.marketplace_id = 1 AND ms.deleted_at IS NULL LIMIT 1) != available_stocks_count');
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
     * Get competitors from API and refresh prices (like V1 getCompetitors)
     * Only works for Backmarket (marketplace_id = 1)
     */
    public function getCompetitors($variationId, $no_check = 0)
    {
        $error = "";
        $variation = Variation_model::with('listings')->find($variationId);
        
        if (!$variation) {
            return response()->json(['error' => 'Variation not found'], 404);
        }
        
        // Get reference_uuid for Backmarket (marketplace_id = 1)
        $backmarketListing = $variation->listings->where('marketplace_id', 1)->whereNotNull('reference_uuid')->first();
        
        if (!$backmarketListing || !$backmarketListing->reference_uuid) {
            return response()->json(['error' => 'No Backmarket reference UUID found'], 400);
        }
        
        $reference = $backmarketListing->reference_uuid;
        
        // Call BackMarket API to get competitors
        $bm = new \App\Http\Controllers\BackMarketAPIController();
        $responses = $bm->getListingCompetitors($reference);
        
        if (is_string($responses) || is_int($responses) || is_null($responses)) {
            $error = $responses;
            $error .= " - " . $variation->reference_id;
            return response()->json(['error' => $error]);
        }
        
        // Update listings with fresh API data
        foreach ($responses as $list) {
            if (is_string($list) || is_int($list)) {
                $error .= $list;
                continue;
            }
            if (is_array($list)) {
                $error .= json_encode($list);
                continue;
            }
            
            $country = \App\Models\Country_model::where('code', $list->market)->first();
            if (!$country) {
                continue;
            }
            
            $listings = Listing_model::where('variation_id', $variationId)
                ->where('country', $country->id)
                ->where('marketplace_id', 1)
                ->get();
            
            // If multiple listings exist, keep only the first one
            if ($listings->count() > 1) {
                $listings->each(function($listing, $key) {
                    if ($key > 0) {
                        $listing->delete();
                    }
                });
            }
            
            $listing = Listing_model::firstOrNew([
                'variation_id' => $variationId,
                'country' => $country->id,
                'marketplace_id' => 1
            ]);
            
            if (isset($list->product_id)) {
                $listing->reference_uuid_2 = $list->product_id;
            }
            
            if ($list->price != null) {
                $listing->price = $list->price->amount;
                $currency = \App\Models\Currency_model::where('code', $list->price->currency)->first();
            }
            if ($list->min_price != null) {
                $listing->min_price = $list->min_price->amount;
                if (!isset($currency)) {
                    $currency = \App\Models\Currency_model::where('code', $list->min_price->currency)->first();
                }
            }
            
            if (isset($currency)) {
                $listing->currency_id = $currency->id;
            }
            
            $listing->buybox = $list->is_winning ?? 0;
            if (isset($list->price_to_win) && isset($list->price_to_win->amount)) {
                $listing->buybox_price = $list->price_to_win->amount;
            }
            if (isset($list->winner_price) && isset($list->winner_price->amount)) {
                $listing->buybox_winner_price = $list->winner_price->amount;
            }
            
            $listing->save();
        }
        
        if ($no_check == 1) {
            return response()->json(['responses' => $responses, 'error' => $error]);
        }
        
        // Return updated listings
        $listings = Listing_model::with('marketplace')
            ->where('variation_id', $variationId)
            ->get();
        
        return response()->json(['listings' => $listings, 'error' => $error]);
    }

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
        // For Backmarket (marketplace_id = 1), include orders with marketplace_id = null OR 1
        // For other marketplaces, only that specific marketplace_id
        // Use same currency conversion logic as variation card for consistency
        $todayOrders = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                if ($marketplaceId == 1) {
                    // Backmarket: include null and 1
                    $q->where(function($query) {
                        $query->whereNull('marketplace_id')
                              ->orWhere('marketplace_id', 1);
                    });
                } else {
                    // Other marketplaces: only that specific ID
                    $q->where('marketplace_id', $marketplaceId);
                }
                $q->whereBetween('created_at', [now()->startOfDay(), now()])
                  ->where('order_type_id', 3);
            })
            ->with('order.currency_id')
            ->get();

        // Convert prices to EUR and calculate average (same logic as variation card)
        $todayTotal = $todayOrders->sum(function($item) {
            if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                return $item->price / $rate;
            }
            return $item->price;
        });
        $todayCount = $todayOrders->count();
        $todayAvg = $todayCount > 0 ? $todayTotal / $todayCount : 0;

        // Calculate yesterday's summary
        // For Backmarket (marketplace_id = 1), include orders with marketplace_id = null OR 1
        // For other marketplaces, only that specific marketplace_id
        // Use same currency conversion logic as variation card for consistency
        $yesterdayOrders = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                if ($marketplaceId == 1) {
                    // Backmarket: include null and 1
                    $q->where(function($query) {
                        $query->whereNull('marketplace_id')
                              ->orWhere('marketplace_id', 1);
                    });
                } else {
                    // Other marketplaces: only that specific ID
                    $q->where('marketplace_id', $marketplaceId);
                }
                $q->whereBetween('created_at', [now()->yesterday()->startOfDay(), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3);
            })
            ->with('order.currency_id')
            ->get();

        // Convert prices to EUR and calculate average (same logic as variation card)
        $yesterdayTotal = $yesterdayOrders->sum(function($item) {
            if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                return $item->price / $rate;
            }
            return $item->price;
        });
        $yesterdayCount = $yesterdayOrders->count();
        $yesterdayAvg = $yesterdayCount > 0 ? $yesterdayTotal / $yesterdayCount : 0;

        // Calculate 7 days summary
        // For Backmarket (marketplace_id = 1), include orders with marketplace_id = null OR 1
        // For other marketplaces, only that specific marketplace_id
        // Use same currency conversion logic as variation card for consistency
        $last7DaysOrders = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                if ($marketplaceId == 1) {
                    // Backmarket: include null and 1
                    $q->where(function($query) {
                        $query->whereNull('marketplace_id')
                              ->orWhere('marketplace_id', 1);
                    });
                } else {
                    // Other marketplaces: only that specific ID
                    $q->where('marketplace_id', $marketplaceId);
                }
                $q->whereBetween('created_at', [now()->subDays(7)->startOfDay(), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3);
            })
            ->with('order.currency_id')
            ->get();

        // Convert prices to EUR and calculate average (same logic as variation card)
        $last7DaysTotal = $last7DaysOrders->sum(function($item) {
            if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                return $item->price / $rate;
            }
            return $item->price;
        });
        $last7DaysCount = $last7DaysOrders->count();
        $last7DaysAvg = $last7DaysCount > 0 ? $last7DaysTotal / $last7DaysCount : 0;

        // Calculate 14 days summary
        // For Backmarket (marketplace_id = 1), include orders with marketplace_id = null OR 1
        // For other marketplaces, only that specific marketplace_id
        // Use same currency conversion logic as variation card for consistency
        $last14DaysOrders = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                if ($marketplaceId == 1) {
                    // Backmarket: include null and 1
                    $q->where(function($query) {
                        $query->whereNull('marketplace_id')
                              ->orWhere('marketplace_id', 1);
                    });
                } else {
                    // Other marketplaces: only that specific ID
                    $q->where('marketplace_id', $marketplaceId);
                }
                $q->whereBetween('created_at', [now()->subDays(14), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3);
            })
            ->with('order.currency_id')
            ->get();

        // Convert prices to EUR and calculate average (same logic as variation card)
        $last14DaysTotal = $last14DaysOrders->sum(function($item) {
            if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                return $item->price / $rate;
            }
            return $item->price;
        });
        $last14DaysCount = $last14DaysOrders->count();
        $last14DaysAvg = $last14DaysCount > 0 ? $last14DaysTotal / $last14DaysCount : 0;

        // Calculate 30 days summary
        // For Backmarket (marketplace_id = 1), include orders with marketplace_id = null OR 1
        // For other marketplaces, only that specific marketplace_id
        // Use same currency conversion logic as variation card for consistency
        $last30DaysOrders = Order_item_model::where('variation_id', $variationId)
            ->whereHas('order', function($q) use ($marketplaceId) {
                if ($marketplaceId == 1) {
                    // Backmarket: include null and 1
                    $q->where(function($query) {
                        $query->whereNull('marketplace_id')
                              ->orWhere('marketplace_id', 1);
                    });
                } else {
                    // Other marketplaces: only that specific ID
                    $q->where('marketplace_id', $marketplaceId);
                }
                $q->whereBetween('created_at', [now()->subDays(30), now()->yesterday()->endOfDay()])
                  ->where('order_type_id', 3);
            })
            ->with('order.currency_id')
            ->get();

        // Convert prices to EUR and calculate average (same logic as variation card)
        $last30DaysTotal = $last30DaysOrders->sum(function($item) {
            if ($item->order && $item->order->currency != 4 && $item->order->currency_id) {
                $rate = ExchangeRate::where('target_currency', $item->order->currency_id->code)->first()?->rate ?? 1;
                return $item->price / $rate;
            }
            return $item->price;
        });
        $last30DaysCount = $last30DaysOrders->count();
        $last30DaysAvg = $last30DaysCount > 0 ? $last30DaysTotal / $last30DaysCount : 0;

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
        
        // Cast stock to integer to preserve negative values
        // This ensures -1 stays as -1, not converted to 1
        $stock = (int)$stock;

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
        $variation = Variation_model::with('available_stocks')->find($id);
        
        // FIX: Calculate current total stock from marketplace stocks (includes manual_adjustment)
        // Total = sum of all marketplace listed_stock + sum of all marketplace manual_adjustment
        // This ensures we use the actual current total that the user sees, not just variation.listed_stock
        $currentTotalListedStock = MarketplaceStockModel::where('variation_id', $variation->id)
            ->sum('listed_stock');
        $currentTotalManualAdjustment = MarketplaceStockModel::where('variation_id', $variation->id)
            ->sum('manual_adjustment');
        $current_listed_stock = (int)$currentTotalListedStock + (int)$currentTotalManualAdjustment;
        
        // Fallback to variation.listed_stock if no marketplace stocks exist
        if($current_listed_stock == 0 && $currentTotalListedStock == 0 && $currentTotalManualAdjustment == 0) {
            $current_listed_stock = (int)($variation->listed_stock ?? 0);
        }
        
        $bm = new BackMarketAPIController();
        $previous_qty = $variation->update_qty($bm);

        // Reload variation with relationships after update_qty
        $variation = Variation_model::with('available_stocks')->find($id);

        if(!in_array($variation->state, [0,1,2,3])){
            return response()->json([
                'error' => 'Ad State is not valid for Topup: ' . $variation->state
            ], 400);
        }
        $pending_orders = $variation->pending_orders->sum('quantity');

        // Get available stock count (physical inventory)
        $availableCount = $variation->available_stocks->count();

        // If setting exact stock, use the exact value directly
        if($setExactStock && $exactStockValue !== null){
            $new_quantity = (int)$exactStockValue;
        } else {
            // Normal flow: calculate based on addition/subtraction
            $check_active_verification = Process_model::where('process_type_id',21)->where('status',1)->where('id', $process_id)->first();
            if($check_active_verification != null){
                // For active verification: stock value represents the new total (after subtracting pending orders)
                $new_quantity = $stock - $pending_orders;
            } else {
                // Normal case: stock value is an adjustment (add/subtract from current)
                // The $stock value should be added/subtracted from current_listed_stock
                // This handles both positive (add) and negative (subtract) values correctly
                $new_quantity = $current_listed_stock + $stock;
            }
        }

        $stockChange = (int)$stock; // The pushed value (e.g., +1 or -1)
        
        // EDGE CASE: Prevent total stock from going below zero
        if($stockChange < 0) {
            $maxAllowedSubtraction = -$current_listed_stock;
            $stockChange = max($stockChange, $maxAllowedSubtraction);
        }
        
        $backmarketApiQuantity = null; // For badge update (only set when we push to API)
        
        // Get or create marketplace stock for BackMarket (marketplace_id = 1)
        $marketplace1Stock = MarketplaceStockModel::firstOrCreate(
            [
                'variation_id' => $variation->id,
                'marketplace_id' => 1
            ],
            [
                'listed_stock' => 0,
                'manual_adjustment' => 0,
                'locked_stock' => 0,
                'admin_id' => session('user_id') ?? 1
            ]
        );
        
        if($stockChange != 0){
            // Push to Backmarket API when variation has reference_id (like V1)
            if($variation->reference_id) {
                $bmCurrent = (int)($marketplace1Stock->listed_stock ?? 0) + (int)($marketplace1Stock->manual_adjustment ?? 0);
                $quantityToPushToApi = $bmCurrent + $stockChange;
                $quantityToPushToApi = max(0, $quantityToPushToApi);
                
                Log::info("V2 add_quantity: Calling Backmarket API updateOneListing", [
                    'variation_id' => $id,
                    'reference_id' => $variation->reference_id,
                    'quantity' => $quantityToPushToApi,
                    'stock_change' => $stockChange
                ]);
                $response = $bm->updateOneListing($variation->reference_id, json_encode(['quantity' => $quantityToPushToApi]), null, true);
                Log::info("V2 add_quantity: Backmarket API updateOneListing completed", [
                    'variation_id' => $id,
                    'response_type' => is_object($response) ? 'object' : gettype($response),
                    'has_quantity' => is_object($response) && isset($response->quantity)
                ]);
                
                if(is_string($response) || is_int($response) || is_null($response)){
                    Log::error("V2 add_quantity: Backmarket API error for variation ID $id", ['response' => $response]);
                    return response()->json([
                        'error' => 'Failed to update Backmarket: ' . (is_string($response) ? $response : 'API error')
                    ], 500);
                }
                
                $responseQuantity = null;
                $quantityFromResponse = is_object($response) ? ($response->quantity ?? null) : (is_array($response) ? ($response['quantity'] ?? null) : null);
                if($quantityFromResponse !== null){
                    $responseQuantity = (int)$quantityFromResponse;
                } else {
                    $variation->refresh();
                    $responseQuantity = (int)($variation->update_qty($bm) ?? $quantityToPushToApi);
                    Log::warning("V2 add_quantity: API response missing quantity, fetched: $responseQuantity");
                }
                
                $backmarketApiQuantity = $responseQuantity;
                
                $listedStockBefore = (int)($marketplace1Stock->listed_stock ?? 0);
                // Sync DB: listed_stock = API response, clear manual_adjustment for Backmarket
                $marketplace1Stock->listed_stock = $responseQuantity;
                $marketplace1Stock->manual_adjustment = 0;
                $marketplace1Stock->admin_id = session('user_id') ?? 1;
                $marketplace1Stock->save();
                
                \App\Models\V2\MarketplaceStockHistory::create([
                    'marketplace_stock_id' => $marketplace1Stock->id,
                    'variation_id' => $variation->id,
                    'marketplace_id' => 1,
                    'listed_stock_before' => $listedStockBefore,
                    'listed_stock_after' => $responseQuantity,
                    'locked_stock_before' => $marketplace1Stock->locked_stock ?? 0,
                    'locked_stock_after' => $marketplace1Stock->locked_stock ?? 0,
                    'available_stock_before' => $marketplace1Stock->available_stock ?? 0,
                    'available_stock_after' => $marketplace1Stock->available_stock ?? 0,
                    'quantity_change' => $stockChange,
                    'change_type' => 'api_sync',
                    'admin_id' => session('user_id') ?? 1,
                    'notes' => "Pushed to Backmarket API: " . ($stockChange > 0 ? '+' : '') . $stockChange
                ]);
            } else {
                // No reference_id: store in manual_adjustment only (fallback)
                $oldManualAdjustment = (int)($marketplace1Stock->manual_adjustment ?? 0);
                $newManualAdjustment = $oldManualAdjustment + $stockChange;
                $marketplace1Stock->manual_adjustment = $newManualAdjustment;
                $marketplace1Stock->admin_id = session('user_id') ?? 1;
                $marketplace1Stock->save();
                
                \App\Models\V2\MarketplaceStockHistory::create([
                    'marketplace_stock_id' => $marketplace1Stock->id,
                    'variation_id' => $variation->id,
                    'marketplace_id' => 1,
                    'listed_stock_before' => $marketplace1Stock->listed_stock,
                    'listed_stock_after' => $marketplace1Stock->listed_stock,
                    'locked_stock_before' => $marketplace1Stock->locked_stock ?? 0,
                    'locked_stock_after' => $marketplace1Stock->locked_stock ?? 0,
                    'available_stock_before' => $marketplace1Stock->available_stock ?? 0,
                    'available_stock_after' => $marketplace1Stock->available_stock ?? 0,
                    'quantity_change' => $stockChange,
                    'change_type' => 'manual',
                    'admin_id' => session('user_id') ?? 1,
                    'notes' => "Manual stock adjustment (no Backmarket ref): " . ($stockChange > 0 ? '+' : '') . $stockChange
                ]);
            }
        }

        // Get updated marketplace stocks (including manual_adjustment) AFTER saving
        // This ensures we have the correct values after the manual adjustment was saved
        $marketplaceStocks = MarketplaceStockModel::where('variation_id', $variation->id)
            ->get()
            ->mapWithKeys(function($stock) {
                // Return total: listed_stock + manual_adjustment for display
                $total = (int)($stock->listed_stock ?? 0) + (int)($stock->manual_adjustment ?? 0);
                return [$stock->marketplace_id => $total];
            });
        
        // Create distribution preview from actual updated stocks (for consistency)
        // This ensures preview matches the actual saved values
        $distributionPreview = [];
        if($stockChange != 0){
            foreach($marketplaceStocks as $marketplaceId => $totalStock){
                $mpStock = MarketplaceStockModel::where('variation_id', $variation->id)
                    ->where('marketplace_id', $marketplaceId)
                    ->first();
                if($mpStock){
                    $distributionPreview[$marketplaceId] = [
                        'listed_stock' => (int)($mpStock->listed_stock ?? 0),
                        'manual_adjustment' => (int)($mpStock->manual_adjustment ?? 0),
                        'total' => $totalStock
                    ];
                }
            }
        }

        // Calculate total stock: sum of listed_stock + sum of manual_adjustment
        $totalListedStock = MarketplaceStockModel::where('variation_id', $variation->id)
            ->sum('listed_stock');
        $totalManualAdjustment = MarketplaceStockModel::where('variation_id', $variation->id)
            ->sum('manual_adjustment');
        $calculatedTotalStock = (int)$totalListedStock + (int)$totalManualAdjustment;
        
        // EDGE CASE: Ensure total stock never goes below zero (safety check)
        // This is a final safeguard in case of any edge cases
        $calculatedTotalStock = max(0, $calculatedTotalStock);
        
        // Update variation.listed_stock to match calculated total (for backward compatibility)
        $variation->listed_stock = $calculatedTotalStock;
        $variation->save();

        $listed_stock_verification = new Listed_stock_verification_model();
        $listed_stock_verification->process_id = $process_id;
        $listed_stock_verification->variation_id = $variation->id;
        $listed_stock_verification->pending_orders = $pending_orders;
        // FIX: Use current_listed_stock (before API overwrite) for qty_from to show actual value before push
        $listed_stock_verification->qty_from = $current_listed_stock;
        $listed_stock_verification->qty_change = $stock;
        // FIX: Use calculated total (listed_stock + manual_adjustment) instead of API response
        $listed_stock_verification->qty_to = $calculatedTotalStock;
        $listed_stock_verification->admin_id = session('user_id');
        $listed_stock_verification->save();

        // Always return JSON response for V2 API
        // Include backmarket_api_quantity when we pushed to API - frontend uses this to update badge without extra fetch
        $responseData = [
            'quantity' => $calculatedTotalStock,
            'total_stock' => $calculatedTotalStock,
            'marketplace_stocks' => $marketplaceStocks->toArray(),
            'distribution_preview' => $distributionPreview,
            'stock_change' => $stockChange
        ];
        if ($backmarketApiQuantity !== null) {
            $responseData['backmarket_api_quantity'] = $backmarketApiQuantity;
        }
        return response()->json($responseData);
    }

    /**
     * Set listed stock to match available (physical) count. Admin only.
     * Computes delta = available - current_total and pushes via add_quantity.
     */
    public function set_listed_available($id)
    {
        $user = session('user');
        if (!$user || (int) ($user->role_id ?? 0) !== 1) {
            return response()->json(['error' => 'Unauthorized. Admin role only.'], 403);
        }

        $variation = Variation_model::with('available_stocks')->find($id);
        if (!$variation) {
            return response()->json(['error' => 'Variation not found'], 404);
        }

        $currentTotalListedStock = MarketplaceStockModel::where('variation_id', $variation->id)->sum('listed_stock');
        $currentTotalManualAdjustment = MarketplaceStockModel::where('variation_id', $variation->id)->sum('manual_adjustment');
        $currentTotal = (int) $currentTotalListedStock + (int) $currentTotalManualAdjustment;
        if ($currentTotal === 0 && $currentTotalListedStock == 0 && $currentTotalManualAdjustment == 0) {
            $currentTotal = (int) ($variation->listed_stock ?? 0);
        }

        $availableCount = $variation->available_stocks->count();
        $delta = $availableCount - $currentTotal;

        if ($delta === 0) {
            return response()->json([
                'total_stock' => $currentTotal,
                'quantity' => $currentTotal,
                'marketplace_stocks' => [],
                'stock_change' => 0,
                'no_change' => true,
            ]);
        }

        request()->merge(['stock' => (string) $delta]);
        return $this->add_quantity($id, $delta, null, false);
    }

    /**
     * Get listing history for a specific listing
     */
    public function get_listing_history($listingId, Request $request)
    {
        $listing = Listing_model::with([
            'variation.product',
            'variation.storage_id',
            'variation.color_id',
            'marketplace',
            'country_id'
        ])->find($listingId);

        if (!$listing) {
            return response()->json([
                'error' => 'Listing not found'
            ], 404);
        }

        $variationId = $request->input('variation_id', $listing->variation_id);
        $marketplaceId = $request->input('marketplace_id', $listing->marketplace_id);
        $countryId = $request->input('country_id', $listing->country);

        // Get history for this listing
        $history = ListingMarketplaceHistory::where('listing_id', $listingId)
            ->with(['admin'])
            ->orderBy('changed_at', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'field_name' => $item->field_name,
                    'field_label' => $item->field_label,
                    'old_value' => $item->old_value,
                    'new_value' => $item->new_value,
                    'formatted_old_value' => $item->formatted_old_value,
                    'formatted_new_value' => $item->formatted_new_value,
                    'row_snapshot' => $item->row_snapshot,
                    'change_type' => $item->change_type,
                    'change_reason' => $item->change_reason,
                    'admin_id' => $item->admin_id,
                    'admin_name' => $item->admin ? (trim($item->admin->first_name ?? '') ?: ($item->admin->username ?? 'Admin #' . $item->admin_id)) : 'System',
                    'changed_at' => $item->changed_at ? $item->changed_at->toDateTimeString() : null,
                ];
            });

        // Get descriptive information
        $variationName = 'N/A';
        if ($listing->variation && $listing->variation->product) {
            $product = $listing->variation->product;
            $variationName = $product->model ?? ($product->name ?? 'Variation #' . $listing->variation_id);
            // Add storage and color if available
            if ($listing->variation->storage_id) {
                $variationName .= ' - ' . ($listing->variation->storage_id->name ?? '');
            }
            if ($listing->variation->color_id) {
                $variationName .= ' ' . ($listing->variation->color_id->name ?? '');
            }
        }

        $marketplaceName = 'N/A';
        if ($listing->marketplace) {
            $marketplaceName = $listing->marketplace->name ?? 'Marketplace #' . $listing->marketplace_id;
        }

        $countryName = 'N/A';
        if ($listing->country_id) {
            $countryName = $listing->country_id->title ?? ($listing->country_id->code ?? 'Country #' . $listing->country);
        }

        return response()->json([
            'listing' => [
                'id' => $listing->id,
                'variation_id' => $listing->variation_id,
                'variation_name' => $variationName,
                'marketplace_id' => $listing->marketplace_id,
                'marketplace_name' => $marketplaceName,
                'country_id' => $listing->country,
                'country_name' => $countryName,
                'country_code' => $listing->country_id ? $listing->country_id->code : null,
            ],
            'history' => $history
        ]);
    }

    /**
     * Capture a full listing row snapshot as JSON
     * @param Listing_model $listing
     * @return array
     */
    private function captureListingSnapshot($listing)
    {
        if (!$listing) {
            return null;
        }

        // Load relationships if not already loaded
        $listing->load(['country_id', 'currency', 'marketplace', 'variation']);

        // Capture all relevant listing fields
        return [
            'id' => $listing->id,
            'variation_id' => $listing->variation_id,
            'marketplace_id' => $listing->marketplace_id,
            'country' => $listing->country,
            'country_id' => [
                'id' => $listing->country_id->id ?? null,
                'code' => $listing->country_id->code ?? null,
                'title' => $listing->country_id->title ?? null,
            ],
            'marketplace' => [
                'id' => $listing->marketplace->id ?? null,
                'name' => $listing->marketplace->name ?? null,
            ],
            'currency_id' => $listing->currency_id,
            'currency' => [
                'id' => $listing->currency->id ?? null,
                'code' => $listing->currency->code ?? null,
                'sign' => $listing->currency->sign ?? null,
            ],
            'reference_uuid' => $listing->reference_uuid,
            'reference_uuid_2' => $listing->reference_uuid_2 ?? null,
            'name' => $listing->name ?? null,
            'min_price' => $listing->min_price,
            'max_price' => $listing->max_price ?? null,
            'price' => $listing->price,
            'buybox' => $listing->buybox,
            'buybox_price' => $listing->buybox_price,
            'buybox_winner_price' => $listing->buybox_winner_price ?? null,
            'min_price_limit' => $listing->min_price_limit,
            'price_limit' => $listing->price_limit,
            'handler_status' => $listing->handler_status,
            'target_price' => $listing->target_price ?? null,
            'target_percentage' => $listing->target_percentage ?? null,
            'admin_id' => $listing->admin_id ?? null,
            'status' => $listing->status ?? null,
            'is_enabled' => $listing->is_enabled ?? null,
            'created_at' => $listing->created_at ? $listing->created_at->toDateTimeString() : null,
            'updated_at' => $listing->updated_at ? $listing->updated_at->toDateTimeString() : null,
        ];
    }

    /**
     * Record a listing change to the database
     * This is called when a user changes a field value in the listing table
     */
    public function record_listing_change(Request $request)
    {
        $request->validate([
            'listing_id' => 'required|integer|exists:listings,id',
            'field_name' => 'required|string|in:min_handler,price_handler,buybox,buybox_price,min_price,price',
            'old_value' => 'nullable',
            'new_value' => 'nullable',
            'change_reason' => 'nullable|string|max:255',
        ]);

        $listing = Listing_model::find($request->listing_id);

        if (!$listing) {
            return response()->json(['error' => 'Listing not found'], 404);
        }

        $variationId = $listing->variation_id;
        $marketplaceId = $listing->marketplace_id;
        $countryId = $listing->country;
        $listingId = $listing->id;

        // Get or create state record
        $state = ListingMarketplaceState::getOrCreateState(
            $variationId,
            $marketplaceId,
            $listingId,
            $countryId
        );

        // Map field names from database field names to listing table columns
        // This is needed to get the actual value from the listing if state doesn't have it
        $listingFieldMapping = [
            'min_handler' => 'min_price_limit',  // min_handler in state = min_price_limit in listings
            'price_handler' => 'price_limit',    // price_handler in state = price_limit in listings
            'buybox' => 'buybox',
            'buybox_price' => 'buybox_price',
            'min_price' => 'min_price',
            'price' => 'price',
        ];

        // Map field names to state columns
        $stateFieldMapping = [
            'min_handler' => 'min_handler',
            'price_handler' => 'price_handler',
            'buybox' => 'buybox',
            'buybox_price' => 'buybox_price',
            'min_price' => 'min_price',
            'price' => 'price',
        ];

        $fieldName = $request->field_name;
        $stateField = $stateFieldMapping[$fieldName] ?? null;
        $listingField = $listingFieldMapping[$fieldName] ?? null;

        if (!$stateField || !$listingField) {
            return response()->json(['error' => 'Invalid field name'], 400);
        }

        // Get the actual old value from listing table if state field is null (first change)
        $actualOldValue = null;
        if ($state->$stateField === null && $listingField) {
            // Get the actual value from the listing table (this is the true old value)
            $actualOldValue = $listing->$listingField;
            // Set it in the state so we have the baseline for future changes
            $state->$stateField = $actualOldValue;
            $state->save();
        } else {
            // Use the current state value as old value
            $actualOldValue = $state->$stateField;
        }

        // Prepare update data
        $updateData = [];

        // Convert value based on field type
        if ($fieldName === 'buybox') {
            $updateData[$stateField] = $request->new_value === '1' || $request->new_value === 1 || $request->new_value === true || $request->new_value === 'true' ? 1 : 0;
        } else {
            $updateData[$stateField] = $request->new_value !== null && $request->new_value !== '' ? (float)$request->new_value : null;
        }

        // Capture full listing row snapshot before the change
        $rowSnapshot = $this->captureListingSnapshot($listing);

        // Update state and track changes with explicit old value and row snapshot
        $result = $state->updateState(
            $updateData,
            'listing', // change_type
            $request->change_reason ?? 'User edit from listing page',
            [$stateField => $actualOldValue], // Pass explicit old value
            $rowSnapshot // Pass row snapshot
        );

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Change recorded successfully',
                'state_id' => $state->id
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No changes detected or failed to record'
            ], 400);
        }
    }

    /**
     * Update listing price (min_price or price)
     * V2 version with change tracking
     */
    public function update_price($id, Request $request)
    {
        $listing = Listing_model::with(['variation', 'marketplace', 'country_id', 'currency'])->find($id);

        if (!$listing) {
            return response()->json(['error' => 'Listing not found'], 404);
        }

        $variationId = $listing->variation_id;
        $marketplaceId = $listing->marketplace_id;
        $countryId = $listing->country;

        $changes = [];
        $updateData = [];

        // Track min_price change
        if ($request->has('min_price')) {
            $newMinPrice = $request->input('min_price');
            $oldMinPrice = $listing->min_price;

            if ($oldMinPrice != $newMinPrice) {
                $updateData['min_price'] = $newMinPrice;
                $changes['min_price'] = [
                    'old' => $oldMinPrice,
                    'new' => $newMinPrice
                ];
            }
        }

        // Track price change
        if ($request->has('price')) {
            $newPrice = $request->input('price');
            $oldPrice = $listing->price;

            if ($oldPrice != $newPrice) {
                $updateData['price'] = $newPrice;
                $changes['price'] = [
                    'old' => $oldPrice,
                    'new' => $newPrice
                ];
            }
        }

        // Update listing if there are changes
        if (!empty($updateData)) {
            // Capture snapshot BEFORE updating the listing
            $rowSnapshot = $this->captureListingSnapshot($listing);
            
            $listing->fill($updateData);
            $listing->save();

            // Update BackMarket API if needed
            $bm = new BackMarketAPIController();
            if ($listing->variation && $listing->variation->reference_id && $listing->country_id) {
                $currencyCode = $listing->currency ? $listing->currency->code : 'EUR';
                $marketCode = $listing->country_id->market_code ?? null;

                $apiPayload = [];
                if (isset($updateData['min_price'])) {
                    $apiPayload['min_price'] = $updateData['min_price'];
                }
                if (isset($updateData['price'])) {
                    $apiPayload['price'] = $updateData['price'];
                }
                $apiPayload['currency'] = $currencyCode;

                if (!empty($apiPayload)) {
                    $bm->updateOneListing($listing->variation->reference_id, json_encode($apiPayload), $marketCode);
                }
            }

            // Track changes in history with pre-captured snapshot
            $this->trackListingChanges($variationId, $marketplaceId, $listing->id, $countryId, $changes, 'listing', 'Price update via form', $rowSnapshot);
        }

        return response()->json([
            'success' => true,
            'listing' => $listing,
            'changes' => $changes
        ]);
    }

    /**
     * Restore listing field to previous value from history
     */
    public function restore_history($listingId, Request $request)
    {
        $listing = Listing_model::with(['variation', 'marketplace', 'country_id', 'currency'])->find($listingId);

        if (!$listing) {
            return response()->json(['success' => false, 'error' => 'Listing not found'], 404);
        }

        $historyId = $request->input('history_id');
        $fieldName = $request->input('field_name');
        $oldValue = $request->input('old_value');

        if (!$fieldName || $oldValue === null) {
            return response()->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $variationId = $listing->variation_id;
        $marketplaceId = $listing->marketplace_id;
        $countryId = $listing->country;

        // Map field names from state fields to listing table columns
        $listingFieldMapping = [
            'min_handler' => 'min_price_limit',
            'price_handler' => 'price_limit',
            'buybox' => 'buybox',
            'buybox_price' => 'buybox_price',
            'min_price' => 'min_price',
            'price' => 'price',
        ];

        $listingField = $listingFieldMapping[$fieldName] ?? null;
        if (!$listingField) {
            return response()->json(['success' => false, 'error' => 'Invalid field name'], 400);
        }

        // Capture snapshot BEFORE updating
        $rowSnapshot = $this->captureListingSnapshot($listing);

        // Get current value before restore
        $currentValue = $listing->$listingField;

        // Convert old value to appropriate type
        $restoredValue = $oldValue;
        if ($fieldName === 'buybox') {
            // Convert to boolean/integer for buybox
            $restoredValue = ($oldValue === '1' || $oldValue === 1 || $oldValue === true || $oldValue === 'true') ? 1 : 0;
        } elseif (in_array($fieldName, ['min_price', 'price', 'min_handler', 'price_handler', 'buybox_price'])) {
            // Convert to float for price fields
            $restoredValue = (float)$oldValue;
        }

        // Update listing with restored value
        $listing->$listingField = $restoredValue;
        $listing->save();

        // Update BackMarket API if needed (for price fields)
        $bm = new BackMarketAPIController();
        if ($listing->variation && $listing->variation->reference_id && $listing->country_id) {
            $currencyCode = $listing->currency ? $listing->currency->code : 'EUR';
            $marketCode = $listing->country_id->market_code ?? null;

            $apiPayload = [];
            if ($fieldName === 'min_price' || $fieldName === 'min_handler') {
                $apiPayload['min_price'] = $oldValue;
            }
            if ($fieldName === 'price' || $fieldName === 'price_handler') {
                $apiPayload['price'] = $oldValue;
            }
            
            if (!empty($apiPayload)) {
                $apiPayload['currency'] = $currencyCode;
                $bm->updateOneListing($listing->variation->reference_id, json_encode($apiPayload), $marketCode);
            }
        }

        // Track restore action in history
        $changes = [
            $fieldName => [
                'old' => $currentValue,
                'new' => $oldValue
            ]
        ];

        $this->trackListingChanges(
            $variationId,
            $marketplaceId,
            $listing->id,
            $countryId,
            $changes,
            'listing',
            "Restored from history (History ID: {$historyId})",
            $rowSnapshot
        );

        return response()->json([
            'success' => true,
            'message' => 'Field restored successfully',
            'listing' => $listing,
            'restored_field' => $fieldName,
            'restored_value' => $oldValue
        ]);
    }

    /**
     * Update listing limits (min_price_limit and price_limit - handlers)
     * V2 version with change tracking
     */
    public function update_limit($id, Request $request)
    {
        $listing = Listing_model::with(['variation', 'marketplace', 'country_id'])->find($id);

        if (!$listing) {
            return response()->json(['error' => 'Listing not found'], 404);
        }

        $variationId = $listing->variation_id;
        $marketplaceId = $listing->marketplace_id;
        $countryId = $listing->country;

        $changes = [];
        $updateData = [];

        // Track min_price_limit change (maps to min_handler in state)
        if ($request->has('min_price_limit')) {
            $newMinLimit = $request->input('min_price_limit');
            $oldMinLimit = $listing->min_price_limit;

            if ($oldMinLimit != $newMinLimit) {
                $updateData['min_price_limit'] = $newMinLimit;
                $changes['min_handler'] = [
                    'old' => $oldMinLimit,
                    'new' => $newMinLimit
                ];
            }
        }

        // Track price_limit change (maps to price_handler in state)
        if ($request->has('price_limit')) {
            $newPriceLimit = $request->input('price_limit');
            $oldPriceLimit = $listing->price_limit;

            if ($oldPriceLimit != $newPriceLimit) {
                $updateData['price_limit'] = $newPriceLimit;
                $changes['price_handler'] = [
                    'old' => $oldPriceLimit,
                    'new' => $newPriceLimit
                ];
            }
        }

        // Update handler_status based on limits
        if (!empty($updateData)) {
            if (($listing->min_price_limit === null || $listing->min_price_limit == 0) &&
                ($listing->price_limit === null || $listing->price_limit == 0)) {
                $updateData['handler_status'] = 0;
            } else {
                $updateData['handler_status'] = 1;
            }
        }

        // Update listing if there are changes
        if (!empty($updateData)) {
            // Capture snapshot BEFORE updating the listing
            $rowSnapshot = $this->captureListingSnapshot($listing);
            
            $listing->fill($updateData);
            $listing->save();

            // Track changes in history (using handler field names) with pre-captured snapshot
            $this->trackListingChanges($variationId, $marketplaceId, $listing->id, $countryId, $changes, 'listing', 'Handler limit update via form', $rowSnapshot);
        }

        return response()->json([
            'success' => true,
            'listing' => $listing,
            'changes' => $changes
        ]);
    }

    /**
     * Track listing changes in history
     * @param int $variationId
     * @param int $marketplaceId
     * @param int $listingId
     * @param int $countryId
     * @param array $changes
     * @param string $changeType
     * @param string|null $reason
     * @param array|null $rowSnapshot Optional pre-captured snapshot (if listing was already updated)
     */
    private function trackListingChanges($variationId, $marketplaceId, $listingId, $countryId, $changes, $changeType = 'listing', $reason = null, $rowSnapshot = null)
    {
        if (empty($changes)) {
            return;
        }

        // Get or create state record
        $state = ListingMarketplaceState::getOrCreateState($variationId, $marketplaceId, $listingId, $countryId);

        // Get the listing to retrieve actual values for first-time changes
        $listing = Listing_model::find($listingId);
        
        // If snapshot not provided, capture it now (before any updates)
        if ($rowSnapshot === null) {
            $rowSnapshot = $this->captureListingSnapshot($listing);
        }

        // Map field names from state fields to listing table columns
        $listingFieldMapping = [
            'min_handler' => 'min_price_limit',
            'price_handler' => 'price_limit',
            'buybox' => 'buybox',
            'buybox_price' => 'buybox_price',
            'min_price' => 'min_price',
            'price' => 'price',
        ];

        // For first-time changes, get actual values from listing table
        // This ensures old_value in history shows the actual database value, not null
        $needsSave = false;
        $explicitOldValues = [];

        foreach ($changes as $field => $values) {
            $stateField = $field;
            $listingField = $listingFieldMapping[$field] ?? null;

            // Determine the actual old value to use
            $actualOldValue = null;

            // If state field is null (first change), get the actual value from listing table
            if ($listing && $listingField && $state->$stateField === null) {
                // Prefer the 'old' value from changes array (from update_marketplace_handlers/update_marketplace_prices)
                // Otherwise get from listing table
                if (isset($values['old'])) {
                    $actualOldValue = $values['old'];
                } else {
                    // Get from listing table - this is the true old value from database
                    $actualOldValue = $listing->$listingField;
                }
                // Set it in the state so we have the baseline for future changes
                $state->$stateField = $actualOldValue;
                $needsSave = true;
            } else {
                // Use current state value as old value
                $actualOldValue = $state->$stateField;
            }

            // Store explicit old value for this field
            $explicitOldValues[$stateField] = $actualOldValue;
        }

        // Save state if we updated any null values
        if ($needsSave) {
            $state->save();
        }

        // Prepare data for state update
        $stateData = [];
        foreach ($changes as $field => $values) {
            $stateData[$field] = $values['new'];
        }

        // Update state and track changes with explicit old values and row snapshot
        $state->updateState($stateData, $changeType, $reason, $explicitOldValues, $rowSnapshot);
    }

    /**
     * Update marketplace-level handlers (bulk update for all listings in a marketplace)
     * V2 version with change tracking
     */
    public function update_marketplace_handlers($variationId, $marketplaceId, Request $request)
    {
        $variation = Variation_model::find($variationId);
        if (!$variation) {
            return response()->json(['error' => 'Variation not found'], 404);
        }

        $minHandler = $request->input('all_min_handler');
        $priceHandler = $request->input('all_handler');

        // Get all listings for this variation and marketplace
        // V1 Pattern: Only update EUR listings (currency_id = 4, country = 73)
        $listings = Listing_model::where('variation_id', $variationId)
            ->where('marketplace_id', $marketplaceId)
            ->where('currency_id', 4) // EUR currency only (matches V1 behavior)
            ->get();

        $updatedCount = 0;
        $changes = [];

        foreach ($listings as $listing) {
            $listingChanges = [];
            $updateData = [];
            $countryId = $listing->country;

            // Update min_price_limit (min_handler) if provided
            if ($minHandler !== null) {
                $oldMinLimit = $listing->min_price_limit;
                if ($oldMinLimit != $minHandler) {
                    $updateData['min_price_limit'] = $minHandler;
                    $listingChanges['min_handler'] = [
                        'old' => $oldMinLimit,
                        'new' => $minHandler
                    ];
                }
            }

            // Update price_limit (price_handler) if provided
            if ($priceHandler !== null) {
                $oldPriceLimit = $listing->price_limit;
                if ($oldPriceLimit != $priceHandler) {
                    $updateData['price_limit'] = $priceHandler;
                    $listingChanges['price_handler'] = [
                        'old' => $oldPriceLimit,
                        'new' => $priceHandler
                    ];
                }
            }

            // Update handler_status
            if (!empty($updateData)) {
                if (($updateData['min_price_limit'] ?? $listing->min_price_limit) == null &&
                    ($updateData['price_limit'] ?? $listing->price_limit) == null) {
                    $updateData['handler_status'] = 0;
                } else {
                    $updateData['handler_status'] = 1;
                }

                // Capture snapshot BEFORE updating the listing
                $rowSnapshot = $this->captureListingSnapshot($listing);

                $listing->fill($updateData);
                $listing->save();
                $updatedCount++;

                // Track changes for this listing with pre-captured snapshot
                if (!empty($listingChanges)) {
                    $this->trackListingChanges(
                        $variationId,
                        $marketplaceId,
                        $listing->id,
                        $listing->country,
                        $listingChanges,
                        'bulk',
                        'Bulk handler update from marketplace bar',
                        $rowSnapshot
                    );
                }
            }
        }

        return response()->json([
            'success' => true,
            'updated_count' => $updatedCount,
            'message' => "Updated {$updatedCount} listing(s)"
        ]);
    }

    /**
     * Update marketplace-level prices (bulk update for all listings in a marketplace)
     * V2 version with change tracking
     */
    public function update_marketplace_prices($variationId, $marketplaceId, Request $request)
    {
        $variation = Variation_model::find($variationId);
        if (!$variation) {
            return response()->json(['error' => 'Variation not found'], 404);
        }

        $minPrice = $request->input('all_min_price');
        $price = $request->input('all_price');

        // Get all listings for this variation and marketplace
        // V1 Pattern: Only update EUR listings (currency_id = 4, country = 73)
        $listings = Listing_model::where('variation_id', $variationId)
            ->where('marketplace_id', $marketplaceId)
            ->where('currency_id', 4) // EUR currency only (matches V1 behavior)
            ->with(['currency', 'country_id'])
            ->get();

        $updatedCount = 0;
        $bm = new BackMarketAPIController();

        foreach ($listings as $listing) {
            $listingChanges = [];
            $updateData = [];

            // Update min_price if provided
            if ($minPrice !== null) {
                $oldMinPrice = $listing->min_price;
                if ($oldMinPrice != $minPrice) {
                    $updateData['min_price'] = $minPrice;
                    $listingChanges['min_price'] = [
                        'old' => $oldMinPrice,
                        'new' => $minPrice
                    ];
                }
            }

            // Update price if provided
            if ($price !== null) {
                $oldPrice = $listing->price;
                if ($oldPrice != $price) {
                    $updateData['price'] = $price;
                    $listingChanges['price'] = [
                        'old' => $oldPrice,
                        'new' => $price
                    ];
                }
            }

            // Update listing if there are changes
            if (!empty($updateData)) {
                // Capture snapshot BEFORE updating the listing
                $rowSnapshot = $this->captureListingSnapshot($listing);

                $listing->fill($updateData);
                $listing->save();
                $updatedCount++;

                // Update BackMarket API
                if ($variation->reference_id && $listing->country_id) {
                    $currencyCode = $listing->currency ? $listing->currency->code : 'EUR';
                    $marketCode = $listing->country_id->market_code ?? null;

                    $apiPayload = [];
                    if (isset($updateData['min_price'])) {
                        $apiPayload['min_price'] = $updateData['min_price'];
                    }
                    if (isset($updateData['price'])) {
                        $apiPayload['price'] = $updateData['price'];
                    }
                    $apiPayload['currency'] = $currencyCode;

                    if (!empty($apiPayload)) {
                        $bm->updateOneListing($variation->reference_id, json_encode($apiPayload), $marketCode);
                    }
                }

                // Track changes for this listing with pre-captured snapshot
                if (!empty($listingChanges)) {
                    $this->trackListingChanges(
                        $variationId,
                        $marketplaceId,
                        $listing->id,
                        $listing->country,
                        $listingChanges,
                        'bulk',
                        'Bulk price update from marketplace bar',
                        $rowSnapshot
                    );
                }
            }
        }

        return response()->json([
            'success' => true,
            'updated_count' => $updatedCount,
            'message' => "Updated {$updatedCount} listing(s)"
        ]);
    }

    /**
     * Track buybox changes (called from API sync or other sources)
     */
    public function trackBuyboxChange($listingId, $buybox, $buyboxPrice = null)
    {
        $listing = Listing_model::find($listingId);
        if (!$listing) {
            return;
        }

        $variationId = $listing->variation_id;
        $marketplaceId = $listing->marketplace_id;
        $countryId = $listing->country;

        $changes = [];

        // Track buybox change
        $oldBuybox = $listing->buybox;
        if ($oldBuybox != $buybox) {
            $changes['buybox'] = [
                'old' => $oldBuybox,
                'new' => $buybox
            ];
            $listing->buybox = $buybox;
        }

        // Track buybox_price change
        if ($buyboxPrice !== null) {
            $oldBuyboxPrice = $listing->buybox_price;
            if ($oldBuyboxPrice != $buyboxPrice) {
                $changes['buybox_price'] = [
                    'old' => $oldBuyboxPrice,
                    'new' => $buyboxPrice
                ];
                $listing->buybox_price = $buyboxPrice;
            }
        }

        if (!empty($changes)) {
            $listing->save();
            $this->trackListingChanges($variationId, $marketplaceId, $listingId, $countryId, $changes, 'auto', 'Buybox update from API');
        }
    }

    /**
     * Get updated stock quantity from Backmarket API (V2 endpoint)
     * Uses ListingDataService for service layer architecture
     * 
     * @param int $variationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUpdatedQuantity(int $variationId)
    {
        try {
            $result = $this->dataService->getBackmarketStockQuantity($variationId);
            
            return response()->json([
                'success' => $result['updated'],
                'quantity' => $result['quantity'],
                'sku' => $result['sku'],
                'state' => $result['state'],
                'total_stock' => $result['total_stock'] ?? null, // Include total stock for frontend update
                'error' => $result['error'] ?? null
            ]);
        } catch (\Exception $e) {
            Log::error("V2 getUpdatedQuantity error: " . $e->getMessage(), [
                'variation_id' => $variationId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'quantity' => 0,
                'error' => 'Error fetching stock quantity'
            ], 500);
        }
    }

    /**
     * Get all marketplace stock data for comparison (for stock difference modal)
     * 
     * @param int $variationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMarketplaceStockComparison(int $variationId)
    {
        try {
            // Load available_stocks so "available" matches variation card (inventory count, not marketplace_stock.available_stock)
            $variation = Variation_model::with(['product', 'color_id', 'storage_id', 'grade_id', 'available_stocks'])->find($variationId);
            if (!$variation) {
                return response()->json([
                    'success' => false,
                    'error' => 'Variation not found'
                ], 404);
            }

            // Available stock = physical inventory count (same as variation card) – same for all marketplaces
            $variationAvailableCount = ($variation->available_stocks ?? collect())->count();

            // Get all marketplaces
            $marketplaces = Marketplace_model::all()->keyBy('id');
            
            // Get all marketplace stocks for this variation
            $marketplaceStocks = MarketplaceStockModel::where('variation_id', $variationId)
                ->with('marketplace')
                ->get()
                ->keyBy('marketplace_id');

            // Get Backmarket API stock (only for marketplace 1)
            $apiStock = null;
            try {
                $apiResult = $this->dataService->getBackmarketStockQuantity($variationId);
                if ($apiResult['updated'] && isset($apiResult['quantity'])) {
                    $apiStock = (int) $apiResult['quantity'];
                }
            } catch (\Exception $e) {
                // API stock fetch failed, continue without it
                Log::warning("Failed to fetch API stock for comparison: " . $e->getMessage());
            }

            // Build comparison data
            $comparisonData = [];
            $totalListedStock = 0;
            $totalLockedStock = 0;

            foreach ($marketplaces as $marketplaceId => $marketplace) {
                $marketplaceIdInt = (int) $marketplaceId;
                $marketplaceStock = $marketplaceStocks->get($marketplaceIdInt);
                
                $listedStock = $marketplaceStock ? (int) ($marketplaceStock->listed_stock ?? 0) : 0;
                // Available stock comes from inventory (variation-level), same as variation card – do NOT use marketplace_stock.available_stock
                $availableStock = $variationAvailableCount;

                // Get listing count for this marketplace
                $listingCount = Listing_model::where('variation_id', $variationId)
                    ->where('marketplace_id', $marketplaceIdInt)
                    ->count();

                $comparisonData[] = [
                    'marketplace_id' => $marketplaceIdInt,
                    'marketplace_name' => $marketplace->name ?? 'Marketplace ' . $marketplaceIdInt,
                    'listed_stock' => $listedStock,
                    'available_stock' => $availableStock,
                    'locked_stock' => 0, // Stock lock system removed
                    'listing_count' => $listingCount,
                    'is_backmarket' => $marketplaceIdInt === 1
                ];

                $totalListedStock += $listedStock;
                // Stock lock system removed - locked stock always 0
            }

            $totalAvailableStock = $variationAvailableCount;

            // Get total stock from variation (this is the total stock we have in the system)
            $totalStock = (int) ($variation->listed_stock ?? 0);

            // Get variation details for display
            $productModel = $variation->product->model ?? 'N/A';
            $storageName = $variation->storage_id->name ?? 'N/A';
            $colorName = $variation->color_id->name ?? 'N/A';
            $gradeName = $variation->grade_id->name ?? 'N/A';

            // Check if default formulas are applied globally (check if any marketplace has global default formula)
            $hasGlobalDefaults = false;
            try {
                $globalDefaults = \App\Models\MarketplaceDefaultFormula::where('is_active', true)->exists();
                $hasGlobalDefaults = $globalDefaults;
            } catch (\Exception $e) {
                // If table doesn't exist or error, assume false
                $hasGlobalDefaults = false;
            }

            return response()->json([
                'success' => true,
                'variation_id' => $variationId,
                'variation_sku' => $variation->sku ?? '',
                'variation_details' => [
                    'sku' => $variation->sku ?? '',
                    'model' => $productModel,
                    'storage' => $storageName,
                    'color' => $colorName,
                    'grade' => $gradeName,
                ],
                'is_global' => $hasGlobalDefaults, // Whether default formulas are applied globally
                'total_stock' => $totalStock, // Total stock we have in the system
                'api_stock' => $apiStock,
                'marketplaces' => $comparisonData,
                'totals' => [
                    'listed_stock' => $totalListedStock, // Sum of all marketplace listed stocks
                    'available_stock' => $totalAvailableStock, // Physical inventory count (same as variation card)
                    'locked_stock' => 0 // Stock lock system removed
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("V2 getMarketplaceStockComparison error: " . $e->getMessage(), [
                'variation_id' => $variationId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error fetching stock comparison data'
            ], 500);
        }
    }

    /**
     * Fix stock mismatches for a variation
     * Syncs marketplace stocks with API and parent stock
     * 
     * @param int $variationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function fixStockMismatch(int $variationId)
    {
        try {
            DB::beginTransaction();
            
            $variation = Variation_model::find($variationId);
            if (!$variation) {
                return response()->json([
                    'success' => false,
                    'error' => 'Variation not found'
                ], 404);
            }

            // Get all marketplace stocks
            $marketplaceStocks = MarketplaceStockModel::where('variation_id', $variationId)
                ->get();

            // Get Backmarket API stock (only for marketplace 1)
            $apiStock = null;
            try {
                $apiResult = $this->dataService->getBackmarketStockQuantity($variationId);
                if ($apiResult['updated'] && isset($apiResult['quantity'])) {
                    $apiStock = (int) $apiResult['quantity'];
                }
            } catch (\Exception $e) {
                Log::warning("Failed to fetch API stock for fix: " . $e->getMessage());
            }

            $fixes = [];
            $sumListedStock = 0;

            // Fix each marketplace stock
            foreach ($marketplaceStocks as $ms) {
                $marketplaceId = $ms->marketplace_id;
                $oldListedStock = (int)($ms->listed_stock ?? 0);
                $lockedStock = (int)($ms->locked_stock ?? 0);
                $newListedStock = $oldListedStock;
                $needsFix = false;

                // If Backmarket (marketplace 1) and API stock is available, use API stock
                if ($marketplaceId == 1 && $apiStock !== null) {
                    $newListedStock = $apiStock;
                    if ($newListedStock != $oldListedStock) {
                        $needsFix = true;
                        $fixes[] = [
                            'marketplace_id' => $marketplaceId,
                            'field' => 'listed_stock',
                            'old_value' => $oldListedStock,
                            'new_value' => $newListedStock,
                            'reason' => 'Synced with API'
                        ];
                    }
                }

                // NOTE: Available stock should NOT be calculated from listed_stock - locked_stock
                // Available stock comes from inventory (variation-level physical stock count)
                // It should be the SAME for all marketplaces and should not be changed by sync operations
                // The marketplace_stock.available_stock field may exist for internal tracking,
                // but for DISPLAY purposes, we use variation->available_stocks count (from inventory)
                
                // Update if needed (only listed_stock, NOT available_stock)
                if ($needsFix) {
                    $ms->listed_stock = $newListedStock;
                    // DO NOT update available_stock - it comes from inventory, not from marketplace calculations
                    // $ms->available_stock = $newAvailableStock; // REMOVED - wrong logic
                    $ms->admin_id = session('user_id');
                    $ms->save();
                }

                $sumListedStock += $newListedStock;
            }

            // Update parent total stock to match sum of marketplace stocks
            $oldParentStock = (int)($variation->listed_stock ?? 0);
            if ($sumListedStock != $oldParentStock) {
                $variation->listed_stock = $sumListedStock;
                $variation->save();
                
                $fixes[] = [
                    'marketplace_id' => null,
                    'field' => 'variation.listed_stock',
                    'old_value' => $oldParentStock,
                    'new_value' => $sumListedStock,
                    'reason' => 'Synced with sum of marketplace stocks'
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock mismatches fixed successfully',
                'variation_id' => $variationId,
                'fixes_applied' => count($fixes),
                'fixes' => $fixes,
                'summary' => [
                    'parent_stock_before' => $oldParentStock,
                    'parent_stock_after' => $sumListedStock,
                    'api_stock' => $apiStock
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("V2 fixStockMismatch error: " . $e->getMessage(), [
                'variation_id' => $variationId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error fixing stock mismatch: ' . $e->getMessage()
            ], 500);
        }
    }
}
