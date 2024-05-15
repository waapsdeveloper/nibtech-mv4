<?php

namespace App\Http\Livewire;

use App\Models\Api_request_model;
use App\Models\Color_model;
use Livewire\Component;
use App\Models\Stock_model;
use App\Models\Grade_model;
use App\Models\Order_model;
use App\Models\Products_model;
use App\Models\Stock_operations_model;
use App\Models\Storage_model;
use App\Models\Variation_model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

        $storages = Storage_model::pluck('name','id')->toArray();
        $colors = Color_model::pluck('name','id')->toArray();

        $requests = Api_request_model::where('status',null)->limit(20)->get();
        foreach($requests as $request){
            $data = $request->request;
            $datas = json_decode(json_decode(preg_split('/(?<=\}),(?=\{)/', $data)[0]));
            $stock = Stock_model::where('imei',$datas->Imei)->orWhere('imei',$datas->Imei2)->orWhere('serial_number',$datas->Serial)->first();

            if(in_array($datas->Memory, $storages)){
                $storage = array_search($datas->Memory,$storages);
            }

            // Convert each color name to lowercase
            $lowercaseColors = array_map('strtolower', $colors);

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
                // Retrieve the ID of the newly created color
                $color = $newColor->id;
            }
            if($stock != null && $stock->variation->storage == $storage){

                echo "<pre>";

                print_r($datas);
                print_r($stock);
                echo "</pre>";
            }
        }






        return view('livewire.testing', $data); // Return the Blade view instance with data
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
