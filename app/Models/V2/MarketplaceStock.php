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
     * Lock stock for an order
     * V2 version with enhanced validation
     * 
     * @param int $orderId
     * @param int $orderItemId
     * @param int $quantity
     * @return \App\Models\MarketplaceStockLock|null
     */
    public function lockStockForOrder(int $orderId, int $orderItemId, int $quantity): ?\App\Models\V2\MarketplaceStockLock
    {
        // Validate available stock
        $availableStock = $this->available_stock ?? max(0, $this->listed_stock - $this->locked_stock);
        
        if ($availableStock < $quantity) {
            Log::warning("V2 MarketplaceStock: Insufficient stock to lock", [
                'variation_id' => $this->variation_id,
                'marketplace_id' => $this->marketplace_id,
                'order_id' => $orderId,
                'requested_quantity' => $quantity,
                'available_stock' => $availableStock
            ]);
            
            return null;
        }
        
        // Lock stock
        $this->locked_stock += $quantity;
        $this->available_stock = max(0, $this->listed_stock - $this->locked_stock);
        $this->save();
        
        // Create lock record
        $lock = \App\Models\V2\MarketplaceStockLock::create([
            'marketplace_stock_id' => $this->id,
            'variation_id' => $this->variation_id,
            'marketplace_id' => $this->marketplace_id,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'quantity_locked' => $quantity,
            'lock_status' => 'locked',
            'locked_at' => now(),
        ]);
        
        Log::info("V2 MarketplaceStock: Stock locked for order", [
            'variation_id' => $this->variation_id,
            'marketplace_id' => $this->marketplace_id,
            'order_id' => $orderId,
            'quantity_locked' => $quantity,
            'available_stock_after' => $this->available_stock
        ]);
        
        return $lock;
    }
    
    /**
     * Release stock lock for an order
     * V2 version
     * 
     * @param int $orderId
     * @param int $orderItemId
     * @return bool Success status
     */
    public function releaseStockLock(int $orderId, int $orderItemId): bool
    {
        $locks = \App\Models\V2\MarketplaceStockLock::where([
            'marketplace_stock_id' => $this->id,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'lock_status' => 'locked'
        ])->get();
        
        if ($locks->isEmpty()) {
            return false;
        }
        
        $totalLocked = $locks->sum('quantity_locked');
        
        // Release locks
        foreach ($locks as $lock) {
            $lock->lock_status = 'released';
            $lock->released_at = now();
            $lock->save();
        }
        
        // Update stock
        $this->locked_stock = max(0, $this->locked_stock - $totalLocked);
        $this->available_stock = max(0, $this->listed_stock - $this->locked_stock);
        $this->save();
        
        Log::info("V2 MarketplaceStock: Stock lock released", [
            'variation_id' => $this->variation_id,
            'marketplace_id' => $this->marketplace_id,
            'order_id' => $orderId,
            'quantity_released' => $totalLocked,
            'available_stock_after' => $this->available_stock
        ]);
        
        return true;
    }
    
    /**
     * Consume stock lock (order completed)
     * V2 version
     * 
     * @param int $orderId
     * @param int $orderItemId
     * @param int $quantity
     * @return bool Success status
     */
    public function consumeStockLock(int $orderId, int $orderItemId, int $quantity): bool
    {
        $locks = \App\Models\V2\MarketplaceStockLock::where([
            'marketplace_stock_id' => $this->id,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'lock_status' => 'locked'
        ])->get();
        
        if ($locks->isEmpty()) {
            return false;
        }
        
        $totalLocked = $locks->sum('quantity_locked');
        
        // Mark locks as consumed
        foreach ($locks as $lock) {
            $lock->lock_status = 'consumed';
            $lock->consumed_at = now();
            $lock->save();
        }
        
        // Reduce listed stock and unlock
        $this->listed_stock = max(0, $this->listed_stock - $quantity);
        $this->locked_stock = max(0, $this->locked_stock - $totalLocked);
        $this->available_stock = max(0, $this->listed_stock - $this->locked_stock);
        $this->save();
        
        // Sync with marketplace API
        $this->syncWithMarketplace($this->available_stock);
        
        Log::info("V2 MarketplaceStock: Stock lock consumed", [
            'variation_id' => $this->variation_id,
            'marketplace_id' => $this->marketplace_id,
            'order_id' => $orderId,
            'quantity_consumed' => $quantity,
            'listed_stock_after' => $this->listed_stock,
            'available_stock_after' => $this->available_stock
        ]);
        
        return true;
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

