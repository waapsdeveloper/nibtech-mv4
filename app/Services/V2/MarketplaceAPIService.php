<?php

namespace App\Services\V2;

use App\Models\Marketplace_model;
use App\Models\V2\MarketplaceStockModel;
use App\Models\Variation_model;
use App\Http\Controllers\BackMarketAPIController;
use App\Http\Controllers\RefurbedAPIController;
use Illuminate\Support\Facades\Log;

/**
 * Generic Marketplace API Service for V2
 * Handles all marketplace APIs (BackMarket, Refurbed, etc.) in a unified way
 */
class MarketplaceAPIService
{
    /**
     * Update stock quantity for a variation on a specific marketplace
     * Automatically applies buffer if configured
     * 
     * @param int $variationId
     * @param int $marketplaceId
     * @param int $quantity Original quantity (before buffer)
     * @param array $additionalData Additional data to send to API (e.g., price, currency)
     * @return array|object|null API response
     */
    public function updateStock(int $variationId, int $marketplaceId, int $quantity, array $additionalData = [])
    {
        $variation = Variation_model::find($variationId);
        if (!$variation) {
            Log::error("MarketplaceAPIService: Variation not found", [
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId
            ]);
            return null;
        }

        $marketplace = Marketplace_model::find($marketplaceId);
        if (!$marketplace) {
            Log::error("MarketplaceAPIService: Marketplace not found", [
                'marketplace_id' => $marketplaceId
            ]);
            return null;
        }

        // Get marketplace stock record to check buffer
        $marketplaceStock = MarketplaceStockModel::where([
            'variation_id' => $variationId,
            'marketplace_id' => $marketplaceId
        ])->first();

        // Apply buffer if configured
        $bufferedQuantity = $this->applyBuffer($quantity, $marketplaceStock);
        
        Log::info("MarketplaceAPIService: Updating stock", [
            'variation_id' => $variationId,
            'marketplace_id' => $marketplaceId,
            'marketplace_name' => $marketplace->name,
            'original_quantity' => $quantity,
            'buffered_quantity' => $bufferedQuantity,
            'buffer_percentage' => $marketplaceStock ? $marketplaceStock->buffer_percentage : 0
        ]);

        // Route to appropriate marketplace handler
        switch ($marketplaceId) {
            case 1: // Back Market
                return $this->updateBackMarketStock($variation, $bufferedQuantity, $additionalData);
            
            case 4: // Refurbed
                return $this->updateRefurbedStock($variation, $bufferedQuantity, $additionalData);
            
            default:
                Log::warning("MarketplaceAPIService: Unsupported marketplace", [
                    'marketplace_id' => $marketplaceId,
                    'marketplace_name' => $marketplace->name
                ]);
                return null;
        }
    }

    /**
     * Apply buffer percentage to quantity
     * 
     * @param int $quantity
     * @param MarketplaceStockModel|null $marketplaceStock
     * @return int Buffered quantity
     */
    private function applyBuffer(int $quantity, ?MarketplaceStockModel $marketplaceStock): int
    {
        if (!$marketplaceStock || $marketplaceStock->buffer_percentage <= 0) {
            return $quantity;
        }

        $bufferPercentage = $marketplaceStock->buffer_percentage;
        $bufferedQuantity = max(0, floor($quantity * (1 - $bufferPercentage / 100)));
        
        return (int)$bufferedQuantity;
    }

    /**
     * Update stock on Back Market
     * 
     * @param Variation_model $variation
     * @param int $quantity Buffered quantity
     * @param array $additionalData
     * @return object|null
     */
    private function updateBackMarketStock(Variation_model $variation, int $quantity, array $additionalData = [])
    {
        if (!$variation->reference_id) {
            Log::error("MarketplaceAPIService: BackMarket variation missing reference_id", [
                'variation_id' => $variation->id
            ]);
            return null;
        }

        try {
            $bm = new BackMarketAPIController();
            
            // Build request payload
            $payload = array_merge(['quantity' => $quantity], $additionalData);
            $requestJson = json_encode($payload);
            
            // Get market code if provided in additional data
            $code = $additionalData['market_code'] ?? null;
            
            // Skip buffer in updateOneListing since we already applied it in applyBuffer()
            $response = $bm->updateOneListing($variation->reference_id, $requestJson, $code, true);
            
            // Update marketplace stock record
            if ($response && is_object($response) && isset($response->quantity)) {
                $this->updateMarketplaceStockRecord(
                    $variation->id,
                    1, // Back Market marketplace_id
                    $response->quantity,
                    $quantity // Store the buffered quantity we sent
                );
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error("MarketplaceAPIService: Error updating BackMarket stock", [
                'variation_id' => $variation->id,
                'reference_id' => $variation->reference_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Update stock on Refurbed
     * 
     * @param Variation_model $variation
     * @param int $quantity Buffered quantity
     * @param array $additionalData
     * @return array|null
     */
    private function updateRefurbedStock(Variation_model $variation, int $quantity, array $additionalData = [])
    {
        if (!$variation->sku) {
            Log::error("MarketplaceAPIService: Refurbed variation missing SKU", [
                'variation_id' => $variation->id
            ]);
            return null;
        }

        try {
            $refurbed = new RefurbedAPIController();
            
            // Build identifier and updates
            $identifier = ['sku' => $variation->sku];
            $updates = array_merge(['stock' => $quantity], $additionalData);
            
            $response = $refurbed->updateOffer($identifier, $updates);
            
            // Update marketplace stock record
            if ($response && isset($response['offer'])) {
                $offerStock = $response['offer']['stock'] ?? $quantity;
                $this->updateMarketplaceStockRecord(
                    $variation->id,
                    4, // Refurbed marketplace_id
                    $offerStock,
                    $quantity // Store the buffered quantity we sent
                );
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error("MarketplaceAPIService: Error updating Refurbed stock", [
                'variation_id' => $variation->id,
                'sku' => $variation->sku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Update marketplace stock record after API call
     * 
     * @param int $variationId
     * @param int $marketplaceId
     * @param int $apiQuantity Quantity returned by API
     * @param int $sentQuantity Quantity we sent (buffered)
     */
    private function updateMarketplaceStockRecord(int $variationId, int $marketplaceId, int $apiQuantity, int $sentQuantity): void
    {
        $marketplaceStock = MarketplaceStockModel::firstOrCreate(
            [
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId
            ],
            [
                'listed_stock' => 0,
                'locked_stock' => 0,
                'available_stock' => 0,
                'buffer_percentage' => 10.00
            ]
        );

        $marketplaceStock->last_synced_at = now();
        $marketplaceStock->last_api_quantity = $sentQuantity; // Store what we sent (buffered)
        $marketplaceStock->save();
    }

    /**
     * Get available stock with buffer applied
     * 
     * @param int $variationId
     * @param int $marketplaceId
     * @return int Available stock with buffer
     */
    public function getAvailableStockWithBuffer(int $variationId, int $marketplaceId): int
    {
        $marketplaceStock = MarketplaceStockModel::where([
            'variation_id' => $variationId,
            'marketplace_id' => $marketplaceId
        ])->first();

        if (!$marketplaceStock) {
            return 0;
        }

        return $marketplaceStock->getAvailableStockWithBuffer();
    }
}

