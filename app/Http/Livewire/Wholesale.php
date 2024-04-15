<?php

namespace App\Http\Livewire;
    use App\Http\Controllers\BackMarketAPIController;
    use Livewire\Component;
    use App\Models\Admin_model;
    use App\Models\Variation_model;
    use App\Models\Products_model;
    use App\Models\Stock_model;
    use App\Models\Order_model;
    use App\Models\Order_item_model;
    use App\Models\Order_status_model;
    use App\Models\Customer_model;
    use App\Models\Currency_model;
    use App\Models\Country_model;
    use App\Models\Color_model;
    use App\Models\Storage_model;
    use GuzzleHttp\Psr7\Request;
    use Carbon\Carbon;
    use Illuminate\Support\Facades\DB;


class Wholesale extends Component
{

    public $imei;
    public $price;

    public function mount()
    {

    }
    public function render()
    {

        $user_id = session('user_id');
        $data['vendors'] = Customer_model::where('is_vendor','!=',null)->pluck('first_name','id');
        $data['currencies'] = Currency_model::pluck('sign','id');
        $data['order_statuses'] = Order_status_model::get();
            if(request('per_page') != null){
                $per_page = request('per_page');
            }else{
                $per_page = 10;
            }
            switch (request('sort')){
                case 2: $sort = "orders.reference_id"; $by = "ASC"; break;
                case 3: $sort = "variation.name"; $by = "DESC"; break;
                case 4: $sort = "variation.name"; $by = "ASC"; break;
                default: $sort = "orders.reference_id"; $by = "DESC";
            }
            $data['orders'] = Order_model::select(
                'orders.id',
                'orders.reference_id',
                'orders.customer_id',
                'orders.currency',
                // DB::raw('SUM(order_items.price) as total_price'),
                // DB::raw('COUNT(order_items.id) as total_quantity'),
                // DB::raw('(SELECT COUNT(order_items.id) FROM order_items INNER JOIN stock ON order_items.stock_id = stock.id WHERE order_items.order_id = orders.id AND stock.status = 1) as available_stock'),
                'orders.created_at')
            ->where('orders.order_type_id',5)
            // ->join('order_items', 'orders.id', '=', 'order_items.order_id')

            ->when(request('start_date') != '', function ($q) {
                return $q->where('orders.created_at', '>=', request('start_date', 0));
            })
            ->when(request('end_date') != '', function ($q) {
                return $q->where('orders.created_at', '<=', request('end_date', 0) . " 23:59:59");
            })
            ->when(request('order_id') != '', function ($q) {
                return $q->where('orders.reference_id', 'LIKE', request('order_id') . '%');
            })
            ->groupBy('orders.id', 'orders.reference_id', 'orders.customer_id', 'orders.currency', 'orders.created_at')
            ->orderBy('orders.reference_id', 'desc') // Secondary order by reference_id
            // ->select('orders.*')
            ->paginate($per_page)
            ->onEachSide(5)
            ->appends(request()->except('page'));



        // dd($data['orders']);
        return view('livewire.wholesale')->with($data);
    }
    public function delete_order($order_id){

        $items = Order_item_model::where('order_id',$order_id)->get();
        foreach($items as $orderItem){
            if($orderItem->stock){
                // Access the variation through orderItem->stock->variation
                $variation = $orderItem->stock->variation;

                // If a variation record exists and either product_id or sku is not null
                if ($variation->stock == 1 && $variation->product_id == null && $variation->sku == null) {
                    // Decrement the stock by 1

                    // Save the variation record
                    $variation->delete();
                } else {
                    $variation->stock -= 1;
                    // No variation record found or product_id and sku are both null, delete the order item
                }
                Stock_model::find($orderItem->stock_id)->delete();
            }
            $orderItem->delete();
        }
        Order_model::where('id',$order_id)->delete();
        return redirect()->back();
    }
    public function delete_order_item($item_id){

        $orderItem = Order_item_model::find($item_id);

        // Access the variation through orderItem->stock->variation
        $variation = $orderItem->stock->variation;

        $variation->stock += 1;
        $variation->save();

        // No variation record found or product_id and sku are both null, delete the order item

        // $orderItem->stock->delete();
        Stock_model::find($orderItem->stock_id)->update(['status'=>1]);
        $orderItem->delete();

        return redirect()->back();
    }
    public function wholesale_detail($order_id){

        $data['imeis'] = Stock_model::whereIn('status',[1,3])->get();
        $data['storages'] = Storage_model::pluck('name','id');
        $data['variations'] = Variation_model::whereHas('stocks.order_item', function($query) use ($order_id) {
            $query->where('order_id', $order_id);
        })
        ->with('stocks','stocks.order_item','stocks.variation')
        ->orderBy('grade','desc')
        ->get();

        $data['all_variations'] = Variation_model::where('grade',9)->get();
        $data['order'] = Order_model::find($order_id);
        $data['order_id'] = $order_id;
        $data['currency'] = $data['order']->currency_id->sign;


        // echo "<pre>";
        // // print_r($items->stocks);
        // print_r($items);

        // echo "</pre>";
        // dd($items);
        return view('livewire.wholesale_detail')->with($data);

    }


    public function add_wholesale(){
        // dd(request('wholesale'));
        $wholesale = (object) request('wholesale');
        $error = "";


        $customer = Customer_model::firstOrNew(['first_name' => $wholesale->vendor, ['is_vendor','!=',null] ]);
        if($customer->id == null){
            $customer->is_vendor = 2;
        }
        $customer->save();

        $order = Order_model::firstOrNew(['reference_id' => $wholesale->reference_id, 'order_type_id' => $wholesale->type ]);
        $order->customer_id = $customer->id;
        $order->status = $wholesale->status;
        $order->currency = 4;
        $order->order_type_id = $wholesale->type;
        $order->processed_by = session('user_id');
        $order->created_at = now()->format('Y-m-d H:i:s');
        $order->save();

        // Delete the temporary file
        // Storage::delete($filePath);
        if($error != ""){

            session()->put('error', $error);
        }
        return redirect()->back();
    }
    public function add_wholesale_item($order_id){

        if(ctype_digit(request('imei'))){
            $i = request('imei');
            $s = null;
        }else{
            $i = null;
            $s = request('imei');
        }

        $stock = Stock_model::where(['imei' => $i, 'serial_number' => $s])->first();
        $stock->status = 2;
        $stock->save();

        $variation = Variation_model::firstOrNew(['id' => $stock->variation_id]);
        $variation->stock -= 1;
        $variation->save();


        $order_item = new Order_item_model();
        $order_item->order_id = $order_id;
        $order_item->variation_id = $variation->id;
        $order_item->stock_id = $stock->id;
        $order_item->quantity = 1;
        $order_item->price = request('price');
        $order_item->status = 3;
        $order_item->save();



        // Delete the temporary file
        // Storage::delete($filePath);

        return redirect()->back();
    }


}
