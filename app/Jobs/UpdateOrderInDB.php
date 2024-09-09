<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Customer_model;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Variation_model;
use App\Models\Stock_model;
use Carbon\Carbon;
use App\Http\Controllers\BackMarketAPIController;
use App\Models\Currency_model;
use App\Models\Country_model;

class UpdateOrderInDB implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $orderObj;
    protected $bm;
    protected $currency_codes;
    protected $country_codes;
    protected $invoice;
    protected $is_vendor;
    protected $tester;

    public function __construct($orderObj, $invoice = false, $is_vendor = false, $tester = null)
    {


        $bm = new BackMarketAPIController();

        $currency_codes = Currency_model::pluck('id','code');
        $country_codes = Country_model::pluck('id','code');

        $this->orderObj = $orderObj;
        $this->bm = $bm;
        $this->currency_codes = $currency_codes;
        $this->country_codes = $country_codes;
        $this->invoice = $invoice;
        $this->is_vendor = $is_vendor;
        $this->tester = $tester;
    }

    public function handle()
    {
        // Your implementation here
        $orderObj = $this->orderObj;
        $invoice = $this->invoice;
        $bm = $this->bm;
        $currency_codes = $this->currency_codes;
        $country_codes = $this->country_codes;

        if (!isset($orderObj->order_id)) {
            print_r($orderObj);
        }

        $order = Order_model::firstOrNew(['reference_id' => $orderObj->order_id]);
        $order->customer_id = $this->updateCustomerInDB();
        $order->status = $this->mapStateToStatus();
        $order->currency = $currency_codes[$orderObj->currency];
        $order->order_type_id = 3;
        $order->price = $orderObj->price;
        $order->delivery_note_url = $orderObj->delivery_note;
        if ($order->label_url == null && $bm->getOrderLabel($orderObj->order_id) != null) {
            if ($bm->getOrderLabel($orderObj->order_id)->results != null) {
                $order->label_url = $bm->getOrderLabel($orderObj->order_id)->results[0]->labelUrl;
            }
        }
        $order->tracking_number = $orderObj->tracking_number;
        if ($invoice == true) {
            $order->processed_by = session('user_id');
            $order->processed_at = now()->format('Y-m-d H:i:s');
        }
        $order->created_at = Carbon::parse($orderObj->date_creation)->format('Y-m-d H:i:s');
        $order->updated_at = Carbon::parse($orderObj->date_modification)->format('Y-m-d H:i:s');
        $order->save();

        $this->updateOrderItemsInDB();



    }

    private function mapStateToStatus()
    {

        $order = $this->orderObj;

        $orderlines = $order->orderlines;
        // echo $order->state." ";

        // if the state of order or is 0 or 1, then the order status is 'Created'
        if ($order->state == 0 || $order->state == 1) return 1;

        if ($order->state == 3) {
        foreach($orderlines as $key => $value) {
            // in case there are some of the orderlines not being validated, then the status is still 'Created'
            if ($orderlines[$key]->state == 0 || $orderlines[$key]->state == 1) return 1;
            else if ($orderlines[$key]->state == 2) return 2;
            else continue;
        }
        // if all the states of orderlines are 2, the order status should be 'Validated'
        // return 3;
        }

        if ($order->state == 8) return 4;

        if ($order->state == 9) {
        // if any one of the states of orderlines is 6, the order status should be 'Returned'
        foreach($orderlines as $key => $value) {
            if ($orderlines[$key]->state == 6) return 6;
        }

        // if any one of the states of orderlines is 4 or 5
        foreach($orderlines as $key => $value) {
            if ($orderlines[$key]->state == 4 || $orderlines[$key]->state == 5) return 5;
        }

        // if any one of the states of orderlines is 3, the order status should be 'Shipped'
        foreach($orderlines as $key => $value) {
            if ($orderlines[$key]->state == 3) return 3;
        }
        }
    }

    private function updateCustomerInDB()
    {
        $orderObj = $this->orderObj;
        $is_vendor = $this->is_vendor;
        $currency_codes = $this->currency_codes;
        $country_codes = $this->country_codes;
        // Your implementation here using Eloquent ORM
        // Example:
        // $orderObj = (object) $orderObj[0];
        // print_r($orderObj);
        $customerObj = $orderObj->billing_address;

        if((int) $customerObj->phone > 0){
            $numberWithoutSpaces = str_replace(' ', '', strval($customerObj->phone));
            $phone =  $numberWithoutSpaces;
        }else{
            $numberWithoutSpaces = str_replace(' ', '', strval($orderObj->shipping_address->phone));
            $phone =  $numberWithoutSpaces;
        }

        $customer = Customer_model::firstOrNew(['company' => $customerObj->company,'first_name' => $customerObj->first_name,'last_name' => $customerObj->last_name,'phone' => $phone,]);
        $customer->company = $customerObj->company;
        $customer->first_name = $customerObj->first_name;
        $customer->last_name = $customerObj->last_name;
        $customer->street = $customerObj->street;
        $customer->street2 = $customerObj->street2;
        $customer->postal_code = $customerObj->postal_code;
        // echo $customerObj->country." ";
        // if(Country_model::where('code', $customerObj->country)->first()  == null){
            // dd($country_codes);
        // }
        $customer->country = $country_codes[$customerObj->country];
        $customer->city = $customerObj->city;
        $customer->phone =  $phone;
        $customer->email = $customerObj->email;
        if($is_vendor == true){
            $customer->is_vendor = 1;
        }
        $customer->reference = "BackMarket";
        // ... other fields
        $customer->save();
        // echo "----------------------------------------";
        return $customer->id;
    }

    private function updateOrderItemsInDB()
    {

        $orderObj = $this->orderObj;
        $tester = $this->tester;
        $bm = $this->bm;

        foreach ($orderObj->orderlines as $itemObj) {
            // Your implementation here using Eloquent ORM
            // Example:
            // print_r($orderObj);
            // echo "<br>";
            $orderItem = Order_item_model::firstOrNew(['reference_id' => $itemObj->id]);
            $variation = Variation_model::where(['reference_id' => $itemObj->listing_id])->first();
            if($variation == null){
                // $this->updateBMOrdersAll();
                $list = $bm->getOneListing($itemObj->listing_id);
                $variation = Variation_model::firstOrNew(['reference_id' => $list->listing_id]);
                $variation->name = $list->title;
                $variation->sku = $list->sku;
                $variation->grade = $list->state+1;
                $variation->status = 1;
                // ... other fields
                $variation->save();
                // dd($orderObj);
            }
            if($orderItem->stock_id == null){
                if($itemObj->imei != null || $itemObj->serial_number != null){
                    if($itemObj->imei != null){
                        $stock = Stock_model::withTrashed()->firstOrNew(['imei' => $itemObj->imei]);
                        $stock->imei = $itemObj->imei;
                        if($stock->id != null){
                            $stock->status = 2;
                            foreach($stock->order_item as $item){
                                if($item->order_id == $stock->order_id){
                                    $orderItem->linked_id = $item->id;
                                    break;
                                }
                            }
                        }
                    }
                    if($itemObj->serial_number != null){
                        $stock = Stock_model::withTrashed()->firstOrNew(['serial_number' => $itemObj->serial_number,]);
                        if(strlen($itemObj->serial_number) > 20){
                            continue;
                        }
                        $stock->serial_number = $itemObj->serial_number;
                        if($stock->id != null){
                            $stock->status = 2;
                            foreach($stock->order_item as $item){
                                if($item->order_id == $stock->order_id){
                                    $orderItem->linked_id = $item->id;
                                    break;
                                }
                            }
                        }
                    }

                    $stock->variation_id = $variation->id;
                    $stock->created_at = Carbon::parse($itemObj->date_creation)->format('Y-m-d H:i:s');
                    $stock->save();
                    $orderItem->stock_id = $stock->id;

                }
            }
            $orderItem->order_id = Order_model::where(['reference_id' => $orderObj->order_id])->first()->id;
            $orderItem->created_at = Carbon::parse($itemObj->date_creation)->format('Y-m-d H:i:s');
            $orderItem->variation_id = $variation->id;
            $orderItem->reference_id = $itemObj->id;
            $orderItem->price = $itemObj->price;
            $orderItem->quantity = $itemObj->quantity;
            switch ($itemObj->state){
                case 0: $state = 0; break;
                case 8: $state = 0; break;
                case 1: $state = 1; break;
                case 2: $state = 2; break;
                case 3: $state = 3; break;
                case 4: $state = 4; break;
                case 5: $state = 5; break;
                case 6: $state = 6; break;
                case 7: $state = 0; break;
            }
            $orderItem->status = $state;
            // ... other fields
            $orderItem->save();
            // echo "----------------------------------------";
        }


    }


}
