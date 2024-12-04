<?php

namespace App\Http\Livewire;

use App\Models\Admin_model;
use App\Models\Color_model;
use Livewire\Component;
use App\Models\Stock_model;
use App\Models\Grade_model;
use App\Models\Order_model;
use App\Models\Products_model;
use App\Models\Stock_operations_model;
use App\Models\Storage_model;
use App\Models\Variation_model;
use App\Models\Vendor_grade_model;
use Carbon\Carbon;


class MoveInventory extends Component
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

        $data['title_page'] = "Move Inventory";

        $data['admins'] = Admin_model::where('id','!=',1)->get();
        $data['products'] = Products_model::orderBy('model','asc')->get();
        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::all();
        $data['vendor_grades'] = Vendor_grade_model::all();

        $start_date = Carbon::now()->startOfDay();
        $end_date = date('Y-m-d 23:59:59');
        if (request('start_date') != NULL && request('end_date') != NULL) {
            $start_date = request('start_date') . " 00:00:00";
            $end_date = request('end_date') . " 23:59:59";
        }

        if(request('grade')){
            session()->put('grade',request('grade'));
            session()->put('success',request('grade'));

        }
        $grade = session('grade');
        if(request('description')){
            session()->put('description',request('description'));
        }
        $per_page = 50;
        if(request('per_page')){
            $per_page = request('per_page');
        }


        $stocks = Stock_operations_model::when(request('search') != '', function ($q) {
                return $q->where('description', 'LIKE', '%' . request('search') . '%');
            })
            ->when(request('imei') != '', function ($q) {
                return $q->where('stock_id', Stock_model::where('imei', request('imei'))->orWhere('serial_number', request('imei'))->first()->id);
            })
            ->when(request('imei') == '' && request('moved') == '' && (request('search') == '' || request('start_date') != NULL || request('end_date') != NULL), function ($q) use ($start_date,$end_date) {
                return $q->where('created_at','>=',$start_date)->where('created_at','<=',$end_date);
            })
            ->when(request('moved') == 1, function ($q) {
                return $q->join('variation as old_variation', 'stock_operations.old_variation_id', '=', 'old_variation.id')
                         ->join('variation as new_variation', 'stock_operations.new_variation_id', '=', 'new_variation.id')
                        //  ->join('stock', 'stock_operations.stock_id', '=', 'stock.id')
                        //  ->join('variation as stock_variation', 'stock.variation_id', '=', 'stock_variation.id')
                        //  ->whereColumn('old_variation.product_id', '!=', 'stock_variation.product_id')
                        // ->orWhereColumn('old_variation.storage', '!=', 'stock_variation.storage')
                         ->whereColumn('old_variation.product_id', '!=', 'new_variation.product_id')
                         ->orWhereColumn('old_variation.storage', '!=', 'new_variation.storage')
                         ->Where('old_variation.storage', '!=', 0)
                         ->select('stock_operations.*');
            })
            // ->when(request('moved') == 1, function ($q) {
            //     return $q->whereHas('old_variation', function ($subQuery) {
            //         $subQuery->whereHas('stock_operation', function ($innerQuery) {
            //             $innerQuery->whereColumn('old_variation.product_id', '!=', 'new_variation.product_id')
            //                        ->orWhereColumn('old_variation.storage', '!=', 'new_variation.storage');
            //         });
            //     });
            // })

            ->when(request('adm') != '', function ($q) {
                return $q->where('admin_id', request('adm'));
            })
            ->orderBy('id','desc');

        if(request('search') != ''){
            $stocks = $stocks->whereHas('stock', function ($q) {
                // $q->where('status', 1);
            })->get();
        }else{
            $stocks = $stocks->where('description','!=','Grade changed for Sell')
            ->paginate($per_page)
            ->onEachSide(5)
            ->appends(request()->except('page'));
        }


        $data['stocks'] = $stocks;
        $data['grade'] = Grade_model::find($grade);

        return view('livewire.move_inventory', $data); // Return the Blade view instance with data
    }

    public function change_grade($allow_same = false){

        if(request('grade')){
            session()->put('grade',request('grade'));
        }
        session()->put('description',request('description'));


        if (request('imei')) {
            $imeis = explode(' ',request('imei'));
            $imei_count = count($imeis);
            if(request('price')){
                $prices = explode(' ',request('price'));
            }

            foreach($imeis as $key => $imei){
                $description = request('description');
                if (ctype_digit($imei)) {
                    $i = $imei;
                    $s = null;
                } else {
                    $i = null;
                    $s = $imei;
                }

                $stock = Stock_model::where(['imei' => $i, 'serial_number' => $s])->first();
                if ($imei == '' || !$stock || $stock == null) {
                    session()->put('error', 'IMEI Invalid / Not Available');
                    // return redirect()->back();
                    continue;
                }
                if ($stock->order_id == null) {
                    session()->put('error', 'Stock Not Purchased');
                    // return redirect()->back();
                    continue;
                }
                $stock_id = $stock->id;

                $product_id = $stock->variation->product_id;
                $storage = $stock->variation->storage;
                $color = $stock->variation->color;
                $grade = $stock->variation->grade;
                $sub_grade = $stock->variation->sub_grade;
                if(request('if_grade') != ''){
                    if($grade != request('if_grade')){
                        session()->put('error', $imei.' Stock Grade Not Matched');
                        // return redirect()->back();
                        continue;
                    }
                }
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
                        if($imei_count == count($prices)){
                            $price = $prices[$key];
                            // dd($price);

                        }else{
                            $price = request('price');
                        }
                        $p_order = $stock->purchase_item;

                        $description .= " Price changed from ".$p_order->price;
                        $p_order->price = $price;
                        $p_order->save();

                        // dd($p_order);
                    }
                    if(request('vendor_grade') != ''){
                        $vendor_grade = request('vendor_grade');
                        $p_order = $stock->purchase_item;

                        $description .= " Vendor Grade changed from ".$p_order->reference_id;
                        $p_order->reference_id = $vendor_grade;
                        $p_order->save();

                    }
                }

                if(request('battery') != null){
                    $description .= " || B: ".request('battery');
                }

                if(request('locked') != null){
                    $description .= " || L: ".request('locked');
                }
                if(request('grade') != ''){
                    $grade = request('grade');
                }
                if(request('sub_grade') != ''){
                    $sub_grade = request('sub_grade');
                }
                $new_variation = Variation_model::firstOrNew([
                    'product_id' => $product_id,
                    'storage' => $storage,
                    'color' => $color,
                    'grade' => $grade,
                    'sub_grade' => $sub_grade,
                ]);
                $new_variation->status = 1;

                if($new_variation->id && $stock->variation_id == $new_variation->id && request('price') == null && $allow_same == false){
                    session()->put('error', 'Stock already exist in this variation');
                    // return redirect()->back();
                    continue;

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
            }

            // session()->put('added_imeis['.$grade.'][]', $stock_id);
            // dd($orders);
        }


        session()->put('success', 'Stock Sent Successfully');
        return redirect()->back();

    }
    public function edit_description($id = null){
        if(session('user')->hasPermission('edit_stock_operation_description')){
            if($id == null){
                $id = request('id');
            }
            $ids = explode(' ',$id);
            foreach($ids as $id){
                $stock_operation = Stock_operations_model::find($id);
                $stock_operation->description = request('description');
                $stock_operation->save();
            }
            session()->put('success', 'Description Updated Successfully');
        }else{
            session()->put('error', 'Permission Denied');
        }
        return redirect()->back();
    }
    public function delete_move($id = null){
        if($id == null){
            $id = request('id');
        }
        if(request('grade')){
            session()->put('grade',request('grade'));
        }
        if(request('description')){
        session()->put('description',request('description'));
        }

        if ($id != null) {
            $stock_operation = Stock_operations_model::find($id);
            if($stock_operation == null){
                session()->put('error', 'Stock Operation Not Found');
                return redirect()->back();
            }
            $stock = $stock_operation->stock;
            if($id == $stock->latest_operation->id){
                $stock->variation_id = $stock_operation->old_variation_id;
                $stock->save();
            }
            $stock_operation->delete();
        }


        session()->put('success', 'Stock Sent Back Successfully');
        return redirect()->back();

    }
    public function delete_multiple_moves(){
        if(session('user')->hasPermission('delete_multiple_moves')){
            $ids = request('ids');
            foreach($ids as $id){
                $this->delete_move($id);
            }
            session()->put('success', 'Stock Sent Back Successfully');
        }else{
            session()->put('error', 'Permission Denied');
        }

        // $operations = Stock_operations_model::where('description','LIKE','%Storage changed%')->pluck('stock_id')->unique();

        // $stocks = Stock_model::whereIn('id',$operations)->get();

        // foreach($stocks as $stock){
        //     $new_variation_ids = $stock->stock_operations->pluck('new_variation_id')->unique()->toArray();
        //     $old_variation_ids = $stock->stock_operations->pluck('old_variation_id')->unique()->toArray();

        //     $array = $new_variation_ids + $old_variation_ids;
        //     $array = array_unique($array);

        //     print_r($array);
        //     echo "<br>";
        //     print_r($new_variation_ids);
        //     echo "<br>";
        //     print_r($old_variation_ids);
        //     echo "<br>";
        //     echo "<br>";


        // }
        // $ids = request('ids');
        // if(request('grade')){
        //     session()->put('grade',request('grade'));
        // }
        // session()->put('description',request('description'));


        // if ($ids != null) {
        //     foreach($ids as $id){
        //         $stock_operation = Stock_operations_model::find($id);
        //         $stock = $stock_operation->stock;
        //         if($id == $stock->latest_operation->id){
        //             $stock->variation_id = $stock_operation->old_variation_id;
        //             $stock->save();
        //         }
        //         $stock_operation->delete();
        //     }
        // }

        return redirect()->back();

    }

    public function check_storage_change(){
        $operations = Stock_operations_model::where('description','LIKE','%Storage changed%')->pluck('stock_id')->unique();

        $stocks = Stock_model::whereIn('id',$operations)->get();

        foreach($stocks as $stock){
            $new_variation_ids = $stock->stock_operations->pluck('new_variation_id')->unique()->toArray();
            $old_variation_ids = $stock->stock_operations->pluck('old_variation_id')->unique()->toArray();

            $array = array_merge($new_variation_ids,$old_variation_ids);
            // $array = array_unique($array);

            $variations = Variation_model::whereIn('id',$array)->pluck('storage')->unique()->toArray();

            if(Variation_model::whereIn('id',$array)->whereNotIn('storage',[1,2,3,4,5,6,7,8,9,10])->count() > 0){
                // dd("Invalid Storage");
                continue;

            }
            if(count($variations) == 1){
                Stock_operations_model::where('stock_id',$stock->id)->where('description','LIKE','%Storage changed%')->delete();
            }
            print_r($variations);
            echo "<br>";

            print_r($array);
            echo "<br>";
            print_r($new_variation_ids);
            echo "<br>";
            print_r($old_variation_ids);
            echo "<br>";
            echo "<br>";


        }




    }

}
