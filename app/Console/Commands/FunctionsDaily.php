<?php

namespace App\Console\Commands;

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

class FunctionsDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'functions:daily';

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
        ini_set('max_execution_time', 1200);
        $this->remove_extra_customers();
        $this->check_stock_status();
    }
    private function remove_extra_customers(){

        // $data['customers'] = Customer_model::where('is_vendor',null)->get();

        // foreach($data['customers'] as $customer){
        //     if($customer->orders->count() == 0){
        //         $customer->delete();
        //         $customer->forceDelete();
        //     }
        // }

    }

    private function check_stock_status(){

        // $items = Order_item_model::where('linked_id',null)->where('stock_id','!=',null)->whereHas('order', function ($query) {
        //     $query->where('order_type_id', '!=', 1);
        // })->get();
        // foreach($items as $item){
        //     if($item->stock != null){

        //         $litem = $item->stock->last_item();
        //         if($litem != null){
        //         $item->linked_id = $litem->id;
        //         }
        //     }
        // }


        $stocks = Stock_model::where('status','!=',null)->where('order_id','!=',null)->orderByDesc('id')->get();
        foreach($stocks as $stock){

            $last_item = $stock->last_item();

            $items2 = Order_item_model::where(['stock_id'=>$stock->id,'linked_id'=>null])->whereHas('order', function ($query) {
                $query->where('order_type_id', 1)->where('reference_id','<=',10009);
            })->orderBy('id','asc')->get();
            if($items2->count() > 1){
                $i = 0;
                foreach($items2 as $item2){
                    $i ++;
                    if($i == 1){
                        $stock->order_id = $item2->order_id;
                        $stock->save();
                    }else{
                        $item2->delete();
                    }
                }
            }

            $items3 = Order_item_model::where(['stock_id'=>$stock->id, 'linked_id' => $stock->purchase_item->id])->whereHas('order', function ($query) {
                $query->whereIn('order_type_id', [5,3]);
            })->orderBy('id','asc')->get();
            if($items3->count() > 1){
                $i = 0;
                foreach($items3 as $item3){
                    $i ++;
                    if($i == 1){
                    }else{
                        $item3->linked_id = null;
                        $item3->save();
                    }
                }
            }

            $items4 = Order_item_model::where(['stock_id'=>$stock->id])->whereHas('order', function ($query) {
                $query->whereIn('order_type_id', [5,3]);
            })->orderBy('id','asc')->get();
            if($items4->count() == 1){
                foreach($items4 as $item4){
                    if($item4->linked_id != $stock->purchase_item->id && $item4->linked_id != null){
                        $item4->linked_id = $stock->purchase_item->id;
                        $item4->save();
                    }
                }
            }
            $items5 = Order_item_model::where(['stock_id'=>$stock->id,'linked_id'=>null])->whereHas('order', function ($query) {
                $query->whereIn('order_type_id', [2,3,4,5]);
            })->orderBy('id','asc')->get();
            if($items5->count() == 1){
                foreach($items5 as $item5){
                    $last_item = $stock->last_item();
                    $item5->linked_id = $last_item->id;
                    $item5->save();
                }
                    $last_item = $stock->last_item();
            }
            $item = $stock->last_item();
            if($item != null){

                if(in_array($item->order->order_type_id,[1,4])){
                    $stock->status = 1;
                    $stock->save();
                }else{
                    $stock->status = 2;
                    $stock->save();

                }
            }
        }




    }
}
