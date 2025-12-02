<?php

namespace App\Http\Livewire\V2\Listing;

use App\Models\Variation_model;
use App\Services\V2\ListingCalculationService;
use Livewire\Component;

class ListingItem extends Component
{
    public int $variationId;
    public int $rowNumber;
    
    public ?Variation_model $variation = null;
    public bool $ready = false;
    public bool $detailsExpanded = false;
    
    public array $stats = [];
    public array $pricingInfo = [];
    public float $averageCost = 0.0;
    public int $totalOrdersCount = 0;
    public array $buyboxListings = [];
    public array $marketplaceSummaries = []; // Marketplace ID => summary data

    // Reference data (passed from parent)
    public array $storages = [];
    public array $colors = [];
    public array $grades = [];
    public array $exchangeRates = [];
    public float $eurGbp = 0;
    public array $currencies = [];
    public array $currencySign = [];
    public array $countries = [];
    public array $marketplaces = [];
    public ?string $processId = null;

    protected ListingCalculationService $calculationService;

    public function boot(ListingCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    public function mount(
        int $variationId,
        int $rowNumber,
        array $storages,
        array $colors,
        array $grades,
        array $exchangeRates,
        float $eurGbp,
        array $currencies,
        array $currencySign,
        array $countries,
        array $marketplaces,
        ?string $processId = null,
        ?array $preloadedVariationData = null
    ): void {
        $this->variationId = $variationId;
        $this->rowNumber = $rowNumber;
        $this->storages = $storages;
        $this->colors = $colors;
        $this->grades = $grades;
        $this->exchangeRates = $exchangeRates;
        $this->eurGbp = $eurGbp;
        $this->currencies = $currencies;
        $this->currencySign = $currencySign;
        $this->countries = $countries;
        $this->marketplaces = $marketplaces;
        $this->processId = $processId;
        
        // If preloaded data is available, use it immediately
        if ($preloadedVariationData !== null) {
            $this->initializeFromPreloadedData($preloadedVariationData);
        }
    }

    /**
     * Initialize component from preloaded variation data
     * Uses pre-calculated stats to avoid re-querying and re-calculating
     */
    private function initializeFromPreloadedData(array $data): void
    {
        // Reconstruct Variation model from preloaded data array
        $variationArray = $data['variation_data'] ?? [];
        if (empty($variationArray)) {
            return;
        }
        
        // Create model instance from array using forceFill to bypass mass assignment protection
        $this->variation = new Variation_model();
        $this->variation->forceFill($variationArray);
        $this->variation->exists = true; // Mark as existing to avoid save attempts
        
        // Set relationships from array data
        if (isset($variationArray['product']) && is_array($variationArray['product'])) {
            $product = new \App\Models\Products_model();
            $product->forceFill($variationArray['product']);
            $product->exists = true;
            $this->variation->setRelation('product', $product);
        }
        if (isset($variationArray['storage_id']) && is_array($variationArray['storage_id'])) {
            $storage = new \App\Models\Storage_model();
            $storage->forceFill($variationArray['storage_id']);
            $storage->exists = true;
            $this->variation->setRelation('storage_id', $storage);
        }
        if (isset($variationArray['color_id']) && is_array($variationArray['color_id'])) {
            $color = new \App\Models\Color_model();
            $color->forceFill($variationArray['color_id']);
            $color->exists = true;
            $this->variation->setRelation('color_id', $color);
        }
        if (isset($variationArray['grade_id']) && is_array($variationArray['grade_id'])) {
            $grade = new \App\Models\Grade_model();
            $grade->forceFill($variationArray['grade_id']);
            $grade->exists = true;
            $this->variation->setRelation('grade_id', $grade);
        }
        
        // Set listings relationship
        if (isset($variationArray['listings']) && is_array($variationArray['listings'])) {
            $listings = collect($variationArray['listings'])->map(function($listing) {
                $listingModel = new \App\Models\Listing_model();
                $listingModel->forceFill($listing);
                $listingModel->exists = true;
                if (isset($listing['country_id']) && is_array($listing['country_id'])) {
                    $country = new \App\Models\Country_model();
                    $country->forceFill($listing['country_id']);
                    $country->exists = true;
                    $listingModel->setRelation('country_id', $country);
                }
                return $listingModel;
            });
            $this->variation->setRelation('listings', $listings);
        }
        
        // Set available_stocks and pending_orders
        if (isset($variationArray['available_stocks']) && is_array($variationArray['available_stocks'])) {
            $stocks = collect($variationArray['available_stocks'])->map(function($stock) {
                $stockModel = new \App\Models\Stock_model();
                $stockModel->forceFill($stock);
                $stockModel->exists = true;
                return $stockModel;
            });
            $this->variation->setRelation('available_stocks', $stocks);
        }
        if (isset($variationArray['pending_orders']) && is_array($variationArray['pending_orders'])) {
            $orders = collect($variationArray['pending_orders'])->map(function($order) {
                $orderModel = new \App\Models\Order_item_model();
                $orderModel->forceFill($order);
                $orderModel->exists = true;
                return $orderModel;
            });
            $this->variation->setRelation('pending_orders', $orders);
        }
        
        // Use pre-calculated stats if available
        if (isset($data['calculated_stats'])) {
            $calculated = $data['calculated_stats'];
            $this->stats = $calculated['stats'] ?? [];
            $this->pricingInfo = $calculated['pricing_info'] ?? [];
            $this->averageCost = $calculated['average_cost'] ?? 0.0;
            $this->totalOrdersCount = $calculated['total_orders_count'] ?? 0;
            $this->buyboxListings = $calculated['buybox_listings'] ?? [];
            $this->marketplaceSummaries = $calculated['marketplace_summaries'] ?? [];
        } else {
            // Fallback: calculate stats if not pre-calculated
            $this->calculateStats();
        }
        
        $this->ready = true;
        $this->detailsExpanded = false;
    }

    /**
     * Load variation data lazily when component is initialized (fallback if no preloaded data)
     */
    public function loadRow(): void
    {
        if ($this->ready) {
            return;
        }

        // Load variation with all necessary relationships
        $this->variation = Variation_model::with([
            'listings',
            'listings.country_id',
            'listings.currency',
            'listings.marketplace',
            'product',
            'available_stocks',
            'pending_orders',
            'storage_id',
            'color_id',
            'grade_id',
        ])->find($this->variationId);

        if ($this->variation) {
            $this->calculateStats();
            $this->ready = true;
            // Don't auto-expand - let user expand manually
            $this->detailsExpanded = false;
        }
    }

    /**
     * Calculate all stats from variation data
     */
    private function calculateStats(): void
    {
        if (!$this->variation) {
            return;
        }

        // Calculate stats using service
        $this->stats = $this->calculationService->calculateVariationStats($this->variation);
        
        // Calculate pricing info
        $this->pricingInfo = $this->calculationService->calculatePricingInfo(
            $this->variation->listings ?? collect(),
            $this->exchangeRates,
            $this->eurGbp
        );
        
        // Calculate average cost
        $this->averageCost = $this->calculationService->calculateAverageCost(
            $this->variation->available_stocks ?? collect()
        );
        
        // Calculate total orders count
        $this->totalOrdersCount = $this->calculationService->calculateTotalOrdersCount($this->variationId);
        
        // Calculate marketplace summaries
        $this->marketplaceSummaries = $this->calculationService->calculateMarketplaceSummaries(
            $this->variationId,
            $this->variation->listings ?? collect()
        );
        
        // Get buybox listings with country info for display
        if ($this->variation->listings) {
            $this->buyboxListings = $this->variation->listings
                ->where('buybox', 1)
                ->map(function($listing) {
                    $countryId = is_object($listing->country_id) ? $listing->country_id->id : ($listing->country_id ?? null);
                    return [
                        'id' => $listing->id,
                        'country_id' => $countryId,
                        'reference_uuid_2' => $listing->reference_uuid_2 ?? '',
                        'country' => is_object($listing->country_id) ? $listing->country_id : null,
                    ];
                })
                ->values()
                ->toArray();
        }
    }

    public function toggleDetails(): void
    {
        $this->detailsExpanded = !$this->detailsExpanded;
    }

    /**
     * Get all marketplace IDs that have listings for this variation
     */
    public function getMarketplaceIds(): array
    {
        if (!$this->variation || !$this->variation->listings) {
            return [];
        }

        // Ensure listings is a collection (it might be an array from preloaded data)
        $listings = is_array($this->variation->listings) 
            ? collect($this->variation->listings)
            : $this->variation->listings;

        return $listings
            ->pluck('marketplace_id')
            ->unique()
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Get all marketplaces from system (for accordion structure)
     * Returns array with marketplace ID as key and name as value
     */
    public function getAllMarketplaces(): array
    {
        $result = [];
        foreach ($this->marketplaces as $id => $marketplace) {
            if (is_object($marketplace)) {
                $result[$id] = [
                    'id' => $id,
                    'name' => $marketplace->name ?? 'Marketplace ' . $id
                ];
            } elseif (is_array($marketplace)) {
                $result[$id] = [
                    'id' => $id,
                    'name' => $marketplace['name'] ?? 'Marketplace ' . $id
                ];
            } else {
                $result[$id] = [
                    'id' => $id,
                    'name' => 'Marketplace ' . $id
                ];
            }
        }
        return $result;
    }

    public function render()
    {
        return view('livewire.v2.listing.listing-item');
    }
}

