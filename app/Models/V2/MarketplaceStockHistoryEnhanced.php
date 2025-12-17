<?php

namespace App\Models\V2;

use App\Models\V2\MarketplaceStockHistory as BaseMarketplaceStockHistory;

/**
 * V2 Enhanced Version of MarketplaceStockHistory
 * Enhanced with V2-specific methods and queries
 */
class MarketplaceStockHistoryEnhanced extends BaseMarketplaceStockHistory
{
    /**
     * Get history for a specific variation and marketplace
     * 
     * @param int $variationId
     * @param int $marketplaceId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getHistoryForVariation(int $variationId, int $marketplaceId, int $limit = 50)
    {
        return static::where('variation_id', $variationId)
            ->where('marketplace_id', $marketplaceId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get history by change type
     * 
     * @param string $changeType
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getHistoryByType(string $changeType, int $limit = 100)
    {
        return static::where('change_type', $changeType)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get history for an order
     * 
     * @param int $orderId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getHistoryForOrder(int $orderId)
    {
        return static::where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    /**
     * Get stock change summary
     * 
     * @return array
     */
    public function getChangeSummary(): array
    {
        return [
            'id' => $this->id,
            'variation_id' => $this->variation_id,
            'marketplace_id' => $this->marketplace_id,
            'change_type' => $this->change_type,
            'listed_stock_change' => $this->listed_stock_after - $this->listed_stock_before,
            'locked_stock_change' => $this->locked_stock_after - $this->locked_stock_before,
            'available_stock_change' => $this->available_stock_after - $this->available_stock_before,
            'quantity_change' => $this->quantity_change,
            'order_id' => $this->order_id,
            'order_reference' => $this->reference_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
    
    /**
     * Check if this is a stock increase
     * 
     * @return bool
     */
    public function isIncrease(): bool
    {
        return $this->quantity_change > 0;
    }
    
    /**
     * Check if this is a stock decrease
     * 
     * @return bool
     */
    public function isDecrease(): bool
    {
        return $this->quantity_change < 0;
    }
    
    /**
     * Get formatted change description
     * 
     * @return string
     */
    public function getFormattedChange(): string
    {
        $change = abs($this->quantity_change);
        $direction = $this->isIncrease() ? 'increased' : 'decreased';
        $type = $this->change_type;
        
        return "Stock {$direction} by {$change} ({$type})";
    }
}

