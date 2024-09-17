<?php

namespace App\Http\Livewire;
use Livewire\Component;
use App\Models\Admin_model;
use App\Models\Api_request_model;
use App\Models\Color_model;
use App\Models\Stock_model;
use App\Models\Order_item_model;
use App\Models\Grade_model;
use App\Models\Order_model;
use App\Models\Process_stock_model;
use App\Models\Products_model;
use App\Models\Stock_movement_model;
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
            $stock_movement = Stock_movement_model::where(['stock_id'=>$stock->id, 'received_at'=>null])->first();
            if($stock_movement != null && $stock->status == 2){
                Stock_movement_model::where(['stock_id'=>$stock->id, 'received_at'=>null])->update([
                    'received_at' => Carbon::now(),
                ]);
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
                $query->whereIn('order_type_id', [2,3,4,5,6]);
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

                if(in_array($last_item->order->order_type_id,[1,4,6])){
                    $message = 'IMEI is Available';
                    // if($stock->status == 2){
                        if($process_stocks->where('status',1)->count() == 0){
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
                    session()->put('success', $message);
            }
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

    public function change_po($stock_id){
        $stock = Stock_model::find($stock_id);
        if(!$stock){
            session()->put('error', 'Stock not found');
            return redirect()->back();
        }
        if(request('order_id') == null){
            session()->put('error', 'Please select a PO');
            return redirect()->back();
        }
        if(session('user')->hasPermission('change_po_all') || (session('user')->hasPermission('change_po_old') && $stock->created_at->diffInDays() < 7 && $stock->added_by == session('user_id') && in_array($stock->order_id,[4739, 1, 5, 8, 9, 12, 13, 14, 185, 263, 8441]))){

            $order_id = request('order_id');
            $order = Order_model::find($order_id);
            $purchase_item = Order_item_model::where('stock_id', $stock->id)->where('order_id', $stock->order_id)->first();

            Stock_operations_model::create([
                'stock_id' => $stock->id,
                'old_variation_id' => $stock->variation_id,
                'new_variation_id' => $stock->variation_id,
                'description' => request('description')." | PO Changed from ".$stock->order->reference_id." to ".$order->reference_id,
                'admin_id' => session('user_id'),
            ]);
            $purchase_item->order_id = $order_id;
            $stock->order_id = $order_id;
            $stock->save();
            $purchase_item->save();

            session()->put('success', 'PO Changed Successfully');
            return redirect()->back();
        }else{
            session()->put('error', 'You are not authorized to perform this action');
            return redirect()->back();
        }

    }

    // public function rearrange($stock_id){
    //     $stock = Stock_model::find($stock_id);
    //     if(!$stock){
    //         session()->put('error', 'Stock not found');
    //         return redirect()->back();
    //     }
    //     $order = [];
    //     $new_order = [];
    //     $i = 1;
    //     $order_items = Order_item_model::where('stock_id', $stock_id)->orderBy('id','asc')->get();
    //     foreach($order_items as $item){
    //         $item->linked_id = null;
    //         $item->save();
    //         if($item->order->order_type_id == 1){
    //             $order[0] = $item->id;
    //         }else{
    //             $order[$i] = $item->id;
    //             $i++;
    //         }
    //     }
    //     $even = [];
    //     $odd = [];
    //     foreach($order as $key => $item_id){
    //         if($key == 0){
    //             continue;
    //         }
    //         if($key % 2 == 0){
    //             $even[] = $key;
    //         }else{
    //             $odd[] = $key;
    //         }
    //     }

    //     foreach($order as $key => $item_id){
    //         if($key == 0){
    //             continue;
    //         }
    //         $item = Order_item_model::find($item_id);
    //         if(in_array($item->order->order_type_id, [2,3,5])){

    //             $new_order[] = $item_id;
    //         }else{

    //         }

    //     }
    //     foreach($new_order as $key => $item_id){
    //         if($key == 0){
    //             continue;
    //         }
    //         $item = Order_item_model::find($item_id);
    //         $item->linked_id = $new_order[$key-1];
    //         $item->save();
    //     }
    //     session()->put('success', 'Rearranged Successfully');
    //     return redirect()->back();
    // }
    public function rearrange($stock_id)
    {
        $stock = Stock_model::find($stock_id);
        if (!$stock) {
            session()->put('error', 'Stock not found');
            return redirect()->back();
        }

        $new_order = []; // To store the ordered items
        $linked_id = null; // This will hold the last linked item's ID
        $reserve = []; // This will hold the reserve items

        // Fetch all order items for the specific stock, ordered by 'id' ascending
        $order_items = Order_item_model::where('stock_id', $stock_id)->orderBy('id', 'asc')->get();

        // First, find the initial Purchase (`order_type_id = 1`)
        foreach ($order_items as $item) {
            $item->linked_id = null; // Reset linked_id
            $item->save();

            // If this is a purchase order (order_type_id = 1), it starts the sequence
            if ($item->order->order_type_id == 1) {
                if($linked_id == null){
                    // $new_order[] = $item->id;
                    $linked_id = $item->id;
                }
            }
        }

        // Now, process the rest of the order items based on the rules
        foreach ($order_items as $item) {
            $last_item = Order_item_model::find($linked_id);
            if (in_array($item->order->order_type_id, [3, 5, 2]) && $linked_id && in_array($last_item->order->order_type_id, [1, 4, 6])) {
                // $new_order[] = $item;
                $item->linked_id = $linked_id;
                $item->save();
                $linked_id = $item->id;
            } elseif (in_array($item->order->order_type_id, [4, 6]) && $linked_id && in_array($last_item->order->order_type_id, [3, 5])) {
                // $new_order[] = $item;
                $item->linked_id = $linked_id;
                $item->reference_id = $last_item->order->reference_id;
                $item->price = $last_item->price;
                $item->save();
                $linked_id = $item->id;
            } elseif ($item->order->order_type_id == 1 && $linked_id && $last_item->order->order_type_id == 2) {
                // $new_order[] = $item;
                $item->linked_id = $linked_id;
                $item->save();
                $linked_id = $item->id;
            } else {
                $reserve[] = $item->id;
            }

            if (isset($reserve[0])) {
                $item2 = Order_item_model::find($reserve[0]);
                if (in_array($item2->order->order_type_id, [3, 5, 2]) && $linked_id && in_array($last_item->order->order_type_id, [1, 4, 6])) {
                    // $new_order[] = $item2;
                    $item2->linked_id = $linked_id;
                    $item2->save();
                    $linked_id = $item2->id;
                    array_shift($reserve);
                } elseif (in_array($item2->order->order_type_id, [4, 6]) && $linked_id && in_array($last_item->order->order_type_id, [3, 5])) {
                    // $new_order[] = $item2;
                    $item2->linked_id = $linked_id;
                    $item2->reference_id = $last_item->order->reference_id;
                    $item2->price = $last_item->price;
                    $item2->save();
                    $linked_id = $item2->id;
                    array_shift($reserve);
                }

            }

            // // After Sales (order_type_id = 3), only Sales Return (order_type_id = 4) can occur
            // if ($item->order->order_type_id == 4 && $linked_id && $new_order[count($new_order)-1]->order->order_type_id == 3) {
            //     $new_order[] = $item;
            //     $item->linked_id = $linked_id;
            //     $item->save();
            //     $linked_id = $item->id;
            // }

            // // After Wholesale (order_type_id = 5), only Wholesale Return (order_type_id = 6) can occur
            // if ($item->order->order_type_id == 6 && $linked_id && $new_order[count($new_order)-1]->order->order_type_id == 5) {
            //     $new_order[] = $item;
            //     $item->linked_id = $linked_id;
            //     $item->save();
            //     $linked_id = $item->id;
            // }

            // // After Purchase Return (order_type_id = 2), only Purchase (order_type_id = 1) can occur
            // if ($item->order->order_type_id == 1 && $linked_id && $new_order[count($new_order)-1]->order->order_type_id == 2) {
            //     $new_order[] = $item;
            //     $item->linked_id = $linked_id;
            //     $item->save();
            //     $linked_id = $item->id;
            // }
        }

        session()->put('success', 'Rearranged Successfully');
        return redirect()->back();
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
        $order_item->currency = $item->order->currency;
        $order_item->status = 3;
        $order_item->linked_id = $item->id;
        $order_item->admin_id = session('user_id');
        $order_item->save();


        session()->put('success', 'Stock Refunded Successfully');
        return redirect()->back();
    }


}
