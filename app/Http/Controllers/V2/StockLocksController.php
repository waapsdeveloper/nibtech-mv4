<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\V2\MarketplaceStockLock;
use App\Services\V2\StockLockService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class StockLocksController extends Controller
{
    /**
     * Get stock locks as HTML (rendered Blade template)
     * 
     * @param Request $request
     * @return View
     */
    public function getLocks(Request $request): View
    {
        $orderId = $request->input('order_id');
        $variationId = $request->input('variation_id');
        $marketplaceId = $request->input('marketplace_id');
        $showAll = filter_var($request->input('show_all', false), FILTER_VALIDATE_BOOLEAN);

        $query = MarketplaceStockLock::with([
            'marketplaceStock.marketplace',
            'marketplaceStock.variation.product',
            'order',
            'orderItem.variation'
        ]);

        if ($orderId) {
            $query->where('order_id', $orderId);
        }

        if ($variationId) {
            $query->where('variation_id', $variationId);
        }

        if ($marketplaceId) {
            $query->where('marketplace_id', $marketplaceId);
        }

        if (!$showAll) {
            $query->where('lock_status', 'locked');
        }

        $locks = $query->orderBy('locked_at', 'desc')->get();

        // Get summary statistics
        $summary = [
            'total_locked' => $locks->where('lock_status', 'locked')->sum('quantity_locked'),
            'total_consumed' => $locks->where('lock_status', 'consumed')->sum('quantity_locked'),
            'total_released' => $locks->where('lock_status', 'released')->sum('quantity_locked'),
            'active_locks_count' => $locks->where('lock_status', 'locked')->count(),
            'total_locks_count' => $locks->count(),
        ];

        return view('v2.listing.partials.stock-locks-content', [
            'locks' => $locks,
            'summary' => $summary
        ]);
    }

    /**
     * Get stock locks as JSON API response (for backward compatibility)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getLocksJson(Request $request): JsonResponse
    {
        $orderId = $request->input('order_id');
        $variationId = $request->input('variation_id');
        $marketplaceId = $request->input('marketplace_id');
        $showAll = filter_var($request->input('show_all', false), FILTER_VALIDATE_BOOLEAN);

        $query = MarketplaceStockLock::with([
            'marketplaceStock.marketplace',
            'marketplaceStock.variation.product',
            'order',
            'orderItem.variation'
        ]);

        if ($orderId) {
            $query->where('order_id', $orderId);
        }

        if ($variationId) {
            $query->where('variation_id', $variationId);
        }

        if ($marketplaceId) {
            $query->where('marketplace_id', $marketplaceId);
        }

        if (!$showAll) {
            $query->where('lock_status', 'locked');
        }

        $locks = $query->orderBy('locked_at', 'desc')->get();

        // Format locks for JSON response
        $formattedLocks = $locks->map(function ($lock) {
            $order = $lock->order;
            $orderItem = $lock->orderItem;
            $variation = $orderItem->variation ?? null;
            $marketplace = $lock->marketplaceStock->marketplace ?? null;

            return [
                'id' => $lock->id,
                'order_id' => $lock->order_id,
                'order_reference' => $order->reference_id ?? 'N/A',
                'order_item_id' => $lock->order_item_id,
                'variation_id' => $lock->variation_id,
                'variation_sku' => $variation->sku ?? 'N/A',
                'marketplace_id' => $lock->marketplace_id,
                'marketplace_name' => $marketplace->name ?? 'N/A',
                'quantity_locked' => $lock->quantity_locked,
                'lock_status' => $lock->lock_status,
                'locked_at' => $lock->locked_at ? $lock->locked_at->format('Y-m-d H:i:s') : null,
                'consumed_at' => $lock->consumed_at ? $lock->consumed_at->format('Y-m-d H:i:s') : null,
                'released_at' => $lock->released_at ? $lock->released_at->format('Y-m-d H:i:s') : null,
                'lock_duration_minutes' => $lock->locked_at ? now()->diffInMinutes($lock->locked_at) : null,
            ];
        });

        // Get summary statistics
        $summary = [
            'total_locked' => $locks->where('lock_status', 'locked')->sum('quantity_locked'),
            'total_consumed' => $locks->where('lock_status', 'consumed')->sum('quantity_locked'),
            'total_released' => $locks->where('lock_status', 'released')->sum('quantity_locked'),
            'active_locks_count' => $locks->where('lock_status', 'locked')->count(),
            'total_locks_count' => $locks->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'locks' => $formattedLocks,
                'summary' => $summary
            ]
        ]);
    }

    /**
     * Release/unfreeze a stock lock
     * 
     * @param Request $request
     * @param int $lockId
     * @return JsonResponse
     */
    public function releaseLock(Request $request, int $lockId): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500'
        ]);

        $adminId = Auth::id() ?? session('user_id');
        $reason = $request->input('reason');

        $stockLockService = app(StockLockService::class);
        $result = $stockLockService->releaseLock($lockId, $adminId, $reason);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'] ?? null
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }
}


