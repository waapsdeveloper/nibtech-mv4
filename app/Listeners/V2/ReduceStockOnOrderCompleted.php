<?php
namespace App\Listeners\V2;

use App\Events\V2\OrderStatusChanged;
use App\Http\Controllers\BackMarketAPIController;
use App\Models\Variation_model;
use App\Models\V2\MarketplaceStockModel;
use App\Models\V2\MarketplaceStockHistory;
use App\Models\Stock_model;
use App\Services\V2\MarketplaceAPIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * V2 Version of ReduceStockOnOrderCompleted Listener
 * - BackMarket (marketplace_id = 1): Sync listed_stock FROM API on order completed (BackMarket already reduced).
 * - Other marketplaces: Reduce listed_stock locally and push to API (unchanged).
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
        
        // Check for existing stock reductions (idempotency check - replaces lock checking)
        $existingReductions = MarketplaceStockHistory::where('order_id', $order->id)
            ->whereIn('variation_id', $variationIds)
            ->where('change_type', 'order_completed')
            ->pluck('variation_id', 'order_item_id')
            ->toArray();
        
        // Eager load all stock records in one query
        $stockIds = collect($event->orderItems)->pluck('stock_id')->filter()->unique()->values()->all();
        $stocks = !empty($stockIds) 
            ? Stock_model::withTrashed()->whereIn('id', $stockIds)->get()->keyBy('id')
            : collect();
        
        $isBackMarket = ($marketplaceId == 1);
        
        // Process all order items in a single transaction for better performance
        DB::transaction(function () use ($order, $marketplaceId, $orderItemsByVariation, $marketplaceStocks, $existingReductions, $stocks, $isBackMarket) {
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
                
                if ($isBackMarket) {
                    // BackMarket: sync FROM API (they already reduced quantity on sale). Single source of truth.
                    $this->syncBackMarketStockOnOrderCompleted(
                        $order,
                        $variationId,
                        $orderItems,
                        $marketplaceStock,
                        $existingReductions,
                        $stocks
                    );
                } else {
                    // Other marketplaces: reduce locally and push to API (existing behaviour)
                    foreach ($orderItems as $orderItem) {
                        $quantity = $orderItem->quantity ?? 1;
                        
                        if ($quantity <= 0) {
                            continue;
                        }
                        
                        if (isset($existingReductions[$orderItem->id])) {
                            Log::info("V2: Stock already reduced for this order item; skipping (idempotency)", [
                                'order_id' => $order->id,
                                'order_reference' => $order->reference_id,
                                'order_item_id' => $orderItem->id,
                                'variation_id' => $variationId,
                                'marketplace_id' => $marketplaceId,
                            ]);
                            continue;
                        }
                        
                        $listedStockBefore = $marketplaceStock->listed_stock;
                        $availableStockBefore = $marketplaceStock->available_stock;
                        
                        $marketplaceStock->listed_stock = max(0, $marketplaceStock->listed_stock - $quantity);
                        $marketplaceStock->available_stock = $marketplaceStock->listed_stock;
                        $marketplaceStock->save();

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
                        
                        MarketplaceStockHistory::create([
                            'marketplace_stock_id' => $marketplaceStock->id,
                            'variation_id' => $variationId,
                            'marketplace_id' => $marketplaceId,
                            'listed_stock_before' => $listedStockBefore,
                            'listed_stock_after' => $marketplaceStock->listed_stock,
                            'locked_stock_before' => 0,
                            'locked_stock_after' => 0,
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
                    
                    $this->updateMarketplaceAPI($marketplaceStock, $variationId, $marketplaceId);
                }
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
     * BackMarket only: sync listed_stock FROM API after order completed.
     * BackMarket already reduces quantity on sale; we make API the single source of truth.
     */
    private function syncBackMarketStockOnOrderCompleted(
        $order,
        int $variationId,
        array $orderItems,
        MarketplaceStockModel $marketplaceStock,
        array $existingReductions,
        $stocks
    ): void {
        $variation = Variation_model::find($variationId);
        if (!$variation || !$variation->reference_id) {
            Log::warning("V2: BackMarket sync skipped – variation or reference_id missing", [
                'order_id' => $order->id,
                'variation_id' => $variationId,
            ]);
            return;
        }
        
        $bm = new BackMarketAPIController();
        $apiListing = $bm->getOneListing($variation->reference_id);
        if (!$apiListing || !isset($apiListing->quantity)) {
            Log::warning("V2: BackMarket API returned no quantity for sync after order", [
                'order_id' => $order->id,
                'variation_id' => $variationId,
                'reference_id' => $variation->reference_id,
            ]);
            return;
        }
        
        $apiQuantity = (int) $apiListing->quantity;
        $listedStockBefore = (int) $marketplaceStock->listed_stock;
        
        $marketplaceStock->listed_stock = $apiQuantity;
        $marketplaceStock->available_stock = $apiQuantity;
        $marketplaceStock->last_synced_at = now();
        $marketplaceStock->last_api_quantity = $apiQuantity;
        $marketplaceStock->save();
        
        $quantityChange = $apiQuantity - $listedStockBefore;
        
        foreach ($orderItems as $orderItem) {
            if (isset($existingReductions[$orderItem->id])) {
                continue;
            }
            
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
            
            MarketplaceStockHistory::create([
                'marketplace_stock_id' => $marketplaceStock->id,
                'variation_id' => $variationId,
                'marketplace_id' => 1,
                'listed_stock_before' => $listedStockBefore,
                'listed_stock_after' => $apiQuantity,
                'locked_stock_before' => 0,
                'locked_stock_after' => 0,
                'available_stock_before' => $listedStockBefore,
                'available_stock_after' => $apiQuantity,
                'quantity_change' => $quantityChange,
                'change_type' => 'order_completed',
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'reference_id' => $order->reference_id,
                'notes' => "Synced from BackMarket after order completed: {$order->reference_id}",
            ]);
            $quantityChange = 0;
        }
        
        $totalListed = MarketplaceStockModel::where('variation_id', $variationId)->sum('listed_stock');
        $totalManual = MarketplaceStockModel::where('variation_id', $variationId)->sum('manual_adjustment');
        $variation->listed_stock = (int) $totalListed + (int) $totalManual;
        $variation->save();
        
        Log::info("V2: BackMarket stock synced after order completed", [
            'order_id' => $order->id,
            'variation_id' => $variationId,
            'listed_before' => $listedStockBefore,
            'listed_after' => $apiQuantity,
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

