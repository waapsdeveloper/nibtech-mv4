<?php

namespace App\Console\Commands;

use App\Http\Controllers\BackMarketAPIController;
use App\Models\Api_request_model;
use App\Models\Color_model;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Customer_model;
use App\Models\Currency_model;
use App\Models\Country_model;
use App\Models\Grade_model;
use App\Models\Listing_model;
use App\Models\Process_model;
use App\Models\Products_model;
use App\Models\Variation_model;
use App\Models\Stock_model;
use App\Models\Stock_operations_model;
use App\Models\Storage_model;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Illuminate\Console\Command;
use GuzzleHttp\Client;

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

        // $this->remove_extra_variations();
        $this->check_linked_orders();
        $this->duplicate_orders();
        $this->push_testing_api();
    }
    private function remove_extra_variations(){
        // $variations = Variation_model::limit(100)->pluck('id');
        // if(file_exists('variations.txt')){
        //     $last_id = file_get_contents('variations.txt');
        //     $variations = Variation_model::where('id','>',$last_id)->limit(100)->pluck('id');
        //     if($variations->count() == 0){
        //         $variations = Variation_model::limit(100)->pluck('id');
        //     }
        // }
        // foreach($variations as $id){
        //     $variation = Variation_model::find($id);
        //     if($variation != null){

        //         $duplicates = Variation_model::where(['product_id'=>$variation->product_id,'reference_id'=>$variation->reference_id,'storage'=>$variation->storage,'color'=>$variation->color,'grade'=>$variation->grade])
        //         ->whereNot('id',$variation->id)->get();
        //         if($duplicates->count() > 0){
        //             foreach($duplicates as $duplicate){
        //                 Listing_model::where('variation_id',$duplicate->id)->update(['variation_id'=>$variation->id]);
        //                 Order_item_model::where('variation_id',$duplicate->id)->update(['variation_id'=>$variation->id]);
        //                 Process_model::where('old_variation_id',$duplicate->id)->update(['old_variation_id'=>$variation->id]);
        //                 Process_model::where('new_variation_id',$duplicate->id)->update(['new_variation_id'=>$variation->id]);
        //                 Stock_model::where('variation_id',$duplicate->id)->update(['variation_id'=>$variation->id]);
        //                 Stock_operations_model::where('old_variation_id',$duplicate->id)->update(['old_variation_id'=>$variation->id]);
        //                 Stock_operations_model::where('new_variation_id',$duplicate->id)->update(['new_variation_id'=>$variation->id]);

        //                 $duplicate->delete();
        //             }
        //         }
        //         file_put_contents('variations.txt', $id);
        //     }
        // }

        // $variations_2 = Variation_model::where('sku','!=',null)->where('product_id','!=',null)->withTrashed()->get()->pluck('id');
        // echo $variations_2->count();
        // die;
        // if(file_exists('variations_2.txt')){
        //     $last_id = file_get_contents('variations_2.txt');
        //     $variations_2 = Variation_model::where('id','>',$last_id)->where('sku','!=',null)->where('product_id','!=',null)->withTrashed()->get()->pluck('id');
        //     if($variations_2->count() == 0){
        //         $variations_2 = Variation_model::where('sku','!=',null)->where('product_id','!=',null)->withTrashed()->get()->pluck('id');
        //     }
        // }
        // foreach($var

        $variations = Variation_model::whereNotNull('sku')
            ->whereNotNull('product_id')
            ->withTrashed()
            ->pluck('id');

        $processedVariations = $variations->map(function ($id) {
            $variation = Variation_model::withTrashed()->find($id);

            if ($variation) {
                // Find duplicates
                $duplicates = Variation_model::where('sku', $variation->sku)
                    ->where('grade', $variation->grade)
                    ->where('id', '!=', $variation->id)
                    ->withTrashed()
                    ->get();

                if ($duplicates->isNotEmpty()) {
                    DB::transaction(function () use ($variation, $duplicates) {
                        foreach ($duplicates as $duplicate) {
                            // Update related records to point to the original variation
                            Listing_model::where('variation_id', $duplicate->id)->update(['variation_id' => $variation->id]);
                            Order_item_model::where('variation_id', $duplicate->id)->update(['variation_id' => $variation->id]);
                            Process_model::where('old_variation_id', $duplicate->id)->update(['old_variation_id' => $variation->id]);
                            Process_model::where('new_variation_id', $duplicate->id)->update(['new_variation_id' => $variation->id]);
                            Stock_model::where('variation_id', $duplicate->id)->update(['variation_id' => $variation->id]);
                            Stock_operations_model::where('old_variation_id', $duplicate->id)->update(['old_variation_id' => $variation->id]);
                            Stock_operations_model::where('new_variation_id', $duplicate->id)->update(['new_variation_id' => $variation->id]);

                            // Soft delete the duplicate
                            $duplicate->delete();
                        }

                        // Restore the original variation if it was soft deleted
                        if ($variation->trashed()) {
                            $variation->restore();
                        }
                    });

                    Log::info('Processed variation ID: ' . $variation->id);
                }

                // Save the last processed ID to a file (or a more suitable persistent storage)
                file_put_contents('variations_2.txt', $id);
            }
        });

        echo 'Processing completed';

    }
    private function check_linked_orders(){

        $items = Order_item_model::where(['linked_id'=>null])->whereHas('order', function ($q) {
            $q->whereIn('order_type_id',[3,5]);
        })->get();
        foreach($items as $item){
            $it = Order_item_model::where(['stock_id'=>$item->stock_id])->whereHas('order', function ($q) {
                $q->whereIn('order_type_id',[1,4]);
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

    public function push_testing_api(){
        $testing = new Api_request_model();
        $testing->push_testing();
    }

}
