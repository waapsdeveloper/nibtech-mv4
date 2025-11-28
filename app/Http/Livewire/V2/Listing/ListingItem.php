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
     * Since the data is already loaded in renderListingItems, we just load it here with minimal queries
     */
    private function initializeFromPreloadedData(array $data): void
    {
        // Load variation with relationships (data already loaded in renderListingItems, this is fast)
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
            // Calculate all stats immediately
            $this->calculateStats();
            $this->ready = true;
            $this->detailsExpanded = false;
        }
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

        return $this->variation->listings
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

