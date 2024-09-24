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

        $bm = new BackMarketAPIController();
        $order_model = new Order_model();
        $order_item_model = new Order_item_model();

        $currency_codes = Currency_model::pluck('id','code');
        $country_codes = Country_model::pluck('id','code');

        $resArray1 = $bm->getNewOrders();
        $orders = [];
        if ($resArray1 !== null) {
            foreach ($resArray1 as $orderObj) {
                if (!empty($orderObj)) {
                    foreach($orderObj->orderlines as $orderline){
                        $this->validateOrderlines($orderObj->order_id, $orderline->listing, $bm);
                    }
                    $orders[] = $orderObj->order_id;
                }
            }
            foreach($orders as $or){
                $this->updateBMOrder($or, $bm, $currency_codes, $country_codes, $order_model, $order_item_model);
            }
        }
        $orders = Order_model::whereIn('status', [0, 1, 2])
            ->orWhereNull('delivery_note_url')
            ->orWhereNull('label_url')
            ->where('order_type_id', 3)
            ->where('created_at', '>=', Carbon::now()->subDays(2))
            ->pluck('reference_id');
        foreach($orders as $order){
            $this->updateBMOrder($order, $bm, $currency_codes, $country_codes, $order_model, $order_item_model);
        }

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
        $end_point = 'orders/' . $order_id;
        $new_state = 2;

        // construct the request body
        $request = ['order_id' => $order_id, 'new_state' => $new_state, 'sku' => $sku];
        $request_JSON = json_encode($request);

        $result = $bm->apiPost($end_point, $request_JSON);

        return $result;
    }

}
