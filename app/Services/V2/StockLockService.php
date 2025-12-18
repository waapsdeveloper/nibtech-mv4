<?php

namespace App\Services\V2;

use App\Models\V2\MarketplaceStockLock;
use App\Models\V2\MarketplaceStockModel;
use App\Models\V2\MarketplaceStockHistory;
use App\Services\V2\MarketplaceAPIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * V2 Stock Lock Service
 * Handles stock lock operations including release/unfreeze
 */
class StockLockService
{
    protected MarketplaceAPIService $apiService;

    public function __construct(MarketplaceAPIService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * Release/unfreeze a stock lock
     * 
     * @param int $lockId Lock ID to release
     * @param int|null $adminId Admin ID performing the action
     * @param string|null $reason Reason for release
     * @return array Result with success status and message
     */
    public function releaseLock(int $lockId, ?int $adminId = null, ?string $reason = null): array
    {
        try {
            DB::beginTransaction();

            // Find the lock
            $lock = MarketplaceStockLock::with(['marketplaceStock', 'order'])->find($lockId);

            if (!$lock) {
                return [
                    'success' => false,
                    'message' => 'Lock not found'
                ];
            }

            // Validate: Only release locks with status 'locked'
            if ($lock->lock_status !== 'locked') {
                return [
                    'success' => false,
                    'message' => "Cannot release lock with status: {$lock->lock_status}. Only 'locked' locks can be released."
                ];
            }

            // Get marketplace stock record
            $marketplaceStock = $lock->marketplaceStock;
            
            if (!$marketplaceStock) {
                return [
                    'success' => false,
                    'message' => 'Marketplace stock record not found'
                ];
            }

            // Record before values
            $listedStockBefore = $marketplaceStock->listed_stock;
            $lockedStockBefore = $marketplaceStock->locked_stock;
            $availableStockBefore = $marketplaceStock->available_stock;
            $quantityToRelease = $lock->quantity_locked;

            // Update marketplace stock: reduce locked, increase available
            $marketplaceStock->locked_stock = max(0, $marketplaceStock->locked_stock - $quantityToRelease);
            $marketplaceStock->available_stock = max(0, $marketplaceStock->listed_stock - $marketplaceStock->locked_stock);
            $marketplaceStock->save();

            // Update lock record: mark as released
            $lock->lock_status = 'released';
            $lock->released_at = now();
            $lock->save();

            // Create history record
            MarketplaceStockHistory::create([
                'marketplace_stock_id' => $marketplaceStock->id,
                'variation_id' => $lock->variation_id,
                'marketplace_id' => $lock->marketplace_id,
                'listed_stock_before' => $listedStockBefore,
                'listed_stock_after' => $marketplaceStock->listed_stock,
                'locked_stock_before' => $lockedStockBefore,
                'locked_stock_after' => $marketplaceStock->locked_stock,
                'available_stock_before' => $availableStockBefore,
                'available_stock_after' => $marketplaceStock->available_stock,
                'quantity_change' => $quantityToRelease, // Positive because stock is being released
                'change_type' => 'unlock',
                'order_id' => $lock->order_id,
                'order_item_id' => $lock->order_item_id,
                'reference_id' => $lock->order->reference_id ?? null,
                'admin_id' => $adminId,
                'notes' => $reason ?? "Stock lock released manually. Order: " . ($lock->order->reference_id ?? 'N/A')
            ]);

            // Update marketplace API with new available stock (with buffer)
            $this->updateMarketplaceAPI($marketplaceStock, $lock->variation_id, $lock->marketplace_id);

            DB::commit();

            Log::info("V2: Stock lock released", [
                'lock_id' => $lockId,
                'order_id' => $lock->order_id,
                'order_reference' => $lock->order->reference_id ?? null,
                'variation_id' => $lock->variation_id,
                'marketplace_id' => $lock->marketplace_id,
                'quantity_released' => $quantityToRelease,
                'available_stock_after' => $marketplaceStock->available_stock,
                'admin_id' => $adminId
            ]);

            return [
                'success' => true,
                'message' => "Stock lock released successfully. {$quantityToRelease} units are now available.",
                'data' => [
                    'lock_id' => $lock->id,
                    'quantity_released' => $quantityToRelease,
                    'available_stock' => $marketplaceStock->available_stock
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("V2: Error releasing stock lock", [
                'lock_id' => $lockId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error releasing stock lock: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Release all locks for a specific order
     * 
     * @param int $orderId Order ID
     * @param int|null $adminId Admin ID
     * @param string|null $reason Reason for release
     * @return array Result with success status and count
     */
    public function releaseLocksForOrder(int $orderId, ?int $adminId = null, ?string $reason = null): array
    {
        $locks = MarketplaceStockLock::where([
            'order_id' => $orderId,
            'lock_status' => 'locked'
        ])->get();

        $released = 0;
        $errors = 0;

        foreach ($locks as $lock) {
            $result = $this->releaseLock($lock->id, $adminId, $reason);
            if ($result['success']) {
                $released++;
            } else {
                $errors++;
            }
        }

        return [
            'success' => $released > 0,
            'released' => $released,
            'errors' => $errors,
            'message' => "Released {$released} lock(s) for order"
        ];
    }

    /**
     * Update marketplace API with new available stock
     */
    private function updateMarketplaceAPI($marketplaceStock, $variationId, $marketplaceId)
    {
        try {
            $availableStock = $marketplaceStock->available_stock;
            
            $response = $this->apiService->updateStock(
                $variationId,
                $marketplaceId,
                $availableStock
            );

            if ($response) {
                Log::info("V2: Marketplace API updated after lock release", [
                    'variation_id' => $variationId,
                    'marketplace_id' => $marketplaceId,
                    'available_stock' => $availableStock
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("V2: Failed to update marketplace API after lock release", [
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
                'error' => $e->getMessage()
            ]);
            // Don't throw - API update failure shouldn't fail the lock release
        }
    }
}

