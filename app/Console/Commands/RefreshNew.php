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
use Carbon\Carbon;


use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
        
        // Log command start to named log file (not generic laravel.log)
        SlackLogService::post(
            'order_sync',
            'info',
            "🔄 Refresh:new command started",
            [
                'command' => 'Refresh:new',
                'started_at' => now()->toDateTimeString(),
                'local_mode' => env('SYNC_DATA_IN_LOCAL', false)
            ]
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
        
        // Log command completion with statistics
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
            ]
        );
        
        return 0;
    }
    private function updateBMOrder($order_id, $bm, $currency_codes, $country_codes, $order_model, $order_item_model){

        $orderObj = $bm->getOneOrder($order_id);
        if(isset($orderObj->order_id)){

            $order_model->updateOrderInDB($orderObj, false, $bm, $currency_codes, $country_codes);

            $order_item_model->updateOrderItemsInDB($orderObj, null, $bm);
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

}
