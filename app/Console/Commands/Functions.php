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
use App\Models\Products_model;
use App\Models\Variation_model;
use App\Models\Stock_model;
use App\Models\Stock_operations_model;
use App\Models\Storage_model;
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
        $this->check_linked_orders();
        $this->duplicate_orders();
        $this->push_testing_api();
    }
    private function check_linked_orders(){

        $items = Order_item_model::where(['linked_id'=>null])->whereHas('order', function ($q) {
            $q->whereIn('order_type_id',[3,5]);
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

        $products = Products_model::pluck('model','id')->toArray();
        $storages = Storage_model::pluck('name','id')->toArray();
        $colors = Color_model::pluck('name','id')->toArray();
            // Convert each color name to lowercase
        $lowercaseColors = array_map('strtolower', $colors);
        $grades = Grade_model::pluck('name','id')->toArray();
            // Convert each grade name to lowercase
        $lowercaseGrades = array_map('strtolower', $grades);

        $requests = Api_request_model::where('status',null)->orderBy('id','asc')->get();
        foreach($requests as $request){
            $data = $request->request;
            $datas = json_decode(json_decode(preg_split('/(?<=\}),(?=\{)/', $data)[0]));
            if($datas == null || ($datas->Imei == '' && $datas->Serial == '')){
                continue;
            }
            $stock = Stock_model::where('imei',$datas->Imei)->orWhere('imei',$datas->Imei2)->orWhere('serial_number',$datas->Serial)->first();

            if(in_array($datas->ModelName, $products)){
                $product = array_search($datas->ModelName,$products);
            }

            if(in_array($datas->Memory, $storages)){
                $storage = array_search($datas->Memory,$storages);
            }

            echo "<pre>";

            // print_r($request);
            print_r($datas);
            echo "</pre>";

            $colorName = strtolower($datas->Color); // Convert color name to lowercase

            if (in_array($colorName, $lowercaseColors)) {
                // If the color exists in the predefined colors array,
                // retrieve its index
                $color = array_search($colorName, $lowercaseColors);
            } else {
                // If the color doesn't exist in the predefined colors array,
                // create a new color record in the database
                $newColor = Color_model::create([
                    'name' => $colorName
                ]);
                $colors = Color_model::pluck('name','id')->toArray();
                $lowercaseColors = array_map('strtolower', $colors);
                // Retrieve the ID of the newly created color
                $color = $newColor->id;
            }


            $gradeName = strtolower($datas->Grade); // Convert grade name to lowercase

            if (in_array($gradeName, $lowercaseGrades)) {
                // If the grade exists in the predefined grades array,
                // retrieve its index
                $grade = array_search($gradeName, $lowercaseGrades);
            }else{
                if($gradeName == '' || $gradeName == 'ug'){
                    $grade = 7;
                }elseif($gradeName == 'a'){
                    $grade = 2;
                }elseif($gradeName == 'ok'){
                    $grade = 5;
                }else{

                    echo $gradeName;
                    continue;
                }
            }


            if($stock != null && ($stock->variation->storage == $storage || $stock->variation->storage == 0)){
                $new_variation = [
                    'product_id' => $stock->variation->product_id,
                    'storage' => $stock->variation->storage,
                    'color' => $stock->variation->color,
                    'grade' => $stock->variation->grade
                ];

                if($stock->variation->storage == null || $stock->variation->storage == 0 || $stock->variation->storage == $storage){
                    $new_variation['storage'] = $storage;
                }
                if($stock->variation->color == null || $stock->variation->color == $color){
                    $new_variation['color'] = $color;
                }

                if(($stock->variation->grade == 9 || $stock->variation->grade == $grade) && $grade != ''){
                    $new_variation['grade'] = $grade;
                }
                if($stock->status == 1){
                    $new_variation['grade'] = $grade;
                }
                $variation = Variation_model::firstOrNew($new_variation);
                if($stock->status == 1){


                    $stock_operation = Stock_operations_model::create([
                        'stock_id' => $stock->id,
                        'old_variation_id' => $stock->variation_id,
                        'new_variation_id' => $variation->id,
                        'description' => $datas->Comments." | Testing API Push",
                        'admin_id' => NULL,
                    ]);
                    $variation->status = 1;
                    $variation->save();
                    $stock->variation_id = $variation->id;
                    $stock->save();
                    $request->stock_id = $stock->id;
                    $request->status = 1;
                    $request->save();

                }elseif($stock->status == 2){

                    $request->stock_id = $stock->id;
                    $request->status = 1;
                    $request->save();
                }
                echo "<pre>";

                print_r($stock);
                echo "</pre>";
            }
        }

    }
}
