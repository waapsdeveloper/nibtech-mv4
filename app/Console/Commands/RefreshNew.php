<?php

namespace App\Console\Commands;

use App\Jobs\UpdateOrderInDB;
use App\Http\Controllers\BackMarketAPIController;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Customer_model;
use App\Models\Currency_model;
use App\Models\Country_model;
use App\Models\Variation_model;
use App\Models\Stock_model;
use App\Models\V2\MarketplaceStockModel;
use Carbon\Carbon;


use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\V2\SlackLogService;

class RefreshNew extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Refresh:new';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {
        $startTime = microtime(true);

        // Stock deduction entries are now written to storage/logs/stock_deduction.log (no DB writes here)

        // Log command start to named log file only (no Slack)
        SlackLogService::post(
            'order_sync',
            'info',
            "🔄 Refresh:new command started",
            [
                'command' => 'Refresh:new',
                'started_at' => now()->toDateTimeString(),
                'local_mode' => env('SYNC_DATA_IN_LOCAL', false)
            ],
            false,
            true
        );

        $bm = new BackMarketAPIController();
        $order_model = new Order_model();
        $order_item_model = new Order_item_model();

        $currency_codes = Currency_model::pluck('id','code')->toArray();
        $country_codes = Country_model::pluck('id','code')->toArray();

        // Statistics tracking
        $stats = [
            'new_orders_found' => 0,
            'new_orders_synced' => 0,
            'new_orderlines_validated' => 0,
            'incomplete_orders_found' => 0,
            'incomplete_orders_synced' => 0,
            'order_ids_synced' => []
        ];

        // Get new orders from API
        $resArray1 = $bm->getNewOrders();
        $orders = [];
        if ($resArray1 !== null) {
            $stats['new_orders_found'] = count($resArray1);

            foreach ($resArray1 as $orderObj) {
                if (!empty($orderObj)) {
                    // Validate orderlines
                    if (isset($orderObj->orderlines) && is_array($orderObj->orderlines)) {
                        foreach($orderObj->orderlines as $orderline){
                            $this->validateOrderlines($orderObj->order_id, $orderline->listing, $bm);
                            $stats['new_orderlines_validated']++;
                        }
                    }
                    $orders[] = $orderObj->order_id;
                }
            }

            // Sync new orders
            foreach($orders as $or){
                $this->updateBMOrder($or, $bm, $currency_codes, $country_codes, $order_model, $order_item_model);
                $stats['new_orders_synced']++;
                $stats['order_ids_synced'][] = $or;
            }
        }

        // Get incomplete orders (missing labels/delivery notes)
        $incompleteOrders = Order_model::whereIn('status', [0, 1, 2])
            ->where(function($query) {
                $query->whereNull('delivery_note_url')
                      ->orWhereNull('label_url');
            })
            ->where('order_type_id', 3)
            ->where('created_at', '>=', Carbon::now()->subDays(2))
            ->pluck('reference_id');

        $stats['incomplete_orders_found'] = $incompleteOrders->count();

        // Sync incomplete orders
        foreach($incompleteOrders as $order){
            $this->updateBMOrder($order, $bm, $currency_codes, $country_codes, $order_model, $order_item_model);
            $stats['incomplete_orders_synced']++;
            if (!in_array($order, $stats['order_ids_synced'])) {
                $stats['order_ids_synced'][] = $order;
            }
        }

        // Calculate duration
        $duration = round(microtime(true) - $startTime, 2);

        // Prepare summary message
        $summaryParts = [];
        if ($stats['new_orders_synced'] > 0) {
            $summaryParts[] = "New: {$stats['new_orders_synced']} order(s)";
        }
        if ($stats['new_orderlines_validated'] > 0) {
            $summaryParts[] = "Validated: {$stats['new_orderlines_validated']} orderline(s)";
        }
        if ($stats['incomplete_orders_synced'] > 0) {
            $summaryParts[] = "Incomplete: {$stats['incomplete_orders_synced']} order(s)";
        }

        $summaryText = !empty($summaryParts)
            ? " | " . implode(", ", $summaryParts)
            : " | No orders processed";

        // Limit order IDs in log context (max 20 to avoid huge logs)
        $orderIdsForLog = array_slice($stats['order_ids_synced'], 0, 20);
        if (count($stats['order_ids_synced']) > 20) {
            $orderIdsForLog[] = "... and " . (count($stats['order_ids_synced']) - 20) . " more";
        }

        // Log command completion with statistics to file only (no Slack)
        SlackLogService::post(
            'order_sync',
            'info',
            "✅ Refresh:new command completed{$summaryText} | Duration: {$duration}s",
            [
                'command' => 'Refresh:new',
                'completed_at' => now()->toDateTimeString(),
                'duration_seconds' => $duration,
                'local_mode' => env('SYNC_DATA_IN_LOCAL', false),
                'statistics' => [
                    'new_orders_found' => $stats['new_orders_found'],
                    'new_orders_synced' => $stats['new_orders_synced'],
                    'new_orderlines_validated' => $stats['new_orderlines_validated'],
                    'incomplete_orders_found' => $stats['incomplete_orders_found'],
                    'incomplete_orders_synced' => $stats['incomplete_orders_synced'],
                    'total_orders_synced' => count($stats['order_ids_synced']),
                    'order_ids_sample' => $orderIdsForLog
                ]
            ],
            false,
            true
        );

        return 0;
    }
    private function updateBMOrder($order_id, $bm, $currency_codes, $country_codes, $order_model, $order_item_model){

        $orderObj = $bm->getOneOrder($order_id);
        if(isset($orderObj->order_id)){

            // Get order before update to check if it's new or status changed
            $marketplaceId = (int) ($orderObj->marketplace_id ?? 1);
            $existingOrder = Order_model::where('reference_id', $orderObj->order_id)
                ->where('marketplace_id', $marketplaceId)
                ->first();

            $isNewOrder = $existingOrder === null;
            $oldStatus = $existingOrder ? $existingOrder->status : null;

            $order_model->updateOrderInDB($orderObj, false, $bm, $currency_codes, $country_codes);

            $order_item_model->updateOrderItemsInDB($orderObj, null, $bm);

            // Get order after update (refresh existing or fetch new) so deductListedStockForOrder doesn't re-query
            $order = $existingOrder !== null
                ? $existingOrder->refresh()
                : Order_model::where('reference_id', $orderObj->order_id)->where('marketplace_id', $marketplaceId)->first();

            // Deduct listed_stock if conditions are met (pass $order to avoid duplicate fetch)
            $this->deductListedStockForOrder($orderObj, $order, $isNewOrder, $oldStatus);
        }


    }

    private function validateOrderlines($order_id, $sku, $bm)
    {
        // Check if local sync mode is enabled - prevent live data updates to BackMarket
        $syncDataInLocal = env('SYNC_DATA_IN_LOCAL', false);

        if ($syncDataInLocal) {
            // Skip live API update when in local testing mode
            // Log through SlackLogService to named log file instead of default Laravel log
            SlackLogService::post(
                'order_sync',
                'info',
                "RefreshNew: Skipping validateOrderlines API call (SYNC_DATA_IN_LOCAL=true) - Order: {$order_id}, SKU: {$sku}",
                [
                    'order_id' => $order_id,
                    'sku' => $sku,
                    'would_set_state' => 2,
                    'command' => 'Refresh:new',
                    'local_mode' => true
                ]
            );
            $this->info("⚠️  Local Mode: Skipping orderline validation for order {$order_id}, SKU {$sku} (would set state to 2)");
            return null;
        }

        $end_point = 'orders/' . $order_id;
        $new_state = 2;

        // construct the request body
        $request = ['order_id' => $order_id, 'new_state' => $new_state, 'sku' => $sku];
        $request_JSON = json_encode($request);

        $result = $bm->apiPost($end_point, $request_JSON);

        return $result;
    }

    /**
     * Deduct listed_stock when order arrives with status 1 or when status changes from 1 to 2
     *
     * Rules:
     * - New order with status 1: Deduct 1
     * - Existing order status changes from 1 → 2: Deduct 1
     * - New order with status 2: NO deduction (remain as is)
     * - Always deduct 1 (not by quantity)
     * - Update both variations.listed_stock and marketplace_stock.listed_stock
     * - Allow negative stock values
     *
     * @param object $orderObj Raw order from API
     * @param \App\Models\Order_model|null $order Saved order (after update) - passed to avoid duplicate fetch
     */
    private function deductListedStockForOrder($orderObj, $order, $isNewOrder, $oldStatus)
    {
        if ($order === null) {
            return;
        }

        $marketplaceId = (int) ($orderObj->marketplace_id ?? 1);

        // Only process marketplace orders (order_type_id = 3)
        if ($order->order_type_id != 3) {
            return;
        }

        // Determine if we should deduct stock
        $shouldDeduct = false;
        $deductionReason = '';

        if ($isNewOrder && $order->status == 1) {
            // New order with status 1 (Pending)
            $shouldDeduct = true;
            $deductionReason = 'new_order_status_1';
        } elseif (!$isNewOrder && $oldStatus == 1 && $order->status == 2) {
            // Existing order status changed from 1 → 2 (Pending → Accepted)
            $shouldDeduct = true;
            $deductionReason = 'status_change_1_to_2';
        }

        if (!$shouldDeduct) {
            return;
        }

        // Get order items
        $orderItems = Order_item_model::where('order_id', $order->id)->get();

        if ($orderItems->isEmpty()) {
            return;
        }

        // Batch load variations (one query instead of N)
        $variationIds = $orderItems->pluck('variation_id')->filter()->unique()->values()->all();
        $variations = Variation_model::whereIn('id', $variationIds)->get()->keyBy('id');

        $deductions = [];

        // FIX: Use batch mode for loop-based Slack logging to prevent rate limit issues
        SlackLogService::startBatch();

        foreach ($orderItems as $item) {
            if (!$item->variation_id) {
                continue;
            }

            $variation = $variations->get($item->variation_id);
            if (!$variation) {
                continue;
            }

            // Deduct from variations.listed_stock (always deduct 1, not by quantity)
            $oldVariationStock = $variation->listed_stock ?? 0;
            $newVariationStock = $oldVariationStock - 1; // Allow negative
            $variation->listed_stock = $newVariationStock;
            $variation->save();

            // Deduct from marketplace_stock.listed_stock
            $marketplaceStock = MarketplaceStockModel::firstOrNew([
                'variation_id' => $variation->id,
                'marketplace_id' => $marketplaceId,
            ]);

            // FIX 1: Reload to get actual current value from database (handles firstOrNew edge case)
            if ($marketplaceStock->exists) {
                $marketplaceStock->refresh();
            }

            // Get the actual "before" value from database
            $oldMarketplaceStock = (int)($marketplaceStock->listed_stock ?? 0);
            $newMarketplaceStock = $oldMarketplaceStock - 1; // Allow negative
            $marketplaceStock->listed_stock = $newMarketplaceStock;
            $marketplaceStock->save();

            // FIX 2: Only log if stock actually decreased (prevent logging increases)
            $variationStockDecreased = ($newVariationStock < $oldVariationStock);
            $marketplaceStockDecreased = ($newMarketplaceStock < $oldMarketplaceStock);

            // Only create log entry if at least one stock value decreased (log to file instead of DB to avoid connection churn)
            if ($variationStockDecreased || $marketplaceStockDecreased) {
                $payload = [
                    'variation_id' => $variation->id,
                    'marketplace_id' => $marketplaceId,
                    'order_id' => $order->id,
                    'order_reference_id' => $order->reference_id,
                    'variation_sku' => $variation->sku,
                    'before_variation_stock' => $oldVariationStock,
                    'before_marketplace_stock' => $oldMarketplaceStock,
                    'after_variation_stock' => $newVariationStock,
                    'after_marketplace_stock' => $newMarketplaceStock,
                    'deduction_reason' => $deductionReason,
                    'order_status' => $order->status,
                    'is_new_order' => $isNewOrder,
                    'old_order_status' => $oldStatus,
                    'deduction_at' => now()->toIso8601String(),
                ];
                Log::channel('stock_deduction')->info('stock_deduction', $payload);
            }

            $deductions[] = [
                'variation_id' => $variation->id,
                'variation_sku' => $variation->sku,
                'old_variation_stock' => $oldVariationStock,
                'new_variation_stock' => $newVariationStock,
                'old_marketplace_stock' => $oldMarketplaceStock,
                'new_marketplace_stock' => $newMarketplaceStock,
            ];
        }

        // Post batch summary instead of individual messages (prevents Slack rate limiting)
        SlackLogService::postBatch(1); // Threshold of 1 to always post summary

        // Summary is now handled by postBatch() above, no need for separate summary log
    }

    /**
     * Auto-truncate stock_deduction_logs table if oldest record is more than 3 hours old
     */
    private function autoTruncateStockDeductionLogs()
    {
        $oldestRecord = DB::table('stock_deduction_logs')
            ->orderBy('deduction_at', 'asc')
            ->first();

        if ($oldestRecord) {
            $oldestDate = Carbon::parse($oldestRecord->deduction_at);
            $hoursAgo = now()->diffInHours($oldestDate);

            if ($hoursAgo >= 3) {
                $recordCount = DB::table('stock_deduction_logs')->count();
                DB::table('stock_deduction_logs')->truncate();
                
                $this->info("🗑️  Auto-truncated stock_deduction_logs table ({$recordCount} records removed - oldest record was {$hoursAgo} hours old)");
            }
        }
    }

}
