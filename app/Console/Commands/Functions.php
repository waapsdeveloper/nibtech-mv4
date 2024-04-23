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

class Functions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Functions:ten';

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
        $this->check_linked_orders();
        $this->duplicate_orders();
    }
    private function check_linked_orders(){

        $items = Order_item_model::where(['linked_id'=>null])->whereHas('order', function ($q) {
            $q->where('order_type_id',3);
        })->get();
        foreach($items as $item){
            $it = Order_item_model::where(['stock_id'=>$item->stock_id])->whereHas('order', function ($q) {
                $q->where('order_type_id',1);
            })->first();
            if($it != null){
                Order_item_model::where('id',$item->id)->update(['linked_id'=>$it->id]);
            }
        }

    }

    private function duplicate_orders(){

        // Subquery to get the IDs of duplicate orders based on reference_id
        $subquery = Order_model::select('id')->where('reference_id','!=',null)->where('order_type_id',3)
        ->selectRaw('ROW_NUMBER() OVER (PARTITION BY reference_id ORDER BY id) AS row_num');

        // Final query to delete duplicate orders
        Order_model::whereIn('id', function ($query) use ($subquery) {
            $query->select('id')->fromSub($subquery, 'subquery')->where('row_num', '>', 1);
        })->delete();

        // Subquery to get the IDs of duplicate orders based on reference_id
        $subquery = Order_item_model::select('id')->where('reference_id','!=',null)
        ->selectRaw('ROW_NUMBER() OVER (PARTITION BY reference_id ORDER BY id) AS row_num');

        // Final query to delete duplicate orders
        Order_item_model::whereIn('id', function ($query) use ($subquery) {
            $query->select('id')->fromSub($subquery, 'subquery')->where('row_num', '>', 1);
        })->delete();

    }
}
