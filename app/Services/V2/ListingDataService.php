<?php

namespace App\Services\V2;

use App\Models\Color_model;
use App\Models\Country_model;
use App\Models\Currency_model;
use App\Models\Grade_model;
use App\Models\Marketplace_model;
use App\Models\Storage_model;
use App\Models\Variation_model;
use App\Models\V2\MarketplaceStockModel;
use App\Http\Controllers\BackMarketAPIController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ListingDataService
{
    /**
     * Get all dropdown/reference data for listings
     * Uses caching for performance
     */
    public function getReferenceData(): array
    {
        return Cache::remember('listing_reference_data', 3600, function () {
            return [
                'storages' => $this->getStorages(),
                'colors' => $this->getColors(),
                'grades' => $this->getGrades(),
                'currencies' => $this->getCurrencies(),
                'currency_sign' => $this->getCurrencySigns(),
                'countries' => $this->getCountries(),
                'marketplaces' => $this->getMarketplaces(),
            ];
        });
    }

    /**
     * Get storages data
     */
    private function getStorages(): array
    {
        $sessionStorages = session('dropdown_data')['storages'] ?? null;
        if ($sessionStorages) {
            return $sessionStorages->toArray();
        }

        return Storage_model::pluck('name', 'id')->toArray();
    }

    /**
     * Get colors data
     */
    private function getColors(): array
    {
        $sessionColors = session('dropdown_data')['colors'] ?? null;
        if ($sessionColors) {
            return $sessionColors->toArray();
        }

        return Color_model::pluck('name', 'id')->toArray();
    }

    /**
     * Get grades data (only active grades)
     */
    private function getGrades(): array
    {
        return Grade_model::where('id', '<', 6)->pluck('name', 'id')->toArray();
    }

    /**
     * Get currencies data
     */
    private function getCurrencies(): array
    {
        return Currency_model::pluck('code', 'id')->toArray();
    }

    /**
     * Get currency signs
     */
    private function getCurrencySigns(): array
    {
        return Currency_model::pluck('sign', 'id')->toArray();
    }

    /**
     * Get countries data as keyed array
     */
    private function getCountries(): array
    {
        return Country_model::all()->mapWithKeys(function ($country) {
            return [$country->id => $country];
        })->toArray();
    }

    /**
     * Get marketplaces data as keyed array
     */
    private function getMarketplaces(): array
    {
        return Marketplace_model::all()->mapWithKeys(function ($marketplace) {
            return [$marketplace->id => $marketplace];
        })->toArray();
    }

    /**
     * Clear reference data cache
     */
    public function clearCache(): void
    {
        Cache::forget('listing_reference_data');
    }

    /**
     * Get updated stock quantity from Backmarket API for a variation
     * Also updates marketplace stock and variation's listed_stock when syncing
     * 
     * @param int $variationId
     * @return array ['quantity' => int, 'sku' => string, 'state' => int, 'updated' => bool, 'error' => string|null]
     */
    public function getBackmarketStockQuantity(int $variationId): array
    {
        $variation = Variation_model::find($variationId);
        
        if (!$variation || !$variation->reference_id) {
            return [
                'quantity' => 0,
                'sku' => null,
                'state' => null,
                'updated' => false,
                'error' => 'Variation or reference_id not found'
            ];
        }
        
        try {
            $bm = new BackMarketAPIController();
            $apiResponse = $bm->getOneListing($variation->reference_id);
            
            // Handle both object and array responses (Backmarket API may return either)
            if ($apiResponse && (is_object($apiResponse) || is_array($apiResponse))) {
                $quantity = (int)(data_get($apiResponse, 'quantity') ?? $variation->listed_stock ?? 0);
                $sku = data_get($apiResponse, 'sku') ?? $variation->sku;
                $state = data_get($apiResponse, 'publication_state') ?? $variation->state;
                
                // Update marketplace stock (Backmarket = marketplace_id 1) and variation's listed_stock
                try {
                    // Get or create marketplace stock for Backmarket (marketplace_id = 1)
                    $marketplaceStock = MarketplaceStockModel::firstOrCreate(
                        [
                            'variation_id' => $variationId,
                            'marketplace_id' => 1
                        ],
                        [
                            'listed_stock' => 0,
                            'admin_id' => session('user_id') ?? 1
                        ]
                    );
                    
                    // IMPORTANT: Only update listed_stock from API (never touch manual_adjustment)
                    // listed_stock = API-synced stock
                    // manual_adjustment = manual pushes (separate, never synced)
                    // Total = listed_stock + manual_adjustment
                    $currentListedStock = (int)($marketplaceStock->listed_stock ?? 0);
                    $apiStock = (int)$quantity;
                    
                    if ($apiStock >= $currentListedStock) {
                        // Update marketplace stock with API quantity (only if API stock is higher or equal)
                        // This updates listed_stock only - manual_adjustment remains unchanged
                        $marketplaceStock->listed_stock = $apiStock;
                        $marketplaceStock->admin_id = session('user_id') ?? 1;
                        // NOTE: manual_adjustment is NOT touched - it's a separate offset
                        $marketplaceStock->save();
                        
                        // Calculate total stock: sum of listed_stock + sum of manual_adjustment
                        // listed_stock = API-synced stock
                        // manual_adjustment = manual pushes (separate, never synced)
                        $totalListedStock = MarketplaceStockModel::where('variation_id', $variationId)
                            ->sum('listed_stock');
                        $totalManualAdjustment = MarketplaceStockModel::where('variation_id', $variationId)
                            ->sum('manual_adjustment');
                        $totalStock = (int)$totalListedStock + (int)$totalManualAdjustment;
                        
                        // Update variation's listed_stock to reflect total (for backward compatibility)
                        $variation->listed_stock = $totalStock;
                        $variation->save();
                        
                        // Return total stock in response for frontend update
                        return [
                            'quantity' => $quantity,
                            'sku' => $sku,
                            'state' => $state,
                            'updated' => true,
                            'error' => null,
                            'total_stock' => $totalStock, // Include total stock for frontend update
                            'stock_updated' => true // Indicate that stock was actually updated
                        ];
                    } else {
                        // API stock is less than current - don't update, but still return current values
                        Log::info("API stock ({$apiStock}) is less than current listed stock ({$currentListedStock}) - skipping update", [
                            'variation_id' => $variationId,
                            'api_stock' => $apiStock,
                            'current_listed_stock' => $currentListedStock
                        ]);
                        
                        // Calculate total stock without updating (use current values)
                        $totalStock = MarketplaceStockModel::where('variation_id', $variationId)
                            ->sum('listed_stock');
                        
                        return [
                            'quantity' => $quantity,
                            'sku' => $sku,
                            'state' => $state,
                            'updated' => true, // API call was successful
                            'error' => null,
                            'total_stock' => $totalStock, // Return current total stock
                            'stock_updated' => false // Indicate that stock was NOT updated (API stock was lower)
                        ];
                    }
                } catch (\Exception $updateError) {
                    // Log error but don't fail the API call
                    Log::warning("Error updating stock during API sync: " . $updateError->getMessage(), [
                        'variation_id' => $variationId,
                        'error' => $updateError->getMessage()
                    ]);
                }
                
                return [
                    'quantity' => $quantity,
                    'sku' => $sku,
                    'state' => $state,
                    'updated' => true,
                    'error' => null,
                    'total_stock' => $variation->listed_stock ?? 0 // Fallback to variation's listed_stock
                ];
            }
            
            // API returned unexpected format - use DB values for badge, return success so frontend can update
            $quantity = (int)($variation->listed_stock ?? 0);
            $totalStock = (int)MarketplaceStockModel::where('variation_id', $variationId)->sum('listed_stock')
                + (int)MarketplaceStockModel::where('variation_id', $variationId)->sum('manual_adjustment');
            Log::warning("getBackmarketStockQuantity: Invalid API response format", [
                'variation_id' => $variationId,
                'response_type' => $apiResponse === null ? 'null' : gettype($apiResponse),
                'quantity_from_db' => $quantity
            ]);
            return [
                'quantity' => $quantity,
                'sku' => $variation->sku,
                'state' => $variation->state,
                'updated' => true, // Allow badge update with DB values
                'error' => null,
                'total_stock' => $totalStock > 0 ? $totalStock : $quantity
            ];
        } catch (\Exception $e) {
            Log::error("Error fetching Backmarket stock for variation {$variationId}: " . $e->getMessage(), [
                'variation_id' => $variationId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'quantity' => $variation->listed_stock ?? 0,
                'sku' => $variation->sku,
                'state' => $variation->state,
                'updated' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

