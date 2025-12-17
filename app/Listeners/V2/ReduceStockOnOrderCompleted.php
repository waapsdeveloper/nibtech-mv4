<?php
namespace App\Listeners\V2;

use App\Events\V2\OrderStatusChanged;
use App\Models\V2\MarketplaceStockModel;
use App\Models\V2\MarketplaceStockLock;
use App\Models\V2\MarketplaceStockHistory;
use App\Services\V2\MarketplaceAPIService;
use Illuminate\Support\Facades\Log;

/**
 * V2 Version of ReduceStockOnOrderCompleted Listener
 * Uses generic MarketplaceAPIService (no marketplace-specific code)
 */
class ReduceStockOnOrderCompleted
{
    protected MarketplaceAPIService $apiService;
    
    public function __construct(MarketplaceAPIService $apiService)
    {
        $this->apiService = $apiService;
    }
    
    public function handle(OrderStatusChanged $event)
    {
        $order = $event->order;
        
        // Only process marketplace orders (order_type_id = 3)
        if ($order->order_type_id != 3) {
            return;
        }
        
        // Only process when order status changes to completed (status = 3)
        if ($event->newStatus != 3) {
            return;
        }
        
        $marketplaceId = $order->marketplace_id ?? 1;
        
        foreach ($event->orderItems as $orderItem) {
            $variationId = $orderItem->variation_id;
            $quantity = $orderItem->quantity ?? 1;
            
            if (!$variationId || $quantity <= 0) {
                continue;
            }
            
            // Get marketplace stock record
            $marketplaceStock = MarketplaceStockModel::where([
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId
            ])->first();
            
            if (!$marketplaceStock) {
                Log::warning("V2: Marketplace stock not found for completed order", [
                    'order_id' => $order->id,
                    'variation_id' => $variationId,
                    'marketplace_id' => $marketplaceId
                ]);
                continue;
            }
            
            // Find and consume locks for this order
            $locks = MarketplaceStockLock::where([
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'lock_status' => 'locked'
            ])->get();
            
            $totalLocked = $locks->sum('quantity_locked');
            
            // Record before values
            $listedStockBefore = $marketplaceStock->listed_stock;
            $lockedStockBefore = $marketplaceStock->locked_stock;
            $availableStockBefore = $marketplaceStock->available_stock;
            
            // Reduce listed stock and unlock
            $marketplaceStock->listed_stock = max(0, $marketplaceStock->listed_stock - $quantity);
            $marketplaceStock->locked_stock = max(0, $marketplaceStock->locked_stock - $totalLocked);
            $marketplaceStock->available_stock = max(0, $marketplaceStock->listed_stock - $marketplaceStock->locked_stock);
            $marketplaceStock->save();
            
            // Mark locks as consumed
            foreach ($locks as $lock) {
                $lock->lock_status = 'consumed';
                $lock->consumed_at = now();
                $lock->save();
            }
            
            // Log to history
            MarketplaceStockHistory::create([
                'marketplace_stock_id' => $marketplaceStock->id,
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
                'listed_stock_before' => $listedStockBefore,
                'listed_stock_after' => $marketplaceStock->listed_stock,
                'locked_stock_before' => $lockedStockBefore,
                'locked_stock_after' => $marketplaceStock->locked_stock,
                'available_stock_before' => $availableStockBefore,
                'available_stock_after' => $marketplaceStock->available_stock,
                'quantity_change' => -$quantity,
                'change_type' => 'order_completed',
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'reference_id' => $order->reference_id,
                'notes' => "Stock reduced for completed order: {$order->reference_id}"
            ]);
            
            // Update marketplace API with reduced stock (with buffer) using V2 service
            $this->updateMarketplaceAPI($marketplaceStock, $variationId, $marketplaceId);
            
            Log::info("V2: Stock reduced for completed order", [
                'order_id' => $order->id,
                'order_reference' => $order->reference_id,
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
                'quantity_reduced' => $quantity,
                'listed_stock_after' => $marketplaceStock->listed_stock,
                'available_stock_after' => $marketplaceStock->available_stock
            ]);
        }
    }
    
    /**
     * Update marketplace API using V2 MarketplaceAPIService
     * Automatically applies buffer
     */
    private function updateMarketplaceAPI($marketplaceStock, $variationId, $marketplaceId)
    {
        // Use V2 MarketplaceAPIService (automatically applies buffer)
        $availableStock = $marketplaceStock->available_stock;
        
        $response = $this->apiService->updateStock(
            $variationId,
            $marketplaceId,
            $availableStock
        );
        
        if ($response) {
            Log::info("V2: Marketplace API updated after order completion", [
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
                'available_stock' => $availableStock
            ]);
        }
    }
}

