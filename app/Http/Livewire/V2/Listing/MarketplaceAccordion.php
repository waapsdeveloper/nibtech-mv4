<?php

namespace App\Http\Livewire\V2\Listing;

use App\Models\Variation_model;
use App\Models\Stock_model;
use App\Models\Order_item_model;
use App\Models\Order_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;
use App\Models\Customer_model;
use App\Services\V2\ListingCalculationService;
use Livewire\Component;

class MarketplaceAccordion extends Component
{
    public int $variationId;
    public int $marketplaceId;
    public string $marketplaceName;
    
    public ?Variation_model $variation = null;
    public bool $ready = false;
    public bool $expanded = false;
    public bool $showAllStocks = false; // Toggle for showing all stocks vs marketplace-specific
    
    public $stocks = [];
    public $allStocks = []; // All stocks in system for this variation
    public $listings = [];
    public $stockCosts = [];
    public $vendors = [];
    public $po = [];
    public $reference = [];
    public $latestTopupItems = [];
    public $topupReference = [];
    public float $breakevenPrice = 0.0;
    public float $averageCost = 0.0;
    
    // Order summary
    public array $orderSummary = [
        'today_count' => 0,
        'today_total' => 0.0,
        'yesterday_count' => 0,
        'yesterday_total' => 0.0,
        'last_7_days_count' => 0,
        'last_7_days_total' => 0.0,
        'last_30_days_count' => 0,
        'last_30_days_total' => 0.0,
        'pending_count' => 0,
    ];

    // Marketplace header metrics
    public array $headerMetrics = [
        'changes_count' => 0,
        'handlers_active' => 0,
        'handlers_inactive' => 0,
        'prices_min' => 0.0,
        'prices_max' => 0.0,
        'prices_avg' => 0.0,
        'buybox_count' => 0,
        'buybox_without_count' => 0,
    ];
    
    public bool $showHeaderDetails = false; // Toggle for second row

    // Reference data
    public array $exchangeRates = [];
    public float $eurGbp = 0;
    public array $currencies = [];
    public array $currencySign = [];
    public array $countries = [];
    public array $marketplaces = [];

    protected ListingCalculationService $calculationService;

