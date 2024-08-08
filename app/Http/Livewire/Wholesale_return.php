<?php

namespace App\Http\Livewire;
    use Livewire\Component;
    use App\Models\Variation_model;
    use App\Models\Products_model;
    use App\Models\Stock_model;
    use App\Models\Order_model;
    use App\Models\Order_item_model;
    use App\Models\Order_status_model;
    use App\Models\Customer_model;
    use App\Models\Storage_model;
use App\Models\Color_model;
use App\Models\Grade_model;
use App\Models\Order_issue_model;
use App\Models\Stock_operations_model;
use Illuminate\Support\Facades\DB;

class Wholesale_return extends Component
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

        $data['title_page'] = "Bulksale Returns";

        // $data['latest_reference'] = Order_model::where('order_type_id',4)->orderBy('reference_id','DESC')->first()->reference_id;
        $data['order_statuses'] = Order_status_model::get();
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 20;
        }

        $data['orders'] = Order_model::select(
            'orders.id',
            'orders.reference_id',
            // DB::raw('SUM(order_items.price) as total_price'),
            DB::raw('COUNT(order_items.id) as total_quantity'),
            DB::raw('COUNT(CASE WHEN stock.status = 1 THEN order_items.id END) as available_stock'),
            'orders.created_at')
        ->where('orders.order_type_id', 6)
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->join('stock', 'order_items.stock_id', '=', 'stock.id')
        ->when(request('start_date'), function ($q) {
            return $q->where('orders.created_at', '>=', request('start_date'));
        })
        ->when(request('end_date'), function ($q) {
            return $q->where('orders.created_at', '<=', request('end_date') . " 23:59:59");
        })
        ->when(request('order_id'), function ($q) {
            return $q->where('orders.reference_id', 'LIKE', request('order_id') . '%');
        })
        ->when(request('status'), function ($q) {
            return $q->where('orders.status', request('status'));
        })
        ->where('order_items.deleted_at',null)
        ->groupBy('orders.id', 'orders.reference_id', 'orders.created_at')
        ->orderBy('orders.reference_id', 'desc') // Secondary order by reference_id
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));


        // dd($data['orders']);
        return view('livewire.wholesale_return')->with($data);
    }
    public function wholesale_return_ship($order_id){
        $order = Order_model::find($order_id);
        $order->tracking_number = request('tracking_number');
        $order->status = 2;
        $order->save();

        return redirect()->back();
    }
    public function wholesale_return_approve($order_id){
        $order = Order_model::find($order_id);
        $order->reference = request('reference');
        $order->status = 3;
        $order->save();

        return redirect()->back();
    }
    public function wholesale_return_revert_status($order_id){
        $order = Order_model::find($order_id);
        $order->status -= 1;
        $order->save();
        return redirect()->back();
    }
    public function delete_wholesale_return($order_id){

        $stock = Stock_model::where(['order_id'=>$order_id,'status'=>2])->first();
        if($stock != null){
            session()->put('error', "Order cannot be deleted");
            return redirect()->back();
        }

        $items = Order_item_model::where('order_id',$order_id)->get();
        foreach($items as $orderItem){
            if($orderItem->stock){
                // Access the variation through orderItem->stock->variation
                $variation = $orderItem->stock->variation;

                $variation->stock += 1;
                Stock_model::find($orderItem->stock_id)->update([
                    'status' => 2
                ]);
            }
            $orderItem->delete();
        }
        Order_model::where('id',$order_id)->delete();
        Order_issue_model::where('order_id',$order_id)->delete();
        session()->put('success', 'Order deleted successfully');
        return redirect()->back();

    }
    public function delete_wholesale_return_item($item_id){

        $orderItem = Order_item_model::find($item_id);

        if($orderItem->stock->status == 2){
            session()->put('error', "Order Item cannot be deleted");
            return redirect()->back();
        }
        // Access the variation through orderItem->stock->variation
        $variation = $orderItem->stock->variation;

        $variation->stock -= 1;
        $variation->save();

        // No variation record found or product_id and sku are both null, delete the order item

        // $orderItem->stock->delete();
        Stock_model::find($orderItem->stock_id)->update(['status'=>2]);
        $orderItem->delete();
        // $orderItem->forceDelete();

        session()->put('success', 'Stock deleted successfully');

        return redirect()->back();

    }
    public function wholesale_return_detail($order_id){

        $data['title_page'] = "Return Detail";

        $data['storages'] = Storage_model::pluck('name','id');
        $data['products'] = Products_model::pluck('model','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $data['colors'] = Color_model::pluck('name','id');

        $data['imei'] = request('imei');

        $data['all_variations'] = Variation_model::where('grade',9)->get();
        $data['order'] = Order_model::find($order_id);
        $data['order_id'] = $order_id;
        $data['currency'] = $data['order']->currency_id->sign;

        if (request('imei')) {
            if (ctype_digit(request('imei'))) {
                $i = request('imei');
                $s = null;
            } else {
                $i = null;
                $s = request('imei');
            }

            $stock = Stock_model::where(['imei' => $i, 'serial_number' => $s])->first();

            if (request('imei') == '' || !$stock || $stock->status == null) {
                session()->put('error', 'IMEI Invalid / Not Found');
                // return redirect()->back(); // Redirect here is not recommended
                return view('livewire.wholesale_return_detail', $data); // Return the Blade view instance with data
            }
            $check_old_sale = Order_item_model::where(['stock_id'=>$stock->id, 'linked_id'=>null])
            ->whereHas('order', function ($query) {
                $query->whereIn('order_type_id', [2,3,5]);
            })->first();
            if($check_old_sale != null){
                if($stock->purchase_item != null){
                    $check_old_sale->linked_id = $stock->last_item()->id;
                    $check_old_sale->save();
                }

            }
            if($stock->purchase_item){

                $last_item = $stock->last_item();
                if(in_array($last_item->order->order_type_id,[1,4,6])){

                    if($stock->status == 2){
                        $stock->status = 1;
                        $stock->save();
                    }
                        session()->put('success', 'IMEI Available');
                }else{
                    if($stock->status == 1){
                        $stock->status = 2;
                        $stock->save();
                    }
                        session()->put('success', 'IMEI Sold');
                }
            if($stock->status == 2){
                    $data['restock']['order_id'] = $order_id;
                    $data['restock']['reference_id'] = $last_item->order->reference_id;
                    $data['restock']['stock_id'] = $stock->id;
                    $data['restock']['price'] = $last_item->price;
                    $data['restock']['linked_id'] = $last_item->id;
            }
            }
            $stock_id = $stock->id;
            $orders = Order_item_model::where('stock_id', $stock_id)->orderBy('id','desc')->get();
            $data['stock'] = $stock;
            $data['orders'] = $orders;
            // dd($orders);

            $stocks = Stock_operations_model::where('stock_id', $stock_id)->orderBy('id','desc')->get();
            if($stocks->count() > 0){
                $data['stocks'] = $stocks;
            }

        }
        $graded_stocks = Grade_model::with([
            'variations.stocks' => function ($query) use ($order_id) {
                $query->whereHas('order_items', function ($query) use ($order_id) {
                    $query->where('order_id', $order_id)->where('status','!=',2);
                })->when(request('status') != '', function ($q) {
                    return $q->where('status', request('status'));
                });
            }
        ], 'variations.stocks.latest_operation')
        ->whereHas('variations.stocks.order_items', function ($query) use ($order_id) {
            $query->where('order_id', $order_id)->where('status','!=',2);
        })
        ->when(request('status') != '', function ($q) {
            return $q->whereHas('variations.stocks', function ($q) {
                $q->where('status', request('status'));

            });
        })
        ->orderBy('id', 'asc')
        ->get();
        // dd($graded_stock);
        $data['graded_stocks'] = $graded_stocks;

        $last_ten = Order_item_model::where('order_id',$order_id)->orderBy('id','desc')->limit(10)->get();
        $data['last_ten'] = $last_ten;



            $data['received_items'] = [];
        if($data['order']->status == 2){
            $received_items = Order_item_model::where('order_id', $order_id)->where('status',2)->orderByDesc('updated_at')->get();
            $data['received_items'] = $received_items;
        }
        // echo "<pre>";
        // // print_r($items->stocks);
        // print_r($items);

        // echo "</pre>";
        // dd($data['variations']);
        return view('livewire.wholesale_return_detail')->with($data);

    }
    public function add_wholesale_return(){
        $last_order = Order_model::where('order_type_id',6)->orderBy('id','desc')->first();
        if($last_order == null){
            $ref = 5001;
        }else{
            $ref = $last_order->reference_id+1;
            if($last_order->order_items->count() == 0){
                return redirect(url('wholesale_return/detail').'/'.$last_order->id);
            }
        }

        $order = Order_model::create([
            'reference_id' => $ref,
            'status' => 1,
            'currency' => 4,
            'order_type_id' => 6,
            'processed_by' => session('user_id')
        ]);

        return redirect(url('wholesale_return/detail').'/'.$order->id);
    }
    private function insert_wholesale_return_item($products, $storages, $order, $n, $c, $i, $s, $g = null, $dr = null){

        $names = explode(" ",$n);
        $last = end($names);
        if(in_array($last, $storages)){
            $gb = array_search($last,$storages);
            array_pop($names);
            $n = implode(" ", $names);
        }else{
            $gb = 0;
        }

        $stock = Stock_model::firstOrNew(['imei' => $i, 'serial_number' => $s]);
        if($stock->id != null){
            if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
            $issue['data']['row'] = $dr;
            $issue['data']['name'] = $n;
            $issue['data']['storage'] = $st;
            $issue['data']['imei'] = $i.$s;
            $issue['data']['cost'] = $c;
            $issue['data']['stock_id'] = $stock->id;
            if($stock->order_id == $order->id && $stock->status == 1){
                $issue['message'] = 'Duplicate IMEI';
            }else{
                if($stock->status != 2){
                    $issue['message'] = 'IMEI Available In Inventory';
                }else{
                    $issue['message'] = 'IMEI Rewholesale_return';
                }
            }

        }else{
            if(in_array(strtolower($n), array_map('strtolower',$products)) && ($i != null || $s != null)){
                $product = array_search(strtolower($n), array_map('strtolower',$products));
                $storage = $gb;

                // echo $product." ".$grade." ".$storage." | ";

                $variation = Variation_model::firstOrNew(['product_id' => $product, 'grade' => 9, 'storage' => $storage]);

                $variation->stock += 1;
                $variation->status = 1;
                $variation->save();

                $stock->product_id = $product;
                $stock->variation_id = $variation->id;
                $stock->added_by = session('user_id');
                $stock->order_id = $order->id;
                $stock->status = 1;
                $stock->save();

                $order_item = Order_item_model::firstOrNew(['order_id' => $order->id, 'variation_id' => $variation->id, 'stock_id' => $stock->id]);
                $order_item->quantity = 1;
                $order_item->price = $c;
                $order_item->status = 3;
                $order_item->save();


            }else{
                if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
                if($n != null){
                    $error = $n . " " . $st . " " . $i.$s . " || ";
                    $issue['data']['row'] = $dr;
                    $issue['data']['name'] = $n;
                    $issue['data']['storage'] = $st;
                    $issue['data']['imei'] = $i.$s;
                    $issue['data']['cost'] = $c;
                    $issue['data']['stock_id'] = '';
                    if($i == null && $s == null){
                        $issue['message'] = 'IMEI Not Found';
                    }else{
                        $issue['message'] = 'Product Name Not Accepted';
                    }

                }
            }
        }


        if($issue != []){
            Order_issue_model::create([
                'order_id' => $order->id,
                'data' => json_encode($issue['data']),
                'message' => $issue['message'],
            ]);
        }

    }
    public function add_wholesale_return_item($order_id){
        $wholesale_return = request('wholesale_return');
        // print_r($wholesale_return);
        if($order_id == $wholesale_return['order_id']){
            $variation = Variation_model::firstOrNew(['product_id' => $wholesale_return['product'], 'storage' => $wholesale_return['storage'], 'color' => $wholesale_return['color'], 'grade' => $wholesale_return['grade']]);

            $variation->stock += 1;
            $variation->status = 1;
            $variation->save();

            $stock = Stock_model::find($wholesale_return['stock_id']);

            if($stock->id){
                $item = Order_item_model::where(['order_id'=>$order_id, 'stock_id' => $stock->id])->first();
                // print_r($stock);
                if($item == null){

                    $order_item = new Order_item_model();
                    $order_item->order_id = $order_id;
                    $order_item->reference_id = $wholesale_return['reference_id'];
                    $order_item->variation_id = $variation->id;
                    $order_item->stock_id = $stock->id;
                    $order_item->quantity = 1;
                    $order_item->price = $wholesale_return['price'];
                    $order_item->status = 1;
                    $order_item->linked_id = $wholesale_return['linked_id'];
                    $order_item->admin_id = session('user_id');
                    $order_item->save();

                    print_r($order_item);

                    $stock_operation = Stock_operations_model::create([
                        'stock_id' => $stock->id,
                        'old_variation_id' => $stock->variation_id,
                        'new_variation_id' => $variation->id,
                        'description' => $wholesale_return['description'],
                        'admin_id' => session('user_id'),
                    ]);

                    $stock->variation_id = $variation->id;
                    $stock->status = 1;
                    $stock->save();

                    session()->put('success','Item added');
                }else{
                    session()->put('error','Item already added');
                }
            }else{
                session()->put('error','Stock Not Found');
            }

        }else{
            session()->put('error',"Don't ANGRY ME");
        }

        return redirect()->back();

    }

    public function receive_wholesale_return_item($order_id, $imei = null, $back = null){

        if(request('imei')){
            $imei = request('imei');
        }
        if(ctype_digit($imei)){
            $i = $imei;
            $s = null;
        }else{
            $i = null;
            $s = $imei;
        }
        $stock = Stock_model::where(['imei' => $i, 'serial_number' => $s])->first();

        if($imei == '' || !$stock || $stock->status == null){
            session()->put('error', 'IMEI Invalid / Not Found');
            if($back != 1){
                return redirect()->back();
            }else{
                return 1;
            }

        }
        $order_item = Order_item_model::where(['order_id'=>$order_id,'stock_id'=>$stock->id])->first();
        if(!$order_item){
            session()->put('error', "Stock not found in this sheet");
            if($back != 1){
                return redirect()->back();
            }else{
                return 1;
            }
        }
        if($order_item->status == 2){
            session()->put('error', "Stock already added");
            if($back != 1){
                return redirect()->back();
            }else{
                return 1;
            }
        }
        $order_item->status = 2;
        $order_item->save();


        if($back != 1){
            return redirect()->back();
        }else{
            return 1;
        }
    }


}
