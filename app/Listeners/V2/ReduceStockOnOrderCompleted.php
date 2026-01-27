<?php
namespace App\Listeners\V2;

use App\Events\V2\OrderStatusChanged;
use App\Models\V2\MarketplaceStockModel;
use App\Models\V2\MarketplaceStockLock;
use App\Models\V2\MarketplaceStockHistory;
use App\Models\Stock_model;
use App\Services\V2\MarketplaceAPIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
        
        // Collect all variation IDs and order item IDs for batch processing
        $variationIds = [];
        $orderItemIds = [];
        $orderItemsByVariation = [];
        
        foreach ($event->orderItems as $orderItem) {
            $variationId = $orderItem->variation_id;
            if (!$variationId) {
                continue;
            }
            $variationIds[] = $variationId;
            $orderItemIds[] = $orderItem->id;
            if (!isset($orderItemsByVariation[$variationId])) {
                $orderItemsByVariation[$variationId] = [];
            }
            $orderItemsByVariation[$variationId][] = $orderItem;
        }
        
        if (empty($variationIds)) {
            return;
        }
        
        // Eager load all marketplace stocks in one query
        $marketplaceStocks = MarketplaceStockModel::whereIn('variation_id', $variationIds)
            ->where('marketplace_id', $marketplaceId)
            ->get()
            ->keyBy('variation_id');
        
        // Eager load all locks in one query
        $allLocks = MarketplaceStockLock::where('order_id', $order->id)
            ->whereIn('order_item_id', $orderItemIds)
            ->where('lock_status', 'locked')
            ->get()
            ->groupBy('order_item_id');
        
        // Eager load all stock records in one query
        $stockIds = collect($event->orderItems)->pluck('stock_id')->filter()->unique()->values()->all();
        $stocks = !empty($stockIds) 
            ? Stock_model::withTrashed()->whereIn('id', $stockIds)->get()->keyBy('id')
            : collect();
        
        // Process all order items in a single transaction for better performance
        \DB::transaction(function () use ($order, $marketplaceId, $orderItemsByVariation, $marketplaceStocks, $allLocks, $stocks) {
            foreach ($orderItemsByVariation as $variationId => $orderItems) {
                $marketplaceStock = $marketplaceStocks->get($variationId);
                
                if (!$marketplaceStock) {
                    Log::warning("V2: Marketplace stock not found for completed order", [
                        'order_id' => $order->id,
                        'variation_id' => $variationId,
                        'marketplace_id' => $marketplaceId
                    ]);
                    continue;
                }
                
                foreach ($orderItems as $orderItem) {
                    $quantity = $orderItem->quantity ?? 1;
                    
                    if ($quantity <= 0) {
                        continue;
                    }
                    
                    // Get locks for this order item (already loaded)
                    $locks = $allLocks->get($orderItem->id, collect());

                    // If there is no active lock, do NOT reduce stock. This keeps the flow idempotent and
                    // avoids accidental double-reduction.
                    if ($locks->isEmpty()) {
                        Log::info("V2: No active lock found on order completion; skipping stock consume", [
                            'order_id' => $order->id,
                            'order_reference' => $order->reference_id,
                            'order_item_id' => $orderItem->id,
                            'variation_id' => $variationId,
                            'marketplace_id' => $marketplaceId,
                        ]);
                        continue;
                    }
                    
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
                    
                    // Mark locks as consumed (batch update)
                    MarketplaceStockLock::whereIn('id', $locks->pluck('id'))
                        ->update([
                            'lock_status' => 'consumed',
                            'consumed_at' => now()
                        ]);

                    // Mark physical stock unit as SOLD (if this order item has an inventory stock_id).
                    // This is separate from marketplace_stock which tracks marketplace listed/locked/available.
                    if (!empty($orderItem->stock_id)) {
                        $stock = $stocks->get($orderItem->stock_id);
                        if ($stock) {
                            try {
                                $stock->mark_sold($orderItem->id, null, 'V2: Mark sold on order confirmed');
                            } catch (\Throwable $e) {
                                Log::warning('V2: Failed to mark stock as sold on order completion', [
                                    'order_id' => $order->id,
                                    'order_item_id' => $orderItem->id,
                                    'stock_id' => $orderItem->stock_id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
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
                }
                
                // Update marketplace API once per variation (after all items processed)
                $this->updateMarketplaceAPI($marketplaceStock, $variationId, $marketplaceId);
            }
        });
        
        Log::info("V2: Stock reduced for completed order (batch processed)", [
            'order_id' => $order->id,
            'order_reference' => $order->reference_id,
            'marketplace_id' => $marketplaceId,
            'items_processed' => count($event->orderItems)
        ]);
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

