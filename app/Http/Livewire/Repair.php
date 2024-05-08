<?php

namespace App\Http\Livewire;
    use App\Http\Controllers\BackMarketAPIController;
    use Livewire\Component;
    use App\Models\Variation_model;
    use App\Models\Products_model;
    use App\Models\Stock_model;
    use App\Models\Order_model;
    use App\Models\Order_item_model;
    use App\Models\Order_status_model;
    use App\Models\Customer_model;
    use App\Models\Currency_model;
    use App\Models\Country_model;
    use App\Models\Storage_model;
    use Carbon\Carbon;
    use App\Exports\OrdersExport;
    use App\Exports\PickListExport;
    use App\Exports\LabelsExport;
    use App\Exports\DeliveryNotesExport;
    use App\Exports\OrdersheetExport;
    use Maatwebsite\Excel\Facades\Excel;
    use TCPDF;
    use App\Mail\InvoiceMail;
use App\Models\Color_model;
use App\Models\Grade_model;
use App\Models\Order_issue_model;
use App\Models\Process_model;
use App\Models\Stock_operations_model;
use Illuminate\Support\Facades\Mail;


class Repair extends Component
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

        // $data['latest_reference'] = Order_model::where('order_type_id',4)->orderBy('reference_id','DESC')->first()->reference_id;
        $data['repairers'] = Customer_model::where('is_vendor',3)->pluck('first_name','id');
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 20;
        }

        $data['repairs'] = Process_model::where('process_type_id', 9)
        ->when(request('start_date'), function ($q) {
            return $q->where('created_at', '>=', request('start_date'));
        })
        ->when(request('end_date'), function ($q) {
            return $q->where('created_at', '<=', request('end_date') . " 23:59:59");
        })
        ->when(request('reference_id'), function ($q) {
            return $q->where('reference_id', 'LIKE', request('reference_id') . '%');
        })
        ->orderBy('reference_id', 'desc') // Secondary order by reference_id
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));


        // dd($data['orders']);
        return view('livewire.repair')->with($data);
    }
    public function repair_approve($repair_id){
        $repair = Process_model::find($repair_id);
        $repair->tracking_number = request('tracking_number');
        $repair->status = 3;
        $repair->save();

        return redirect()->back();
    }
    public function delete_repair($order_id){

        $stock = Stock_model::where(['order_id'=>$order_id,'status'=>2])->first();
        if($stock != null){
            session()->put('error', "Order cannot be deleted");
            return redirect()->back();
        }

        // $items = Order_item_model::where('order_id',$order_id)->get();
        // foreach($items as $orderItem){
        //     if($orderItem->stock){
        //         // Access the variation through orderItem->stock->variation
        //         $variation = $orderItem->stock->variation;

        //         $variation->stock += 1;
        //         Stock_model::find($orderItem->stock_id)->update([
        //             'status' => 2
        //         ]);
        //     }
        //     $orderItem->delete();
        // }
        // Order_model::where('id',$order_id)->delete();
        // Order_issue_model::where('order_id',$order_id)->delete();
        session()->put('success', 'Order deleted successfully');
        return redirect()->back();

    }
    public function delete_repair_item($item_id){

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
    public function repair_detail($process_id){

        $data['imei'] = request('imei');

        $data['grades'] = Grade_model::all();
        $data['repair'] = Process_model::find($process_id);

        $data['repair_id'] = $process_id;

        $repair_stocks = Stock_model::
        whereHas('variation', function ($query) {
            $query->where('grade', 8);
        })->get();
        $data['repair_stocks'] = $repair_stocks;

        $repaired_stocks = Stock_operations_model::where('created_at','>=',now()->format('Y-m-d')." 00:00:00")->where('admin_id',session('user_id'))
            ->whereHas('stock', function ($query) {
                $query->where('status', 1);
            })->orderBy('id','desc')->get();

        $data['repaired_stocks'] = $repaired_stocks;

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
                session()->put('error', 'IMEI Invalid / Not Found in Repair');
                // return redirect()->back(); // Redirect here is not recommended
                return view('livewire.repair_detail', $data); // Return the Blade view instance with data
            }
            $data['stock_id'] = $stock->id;
            $data['products'] = Products_model::orderBy('model','asc')->get();
            $data['colors'] = Color_model::all();
            $data['storages'] = Storage_model::all();

            if (request('imei') == '' || !$stock || $stock->status == null) {
                session()->put('error', 'IMEI Invalid / Not Found in Repair');
                // return redirect()->back(); // Redirect here is not recommended
                return view('livewire.repair_detail', $data); // Return the Blade view instance with data
            }
            $sale_status = Order_item_model::where(['stock_id'=>$stock->id,'linked_id'=>$stock->purchase_item->id])->first();
            if($stock->status == 1){
                if($sale_status != null){
                    $stock->status = 2;
                    $stock->save();
                }else{
                }
            }
            if($stock->status == 2){
                if($sale_status == null){
                    $stock->status = 1;
                    $stock->save();
                }else{
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

        $variations = Variation_model::with([
            'stocks' => function ($query) use ($process_id) {
                $query->whereHas('order_item', function ($query) use ($process_id) {
                    $query->where('order_id', $process_id);
                });
            },
            'stocks.order_item'
        ])
        ->whereHas('stocks', function ($query) use ($process_id) {
            $query->whereHas('order_item', function ($query) use ($process_id) {
                $query->where('order_id', $process_id);
            });
        })
        ->orderBy('grade', 'desc')
        ->get();

        // Remove variations with no associated stocks
        $variations = $variations->filter(function ($variation) {
            return $variation->stocks->isNotEmpty();
        });

        $data['variations'] = $variations;

        $data['all_variations'] = Variation_model::where('grade',9)->get();
        $data['repair_id'] = $process_id;


        // echo "<pre>";
        // // print_r($items->stocks);
        // print_r($items);

        // echo "</pre>";
        // dd($data['variations']);
        return view('livewire.repair_detail')->with($data);

    }
    public function add_repair(){

        $order = Process_model::create([
            'reference_id' => 20001,
            'process_type_id' => 9,
            'status' => 1,
        ]);

        return redirect(url('repair/detail').'/'.$order->id);
    }
    public function add_repair_item($process_id){
        $repair = request('repair');
        $description = $repair['description'];
        if($repair['grade']){
            session()->put('grade',$repair['grade']);
        }
        session()->put('description',$repair['description']);


        if ($repair['stock_id']) {
            $stock = Stock_model::find($repair['stock_id']);
            if (!$stock || $stock->status != 1 || $stock->variation->grade != 8) {
                session()->put('error', 'IMEI Invalid / Not Available in this Grade');
                return redirect()->back();
            }
            $stock_id = $stock->id;

            $product_id = $stock->variation->product_id;
            $storage = $stock->variation->storage;
            $color = $stock->variation->color;
            $grade = $stock->variation->grade;

            if($repair['grade'] != ''){
                $grade = $repair['grade'];
            }
            $new_variation = Variation_model::firstOrNew([
                'product_id' => $product_id,
                'storage' => $storage,
                'color' => $color,
                'grade' => $grade,
            ]);
            $new_variation->status = 1;
            if($new_variation->id && $stock->variation_id == $new_variation->id && $repair['price'] == null){
                session()->put('error', 'Stock already exist in this variation');
                return redirect()->back();

            }
            $new_variation->save();
            $stock_operation = Stock_operations_model::create([
                'process_id' => $process_id,
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

        return redirect()->back();

    }

    public function internal_repair(){

        $data['imei'] = request('imei');

        $data['grades'] = Grade_model::all();


        $repair_stocks = Stock_model::
        whereHas('variation', function ($query) {
            $query->where('grade', 8);
        })->get();
        $data['repair_stocks'] = $repair_stocks;

        $repaired_stocks = Stock_operations_model::where('created_at','>=',now()->format('Y-m-d')." 00:00:00")->where('admin_id',session('user_id'))
            ->whereHas('stock', function ($query) {
                $query->where('status', 1);
            })->orderBy('id','desc')->get();

        $data['repaired_stocks'] = $repaired_stocks;

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
                session()->put('error', 'IMEI Invalid / Not Found in Repair');
                // return redirect()->back(); // Redirect here is not recommended
                return view('livewire.internal_repair', $data); // Return the Blade view instance with data
            }
            $data['stock_id'] = $stock->id;
            $data['products'] = Products_model::orderBy('model','asc')->get();
            $data['colors'] = Color_model::all();
            $data['storages'] = Storage_model::all();

            if (request('imei') == '' || !$stock || $stock->status == null) {
                session()->put('error', 'IMEI Invalid / Not Found in Repair');
                // return redirect()->back(); // Redirect here is not recommended
                return view('livewire.internal_repair', $data); // Return the Blade view instance with data
            }
            $sale_status = Order_item_model::where(['stock_id'=>$stock->id,'linked_id'=>$stock->purchase_item->id])->first();
            if($stock->status == 1){
                if($sale_status != null){
                    $stock->status = 2;
                    $stock->save();
                }else{
                }
            }
            if($stock->status == 2){
                if($sale_status == null){
                    $stock->status = 1;
                    $stock->save();
                }else{
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


        $data['all_variations'] = Variation_model::where('grade',9)->get();

        return view('livewire.internal_repair')->with($data);

    }
}
