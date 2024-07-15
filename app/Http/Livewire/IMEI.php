<?php

namespace App\Http\Livewire;
use Livewire\Component;
use App\Models\Admin_model;
use App\Models\Api_request_model;
use App\Models\Color_model;
use App\Models\Stock_model;
use App\Models\Order_item_model;
use App\Models\Grade_model;
use App\Models\Process_stock_model;
use App\Models\Products_model;
use App\Models\Stock_operations_model;
use App\Models\Storage_model;
use App\Models\Variation_model;
use Carbon\Carbon;


class IMEI extends Component
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

        $data['title_page'] = "Search Serial";
        $data['last_hour'] = Carbon::now()->subHour(1);
        $data['admins'] = Admin_model::where('id', '!=', 1)->get();
        $user_id = session('user_id');
        $data['imei'] = request('imei');
        if (request('imei')) {
            if (ctype_digit(request('imei'))) {
                $i = request('imei');
                $s = null;
            } else {
                $i = null;
                $s = request('imei');
            }

            $stock = Stock_model::where(['imei' => $i, 'serial_number' => $s])->first();

            $data['products'] = Products_model::orderBy('model','asc')->get();
            $data['colors'] = Color_model::all();
            $data['storages'] = Storage_model::all();
            $data['grades'] = Grade_model::all();
            if (request('imei') == '' || !$stock || $stock->status == null) {
                session()->put('error', 'IMEI Invalid / Not Found');
                // return redirect()->back(); // Redirect here is not recommended
                return view('livewire.imei', $data); // Return the Blade view instance with data
            }
            // $sale_status = Order_item_model::where(['stock_id'=>$stock->id,'linked_id'=>$stock->purchase_item->id])->first();
            // if($stock->status == 1){
            //     if($sale_status != null){
            //         $stock->status = 2;
            //         $stock->save();
            //         session()->put('success', 'IMEI Sold');
            //     }else{
            //         session()->put('success', 'IMEI Available');
            //     }
            // }
            // if($stock->status == 2){
            //     if($sale_status == null){
            //         $stock->status = 1;
            //         $stock->save();
            //         session()->put('success', 'IMEI Available');
            //     }else{
            //         session()->put('success', 'IMEI Sold');
            //     }
            // }
            $last_item = $stock->last_item();

            $items2 = Order_item_model::where(['stock_id'=>$stock->id,'linked_id'=>null])->whereHas('order', function ($query) {
                $query->where('order_type_id', 1)->where('reference_id','<=',10009);
            })->orderBy('id','asc')->get();
            if($items2->count() > 1){
                $i = 0;
                foreach($items2 as $item2){
                    $i ++;
                    if($i == 1){
                        $stock->order_id = $item2->order_id;
                        $stock->save();
                    }else{
                        $item2->delete();
                    }
                }
                $last_item = $stock->last_item();
            }
            if($stock->purchase_item){

                $items3 = Order_item_model::where(['stock_id'=>$stock->id, 'linked_id' => $stock->purchase_item->id])->whereHas('order', function ($query) {
                    $query->whereIn('order_type_id', [5,3]);
                })->orderBy('id','asc')->get();
                if($items3->count() > 1){
                    $i = 0;
                    foreach($items3 as $item3){
                        $i ++;
                        if($i == 1){
                        }else{
                            $item3->linked_id = null;
                            $item3->save();
                        }
                    }
                }

            }
            $items4 = Order_item_model::where(['stock_id'=>$stock->id])->whereHas('order', function ($query) {
                $query->whereIn('order_type_id', [5,3]);
            })->orderBy('id','asc')->get();
            if($items4->count() == 1){
                foreach($items4 as $item4){
                    if($stock->purchase_item){
                        if($item4->linked_id != $stock->purchase_item->id && $item4->linked_id != null){
                            $item4->linked_id = $stock->purchase_item->id;
                            $item4->save();
                        }
                    }
                }
            }
            $items5 = Order_item_model::where(['stock_id'=>$stock->id,'linked_id'=>null])->whereHas('order', function ($query) {
                $query->whereIn('order_type_id', [2,3,4,5]);
            })->orderBy('id','asc')->get();
            if($items5->count() == 1){
                foreach($items5 as $item5){
                    if($stock->last_item()){

                        $last_item = $stock->last_item();
                        $item5->linked_id = $last_item->id;
                        $item5->save();
                    }
                }
                    $last_item = $stock->last_item();
            }
                // if(session('user_id') == 1){
                //     dd($last_item);
                // }

                $stock_id = $stock->id;

            $process_stocks = Process_stock_model::where('stock_id', $stock_id)->whereHas('process', function ($query) {
                $query->where('process_type_id', 9);
            })->orderBy('id','desc')->get();
            $data['process_stocks'] = $process_stocks;

            if($last_item){

                if(in_array($last_item->order->order_type_id,[1,4])){
                    $message = 'IMEI is Available';
                    // if($stock->status == 2){
                        if($process_stocks->where('status',2)->count() == 0){
                            $stock->status = 1;
                            $stock->save();
                        }else{
                            $stock->status = 2;
                            $stock->save();

                            $message = "IMEI sent for repair";
                        }
                    // }else{

                    // }
                }else{
                    $message = "IMEI Sold";
                    if($stock->status == 1){
                        $stock->status = 2;
                        $stock->save();
                    }
                }
            }
                    session()->put('success', $message);
            // print_r($last_item);
            $orders = Order_item_model::where('stock_id', $stock_id)->orderBy('id','desc')->get();
            $data['stock'] = $stock;
            $data['orders'] = $orders;
            // dd($orders);


            $stocks = Stock_operations_model::where('stock_id', $stock_id)->orderBy('id','desc')->get();
            $data['stocks'] = $stocks;

            $test_results = Api_request_model::where('stock_id', $stock_id)->orderBy('id','desc')->get();
            $data['test_results'] = $test_results;
            //     dd($stocks);
            // }

            // if(request('delete') == "YES"){
            //     foreach($orders as $item){
            //         $item->delete();
            //         $item->forceDelete();
            //     }

            //     Stock_model::where(['imei' => $i, 'serial_number' => $s])->withTrashed()->forceDelete();
            // }
        }


        return view('livewire.imei', $data); // Return the Blade view instance with data
    }

    public function change_grade($stock_id){
        $stock = Stock_model::find($stock_id);


    }



    public function refund($stock_id){
        $stock = Stock_model::find($stock_id);
        if(!$stock){
            session()->put('error', 'Stock not found');
            return redirect()->back();
        }
        $item = $stock->last_item();

        $variation = $stock->variation;

        $variation->stock += 1;
        $variation->status = 1;
        $variation->save();



        $stock_operation = Stock_operations_model::create([
            'stock_id' => $stock->id,
            'old_variation_id' => $stock->variation_id,
            'new_variation_id' => $variation->id,
            'description' => request('description')." | Refund",
            'admin_id' => session('user_id'),
        ]);

        $order_item = new Order_item_model();
        $order_item->order_id = 8827;
        $order_item->reference_id = $item->order->reference_id;
        $order_item->variation_id = $item->variation_id;
        $order_item->stock_id = $stock->id;
        $order_item->quantity = 1;
        $order_item->price = $item->price;
        $order_item->status = 3;
        $order_item->linked_id = $item->id;
        $order_item->admin_id = session('user_id');
        $order_item->save();


        session()->put('success', 'Stock Refunded Successfully');
        return redirect()->back();
    }


}
