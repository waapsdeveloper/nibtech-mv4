<?php
namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Models\MarketplaceStockModel;
use App\Models\MarketplaceStockLock;
use App\Models\MarketplaceStockHistory;
use App\Services\V2\MarketplaceAPIService;
use Illuminate\Support\Facades\Log;

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
                Log::warning("Marketplace stock not found for completed order", [
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
            
            // Update Back Market API with reduced stock (with buffer)
            $this->updateMarketplaceAPI($marketplaceStock, $variationId);
            
            Log::info("Stock reduced for completed order", [
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
    
    private function updateMarketplaceAPI($marketplaceStock, $variationId)
    {
        // Use V2 MarketplaceAPIService (automatically applies buffer)
        $availableStock = $marketplaceStock->available_stock;
        
        $response = $this->apiService->updateStock(
            $variationId,
            $marketplaceStock->marketplace_id,
            $availableStock
        );
        
        if ($response) {
            Log::info("Marketplace API updated after order completion", [
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceStock->marketplace_id,
                'available_stock' => $availableStock
            ]);
        }
    }
}