    public function boot(ListingCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    public function mount(
        int $variationId,
        int $marketplaceId,
        string $marketplaceName,
        array $exchangeRates,
        float $eurGbp,
        array $currencies,
        array $currencySign,
        array $countries,
        array $marketplaces
    ): void {
        $this->variationId = $variationId;
        $this->marketplaceId = $marketplaceId;
        $this->marketplaceName = $marketplaceName;
        $this->exchangeRates = $exchangeRates;
        $this->eurGbp = $eurGbp;
        $this->currencies = $currencies;
        $this->currencySign = $currencySign;
        $this->countries = $countries;
        $this->marketplaces = $marketplaces;
    }

    /**
     * Load marketplace-specific data when accordion is expanded
     */
    public function loadMarketplaceData(): void
    {
        if ($this->ready) {
            return;
        }

        // Load variation with relationships
        $this->variation = Variation_model::with([
            'listings' => function($query) {
                $query->where('marketplace_id', $this->marketplaceId);
            },
            'listings.country_id',
            'listings.currency',
            'listings.marketplace',
            'available_stocks',
        ])->find($this->variationId);

        if ($this->variation) {
            // Get listings for this marketplace and format them
            $this->listings = $this->variation->listings
                ->where('marketplace_id', $this->marketplaceId)
                ->map(function($listing) {
                    return [
                        'id' => $listing->id,
                        'country' => $listing->country,
                        'country_id' => $listing->country_id ? (is_object($listing->country_id) ? $listing->country_id->id : $listing->country_id) : null,
                        'marketplace_id' => $listing->marketplace_id,
                        'currency_id' => $listing->currency_id,
                        'min_price' => $listing->min_price,
                        'price' => $listing->price,
                        'min_price_limit' => $listing->min_price_limit,
                        'price_limit' => $listing->price_limit,
                        'buybox' => $listing->buybox,
                        'buybox_price' => $listing->buybox_price,
                        'buybox_winner_price' => $listing->buybox_winner_price ?? null,
                        'target_price' => $listing->target_price,
                        'target_percentage' => $listing->target_percentage,
                        'handler_status' => $listing->handler_status,
                        'reference_uuid' => $listing->reference_uuid,
                        'reference_uuid_2' => $listing->reference_uuid_2,
                        'updated_at' => $listing->updated_at?->toDateTimeString(),
                    ];
                })
                ->values()
                ->toArray();

            // Get marketplace-specific stocks (available stocks ready for listing)
            $marketplaceStocks = Stock_model::where('variation_id', $this->variationId)
                ->where('status', 1)
                ->whereHas('latest_listing_or_topup')
                ->with(['order'])
                ->get();

            // Get all stocks in system for this variation (regardless of status)
            $allStocks = Stock_model::where('variation_id', $this->variationId)
                ->with(['order'])
                ->get();

            $this->allStocks = $allStocks->map(function($stock) {
                return [
                    'id' => $stock->id,
                    'imei' => $stock->imei ?? $stock->serial ?? '',
                    'serial' => $stock->serial ?? '',
                    'order_id' => $stock->order_id ?? null,
                    'status' => $stock->status,
                ];
            })->toArray();

            // Set stocks based on toggle
            $stocks = $this->showAllStocks ? $allStocks : $marketplaceStocks;
            
            $this->stocks = $stocks->map(function($stock) {
                return [
                    'id' => $stock->id,
                    'imei' => $stock->imei ?? $stock->serial ?? '',
                    'serial' => $stock->serial ?? '',
                    'order_id' => $stock->order_id ?? null,
                ];
            })->toArray();

            // Get stock costs for the displayed stocks
            $stockIds = $stocks->pluck('id');
            $stockCosts = Order_item_model::whereHas('order', function($q) {
                $q->where('order_type_id', 1);
            })->whereIn('stock_id', $stockIds)->pluck('price', 'stock_id');

            $this->stockCosts = $stockCosts->toArray();
            
            // Update latest topup items for displayed stocks
            $latestTopupItems = Process_stock_model::whereIn('process_id', array_keys($this->topupReference))
                ->whereIn('stock_id', $stockIds)
                ->pluck('process_id', 'stock_id')
                ->toArray();
            $this->latestTopupItems = $latestTopupItems;

            // Get vendors
            $this->vendors = Customer_model::whereNotNull('is_vendor')->pluck('last_name', 'id')->toArray();

            // Get PO data
            $this->po = Order_model::where('order_type_id', 1)->pluck('customer_id', 'id')->toArray();
            $this->reference = Order_model::where('order_type_id', 1)->pluck('reference_id', 'id')->toArray();

            // Get topup data
            $topupReference = Process_model::whereIn('process_type_id', [21, 22])->pluck('reference_id', 'id')->toArray();
            $this->topupReference = $topupReference;

            // Calculate breakeven price
            if ($stockCosts->count() > 0) {
                $this->breakevenPrice = ($stockCosts->average() + 20) / 0.88;
            }

            // Calculate average cost
            $this->averageCost = $this->calculationService->calculateAverageCost($stocks);

            // Calculate order summary for this marketplace
            $this->calculateOrderSummary();

            // Calculate header metrics
            $this->calculateHeaderMetrics();

            $this->ready = true;
        }
    }

    /**
     * Calculate header metrics (changes, handlers, prices, buybox)
     */
    private function calculateHeaderMetrics(): void
    {
        if (empty($this->listings)) {
            return;
        }

        // Changes: Count listings updated in last 24 hours
        $this->headerMetrics['changes_count'] = collect($this->listings)->filter(function($listing) {
            if (!isset($listing['updated_at'])) {
                return false;
            }
            $updatedAt = \Carbon\Carbon::parse($listing['updated_at']);
            return $updatedAt->isAfter(now()->subDay());
        })->count();

        // Handlers: Count active (status 1) vs inactive (status 2)
        $this->headerMetrics['handlers_active'] = collect($this->listings)->filter(function($listing) {
            return ($listing['handler_status'] ?? 1) == 1;
        })->count();
        
        $this->headerMetrics['handlers_inactive'] = collect($this->listings)->filter(function($listing) {
            return ($listing['handler_status'] ?? 1) == 2;
        })->count();

        // Prices: Calculate min, max, average
        $prices = collect($this->listings)->pluck('price')->filter(function($price) {
            return $price > 0;
        });
        
        if ($prices->isNotEmpty()) {
            $this->headerMetrics['prices_min'] = round($prices->min(), 2);
            $this->headerMetrics['prices_max'] = round($prices->max(), 2);
            $this->headerMetrics['prices_avg'] = round($prices->avg(), 2);
        }

        // Buybox: Count with buybox (1) vs without (0)
        $this->headerMetrics['buybox_count'] = collect($this->listings)->filter(function($listing) {
            return ($listing['buybox'] ?? 0) == 1;
        })->count();
        
        $this->headerMetrics['buybox_without_count'] = collect($this->listings)->filter(function($listing) {
            return ($listing['buybox'] ?? 0) != 1;
        })->count();
    }

    /**
     * Toggle header details row
     */
    public function toggleHeaderDetails(): void
    {
        $this->showHeaderDetails = !$this->showHeaderDetails;
    }

    /**
     * Calculate order summary for this marketplace
     */
    private function calculateOrderSummary(): void
    {
        // Today's orders
        $todayOrders = Order_item_model::where('variation_id', $this->variationId)
            ->whereHas('order', function($q) {
                $q->where('marketplace_id', $this->marketplaceId)
                  ->where('order_type_id', 3)
                  ->whereBetween('created_at', [now()->startOfDay(), now()]);
            })
            ->get();

        $this->orderSummary['today_count'] = $todayOrders->count();
        $this->orderSummary['today_total'] = $todayOrders->sum('price');

        // Yesterday's orders
        $yesterdayOrders = Order_item_model::where('variation_id', $this->variationId)
            ->whereHas('order', function($q) {
                $q->where('marketplace_id', $this->marketplaceId)
                  ->where('order_type_id', 3)
                  ->whereBetween('created_at', [now()->yesterday()->startOfDay(), now()->yesterday()->endOfDay()]);
            })
            ->get();

        $this->orderSummary['yesterday_count'] = $yesterdayOrders->count();
        $this->orderSummary['yesterday_total'] = $yesterdayOrders->sum('price');

        // Last 7 days orders
        $last7DaysOrders = Order_item_model::where('variation_id', $this->variationId)
            ->whereHas('order', function($q) {
                $q->where('marketplace_id', $this->marketplaceId)
                  ->where('order_type_id', 3)
                  ->whereBetween('created_at', [now()->subDays(7)->startOfDay(), now()->yesterday()->endOfDay()]);
            })
            ->get();

        $this->orderSummary['last_7_days_count'] = $last7DaysOrders->count();
        $this->orderSummary['last_7_days_total'] = $last7DaysOrders->sum('price');

        // Last 30 days orders
        $last30DaysOrders = Order_item_model::where('variation_id', $this->variationId)
            ->whereHas('order', function($q) {
                $q->where('marketplace_id', $this->marketplaceId)
                  ->where('order_type_id', 3)
                  ->whereBetween('created_at', [now()->subDays(30)->startOfDay(), now()->yesterday()->endOfDay()]);
            })
            ->get();

        $this->orderSummary['last_30_days_count'] = $last30DaysOrders->count();
        $this->orderSummary['last_30_days_total'] = $last30DaysOrders->sum('price');

        // Pending orders (orders with status 2)
        $pendingOrders = \App\Models\Order_model::whereHas('order_items', function($q) {
                $q->where('variation_id', $this->variationId);
            })
            ->where('marketplace_id', $this->marketplaceId)
            ->where('status', 2)
            ->where('order_type_id', 3)
            ->count();

        $this->orderSummary['pending_count'] = $pendingOrders;
    }

    /**
     * Toggle between marketplace-specific stocks and all stocks
     */
    public function toggleStocksView(): void
    {
        $this->showAllStocks = !$this->showAllStocks;
        
        if ($this->ready && $this->variation) {
            // Get marketplace-specific stocks (available stocks)
            $marketplaceStocks = Stock_model::where('variation_id', $this->variationId)
                ->where('status', 1)
                ->whereHas('latest_listing_or_topup')
                ->with(['order'])
                ->get();

            // Get all stocks in system
            $allStocks = Stock_model::where('variation_id', $this->variationId)
                ->with(['order'])
                ->get();

            // Set stocks based on toggle
            $stocks = $this->showAllStocks ? $allStocks : $marketplaceStocks;

            $this->stocks = $stocks->map(function($stock) {
                return [
                    'id' => $stock->id,
                    'imei' => $stock->imei ?? $stock->serial ?? '',
                    'serial' => $stock->serial ?? '',
                    'order_id' => $stock->order_id ?? null,
                    'status' => $stock->status ?? 1,
                ];
            })->toArray();

            // Recalculate stock costs for displayed stocks
            $stockIds = $stocks->pluck('id');
            $stockCosts = Order_item_model::whereHas('order', function($q) {
                $q->where('order_type_id', 1);
            })->whereIn('stock_id', $stockIds)->pluck('price', 'stock_id');
            $this->stockCosts = $stockCosts->toArray();
            
            // Update latest topup items
            $latestTopupItems = Process_stock_model::whereIn('process_id', array_keys($this->topupReference))
                ->whereIn('stock_id', $stockIds)
                ->pluck('process_id', 'stock_id')
                ->toArray();
            $this->latestTopupItems = $latestTopupItems;

            // Recalculate average cost (only for available stocks)
            $availableStocks = $stocks->where('status', 1);
            $this->averageCost = $this->calculationService->calculateAverageCost($availableStocks);
        }
    }

    public function toggleAccordion(): void
    {
        $this->expanded = !$this->expanded;
        
        if ($this->expanded && !$this->ready) {
            $this->loadMarketplaceData();
        }
    }
    
    /**
     * Auto-expand and load data (called from JavaScript)
     */
    public function autoExpand(): void
    {
        if (!$this->expanded) {
            $this->expanded = true;
            if (!$this->ready) {
                $this->loadMarketplaceData();
            }
        }
    }

    public function render()
    {
        return view('livewire.v2.listing.marketplace-accordion');
    }
}

