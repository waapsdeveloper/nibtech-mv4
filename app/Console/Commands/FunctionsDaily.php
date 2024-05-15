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
        $this->remove_extra_customers();
        $this->check_stock_status();
    }
    private function remove_extra_customers(){

        $data['customers'] = Customer_model::where('is_vendor',null)->get();

        foreach($data['customers'] as $customer){
            if($customer->orders->count() == 0){
                $customer->delete();
                $customer->forceDelete();
            }
        }

    }

    private function check_stock_status(){

        $items = Order_item_model::where('linked_id',null)->whereHas('order', function ($query) {
            $query->where('order_type_id', '!=', 1);
        })->get();
        foreach($items as $item){
            $item->linked_id = $item->stock->last_item()->id;
        }
        $stocks = Stock_model::all();
        foreach($stocks as $stock){

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
