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


        $last_id = Order_item_model::where('care_id','!=',null)->where('created_at','>=',Carbon::now()->subDays(5))->whereHas('sale_order')->orderBy('reference_id','asc')->first()->care_id;
        echo $last_id;
        $care = $bm->getAllCare(false, ['last_id'=>$last_id,'page-size'=>50]);
        // $care = $bm->getAllCare(false, ['page-size'=>50]);
        // print_r($care);
        $care_line = collect($care)->pluck('id','orderline')->toArray();
        $care_keys = array_keys($care_line);


        // Assuming $care_line is already defined from the previous code
        $careLineKeys = array_keys($care_line);

        // Construct the raw SQL expression for the CASE statement
        // $caseExpression = "CASE ";
        foreach ($care_line as $id => $care) {
            // $caseExpression .= "WHEN reference_id = $id THEN $care ";
            $order = Order_item_model::where('reference_id',$id)->update(['care_id' => $care]);
            if($order != 0){
                print_r($order);
            }

        }
        $care = $bm->getAllCare(false, ['page-size'=>50]);
        // $care = $bm->getAllCare(false, ['page-size'=>50]);
        // print_r($care);
        $care_line = collect($care)->pluck('id','orderline')->toArray();
        $care_keys = array_keys($care_line);


        // Assuming $care_line is already defined from the previous code
        $careLineKeys = array_keys($care_line);

        // Construct the raw SQL expression for the CASE statement
        // $caseExpression = "CASE ";
        foreach ($care_line as $id => $care) {
            // $caseExpression .= "WHEN reference_id = $id THEN $care ";
            $order = Order_item_model::where('reference_id',$id)->update(['care_id' => $care]);
            if($order != 0){
                print_r($order);
            }

        }

    }
    private function updateBMOrder($order_id, $bm, $currency_codes, $country_codes, $order_model, $order_item_model){

        $orderObj = $bm->getOneOrder($order_id);
        if(isset($orderObj->order_id)){

            $order_model->updateOrderInDB($orderObj, false, $bm, $currency_codes, $country_codes);

            $order_item_model->updateOrderItemsInDB($orderObj, null, $bm);
        }

        // $serializedPayload = serialize([$orderObj]);

        // Query the database to check if a job with the same payload already exists
        // if (!Job_model::where('payload', $serializedPayload)->exists()) {
        //     // Dispatch the job if it doesn't already exist
        //     UpdateOrderInDB::dispatch($orderObj);
        // }


    }

    // private function updateOrderInDB($orderObj, $invoice = false)
    // {
    //     // Your implementation here using Eloquent ORM
    //     // Example:
    //     // $orderObj = (object) $orderObj[0];
    //     if(!isset($orderObj->order_id)){
    //         print_r($orderObj);
    //     }

    //     $bm = new BackMarketAPIController;
    //     $order = Order_model::firstOrNew(['reference_id' => $orderObj->order_id]);
    //     $order->customer_id = $this->updateCustomerInDB($orderObj);
    //     $order->status = $this->mapStateToStatus($orderObj);
    //     $order->currency = $this->currency_codes[$orderObj->currency];
    //     $order->order_type_id = 3;
    //     $order->price = $orderObj->price;
    //     $order->delivery_note_url = $orderObj->delivery_note;
    //     if($order->label_url == null && $bm->getOrderLabel($orderObj->order_id) != null){
    //         if($bm->getOrderLabel($orderObj->order_id)->results != null){
    //             $order->label_url = $bm->getOrderLabel($orderObj->order_id)->results[0]->labelUrl;
    //         }
    //     }
    //     if($invoice == true){
    //         $order->processed_by = session('user_id');
    //         $order->processed_at = now()->format('Y-m-d H:i:s');
    //     }
    //     $order->created_at = Carbon::parse($orderObj->date_creation)->format('Y-m-d H:i:s');
    //     $order->updated_at = Carbon::parse($orderObj->date_modification)->format('Y-m-d H:i:s');
    //     // echo Carbon::parse($orderObj->date_creation)->format('Y-m-d H:i:s'). "       ";
    //     // ... other fields
    //     $order->save();

    //     // print_r(Order_model::find($order->id));
    //     // echo "----------------------------------------";
    // }
    // private function updateCustomerInDB($orderObj, $is_vendor = false)
    // {
    //     if($this->currency_codes == null){
    //         $this->currency_codes = Currency_model::pluck('id','code');
    //         $this->country_codes = Country_model::pluck('id','code');

    //     }
    //     // Your implementation here using Eloquent ORM
    //     // Example:
    //     // $orderObj = (object) $orderObj[0];
    //     // print_r($orderObj);
    //     $customerObj = $orderObj->billing_address;

    //     if((int) $customerObj->phone > 0){
    //         $numberWithoutSpaces = str_replace(' ', '', strval($customerObj->phone));
    //         $phone =  $numberWithoutSpaces;
    //     }else{
    //         $numberWithoutSpaces = str_replace(' ', '', strval($orderObj->shipping_address->phone));
    //         $phone =  $numberWithoutSpaces;
    //     }

    //     $customer = Customer_model::firstOrNew(['company' => $customerObj->company,'first_name' => $customerObj->first_name,'last_name' => $customerObj->last_name,'phone' => $phone,]);
    //     $customer->company = $customerObj->company;
    //     $customer->first_name = $customerObj->first_name;
    //     $customer->last_name = $customerObj->last_name;
    //     $customer->street = $customerObj->street;
    //     $customer->street2 = $customerObj->street2;
    //     $customer->postal_code = $customerObj->postal_code;
    //     // echo $customerObj->country." ";
    //     // if(Country_model::where('code', $customerObj->country)->first()  == null){
    //         // dd($this->country_codes);
    //     // }
    //     $customer->country = $this->country_codes[$customerObj->country];
    //     $customer->city = $customerObj->city;
    //     $customer->phone =  $phone;
    //     $customer->email = $customerObj->email;
    //     if($is_vendor == true){
    //         $customer->is_vendor = 1;
    //     }
    //     $customer->reference = "BackMarket";
    //     // ... other fields
    //     $customer->save();
    //     // echo "----------------------------------------";
    //     return $customer->id;
    // }

    // private function updateOrderItemsInDB($orderObj, $tester = null)
    // {

    //     $bm = new BackMarketAPIController();
    //     // $orderObj = (object) $orderObj[0];
    //     foreach ($orderObj->orderlines as $itemObj) {
    //         // Your implementation here using Eloquent ORM
    //         // Example:
    //         // print_r($orderObj);
    //         // echo "<br>";
    //         $orderItem = Order_item_model::firstOrNew(['reference_id' => $itemObj->id]);
    //         $variation = Variation_model::where(['reference_id' => $itemObj->listing_id])->first();
    //         if($variation == null){
    //             // $this->updateBMOrdersAll();
    //             $list = $bm->getOneListing($itemObj->listing_id);
    //             $variation = Variation_model::firstOrNew(['reference_id' => $list->listing_id]);
    //             $variation->name = $list->title;
    //             $variation->sku = $list->sku;
    //             $variation->grade = $list->state+1;
    //             $variation->status = 1;
    //             // ... other fields
    //             $variation->save();
    //             // dd($orderObj);
    //         }
    //         if($itemObj->imei != null || $itemObj->serial_number != null){
    //             if($itemObj->imei != null){
    //                 $stock = Stock_model::firstOrNew(['imei' => $itemObj->imei]);
    //                 $stock->imei = $itemObj->imei;
    //                 if($stock->id != null){
    //                     $stock->status = 2;
    //                 }
    //             }
    //             if($itemObj->serial_number != null){
    //                 $stock = Stock_model::firstOrNew(['serial_number' => $itemObj->serial_number,]);
    //                 if(strlen($itemObj->serial_number) > 20){
    //                     continue;
    //                 }
    //                 $stock->serial_number = $itemObj->serial_number;
    //                 if($stock->id != null){
    //                     $stock->status = 2;
    //                 }
    //             }
    //             $stock->tester = $tester;
    //             $stock->variation_id = $variation->id;
    //             $stock->created_at = Carbon::parse($itemObj->date_creation)->format('Y-m-d H:i:s');
    //             $stock->save();
    //             $orderItem->stock_id = $stock->id;

    //         }
    //         $orderItem->order_id = Order_model::where(['reference_id' => $orderObj->order_id])->first()->id;
    //         $orderItem->created_at = Carbon::parse($itemObj->date_creation)->format('Y-m-d H:i:s');
    //         $orderItem->variation_id = $variation->id;
    //         $orderItem->reference_id = $itemObj->id;
    //         $orderItem->price = $itemObj->price;
    //         $orderItem->quantity = $itemObj->quantity;
    //         switch ($itemObj->state){
    //             case 0: $state = 0; break;
    //             case 8: $state = 0; break;
    //             case 1: $state = 1; break;
    //             case 2: $state = 2; break;
    //             case 3: $state = 3; break;
    //             case 4: $state = 4; break;
    //             case 5: $state = 5; break;
    //             case 6: $state = 6; break;
    //             case 7: $state = 0; break;
    //         }
    //         $orderItem->status = $state;
    //         // ... other fields
    //         $orderItem->save();
    //         // echo "----------------------------------------";
    //     }
    // }

    // private function mapStateToStatus($order) {
    //     $orderlines = $order->orderlines;
    //     // echo $order->state." ";

    //     // if the state of order or is 0 or 1, then the order status is 'Created'
    //     if ($order->state == 0 || $order->state == 1) return 1;

    //     if ($order->state == 3) {
    //     foreach($orderlines as $key => $value) {
    //         // in case there are some of the orderlines not being validated, then the status is still 'Created'
    //         if ($orderlines[$key]->state == 0 || $orderlines[$key]->state == 1) return 1;
    //         else if ($orderlines[$key]->state == 2) return 2;
    //         else continue;
    //     }
    //     // if all the states of orderlines are 2, the order status should be 'Validated'
    //     // return 3;
    //     }

    //     if ($order->state == 8) return 4;

    //     if ($order->state == 9) {
    //     // if any one of the states of orderlines is 6, the order status should be 'Returned'
    //     foreach($orderlines as $key => $value) {
    //         if ($orderlines[$key]->state == 6) return 6;
    //     }

    //     // if any one of the states of orderlines is 4 or 5
    //     foreach($orderlines as $key => $value) {
    //         if ($orderlines[$key]->state == 4 || $orderlines[$key]->state == 5) return 5;
    //     }

    //     // if any one of the states of orderlines is 3, the order status should be 'Shipped'
    //     foreach($orderlines as $key => $value) {
    //         if ($orderlines[$key]->state == 3) return 3;
    //     }
    //     }
    // }
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
