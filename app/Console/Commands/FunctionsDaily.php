<?php

namespace App\Console\Commands;

use App\Http\Livewire\Order;
use App\Models\Charge_model;
use App\Models\Order_charge_model;
use App\Models\Order_model;
use App\Models\Product_storage_sort_model;
use App\Models\Stock_model;
use App\Models\Stock_operations_model;
use App\Models\Variation_model;
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
        $this->add_order_charge();
        $this->misc();
    }

    private function check_stock_status(){

        $stocks = Stock_model::where('status',2)->where('order_id','!=',null)->where('sale_order_id',null)->orderByDesc('id')->limit(1000)->get();
        foreach($stocks as $stock){

            $last_item = $stock->last_item();
            if(in_array($last_item->order->order_type_id, [3,5])){
                $stock->sale_order_id = $last_item->order_id;
                $stock->save();
            }

        }




    }
    private function add_order_charge(){
        $orders = Order_model::whereIn('status',[3,5,6])->where('order_type_id',3)->whereNull('charges')->limit(50000)->get();
        $charges = Charge_model::where(['charge_frequency_id'=>2,'order_type_id'=>3,'status'=>1])->get();
        foreach($orders as $order){
            if($charges->where('payment_method_id',$order->payment_method_id)->count() == 0){
                continue;
            }
            $total_charge = 0;
            if($order->status == 5){
                $charge = $charges->where('name',"CCBM")->first();
                $order_charge = Order_charge_model::firstOrNew(['order_id'=>$order->id,'charge_value_id'=>$charge->current_value->id]);
                if($charge->amount_type == 1){
                    $order_charge->amount = $charge->current_value->value;
                    $total_charge += $charge->current_value->value;
                }elseif($charge->amount_type == 2){
                    $order_charge->amount = $order->price * $charge->current_value->value / 100;
                    $total_charge += $order->price * $charge->current_value->value / 100;
                }
                $order_charge->save();

            }else{

                foreach($charges->whereNull('payment_method_id') as $charge){
                    $order_charge = Order_charge_model::firstOrNew(['order_id'=>$order->id,'charge_value_id'=>$charge->current_value->id]);
                    if($charge->amount_type == 1){
                        $order_charge->amount = $charge->current_value->value;
                        $total_charge += $charge->current_value->value;
                    }elseif($charge->amount_type == 2){
                        $order_charge->amount = $order->price * $charge->current_value->value / 100;
                        $total_charge += $order->price * $charge->current_value->value / 100;
                    }
                    $order_charge->save();

                }
            }
            $order->charges = $total_charge;
            $order->save();
            echo $order->id."\n";
        }
    }

    private function misc(){

        Variation_model::where('product_storage_sort_id',null)->each(function($variation){
            $pss = Product_storage_sort_model::firstOrNew(['product_id'=>$variation->product_id,'storage'=>$variation->storage]);
            if($pss->id == null){
                $pss->save();
            }
            $variation->product_storage_sort_id = $pss->id;
            $variation->save();
        });
        $order_c = new Order();
        Order_model::where('scanned',null)->where('order_type_id',3)->where('tracking_number', '!=', null)->whereBetween('created_at', ['2024-05-01 00:00:00', now()->subDays(1)->format('Y-m-d H:i:s')])
        ->orderByDesc('id')->each(function($order) use ($order_c){
            $order_c->getLabel($order->reference_id, false, true);
        });
    }
}
