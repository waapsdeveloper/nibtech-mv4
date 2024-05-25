<?php

namespace App\Http\Livewire;

use App\Models\Api_request_model;
use App\Models\Color_model;
use Livewire\Component;
use App\Models\Stock_model;
use App\Models\Grade_model;
use App\Models\Products_model;
use App\Models\Stock_operations_model;
use App\Models\Storage_model;
use App\Models\Variation_model;

class Testing extends Component
{

    public function mount()
    {
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect('index');
        }
    }
    public function render()
    {

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


            if($stock != null && $stock->variation->storage == $storage){
                $new_variation = [
                    'product_id' => $stock->variation->product_id,
                    'storage' => $stock->variation->storage,
                    'color' => $stock->variation->color,
                    'grade' => $stock->variation->grade
                ];

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
                        'description' => "Testing API Push",
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

        die;

        // $data['requests'] = $requests;




        // return view('livewire.testing', $data); // Return the Blade view instance with data
    }

    public function change_grade(){
        $description = request('description');
        if(request('grade')){
            session()->put('grade',request('grade'));
        }
        session()->put('description',request('description'));


        if (request('imei')) {
            if (ctype_digit(request('imei'))) {
                $i = request('imei');
                $s = null;
            } else {
                $i = null;
                $s = request('imei');
            }

            $stock = Stock_model::where(['imei' => $i, 'serial_number' => $s])->first();
            if (request('imei') == '' || !$stock) {
                session()->put('error', 'IMEI Invalid / Not Available');
                return redirect()->back();
            }
            if ($stock->order_id == null) {
                session()->put('error', 'Stock Not Purchased');
                return redirect()->back();
            }
            $stock_id = $stock->id;

            $product_id = $stock->variation->product_id;
            $storage = $stock->variation->storage;
            $color = $stock->variation->color;
            $grade = $stock->variation->grade;
            if(session('user')->hasPermission('change_variation')){
                if(request('product') != ''){
                    $product_id = request('product');
                }
                if(request('storage') != ''){
                    $storage = request('storage');
                }
                if(request('color') != ''){
                    $color = request('color');
                }
                if(request('price') != ''){
                    $price = request('price');
                    $p_order = $stock->purchase_item;

                    $description .= "Price changed from ".$p_order->price;
                    $p_order->price = $price;
                    $p_order->save();

                    // dd($p_order);
                }
            }

                if(request('grade') != ''){
                    $grade = request('grade');
                }
            $new_variation = Variation_model::firstOrNew([
                'product_id' => $product_id,
                'storage' => $storage,
                'color' => $color,
                'grade' => $grade,
            ]);
            $new_variation->status = 1;
            if($new_variation->id && $stock->variation_id == $new_variation->id && request('price') == null){
                session()->put('error', 'Stock already exist in this variation');
                return redirect()->back();

            }
            $new_variation->save();
            $stock_operation = Stock_operations_model::create([
                'stock_id' => $stock_id,
                'old_variation_id' => $stock->variation_id,
                'new_variation_id' => $new_variation->id,
                'description' => $description,
                'admin_id' => session('user_id'),
            ]);
            $stock->variation_id = $new_variation->id;
            $stock->save();

            // session()->put('added_imeis['.$grade.'][]', $stock_id);
            // dd($orders);
        }


        session()->put('success', 'Stock Sent Successfully');
        return redirect()->back();

    }
    public function delete_move(){
        $id = request('id');
        if(request('grade')){
            session()->put('grade',request('grade'));
        }
        session()->put('description',request('description'));


        if ($id != null) {
            $stock_operation = Stock_operations_model::find($id);
            $stock = $stock_operation->stock;
            $stock->variation_id = $stock_operation->old_variation_id;
            $stock->save();
            $stock_operation->delete();
        }


        session()->put('success', 'Stock Sent Back Successfully');
        return redirect()->back();

    }
    public function delete_multiple_moves(){
        $ids = request('ids');
        if(request('grade')){
            session()->put('grade',request('grade'));
        }
        session()->put('description',request('description'));


        if ($ids != null) {
            foreach($ids as $id){
                $stock_operation = Stock_operations_model::find($id);
                $stock = $stock_operation->stock;
                $stock->variation_id = $stock_operation->old_variation_id;
                $stock->save();
                $stock_operation->delete();
            }
        }


        session()->put('success', 'Stock Sent Back Successfully');
        return redirect()->back();

    }



}
