<?php
namespace App\Listeners\V2;

use App\Events\V2\OrderCreated;
use App\Models\V2\MarketplaceStockModel;
use App\Models\V2\MarketplaceStockLock;
use App\Models\V2\MarketplaceStockHistory;
use Illuminate\Support\Facades\Log;

/**
 * V2 Version of LockStockOnOrderCreated Listener
 * Uses generic marketplace logic (no marketplace-specific code)
 */
class LockStockOnOrderCreated
{
    public function handle(OrderCreated $event)
    {
        $order = $event->order;
        
        // Only process marketplace orders (order_type_id = 3)
        if ($order->order_type_id != 3) {
            return;
        }
        
        // Only lock stock if order is in pending/processing status (status 1 or 2)
        if (!in_array($order->status, [1, 2])) {
            return;
        }
        
        $marketplaceId = $order->marketplace_id ?? 1;
        
        foreach ($event->orderItems as $orderItem) {
            $variationId = $orderItem->variation_id;
            $quantity = $orderItem->quantity ?? 1;
            
            if (!$variationId || $quantity <= 0) {
                continue;
            }
            
            // Get or create marketplace stock record
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
            
            // Check if stock is already locked for this order
            $existingLock = MarketplaceStockLock::where([
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'lock_status' => 'locked'
            ])->first();
            
            if ($existingLock) {
                // Already locked, skip
                continue;
            }
            
            // Record before values
            $listedStockBefore = $marketplaceStock->listed_stock;
            $lockedStockBefore = $marketplaceStock->locked_stock;
            $availableStockBefore = $marketplaceStock->available_stock;
            
            // Lock stock
            $marketplaceStock->locked_stock += $quantity;
            $marketplaceStock->available_stock = max(0, $marketplaceStock->listed_stock - $marketplaceStock->locked_stock);
            $marketplaceStock->save();
            
            // Create lock record
            $lock = MarketplaceStockLock::create([
                'marketplace_stock_id' => $marketplaceStock->id,
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'quantity_locked' => $quantity,
                'lock_status' => 'locked',
                'locked_at' => now(),
            ]);
            
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
                'change_type' => 'lock',
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'reference_id' => $order->reference_id,
                'notes' => "Stock locked for order: {$order->reference_id}"
            ]);
            
            Log::info("V2: Stock locked for order", [
                'order_id' => $order->id,
                'order_reference' => $order->reference_id,
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
                'quantity_locked' => $quantity,
                'available_stock_after' => $marketplaceStock->available_stock
            ]);
        }
    }
}

