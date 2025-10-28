<?php

namespace App\Console\Commands;

use App\Http\Controllers\BackMarketAPIController;
use App\Http\Livewire\Order;
use App\Models\Account_transaction_model;
use App\Models\Api_request_model;
use App\Models\Color_model;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Product_storage_sort_model;
use App\Models\Stock_model;
use App\Models\Variation_model;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class Functions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'functions:ten';

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
        echo 1;
        $this->refund_currency();
        echo " 2";
        $this->check_linked_orders();
        echo " 3";
        $this->duplicate_orders();
        echo " 4";
        $this->misc();
        echo " 5";
        $this->merge_order_transactions();
        echo " 6";
        // $this->push_testing_api();
        return 0;
    }
    private function refund_currency(){

        $items = Order_item_model::whereHas('order', function ($q) {
            $q->where('order_type_id',4);
        })
        ->where('currency',null)
        ->get();
        foreach($items as $item){
            $sale_order = Order_model::where('reference_id', $item->reference_id)->first();
            if($sale_order->order_type_id == 3){
                $item->currency = $sale_order->currency;
                $item->save();
            }
        }

        // $item2 = Order_item_model::where('order_id', 8974)->where('currency',null)->each(function($item){
        //     $item->currency = Order_model::where('reference_id', $item->reference_id)->first()->currency;
        //     $item->save();
        // });
        // $itms = Order_item_model::whereNull('currency')->whereHas('order', function ($q) {
        //     $q->where('order_type_id',3);
        // })->each(function($item){
        //     $item->currency = $item->order->currency;
        //     $item->save();
        // });

    }
    private function check_linked_orders(){

        $items = Order_item_model::where(['linked_id'=>null])->whereNotNUll('stock_id')->whereHas('order', function ($q) {
            $q->whereIn('order_type_id',[3,5]);
        })->whereHas('stock', function ($q) {
            $q->whereNotNull('status');
        })->get();
        echo $items->count();
        foreach($items as $item){
            $it = Order_item_model::where(['stock_id'=>$item->stock_id])->whereHas('order', function ($q) {
                $q->whereIn('order_type_id',[1,4,6]);
            })->orderByDesc('id')->first();
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
        $subquery = Order_item_model::select('id')->where('reference_id','!=',null)->whereHas('order', function ($query) {
            $query->where('order_type_id', 3);
        })
        ->selectRaw('ROW_NUMBER() OVER (PARTITION BY reference_id ORDER BY id) AS row_num');

        // Final query to delete duplicate orders
        Order_item_model::whereIn('id', function ($query) use ($subquery) {
            $query->select('id')->fromSub($subquery, 'subquery')->where('row_num', '>', 1);
        })->delete();

    }

    public function merge_order_transactions()
    {
        $latestRef = optional(
            Account_transaction_model::whereNotNull('reference_id')
                ->whereRaw('reference_id REGEXP "^[0-9]+$"')
                ->orderByDesc('reference_id')
                ->first()
        )->reference_id ?? 0;

        Order_model::where('order_type_id', 3)
            ->with([
                'transactions' => fn ($q) => $q->whereNull('status')->orderBy('id'),
                'order_charges.charge'
            ])
            ->whereHas('transactions', fn ($q) => $q->whereNull('status'))
            ->orderByDesc('id')
            ->limit(60)
            ->each(function ($order) use (&$latestRef) {
                $chargesByName = $order->order_charges->keyBy(
                    fn ($orderCharge) => trim(optional($orderCharge->charge)->name)
                );

                $changed = false;

                foreach ($order->transactions as $transaction) {
                    $description = trim($transaction->description);

                    if ($description === 'sales') {
                        $transaction->reference_id = ++$latestRef;
                        $transaction->status = 1;
                        $transaction->save();
                        continue;
                    }

                    if (!$chargesByName->has($description)) {
                        continue;
                    }

                    $orderCharge = $chargesByName->get($description);
                    $amount = abs($transaction->amount);

                    $orderCharge->transaction_id = $transaction->id;
                    $orderCharge->amount = $amount;
                    $orderCharge->save();

                    $transaction->reference_id = ++$latestRef;
                    $transaction->status = 1;
                    $transaction->save();

                    $changed = true;
                }

                if ($changed) {
                    $order->charges = $order->order_charges->sum('amount');
                    $order->save();
                }
            });
    }

    public function push_testing_api(){
        $testing = new Api_request_model();
        $testing->push_testing();
    }
    private function misc(){

        $variations = Variation_model::whereNull('product_storage_sort_id')->get();
        echo " Misc";
        if($variations->count() > 0){
            echo $variations->count();
            foreach($variations as $variation){
                if($variation->product_id == null){
                    continue;
                }
                $storage = $variation->storage ?? 0;
                $pss = Product_storage_sort_model::firstOrNew(['product_id'=>$variation->product_id,'storage'=>$storage]);
                if($pss->id == null){
                    $product = $variation->product;
                    $cat = $product->category ?? 0;
                    $br = str_pad($product->brand ?? 0, 2, '0', STR_PAD_LEFT);
                    $pro = str_pad($product->id ?? 0, 5, '0', STR_PAD_LEFT);
                    $stor = str_pad($storage ?? 0, 2, '0', STR_PAD_LEFT);
                    $pss->sort = $cat . $br . $pro . $stor;
                    $pss->save();
                }
                $variation->product_storage_sort_id = $pss->id;
                $variation->save();
            };
        }
        echo " Misc2 ";
        $order_c = new Order();
        Order_model::whereNull('scanned')->where('order_type_id',3)->whereNotNull('tracking_number')->whereBetween('created_at', ['2025-05-01 00:00:00', now()->subDays(1)->format('Y-m-d H:i:s')])
        ->orderByDesc('id')->limit(50)->each(function($order) use ($order_c){
            echo 1;
            $order_c->getLabel($order->reference_id, false, true);
        });
    }

}
