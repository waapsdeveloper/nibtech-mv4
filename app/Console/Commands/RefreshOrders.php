<?php

namespace App\Console\Commands;

use App\Jobs\UpdateOrderInDB;

use App\Http\Controllers\BackMarketAPIController;

use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Currency_model;
use App\Models\Country_model;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class RefreshOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh:orders';

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
        $bm = new BackMarketAPIController();
        $order_model = new Order_model();
        $order_item_model = new Order_item_model();

        $currency_codes = Currency_model::pluck('id','code')->toArray();
        $country_codes = Country_model::pluck('id','code')->toArray();

        $resArray1 = $bm->getNewOrders(['page-size'=>50]);
        if ($resArray1 !== null) {
            foreach ($resArray1 as $orderObj) {
                if (!empty($orderObj)) {
                    foreach($orderObj->orderlines as $orderline){
                        $this->validateOrderlines($orderObj->order_id, $orderline->listing, $bm);
                    }
                }
            }

            // Align our status with Back Market for pending orders: if BM says pending (state=1) but we have status != 2,
            // set our status to 2 (pending) — but only when no IMEI is attached (order not yet processed).
            $statusCorrected = 0;
            foreach ($resArray1 as $orderObj) {
                if (empty($orderObj) || empty($orderObj->order_id)) {
                    continue;
                }
                $referenceId = $orderObj->order_id;
                $marketplaceId = (int) ($orderObj->marketplace_id ?? 1);
                $order = Order_model::where('reference_id', $referenceId)
                    ->where('marketplace_id', $marketplaceId)
                    ->where('order_type_id', 3)
                    ->first();
                if (!$order || $order->status == 2) {
                    continue;
                }
                $hasImeiAttached = Order_item_model::where('order_id', $order->id)
                    ->where('stock_id', '>', 0)
                    ->exists();
                if ($hasImeiAttached) {
                    continue;
                }
                $order->status = 2;
                $order->save();
                $statusCorrected++;
            }
            if ($statusCorrected > 0) {
                $this->info("Status corrected to pending (2): {$statusCorrected} order(s) to match Back Market.");
            }
        }

            $modification = false;
        $resArray = $bm->getAllOrders(1, ['page-size'=>50], $modification);
        if ($resArray !== null) {
            foreach ($resArray as $orderObj) {
                if (!empty($orderObj)) {
                $order_model->updateOrderInDB($orderObj, false, $bm, $currency_codes, $country_codes);
                $order_item_model->updateOrderItemsInDB($orderObj,null,$bm);
                }
            }
        } else {
            echo 'No orders have been modified in 3 months!';
        }

    }

    private function validateOrderlines($order_id, $sku, $bm)
    {
        $end_point = 'orders/' . $order_id;
        $new_state = 2;

        // construct the request body
        $request = ['order_id' => $order_id, 'new_state' => $new_state, 'sku' => $sku];
        $request_JSON = json_encode($request);

        $result = $bm->apiPost($end_point, $request_JSON);

        return $result;
    }

}
