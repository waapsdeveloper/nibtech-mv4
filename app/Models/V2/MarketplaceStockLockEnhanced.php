<?php

namespace App\Models\V2;

use App\Models\V2\MarketplaceStockLock as BaseMarketplaceStockLock;

/**
 * V2 Enhanced Version of MarketplaceStockLock
 * Enhanced with V2-specific methods
 */
class MarketplaceStockLockEnhanced extends BaseMarketplaceStockLock
{
    /**
     * Check if lock is active
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->lock_status === 'locked';
    }
    
    /**
     * Check if lock is consumed
     * 
     * @return bool
     */
    public function isConsumed(): bool
    {
        return $this->lock_status === 'consumed';
    }
    
    /**
     * Check if lock is cancelled
     * 
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->lock_status === 'cancelled';
    }
    
    /**
     * Get lock duration in minutes
     * 
     * @return int|null
     */
    public function getLockDurationMinutes(): ?int
    {
        if (!$this->locked_at) {
            return null;
        }
        
        $endTime = $this->consumed_at ?? $this->released_at ?? now();
        
        return $this->locked_at->diffInMinutes($endTime);
    }
    
    /**
     * Get lock summary for display
     * 
     * @return array
     */
    public function getLockSummary(): array
    {
        return [
            'id' => $this->id,
            'variation_id' => $this->variation_id,
            'marketplace_id' => $this->marketplace_id,
            'order_id' => $this->order_id,
            'order_item_id' => $this->order_item_id,
            'quantity_locked' => $this->quantity_locked,
            'lock_status' => $this->lock_status,
            'locked_at' => $this->locked_at?->toDateTimeString(),
            'consumed_at' => $this->consumed_at?->toDateTimeString(),
            'released_at' => $this->released_at?->toDateTimeString(),
            'duration_minutes' => $this->getLockDurationMinutes(),
            'is_active' => $this->isActive(),
        ];
    }
}

