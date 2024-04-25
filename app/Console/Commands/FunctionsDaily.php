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
    protected $signature = 'Functions:daily';

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

        $stocks = Stock_model::all();
        foreach($stocks as $stock){

            if($stock->status == 1){
                $sale_status = Order_item_model::where(['stock_id'=>$stock->id,'linked_id'=>$stock->purchase_item->id])->first();
                // print_r($sale_status);
                if($sale_status != null){
                    $stock->status = 2;
                    $stock->save();
                }
            }
            if($stock->status == 2){
                $sale_status = Order_item_model::where(['stock_id'=>$stock->id,'linked_id'=>$stock->purchase_item->id])->first();
                // print_r($sale_status);
                if($sale_status == null){
                    $stock->status = 1;
                    $stock->save();
                }
            }
        }



    }
}
