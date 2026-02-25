<?php

namespace App\Models\V2;

use App\Models\V2\MarketplaceStockModel as BaseMarketplaceStockModel;
use App\Services\V2\MarketplaceAPIService;
use Illuminate\Support\Facades\Log;

/**
 * V2 Version of MarketplaceStockModel
 * Enhanced with V2-specific methods and MarketplaceAPIService integration
 */
class MarketplaceStock extends BaseMarketplaceStockModel
{
    /**
     * Get available stock with buffer applied
     * V2 version with enhanced logging
     */
    public function getAvailableStockWithBuffer(): int
    {
        $available = $this->available_stock ?? max(0, $this->listed_stock - $this->locked_stock);
        $buffer = $this->buffer_percentage ?? 10.00;
        $buffered = max(0, floor($available * (1 - $buffer / 100)));
        
        Log::debug("V2 MarketplaceStock: Calculated buffered stock", [
            'variation_id' => $this->variation_id,
            'marketplace_id' => $this->marketplace_id,
            'listed_stock' => $this->listed_stock,
            'locked_stock' => $this->locked_stock,
            'available_stock' => $available,
            'buffer_percentage' => $buffer,
            'buffered_stock' => $buffered
        ]);
        
        return (int)$buffered;
    }
    
    /**
     * Sync stock with marketplace API using V2 MarketplaceAPIService
     * 
     * @param int $quantity Quantity to sync (before buffer)
     * @return bool Success status
     */
    public function syncWithMarketplace(int $quantity): bool
    {
        try {
            $apiService = app(MarketplaceAPIService::class);
            
            $response = $apiService->updateStock(
                $this->variation_id,
                $this->marketplace_id,
                $quantity
            );
            
            if ($response) {
                $this->last_synced_at = now();
                $this->save();
                
                Log::info("V2 MarketplaceStock: Stock synced with marketplace", [
                    'variation_id' => $this->variation_id,
                    'marketplace_id' => $this->marketplace_id,
                    'quantity' => $quantity
                ]);
                
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error("V2 MarketplaceStock: Error syncing with marketplace", [
                'variation_id' => $this->variation_id,
                'marketplace_id' => $this->marketplace_id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Get stock summary for display
     * V2 version
     *
     * @return array
     */
    public function getStockSummary(): array
    {
        return [
            'variation_id' => $this->variation_id,
            'marketplace_id' => $this->marketplace_id,
            'marketplace_name' => $this->marketplace->name ?? 'Unknown',
            'listed_stock' => $this->listed_stock,
            'locked_stock' => $this->locked_stock,
            'available_stock' => $this->available_stock,
            'buffered_stock' => $this->getAvailableStockWithBuffer(),
            'buffer_percentage' => $this->buffer_percentage,
            'last_synced_at' => $this->last_synced_at?->toDateTimeString(),
            'last_api_quantity' => $this->last_api_quantity,
        ];
    }
}

