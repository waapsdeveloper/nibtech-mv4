<?php

namespace App\Console\Commands;

use App\Models\Stock_model;
use App\Models\Stock_operations_model;
use Illuminate\Console\Command;

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
        // $this->remove_extra_variations();
        $this->check_stock_status();
    }

    private function check_stock_status(){

        $stocks = Stock_model::where('status',2)->where('order_id','!=',null)->where('sale_order_id',null)->orderByDesc('id')->get();
        foreach($stocks as $stock){

            $last_item = $stock->last_item();
            if(in_array($last_item->order->order_type_id, [3,5])){
                $stock->sale_order_id = $last_item->order_id;
                $stock->save();
            }

        }




    }
}
