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
    use App\Models\Storage_model;
    use Carbon\Carbon;
    use App\Exports\OrdersExport;
    use App\Exports\PickListExport;
    use App\Exports\LabelsExport;
    use App\Exports\DeliveryNotesExport;
    use App\Exports\OrdersheetExport;
use App\Http\Controllers\GoogleController;
use Illuminate\Support\Facades\DB;
    use Maatwebsite\Excel\Facades\Excel;
    use TCPDF;
    use App\Mail\InvoiceMail;
use App\Models\Color_model;
use App\Models\Grade_model;
use App\Models\Order_issue_model;
use App\Models\Stock_operations_model;
use Illuminate\Support\Facades\Mail;
use TCPDF_FONTS;

class Order extends Component
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
        $data['title_page'] = "Sales";
        $data['storages'] = Storage_model::pluck('name','id');
        $data['colors'] = Color_model::pluck('name','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $data['last_hour'] = Carbon::now()->subHour(2);
        $data['admins'] = Admin_model::where('id','!=',1)->get();
        $user_id = session('user_id');
        $data['user_id'] = $user_id;
        $data['pending_orders_count'] = Order_model::where('order_type_id',3)->where('status',2)->count();
        $data['order_statuses'] = Order_status_model::get();
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }
        // if(request('care')){
        //     foreach(Order_model::where('status',2)->pluck('reference_id') as $pend){
        //         $this->recheck($pend);
        //     }
        // }

        switch (request('sort')){
            case 2: $sort = "orders.reference_id"; $by = "ASC"; break;
            case 3: $sort = "products.model"; $by = "DESC"; break;
            case 4: $sort = "products.model"; $by = "ASC"; break;
            default: $sort = "orders.reference_id"; $by = "DESC";
        }

        $orders = Order_model::with(['customer','customer.orders','order_items','order_items.variation','order_items.variation.product', 'order_items.variation.grade_id', 'order_items.stock'])
        ->where('orders.order_type_id',3)
        ->when(request('start_date') != '', function ($q) {
            if(request('adm') > 0){
                return $q->where('orders.processed_at', '>=', request('start_date', 0));
            }else{
                return $q->where('orders.created_at', '>=', request('start_date', 0));

            }
        })
        ->when(request('end_date') != '', function ($q) {
            if(request('adm') > 0){
                return $q->where('orders.processed_at', '<=', request('end_date', 0) . " 23:59:59")->orderBy('orders.processed_at','desc');
            }else{
                return $q->where('orders.created_at', '<=', request('end_date', 0) . " 23:59:59");
            }
        })
        ->when(request('status') != '', function ($q) {
            return $q->where('orders.status', request('status'));
        })
        ->when(request('adm') != '', function ($q) {
            if(request('adm') == 0){
                return $q->where('orders.processed_by', null);
            }
            return $q->where('orders.processed_by', request('adm'));
        })
        ->when(request('care') != '', function ($q) {
            return $q->whereHas('order_items', function ($query) {
                $query->where('care_id', '!=', null);
            });
        })
        ->when(request('order_id') != '', function ($q) {
            if(str_contains(request('order_id'),'<')){
                $order_ref = str_replace('<','',request('order_id'));
                return $q->where('orders.reference_id', '<', $order_ref);
            }elseif(str_contains(request('order_id'),'>')){
                $order_ref = str_replace('>','',request('order_id'));
                return $q->where('orders.reference_id', '>', $order_ref);
            }elseif(str_contains(request('order_id'),'<=')){
                $order_ref = str_replace('<=','',request('order_id'));
                return $q->where('orders.reference_id', '<=', $order_ref);
            }elseif(str_contains(request('order_id'),'>=')){
                $order_ref = str_replace('>=','',request('order_id'));
                return $q->where('orders.reference_id', '>=', $order_ref);
            }elseif(str_contains(request('order_id'),'-')){
                $order_ref = explode('-',request('order_id'));
                return $q->whereBetween('orders.reference_id', $order_ref);
            }elseif(str_contains(request('order_id'),',')){
                $order_ref = explode(',',request('order_id'));
                return $q->whereIn('orders.reference_id', $order_ref);
            }elseif(str_contains(request('order_id'),' ')){
                $order_ref = explode(' ',request('order_id'));
                return $q->whereIn('orders.reference_id', $order_ref);
            }else{
                return $q->where('orders.reference_id', 'LIKE', request('order_id') . '%');
            }
        })
        ->when(request('sku') != '', function ($q) {
            return $q->whereHas('order_items.variation', function ($q) {
                $q->where('sku', 'LIKE', '%' . request('sku') . '%');
            });
        })
        ->when(request('imei') != '', function ($q) {
            return $q->whereHas('order_items.stock', function ($q) {
                $q->where('imei', 'LIKE', '%' . request('imei') . '%');
            });
        })
        ->when(request('tracking_number') != '', function ($q) {
            if(strlen(request('tracking_number')) == 21){
                $tracking = substr(request('tracking_number'),1);
            }else{
                $tracking = request('tracking_number');
            }
            return $q->where('tracking_number', 'LIKE', '%' . $tracking . '%');
        })
        // ->orderBy($sort, $by) // Order by variation name
        // ->when(request('sort') == 4, function ($q) {
        //     return $q->whereHas('order_items.variation.product', function ($q) {
        //         $q->orderBy('model', 'ASC');
        //     })->whereHas('order_items.variation', function ($q) {
        //         $q->orderBy('variation.storage', 'ASC');
        //     })->whereHas('order_items.variation', function ($q) {
        //         $q->orderBy('variation.color', 'ASC');
        //     })->whereHas('order_items.variation', function ($q) {
        //         $q->orderBy('variation.grade', 'ASC');
        //     });

        ->when(request('sort') == 4, function ($q) {
            return $q->join('order_items', 'order_items.order_id', '=', 'orders.id')
                ->join('variation', 'order_items.variation_id', '=', 'variation.id')
                ->join('products', 'variation.product_id', '=', 'products.id')
                ->orderBy('products.model', 'ASC')
                ->orderBy('variation.storage', 'ASC')
                ->orderBy('variation.color', 'ASC')
                ->orderBy('variation.grade', 'ASC')
                ->select('orders.id','orders.reference_id','orders.customer_id','orders.delivery_note_url','orders.label_url','orders.tracking_number','orders.status','orders.processed_by','orders.created_at','orders.processed_at');
        })
        // })
        ->orderBy('orders.reference_id', 'desc'); // Secondary order by reference_id


        if(request('bulk_invoice') && request('bulk_invoice') == 1){

            ini_set('max_execution_time', 300);
            $data['orders2'] = $orders
            ->get();
            foreach($data['orders2'] as $order){
                $data2 = [
                    'order' => $order,
                    'customer' => $order->customer,
                    'orderItems' => $order->order_items,
                ];

                Mail::mailer('no-reply')->to($order->customer->email)->send(new InvoiceMail($data2));
                // $recipientEmail = $order->customer->email;
                // $subject = 'Invoice for Your Recent Purchase';

                // app(GoogleController::class)->sendEmailInvoice($recipientEmail, $subject, new InvoiceMail($data2));
                sleep(2);

            }
            // return redirect()->back();

        }
        $data['orders'] = $orders
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));

        if(count($data['orders']) == 0 && request('order_id')){
            $ors = explode(' ',request('order_id'));
            foreach($ors as $or){
                $this->recheck($or);
            }
        }
        // dd($data['orders']);
        return view('livewire.order')->with($data);
    }
    public function sales_allowed()
    {
        $data['title_page'] = "Sales (Admin)";

        $data['grades'] = Grade_model::all();
        $data['last_hour'] = Carbon::now()->subHour(72);
        $data['admins'] = Admin_model::where('id','!=',1)->get();
        $user_id = session('user_id');
        $data['user_id'] = $user_id;
        $data['pending_orders_count'] = Order_model::where('order_type_id',3)->where('status',2)->count();
        $data['order_statuses'] = Order_status_model::get();
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }
        // if(request('care')){
        //     foreach(Order_model::where('status',2)->pluck('reference_id') as $pend){
        //         $this->recheck($pend);
        //     }
        // }

        switch (request('sort')){
            case 2: $sort = "orders.reference_id"; $by = "ASC"; break;
            case 3: $sort = "products.model"; $by = "DESC"; break;
            case 4: $sort = "products.model"; $by = "ASC"; break;
            default: $sort = "orders.reference_id"; $by = "DESC";
        }

        $orders = Order_model::with(['order_items','order_items.variation', 'order_items.variation.grade_id', 'order_items.stock'])
        ->where('order_type_id',3)
        ->when(request('start_date') != '', function ($q) {
            if(request('adm') > 0){
                return $q->where('processed_at', '>=', request('start_date', 0));
            }else{
                return $q->where('created_at', '>=', request('start_date', 0));

            }
        })
        ->when(request('end_date') != '', function ($q) {
            if(request('adm') > 0){
                return $q->where('processed_at', '<=', request('end_date', 0) . " 23:59:59")->orderBy('processed_at','desc');
            }else{
                return $q->where('created_at', '<=', request('end_date', 0) . " 23:59:59");
            }
        })
        ->when(request('status') != '', function ($q) {
            return $q->where('status', request('status'));
        })
        ->when(request('adm') != '', function ($q) {
            if(request('adm') == 0){
                return $q->where('processed_by', null);
            }
            return $q->where('processed_by', request('adm'));
        })
        ->when(request('care') != '', function ($q) {
            return $q->whereHas('order_items', function ($query) {
                $query->where('care_id', '!=', null);
            });
        })
        ->when(request('order_id') != '', function ($q) {
            return $q->where('reference_id', 'LIKE', request('order_id') . '%');
        })
        ->when(request('sku') != '', function ($q) {
            return $q->whereHas('order_items.variation', function ($q) {
                $q->where('sku', 'LIKE', '%' . request('sku') . '%');
            });
        })
        ->when(request('imei') != '', function ($q) {
            return $q->whereHas('order_items.stock', function ($q) {
                $q->where('imei', 'LIKE', '%' . request('imei') . '%');
            });
        })
        ->when(request('tracking_number') != '', function ($q) {
            if(strlen(request('tracking_number')) == 21){
                $tracking = substr(request('tracking_number'),1);
            }else{
                $tracking = request('tracking_number');
            }
            return $q->where('tracking_number', 'LIKE', '%' . $tracking . '%');
        })
        ->orderBy($sort, $by) // Order by variation name
        ->when(request('sort') == 4, function ($q) {
            return $q->whereHas('order_items.variation.product', function ($q) {
                $q->orderBy('model', 'ASC');
            })->whereHas('order_items.variation', function ($q) {
                $q->orderBy('variation.storage', 'ASC');
            })->whereHas('order_items.variation', function ($q) {
                $q->orderBy('variation.color', 'ASC');
            })->whereHas('order_items.variation', function ($q) {
                $q->orderBy('variation.grade', 'ASC');
            });

        })
        ->orderBy('reference_id', 'desc'); // Secondary order by reference_id
        if(request('bulk_invoice') && request('bulk_invoice') == 1){

            $data['orders2'] = $orders
            ->get();
            foreach($data['orders2'] as $order){

                $data2 = [
                    'order' => $order,
                    'customer' => $order->customer,
                    'orderItems' => $order->order_items,
                ];
                Mail::to($order->customer->email)->send(new InvoiceMail($data2));

            }
            // return redirect()->back();

        }
        $data['orders'] = $orders
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));

        if(count($data['orders']) == 0 && request('order_id')){
            $this->recheck(request('order_id'));
        }
        // dd($data['orders']);
        return view('livewire.sales_allowed')->with($data);
    }
    public function purchase()
    {

        $data['title_page'] = "Purchases";
        $data['latest_reference'] = Order_model::where('order_type_id',1)->orderBy('reference_id','DESC')->first()->reference_id;
        $data['vendors'] = Customer_model::where('is_vendor',1)->pluck('first_name','id');
        $data['order_statuses'] = Order_status_model::get();
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 50;
        }

        $data['orders'] = Order_model::select(
            'orders.id',
            'orders.reference_id',
            'orders.customer_id',
            DB::raw('SUM(order_items.price) as total_price'),
            DB::raw('COUNT(order_items.id) as total_quantity'),
            DB::raw('COUNT(CASE WHEN stock.status = 1 THEN order_items.id END) as available_stock'),
            'orders.status',
            'orders.created_at')
        ->where('orders.order_type_id', 1)
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
        ->when(request('stock'), function ($q) {
            if (request('stock') == 0) {
                return $q->havingRaw('COUNT(CASE WHEN stock.status = 1 THEN order_items.id END) = 0');
            } else {
                return $q->havingRaw('COUNT(CASE WHEN stock.status = 1 THEN order_items.id END) > 0');
            }
        })

        ->groupBy('orders.id', 'orders.reference_id', 'orders.customer_id', 'orders.status', 'orders.created_at')
        ->orderBy('orders.reference_id', 'desc') // Secondary order by reference_id
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));


        // dd($data['orders']);
        return view('livewire.purchase')->with($data);
    }
    public function purchase_approve($order_id){
        $order = Order_model::find($order_id);
        $order->reference = request('reference');
        $order->tracking_number = request('tracking_number');
        if(request('approve') == 1){
            $order->status = 3;
        }
        $order->save();

        if(request('approve') == 1){
            return redirect()->back();
        }else{
            return "Updated";
        }
    }
    public function delete_order($order_id){

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

                // If a variation record exists and either product_id or sku is not null
                if ($variation->stock == 1 && $variation->product_id == null && $variation->sku == null) {
                    // Decrement the stock by 1

                    // Save the variation record
                    $variation->delete();
                } else {
                    $variation->stock -= 1;
                    // No variation record found or product_id and sku are both null, delete the order item
                }
                $stock = Stock_model::find($orderItem->stock_id);
                if($stock->status == 1){
                    $stock->delete();
                }else{
                    $stock->order_id = null;
                    $stock->status = null;
                    $stock->save();
                }
            }
            $orderItem->delete();
        }
        Order_model::where('id',$order_id)->delete();
        Order_issue_model::where('order_id',$order_id)->delete();
        return redirect(url('purchase'));
    }
    public function delete_order_item($item_id){

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
        $stock = Stock_model::find($orderItem->stock_id);
        if($stock->status == 1){
            $stock->delete();
        }else{
            $stock->order_id = null;
            $stock->status = null;
            $stock->save();
        }

        $orderItem->delete();

        return redirect()->back();
    }
    public function purchase_detail($order_id){


        DB::statement("SET SESSION group_concat_max_len = 1000000;");
        $data['title_page'] = "Purchase Detail";
        $data['storages'] = Storage_model::pluck('name','id');
        $data['colors'] = Color_model::pluck('name','id');

        if (!request('status') || request('status') == 1){
            $data['variations'] = Variation_model::with(['stocks' => function ($query) use ($order_id) {
                $query->where(['order_id'=> $order_id, 'status'=>1]);
            }, 'stocks.order_item' => function ($query) use ($order_id) {
                $query->where('order_id', $order_id);
            }])
            ->whereHas('stocks', function ($query) use ($order_id) {
                $query->where(['order_id'=> $order_id, 'status'=>1]);
            })
            ->orderBy('grade', 'desc')
            ->get();
        }

        if (!request('status') || request('status') == 2){

            $data['sold_stocks'] = Stock_model::where(['order_id'=> $order_id, 'status'=>2])
            ->orderBy('variation_id', 'asc')
            ->get();
        }

        $data['missing_stock'] = Order_item_model::where('order_id',$order_id)->whereHas('stock',function ($q) {
            $q->where(['imei'=>null,'serial_number'=>null]);
        })->get();
        // $order_issues = Order_issue_model::where('order_id',$order_id)->orderBy('message','ASC')->get();
        $order_issues = Order_issue_model::where('order_id',$order_id)->select(
            DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.name")) AS name'),
            'message',
            DB::raw('COUNT(*) as count'),
            DB::raw('GROUP_CONCAT(JSON_OBJECT("id", id, "order_id", order_id, "data", data, "message", message, "created_at", created_at, "updated_at", updated_at)) AS all_rows')
        )
        ->groupBy('name', 'message')
        ->get();
        // dd($order_issues);

        $data['order_issues'] = $order_issues;
        // dd($data['missing_stock']);
        $data['all_variations'] = Variation_model::where('grade',9)->get();
        $data['order'] = Order_model::find($order_id);
        $data['order_id'] = $order_id;
        $data['currency'] = $data['order']->currency_id->sign;


        // echo "<pre>";
        // // print_r($items->stocks);
        // print_r($items);

        // echo "</pre>";
        // dd($data['variations']);
        return view('livewire.purchase_detail')->with($data);

    }
    public function add_purchase(){

        // dd(request('purchase'));
        $purchase = (object) request('purchase');
        $error = "";
        $issue = [];
        // Validate the uploaded file
        request()->validate([
            'purchase.sheet' => 'required|file|mimes:xlsx,xls',
        ]);

        // Store the uploaded file in a temporary location
        $filePath = request()->file('purchase.sheet')->store('temp');

        // // Perform operations on the Excel file
        // $spreadsheet = IOFactory::load(storage_path('app/'.$filePath));
        // // Perform your operations here...

        // Replace 'your-excel-file.xlsx' with the actual path to your Excel file
        $excelFilePath = storage_path('app/'.$filePath);

        $data = Excel::toArray([], $excelFilePath)[0];
        $dh = $data[0];
        // print_r($dh);
        unset($data[0]);
        $arrayLower = array_map('strtolower', $dh);
        // Search for the lowercase version of the search value in the lowercase array
        $name = array_search('name', $arrayLower);
        if(!$name){
            print_r($dh);
            session()->put('error', "Heading not Found(name, imei, cost)");
            return redirect()->back();
        }
        // echo $name;
        $imei = array_search('imei', $arrayLower);
        // echo $imei;
        $cost = array_search('cost', $arrayLower);
        $color = array_search('color', $arrayLower);
        $v_grade = array_search('grade', $arrayLower);
        // echo $cost;
        $grade = 9;


        $order = Order_model::firstOrNew(['reference_id' => $purchase->reference_id, 'order_type_id' => $purchase->type ]);
        $order->customer_id = $purchase->vendor;
        $order->status = 2;
        $order->currency = 4;
        $order->order_type_id = $purchase->type;
        $order->processed_by = session('user_id');
        $order->save();

        $storages = Storage_model::pluck('name','id')->toArray();
        $colors = Color_model::pluck('name','id')->toArray();
        $grades = ['mix','a','a-','b+','b','c','asis'];

        $products = Products_model::pluck('model','id')->toArray();

        // $variations = Variation_model::where('grade',$grade)->get();

        foreach($data as $dr => $d){
            // $name = ;
            // echo $dr." ";
            // print_r($d);
            $n = trim($d[$name]);
            $c = $d[$cost];
            if(ctype_digit($d[$imei])){
                $i = $d[$imei];
                $s = null;
            }else{
                $i = null;
                $s = $d[$imei];
            }
            $names = explode(" ",$n);
            $last = end($names);
            if(in_array($last, $storages)){
                $gb = array_search($last,$storages);
                array_pop($names);
                $n = implode(" ", $names);
            }else{
                $gb = null;
            }

            if(trim($d[$imei]) == ''){
                if(trim($n) != '' || trim($c) != ''){
                    if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
                    $issue[$dr]['data']['row'] = $dr;
                    $issue[$dr]['data']['name'] = $n;
                    $issue[$dr]['data']['storage'] = $st;
                    if($color){
                        $issue[$dr]['data']['color'] = $d[$color];
                    }
                    if($v_grade){
                        $issue[$dr]['data']['v_grade'] = $d[$v_grade];
                    }
                    $issue[$dr]['data']['imei'] = $i.$s;
                    $issue[$dr]['data']['cost'] = $c;
                    $issue[$dr]['message'] = 'IMEI not Provided';
                }
                continue;
            }
            if(trim($n) == ''){
                if(trim($n) != '' || trim($c) != ''){
                    if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
                    $issue[$dr]['data']['row'] = $dr;
                    $issue[$dr]['data']['name'] = $n;
                    $issue[$dr]['data']['storage'] = $st;
                    if($color){
                        $issue[$dr]['data']['color'] = $d[$color];
                    }
                    if($v_grade){
                        $issue[$dr]['data']['v_grade'] = $d[$v_grade];
                    }
                    $issue[$dr]['data']['imei'] = $i.$s;
                    $issue[$dr]['data']['cost'] = $c;
                    $issue[$dr]['message'] = 'Name not Provided';
                }
                continue;
            }
            if(trim($c) == ''){
                if(trim($n) != '' || trim($c) != ''){
                if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
                $issue[$dr]['data']['row'] = $dr;
                $issue[$dr]['data']['name'] = $n;
                $issue[$dr]['data']['storage'] = $st;
                if($color){
                    $issue[$dr]['data']['color'] = $d[$color];
                }
                if($v_grade){
                    $issue[$dr]['data']['v_grade'] = $d[$v_grade];
                }
                $issue[$dr]['data']['imei'] = $i.$s;
                $issue[$dr]['data']['cost'] = $c;
                $issue[$dr]['message'] = 'Cost not Provided';
                continue;
                }
            }
            // $last2 = end($names);
            // if($last2 == "5G"){
            //     array_pop($names);
            //     $n = implode(" ", $names);
            // }
            if(in_array(strtolower($n), array_map('strtolower',$products)) && ($i != null || $s != null)){
                $product = array_search(strtolower($n), array_map('strtolower',$products));
                $storage = $gb;
                if ($color) {
                    // Convert each color name to lowercase
                    $lowercaseColors = array_map('strtolower', $colors);

                    $colorName = strtolower($d[$color]); // Convert color name to lowercase

                    if (in_array($colorName, $lowercaseColors)) {
                        // If the color exists in the predefined colors array,
                        // retrieve its index
                        $clr = array_search($colorName, $lowercaseColors);
                    } else {
                        // If the color doesn't exist in the predefined colors array,
                        // create a new color record in the database
                        $newColor = Color_model::create([
                            'name' => $colorName
                        ]);
                        $colors = Color_model::pluck('name','id')->toArray();
                        $lowercaseColors = array_map('strtolower', $colors);
                        // Retrieve the ID of the newly created color
                        $clr = $newColor->id;
                    }
                    $variation = Variation_model::firstOrNew(['product_id' => $product, 'grade' => $grade, 'storage' => $storage, 'color' => $clr]);

                }else{

                $variation = Variation_model::firstOrNew(['product_id' => $product, 'grade' => $grade, 'storage' => $storage]);
                }
                $grd = null;
                if ($v_grade) {
                    // Convert each v_grade name to lowercase
                    $lowercaseGrades = array_map('strtolower', $grades);

                    $v_gradeName = strtolower($d[$v_grade]); // Convert v_grade name to lowercase

                    if (in_array($v_gradeName, $lowercaseGrades)) {
                        // If the v_grade exists in the predefined v_grades array,
                        // retrieve its index
                        $grd = array_search($v_gradeName, $lowercaseGrades);
                    } else {
                        $grd = 6;
                    }
                }

                // echo $product." ".$grade." ".$storage." | ";

                $stock = Stock_model::firstOrNew(['imei' => $i, 'serial_number' => $s]);

                if($stock->id && $stock->status != null && $stock->order_id != null){
                    if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
                    $issue[$dr]['data']['row'] = $dr;
                    $issue[$dr]['data']['name'] = $n;
                    $issue[$dr]['data']['storage'] = $st;
                    if($color){
                        $issue[$dr]['data']['color'] = $d[$color];
                    }
                    if($v_grade){
                        $issue[$dr]['data']['v_grade'] = $d[$v_grade];
                    }
                    $issue[$dr]['data']['imei'] = $i.$s;
                    $issue[$dr]['data']['cost'] = $c;
                    if($stock->order_id == $order->id && $stock->status == 1){
                        $issue[$dr]['message'] = 'Item already added in this order';
                    }else{
                        if($stock->status != 2){
                            $issue[$dr]['message'] = 'Item already available in inventory under order reference '.$stock->order->reference_id;
                        }else{
                            $issue[$dr]['message'] = 'Item previously purchased in order reference '.$stock->order->reference_id;
                        }

                    }

                }else{
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
                    $order_item->reference_id = $grd;
                    $order_item->quantity = 1;
                    $order_item->price = $c;
                    $order_item->status = 3;
                    $order_item->save();

                }

            }else{
                if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
                if($n != null){
                    $error .= $n . " " . $st . " " . $i.$s . " || ";
                    $issue[$dr]['data']['row'] = $dr;
                    $issue[$dr]['data']['name'] = $n;
                    $issue[$dr]['data']['storage'] = $st;
                    if($color){
                        $issue[$dr]['data']['color'] = $d[$color];
                    }
                    if($v_grade){
                        $issue[$dr]['data']['v_grade'] = $d[$v_grade];
                    }
                    $issue[$dr]['data']['imei'] = $i.$s;
                    $issue[$dr]['data']['cost'] = $c;
                    if($i == null && $s == null){
                        $issue[$dr]['message'] = 'IMEI/Serial Not Found';
                    }else{
                        $issue[$dr]['message'] = 'Product Name Not Found';
                    }

                }
            }

        }

        // Delete the temporary file
        // Storage::delete($filePath);
        if($error != ""){

            session()->put('error', $error);
            session()->put('missing', $issue);
        }
        if($issue != []){
            foreach($issue as $row => $datas){
                Order_issue_model::create([
                    'order_id' => $order->id,
                    'data' => json_encode($datas['data']),
                    'message' => $datas['message'],
                ]);
            }
        }
        return redirect(url('purchase/detail').'/'.$order->id);
    }
    public function add_purchase_item($order_id, $imei = null, $variation_id = null, $price = null, $return = null){
        $issue = [];
        if(request('imei')){
            $imei = request('imei');
        }
        if(request('order')){
            $order_id = request('order');
        }
        if(request('variation')){
            $variation_id = request('variation');
        }
        $variation = Variation_model::find($variation_id);
        if(request('price')){
            $price = request('price');
        }

        if(ctype_digit($imei)){
            $i = $imei;
            $s = null;
        }else{
            $i = null;
            $s = $imei;
        }

        if($variation == null){
            session()->put('error', 'Variation Not Found');
            return redirect()->back();
        }

        $stock = Stock_model::firstOrNew(['imei' => $i, 'serial_number' => $s]);
        if($stock->id && $stock->status != null && $stock->order_id != null){
            $issue['data']['variation'] = $variation_id;
            $issue['data']['imei'] = $i.$s;
            $issue['data']['cost'] = $price;
            $issue['data']['stock_id'] = $stock->id;
            if($stock->order_id == $order_id && $stock->status == 1){
                $issue['message'] = 'Duplicate IMEI';
            }else{
                if($stock->status != 2){
                    $issue['message'] = 'IMEI Available In Inventory';
                }else{
                    $issue['message'] = 'IMEI Repurchase';
                }
            }
            // $stock->status = 2;
        }else{

            $variation->stock += 1;
            $variation->status = 1;
            $variation->save();


            $stock->added_by = session('user_id');
            $stock->order_id = $order_id;

            $stock->product_id = $variation->product_id;
            $stock->variation_id = $variation->id;
            $stock->status = 1;
            $stock->save();

            $order_item = new Order_item_model();
            $order_item->order_id = $order_id;
            $order_item->variation_id = $variation->id;
            $order_item->stock_id = $stock->id;
            $order_item->quantity = 1;
            $order_item->price = $price;
            $order_item->status = 3;
            $order_item->save();

            $order = Order_model::find($order_id);
            if($order->status == 3 && !in_array($order_id,[8441,1,5,8,9,12,13,14,185,263,4739]) && $return == null){

                $issue['data']['variation'] = $variation_id;
                $issue['data']['imei'] = $i.$s;
                $issue['data']['cost'] = $price;
                $issue['data']['stock_id'] = $stock->id;
                $issue['message'] = 'Additional Item';
            }

        }

        if($issue != []){
            Order_issue_model::create([
                'order_id' => $order_id,
                'data' => json_encode($issue['data']),
                'message' => $issue['message'],
            ]);
        }else{
            $issue = 1;
        }
        // Delete the temporary file
        // Storage::delete($filePath);
        if($return == null){
            return redirect()->back();
        }else{
            return $issue;
        }

    }
    public function remove_issues(){
        // dd(request('ids'));
        $ids = request('ids');
        $id = request('id');
        if(request('ids')){
            $issues = Order_issue_model::whereIn('id',$ids)->get();
        }
        if(request('id')){
            $issue = Order_issue_model::find($id);
        }

        if(request('remove_entries') == 1){
            foreach ($issues as $issue) {
                $issue->delete();
            }
        }
        if(request('insert_variation') == 1){
            $variation = request('variation');
            foreach($issues as $issue){
                $data = json_decode($issue->data);
                // echo $variation." ".$data->imei." ".$data->cost;

                if($this->add_purchase_item($issue->order_id, $data->imei, $variation, $data->cost, 1) == 1){
                    $issue->delete();
                }

            }
        }
        if(request('add_imei') == 1){
            $imei = request('imei');
            $variation = request('variation');
            $data = json_decode($issue->data);
            // echo $variation." ".$data->imei." ".$data->cost;

            if($this->add_purchase_item($issue->order_id, $imei, $variation, $data->cost, 1) == 1){
                $issue->delete();
            }

        }
        if(request('change_imei') == 1){
            $imei = request('imei');
            $serial_number = null;
            $imei = trim($imei);
            if(!ctype_digit($imei)){
                $serial_number = $imei;
                $imei = null;
            }
            $old_stock = Stock_model::where(['imei'=>$imei,'serial_number'=>$serial_number])->where('status','!=',null)->first();
            if(!$old_stock){

                session()->put('error', "IMEI not Found");
                return redirect()->back();
            }
            $data = json_decode($issue->data);
            $new_stock = Stock_model::find($data->stock_id);
            if(!$new_stock){

                session()->put('error', "Additional Item not added Properly");
                return redirect()->back();
            }
            $new_item = Order_item_model::find($new_stock->purchase_item->id);
            $new_item->order_id = $old_stock->order_id;
            $new_item->price = $old_stock->purchase_item->price;

            $new_stock->order_id = $old_stock->order_id;


            $stock_operation = Stock_operations_model::create([
                'stock_id' => $new_stock->id,
                'old_variation_id' => $old_stock->variation_id,
                'new_variation_id' => $new_stock->variation_id,
                'description' => "IMEI Changed from ".$old_stock->imei.$old_stock->serial_number,
                'admin_id' => session('user_id'),
            ]);

            $old_stock->purchase_item->delete();
            $old_stock->delete();

            $new_item->save();
            $new_stock->save();

            $issue->delete();

        }
        return redirect()->back();

    }
    public function export_invoice($orderId)
    {

        // Find the order
        $order = Order_model::with('customer', 'order_items')->find($orderId);
        $order_items = Order_item_model::where('order_id', $orderId);
        if($order_items->count() > 1){
            $order_items = $order_items->whereHas('stock', function($q) {
                $q->where('status', 2)->orWhere('status',null);
            })->get();
        }else{
            $order_items = $order_items->get();
        }

        // Generate PDF for the invoice content
        $data = [
            'order' => $order,
            'customer' => $order->customer,
            'orderItems' => $order_items,
        ];

        // Create a new TCPDF instance
        $pdf = new TCPDF();

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        // $pdf->SetTitle('Invoice');
        // $pdf->SetHeaderData('', 0, 'Invoice', '');

        // Add a page
        $pdf->AddPage();

        // Set font
        // $fontname = TCPDF_FONTS::addTTFfont(asset('assets/font/OpenSans_Condensed-Regular.ttf'), 'TrueTypeUnicode', '', 96);

        $pdf->SetFont('dejavusans', '', 12);


        // Additional content from your view
        $html = view('export.invoice', $data)->render();
        $pdf->writeHTML($html, true, false, true, false, '');

        // dd($pdfContent);
        // Send the invoice via email

        Mail::to($order->customer->email)->queue(new InvoiceMail($data));
        // if(session('user_id') == 1){

        // $recipientEmail = $order->customer->email;
        // $subject = 'Invoice for Your Recent Purchase';

        // app(GoogleController::class)->sendEmailInvoice($recipientEmail, $subject, new InvoiceMail($data));
        // die;
        // }
        // file_put_contents('invoice.pdf', $pdfContent);

        // Get the PDF content
        // $pdf->Output('', 'I');

        $pdfContent = $pdf->Output('', 'S');
        // Return a response or redirect

        // Pass the PDF content to the view
        return view('livewire.show_pdf')->with(['pdfContent'=> $pdfContent, 'delivery_note'=>$order->delivery_note_url]);
    }
    public function dispatch($id)
    {
        $order = Order_model::where('id',$id)->first();
        $bm = new BackMarketAPIController();

        // $orderObj = $bm->getOneOrder($order->reference_id);
        $orderObj = $this->updateBMOrder($order->reference_id, false, null, true);
        $tester = request('tester');
        $sku = request('sku');
        $imeis = request('imei');

        // Initialize an empty result array
        $skus = [];

        // Loop through the numbers array
        foreach ($sku as $index => $number) {
            // If the value doesn't exist as a key in the skus array, create it
            if (!isset($skus[$number])) {
                $skus[$number] = [];
            }
            // Add the current number to the skus array along with its index in the original array
            $skus[$number][$index] = $number;
        }
        // print_r(request('imei'));
        if($orderObj->state == 3){
            foreach(request('imei') as $i => $imei){

                $variant = Variation_model::where('sku',$sku[$i])->first();
                if($variant->storage != null){
                    $storage2 = $variant->storage_id->name . " - ";
                }else{
                    $storage2 = null;
                }
                if($variant->color != null){
                    $color2 = $variant->color_id->name . " - ";
                }else{
                    $color2 = null;
                }

                $serial_number = null;
                $imei = trim($imei);
                if(!ctype_digit($imei)){
                    $serial_number = $imei;
                    $imei = null;

                }else{

                    if(strlen($imei) != 15){

                        session()->put('error', "IMEI invalid");
                        return redirect()->back();
                    }
                }

                $stock[$i] = Stock_model::where(['imei'=>$imei, 'serial_number'=>$serial_number])->first();

                if(!$stock[$i] || $stock[$i]->status == null){
                    session()->put('error', "Stock not Found");
                    return redirect()->back();

                }
                // if($stock[$i]->status != 1){

                    $last_item = $stock[$i]->last_item();
                    // if(session('user_id') == 1){
                    //     dd($last_item);
                    // }
                    if(in_array($last_item->order->order_type_id,[1,4])){

                        if($stock[$i]->status == 2){
                            $stock[$i]->status = 1;
                            $stock[$i]->save();
                        }
                    }else{
                        if($stock[$i]->status == 1){
                            $stock[$i]->status = 2;
                            $stock[$i]->save();
                        }
                        session()->put('error', "Stock Already Sold");
                        return redirect()->back();
                    }
                // }
                if($stock[$i]->order->status < 3){
                    session()->put('error', "Stock List Awaiting Approval");
                    return redirect()->back();
                }
                if($stock[$i]->variation->grade == 17){
                    session()->put('error', "IMEI Flagged | Contact Admin");
                    return redirect()->back();
                }
                if($stock[$i]->variation->storage != null){
                    $storage = $stock[$i]->variation->storage_id->name . " - ";
                }else{
                    $storage = null;
                }
                if($stock[$i]->variation->color != null){
                    $color = $stock[$i]->variation->color_id->name . " - ";
                }else{
                    $color = null;
                }
                if(($stock[$i]->variation->product_id == $variant->product_id) || ($variant->product_id == 144 && $stock[$i]->variation->product_id == 229) || ($variant->product_id == 142 && $stock[$i]->variation->product_id == 143) || ($variant->product_id == 54 && $stock[$i]->variation->product_id == 55) || ($variant->product_id == 55 && $stock[$i]->variation->product_id == 54) || ($variant->product_id == 58 && $stock[$i]->variation->product_id == 59) || ($variant->product_id == 59 && $stock[$i]->variation->product_id == 58) || ($variant->product_id == 200 && $stock[$i]->variation->product_id == 160)){
                }else{
                    session()->put('error', "Product Model not matched");
                    return redirect()->back();
                }
                if(($stock[$i]->variation->storage == $variant->storage) || ($variant->storage == 5 && in_array($stock[$i]->variation->storage,[0,6]) && $variant->product->brand == 2) || (in_array($variant->product_id, [78,58,59]) && $variant->storage == 4 && in_array($stock[$i]->variation->storage,[0,5]))){
                }else{
                    session()->put('error', "Product Storage not matched");
                    return redirect()->back();
                }
                if(!in_array($stock[$i]->variation->grade, [$variant->grade, 7, 9])){
                    session()->put('error', "Product Grade not matched");
                    return redirect()->back();

                }

                if($stock[$i]->variation_id != $variant->id){
                    echo "<script>
                    if (confirm('System Model: " . $stock[$i]->variation->product->model . " - " . $storage . $color . $stock[$i]->variation->grade_id->name . "\\nRequired Model: " . $variant->product->model . " - " . $storage2 . $color2 . $variant->grade_id->name . "')) {
                        // User clicked OK, do nothing or perform any other action
                    } else {
                        // User clicked Cancel, redirect to the previous page
                        window.history.back();
                    }
                    </script>";

                    $stock_operation = Stock_operations_model::create([
                        'stock_id' => $stock[$i]->id,
                        'old_variation_id' => $stock[$i]->variation_id,
                        'new_variation_id' => $variant->id,
                        'description' => "Grade changed for Sell",
                        'admin_id' => session('user_id'),
                    ]);
                }
                $stock[$i]->variation_id = $variant->id;
                $stock[$i]->tester = $tester[$i];
                $stock[$i]->status = 2;
                $stock[$i]->save();
                $orderObj = $this->updateBMOrder($order->reference_id, true, $tester[$i], true);
            }
            $order = Order_model::find($order->id);
            $items = $order->order_items;
            if(count($items) > 1 || $items[0]->quantity > 1){
                $indexes = 0;
                foreach($skus as $each_sku){
                    if($indexes == 0 && count($each_sku) == 1){
                        $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],trim($imeis[0]),$orderObj->tracking_number,$serial_number);
                    }elseif($indexes == 0 && count($each_sku) > 1){
                        // dd("Hello");
                        $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],false,$orderObj->tracking_number,$serial_number);
                    }elseif($indexes > 0 && count($each_sku) == 1){
                        $order_item = Order_item_model::where('order_id',$order->id)->whereHas('variation', function($q) use ($each_sku){
                            $q->whereIn('sku',$each_sku);
                        })->first();
                        $detail = $bm->orderlineIMEI($order_item->reference_id,trim($imeis[0]),$serial_number);
                    }else{

                    }
                    $indexes++;
                }
            }else{
                $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],trim($imeis[0]),$orderObj->tracking_number,$serial_number);
            }
            // print_r($detail);

            if(is_string($detail)){
                session()->put('error', $detail);
                return redirect()->back();
            }


            foreach ($skus as $each) {
                $inde = 0;
                foreach ($each as $idt => $s) {
                    $variation = Variation_model::where('sku',$s)->first();
                    $item = Order_item_model::where(['order_id'=>$id, 'variation_id'=>$variation->id])->first();
                    if ($inde != 0) {

                        $new_item = new Order_item_model();
                        $new_item->order_id = $id;
                        $new_item->variation_id = $item->variation_id;
                        $new_item->quantity = $item->quantity;
                        $new_item->status = $item->status;
                        $new_item->price = $item->price;
                    }else{
                        $new_item = $item;
                        $new_item->price = $item->price/count($each);
                    }
                    if($stock[$idt]){
                    $new_item->stock_id = $stock[$idt]->id;
                    $new_item->linked_id = $stock[$idt]->last_item()->id;
                    // $new_item->linked_id = Order_item_model::where(['order_id'=>$stock[$idt]->order_id,'stock_id'=>$stock[$idt]->id])->first()->id;
                    }
                    $new_item->save();
                    $inde ++;
                }
            }

            // print_r($d[6]);
        }

        $orderObj = $this->updateBMOrder($order->reference_id, true);
        $order = Order_model::find($order->id);
        if(!isset($detail)){

            $invoice_url = url(session('url').'export_invoice').'/'.$id;
            // JavaScript to open two tabs and print
            echo '<script>
            var newTab1 = window.open("'.$order->delivery_note_url.'", "_blank");
            var newTab2 = window.open("'.$invoice_url.'", "_blank");

            newTab2.onload = function() {
                newTab2.print();
            };
            newTab1.onload = function() {
                newTab1.print();
            };

            </script>';
            if(request('sort') == 4){
                echo "<script> window.close(); </script>";
            }else{
                echo "<script> window.location.href = document.referrer; </script>";
            }
        }
        // if(!$detail->orderlines){
        //     dd($detail);
        // }
        if(isset($detail->orderlines) && $detail->orderlines[0]->imei == null && $detail->orderlines[0]->serial_number  == null){
            $content = "Hi, here are the IMEIs/Serial numbers for this order. \n";
            foreach ($imeis as $im) {
                $content .= $im . "\n";
            }
            $content .= "Regards \n".session('fname');

            // JavaScript code to automatically copy content to clipboard
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    const el = document.createElement('textarea');
                    el.value = '$content';
                    document.body.appendChild(el);
                    el.select();
                    document.execCommand('copy');
                    document.body.removeChild(el);
                });
            </script>";


            // JavaScript to open two tabs and print
            echo '<script>
            window.open("https://backmarket.fr/bo_merchant/orders/all?orderId='.$order->reference_id.'", "_blank");
            window.location.href = document.referrer;
            </script>';
        }else{

            $invoice_url = url(session('url').'export_invoice').'/'.$id;
            // JavaScript to open two tabs and print
            echo '<script>
            var newTab1 = window.open("'.$order->delivery_note_url.'", "_blank");
            var newTab2 = window.open("'.$invoice_url.'", "_blank");

            newTab2.onload = function() {
                newTab2.print();
            };
            newTab1.onload = function() {
                newTab1.print();
            };

            window.location.href = document.referrer;
            </script>';
        }


    }
    public function dispatch_allowed($id)
    {
        $order = Order_model::where('id',$id)->first();
        $bm = new BackMarketAPIController();

        // $orderObj = $bm->getOneOrder($order->reference_id);
        $orderObj = $this->updateBMOrder($order->reference_id, false, null, true);
        $tester = request('tester');
        $sku = request('sku');
        $imeis = request('imei');

        // Initialize an empty result array
        $skus = [];

        // Loop through the numbers array
        foreach ($sku as $index => $number) {
            // If the value doesn't exist as a key in the skus array, create it
            if (!isset($skus[$number])) {
                $skus[$number] = [];
            }
            // Add the current number to the skus array along with its index in the original array
            $skus[$number][$index] = $number;
        }
        // print_r(request('imei'));
        if($orderObj->state == 3){
            foreach(request('imei') as $i => $imei){

                $variant = Variation_model::where('sku',$sku[$i])->first();
                if($variant->storage != null){
                    $storage2 = $variant->storage_id->name . " - ";
                }else{
                    $storage2 = null;
                }
                if($variant->color != null){
                    $color2 = $variant->color_id->name . " - ";
                }else{
                    $color2 = null;
                }

                $serial_number = null;
                $imei = trim($imei);
                if(!ctype_digit($imei)){
                    $serial_number = $imei;
                    $imei = null;

                }else{

                    if(strlen($imei) != 15){

                        session()->put('error', "IMEI invalid");
                        return redirect()->back();
                    }
                }

                $stock[$i] = Stock_model::where(['imei'=>$imei, 'serial_number'=>$serial_number])->first();

                if(!$stock[$i] || $stock[$i]->status == null){
                    session()->put('error', "Stock not Found");
                    return redirect()->back();

                }
                // if($stock[$i]->status != 1){

                    $last_item = $stock[$i]->last_item();
                    // if(session('user_id') == 1){
                    //     dd($last_item);
                    // }
                    if(in_array($last_item->order->order_type_id,[1,4])){

                        if($stock[$i]->status == 2){
                            $stock[$i]->status = 1;
                            $stock[$i]->save();
                        }
                    }else{
                        if($stock[$i]->status == 1){
                            $stock[$i]->status = 2;
                            $stock[$i]->save();
                        }
                        session()->put('error', "Stock Already Sold");
                        return redirect()->back();
                    }
                // }
                if($stock[$i]->order->status < 3){
                    session()->put('error', "Stock List Awaiting Approval");
                    return redirect()->back();
                }
                if($stock[$i]->variation->grade == 17){
                    session()->put('error', "IMEI Flagged | Contact Admin");
                    return redirect()->back();
                }
                if($stock[$i]->variation->storage != null){
                    $storage = $stock[$i]->variation->storage_id->name . " - ";
                }else{
                    $storage = null;
                }
                if($stock[$i]->variation->color != null){
                    $color = $stock[$i]->variation->color_id->name . " - ";
                }else{
                    $color = null;
                }
                if(($stock[$i]->variation->product_id == $variant->product_id) || ($variant->product_id == 144 && $stock[$i]->variation->product_id == 229) || ($variant->product_id == 142 && $stock[$i]->variation->product_id == 143) || ($variant->product_id == 54 && $stock[$i]->variation->product_id == 55) || ($variant->product_id == 55 && $stock[$i]->variation->product_id == 54) || ($variant->product_id == 200 && $stock[$i]->variation->product_id == 160)){
                }else{
                    session()->put('error', "Product Model not matched");
                    // return redirect()->back();
                }
                if(($stock[$i]->variation->storage == $variant->storage) || ($variant->storage == 5 && in_array($stock[$i]->variation->storage,[0,6]) && $variant->product->brand == 2) || (in_array($variant->product_id, [78,58,59]) && $variant->storage == 4 && in_array($stock[$i]->variation->storage,[0,5]))){
                }else{
                    session()->put('error', "Product Storage not matched");
                    // return redirect()->back();
                }
                echo "<script>
                if (confirm('System Model: " . $stock[$i]->variation->product->model . " - " . $storage . $color . $stock[$i]->variation->grade_id->name . "\\nRequired Model: " . $variant->product->model . " - " . $storage2 . $color2 . $variant->grade_id->name . "')) {
                    // User clicked OK, do nothing or perform any other action
                } else {
                    // User clicked Cancel, redirect to the previous page
                    window.history.back();
                }
                </script>";
                if($stock[$i]->variation_id != $variant->id){

                    $stock_operation = Stock_operations_model::create([
                        'stock_id' => $stock[$i]->id,
                        'old_variation_id' => $stock[$i]->variation_id,
                        'new_variation_id' => $variant->id,
                        'description' => "Grade changed for Sell",
                        'admin_id' => session('user_id'),
                    ]);
                }
                $stock[$i]->variation_id = $variant->id;
                $stock[$i]->tester = $tester[$i];
                $stock[$i]->status = 2;
                $stock[$i]->save();
                $orderObj = $this->updateBMOrder($order->reference_id, true, $tester[$i], true);
            }
            $order = Order_model::find($order->id);
            $items = $order->order_items;
            if(count($items) > 1 || $items[0]->quantity > 1){
                $indexes = 0;
                foreach($skus as $each_sku){
                    if($indexes == 0 && count($each_sku) == 1){
                        $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],trim($imeis[0]),$orderObj->tracking_number,$serial_number);
                    }elseif($indexes == 0 && count($each_sku) > 1){
                        // dd("Hello");
                        $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],false,$orderObj->tracking_number,$serial_number);
                    }elseif($indexes > 0 && count($each_sku) == 1){
                        $detail = $bm->orderlineIMEI($order->reference_id,trim($imeis[0]),$serial_number);
                    }else{

                    }
                    $indexes++;
                }
            }else{
                $detail = $bm->shippingOrderlines($order->reference_id,$sku[0],trim($imeis[0]),$orderObj->tracking_number,$serial_number);
            }
            // print_r($detail);

            if(is_string($detail)){
                session()->put('error', $detail);
                return redirect()->back();
            }


            foreach ($skus as $each) {
                $inde = 0;
                foreach ($each as $idt => $s) {
                    $variation = Variation_model::where('sku',$s)->first();
                    $item = Order_item_model::where(['order_id'=>$id, 'variation_id'=>$variation->id])->first();
                    if ($inde != 0) {

                        $new_item = new Order_item_model();
                        $new_item->order_id = $id;
                        $new_item->variation_id = $item->variation_id;
                        $new_item->quantity = $item->quantity;
                        $new_item->status = $item->status;
                        $new_item->price = $item->price;
                    }else{
                        $new_item = $item;
                        $new_item->price = $item->price/count($each);
                    }
                    if($stock[$idt]){
                    $new_item->stock_id = $stock[$idt]->id;
                    $new_item->linked_id = $stock[$idt]->last_item()->id;
                    // $new_item->linked_id = Order_item_model::where(['order_id'=>$stock[$idt]->order_id,'stock_id'=>$stock[$idt]->id])->first()->id;
                    }
                    $new_item->save();
                    $inde ++;
                }
            }

            // print_r($d[6]);
        }

        $orderObj = $this->updateBMOrder($order->reference_id, true);
        $order = Order_model::find($order->id);
        if(!isset($detail)){

            $invoice_url = url(session('url').'export_invoice').'/'.$id;
            // JavaScript to open two tabs and print
            echo '<script>
            var newTab1 = window.open("'.$order->delivery_note_url.'", "_blank");
            var newTab2 = window.open("'.$invoice_url.'", "_blank");

            newTab2.onload = function() {
                newTab2.print();
            };
            newTab1.onload = function() {
                newTab1.print();
            };

            window.location.href = document.referrer;
            </script>';

        }
        if(!$detail->orderlines){
            dd($detail);
        }
        if($detail->orderlines[0]->imei == null && $detail->orderlines[0]->serial_number  == null){
            $content = "Hi, here are the IMEIs/Serial numbers for this order. \n";
            foreach ($imeis as $im) {
                $content .= $im . "\n";
            }
            $content .= "Regards \n".session('fname');

            // JavaScript code to automatically copy content to clipboard
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    const el = document.createElement('textarea');
                    el.value = '$content';
                    document.body.appendChild(el);
                    el.select();
                    document.execCommand('copy');
                    document.body.removeChild(el);
                });
            </script>";


            // JavaScript to open two tabs and print
            echo '<script>
            window.open("https://backmarket.fr/bo_merchant/orders/all?orderId='.$order->reference_id.'", "_blank");
            window.location.href = document.referrer;
            </script>';
        }else{

            $invoice_url = url(session('url').'export_invoice').'/'.$id;
            // JavaScript to open two tabs and print
            echo '<script>
            var newTab1 = window.open("'.$order->delivery_note_url.'", "_blank");
            var newTab2 = window.open("'.$invoice_url.'", "_blank");

            newTab2.onload = function() {
                newTab2.print();
            };
            newTab1.onload = function() {
                newTab1.print();
            };

            window.location.href = document.referrer;
            </script>';
        }


    }
    public function delete_item($id){
        Order_item_model::find($id)->delete();
        return redirect()->back();
    }
    public function delete_replacement_item($id){
        $item = Order_item_model::find($id);
        $item->stock->status = 1;
        $item->stock->save();
        $item->delete();
        return redirect()->back();
    }
    public function correction(){
        $item = Order_item_model::find(request('correction')['item_id']);
        if($item->order->processed_at > Carbon::now()->subHour(2) || session('user')->hasPermission('correction')){
            if($item->quantity > 1 && $item->order->order_items->count() == 1){
                for($i=1; $i<=$item->quantity; $i++){

                    if ($i != 1) {

                        $new_item = new Order_item_model();
                        $new_item->order_id = $item->order_id;
                        $new_item->variation_id = $item->variation_id;
                        $new_item->quantity = $item->quantity;
                        $new_item->status = $item->status;
                        $new_item->price = $item->price;
                    }else{
                        $new_item = $item;
                        $new_item->price = $item->price/$item->quantity;
                    }
                    $new_item->save();
                }
            }
            $imei = request('correction')['imei'];
            $serial_number = null;
            if(!ctype_digit($imei)){
                $serial_number = $imei;
                $imei = null;
            }

            $stock = Stock_model::where(['imei'=>$imei, 'serial_number'=>$serial_number])->first();
            if(!$stock){
                session()->put('error', 'Stock not found');
                return redirect()->back();
            }
            if($stock->order->status != 3){
                session()->put('error', 'Stock list awaiting approval');
                return redirect()->back();
            }
            $stock->variation_id = $item->variation_id;
            $stock->tester = request('correction')['tester'];
            $stock->added_by = session('user_id');
            if($stock->status == 1){
                $stock->status = 2;
            }
            $stock->save();
            if($item->stock_id != null){
                if($item->stock->purchase_item){
                    $last_operation = Stock_operations_model::where('stock_id',$item->stock_id)->orderBy('id','desc')->first();
                    if($last_operation != null){
                        if($last_operation->new_variation_id == $item->stock->variation_id){
                            $last_variation_id = $last_operation->old_variation_id;
                        }else{
                            $last_variation_id = $last_operation->new_variation_id;
                        }
                    }else{
                        $last_variation_id = Order_item_model::where(['order_id'=>$item->stock->order_id,'stock_id'=>$item->stock_id])->first()->variation_id;
                    }
                    $stock_operation = Stock_operations_model::create([
                        'stock_id' => $item->stock->id,
                        'order_item_id' => $item->id,
                        'old_variation_id' => $item->stock->variation_id,
                        'new_variation_id' => $last_variation_id,
                        'description' => request('correction')['reason']." ".$item->order->reference_id." ".$imei.$serial_number,
                        'admin_id' => session('user_id'),
                    ]);
                    $stock_operation->save();
                    $item->stock->variation_id = $last_variation_id;
                    if($item->stock->status == 2){
                        $item->stock->status = 1;
                    }
                    $item->stock->save();
                }

            }

            $item->stock_id = $stock->id;
            $item->linked_id = $stock->purchase_item->id;
            $item->save();

            $message = "Hi, here is the correct IMEI/Serial number for this order. \n".$imei.$serial_number." ".$stock->tester."\n Regards, \n" . session('fname');
            session()->put('success', $message);
            session()->put('copy', $message);
        }else{
            session()->put('error', 'Update deadline exceeded');
        }
        return redirect()->back();
    }

    public function replacement($london = 0){
        $item = Order_item_model::find(request('replacement')['item_id']);
        if(session('user')->hasPermission('replacement')){
            if(!$item->stock->order){
                session()->put('error', 'Stock not purchased');
                return redirect()->back();
            }
            $imei = request('replacement')['imei'];
            $serial_number = null;
            if(!ctype_digit($imei)){
                $serial_number = $imei;
                $imei = null;
            }

            $stock = Stock_model::where(['imei'=>$imei, 'serial_number'=>$serial_number])->first();
            if(!$stock){
                session()->put('error', 'Stock not found');
                return redirect()->back();
            }
            if($stock->status != 1){
                session()->put('error', 'Stock already sold');
                return redirect()->back();
            }
            if($stock->order->status != 3){
                session()->put('error', 'Stock list awaiting approval');
                return redirect()->back();
            }
            if($stock->variation->storage != null){
                $storage = $stock->variation->storage_id->name . " - ";
            }else{
                $storage = null;
            }
            if($stock->variation->color != null){
                $color = $stock->variation->color_id->name . " - ";
            }else{
                $color = null;
            }
            if($item->variation->storage != null){
                $storage2 = $item->variation->storage_id->name . " - ";
            }else{
                $storage2 = null;
            }
            if($item->variation->color != null){
                $color2 = $item->variation->color_id->name . " - ";
            }else{
                $color2 = null;
            }
            if(($stock->variation->product_id == $item->variation->product_id) || ($item->variation->product_id == 144 && $stock->variation->product_id == 229) || ($item->variation->product_id == 142 && $stock->variation->product_id == 143) || ($item->variation->product_id == 54 && $stock->variation->product_id == 55) || ($item->variation->product_id == 55 && $stock->variation->product_id == 54) || ($item->variation->product_id == 58 && $stock->variation->product_id == 59) || ($item->variation->product_id == 59 && $stock->variation->product_id == 58) || ($item->variation->product_id == 200 && $stock->variation->product_id == 160)){
            }else{
                session()->put('error', "Product Model not matched");
                return redirect()->back();
            }
            if(($stock->variation->storage == $item->variation->storage) || ($item->variation->storage == 5 && in_array($stock->variation->storage,[0,6]) && $item->variation->product->brand == 2) || (in_array($item->variation->product_id, [78,58,59]) && $item->variation->storage == 4 && in_array($stock->variation->storage,[0,5]))){
            }else{
                session()->put('error', "Product Storage not matched");
                return redirect()->back();
            }

            if($london == 1){
                $return_order = Order_model::find(8827);
            }else{

                $return_order = Order_model::where(['order_type_id'=>4,'status'=>1])->first();
            }
            $check_return = Order_item_model::where(['linked_id'=>$item->id, 'reference_id'=>$item->order->reference_id])->first();
            if($check_return != null){
                $return_order = $check_return->order;
            }
            // if(in_array($item->stock->last_item()->order->order_type_id,[1,4])){
            //     $return_order = $item->stock->last_item()->order;
            // }
            if(!$return_order){
                session()->put('error', 'No Active Return Order Found');
                return redirect()->back();
            }

            $r_item = Order_item_model::where(['order_id'=>$return_order->id, 'stock_id' => $item->stock_id])->first();
            if($r_item){
                $grade = $r_item->variation->grade;

                $stock_operation = Stock_operations_model::where(['stock_id'=>$item->stock_id])->orderBy('id','desc')->first();
                $stock_operation->order_item_id = $r_item->id;
                $stock_operation->description = $stock_operation->description." | Order: ".$item->order->reference_id." | New IMEI: ".$imei.$serial_number;
                $stock_operation->save();
            }else{
                $grade = request('replacement')['grade'];
            }

            $variation = Variation_model::firstOrNew(['product_id' => $item->variation->product_id, 'storage' => $item->variation->storage, 'color' => $item->variation->color, 'grade' => $grade]);

            $variation->stock += 1;
            $variation->status = 1;
            $variation->save();


            // print_r($stock);
            if($r_item == null){
                $return_item = new Order_item_model();
                $return_item->order_id = $return_order->id;
                $return_item->reference_id = request('replacement')['id'];
                $return_item->variation_id = $variation->id;
                $return_item->stock_id = $item->stock_id;
                $return_item->quantity = 1;
                $return_item->price = $item->price;
                $return_item->status = 3;
                $return_item->linked_id = $item->id;
                $return_item->admin_id = session('user_id');
                $return_item->save();

                print_r($return_item);

                session()->put('success','Item returned');

                $stock_operation = Stock_operations_model::create([
                    'stock_id' => $item->stock_id,
                    'order_item_id' => $return_item->id,
                    'old_variation_id' => $item->variation_id,
                    'new_variation_id' => $variation->id,
                    'description' => request('replacement')['reason']." | Order: ".$item->order->reference_id." | New IMEI: ".$imei.$serial_number,
                    'admin_id' => session('user_id'),
                ]);
            }else{
                session()->put('error','Item already returned');

            }
            $stock_operation_2 = Stock_operations_model::create([
                'stock_id' => $stock->id,
                'order_item_id' => $item->id,
                'old_variation_id' => $stock->variation_id,
                'new_variation_id' => $item->variation_id,
                'description' => "Replacement | Order: ".$item->order->reference_id." | Old IMEI: ".$item->stock->imei.$item->stock->serial_number,
                'admin_id' => session('user_id'),
            ]);

            $item->stock->variation_id = $variation->id;
            $item->stock->status = 1;
            $item->stock->save();

            $stock->variation_id = $item->variation_id;
            $stock->tester = request('replacement')['tester'];
            $stock->added_by = session('user_id');
            if($stock->status == 1){
                $stock->status = 2;
            }
            $stock->save();

            $order_item = new Order_item_model();
            $order_item->order_id = 8974;
            $order_item->reference_id = $item->order->reference_id;
            $order_item->care_id = $item->id;
            $order_item->variation_id = $item->variation_id;
            $order_item->stock_id = $stock->id;
            $order_item->quantity = 1;
            $order_item->price = $item->price;
            $order_item->status = 3;
            $order_item->linked_id = $stock->last_item()->id;
            $order_item->admin_id = session('user_id');
            $order_item->save();


            $message = "Hi, here is the new IMEI/Serial number for this order. \n".$imei.$serial_number." ".$stock->tester."\n Regards, \n" . session('fname');
            session()->put('success', $message);
            session()->put('copy', $message);
        }else{
            session()->put('error', 'Unauthorized');
        }
        return redirect()->back();
    }

    public function recheck($order_id, $refresh = false, $invoice = false, $tester = null, $data = false){

        $bm = new BackMarketAPIController();

        $order_model = new Order_model();
        $order_item_model = new Order_item_model();
        $currency_codes = Currency_model::pluck('id','code');
        $country_codes = Country_model::pluck('id','code');

        $orderObj = $bm->getOneOrder($order_id);
        if($data == true){
            dd($orderObj);
        }
        if(!isset($orderObj->orderlines)){

        }else{
            $order_model->updateOrderInDB($orderObj, $invoice, $bm, $currency_codes, $country_codes);

            $order_item_model->updateOrderItemsInDB($orderObj, $tester, $bm);
            if($refresh == true){
                $order = Order_model::where('reference_id',$order_id)->first();

                $invoice_url = url(session('url').'export_invoice').'/'.$order->id;
                // JavaScript to open two tabs and print
                echo '<script>
                var newTab2 = window.open("'.$invoice_url.'", "_blank");
                var newTab1 = window.open("'.$order->delivery_note_url.'", "_blank");

                newTab1.onload = function() {
                    newTab1.print();
                };

                newTab2.onload = function() {
                    newTab2.print();
                };

                window.close();
                </script>';
            }
        }
        // return redirect()->back();

    }
    public function import()
    {
        // $bm = new BackMarketAPIController();
        // // Replace 'your-excel-file.xlsx' with the actual path to your Excel file
        // $excelFilePath = storage_path(request('file'));

        // $data = Excel::toArray([], $excelFilePath)[0];
        // if(request('product') != null){
        //     foreach($data as $dr => $d){
        //         // $name = ;
        //     }
        // }else{

        //     // Print or use the resulting array
        //     // dd($data);
        //     $i = 0;
        //     foreach($data as $d){
        //         $orderObj = $bm->getOneOrder($d[1]);
        //         $this->updateBMOrder($d[1]);
        //         if($orderObj->state == 3){
        //             print_r($bm->shippingOrderlines($d[1],trim($d[6]),$orderObj->tracking_number));
        //             // $orderObj = $bm->getOneOrder($d[1]);
        //             // $this->updateBMOrder($d[1]);
        //             $i ++;
        //             print_r($orderObj);
        //             print_r($d[6]);
        //         }
        //         if($i == 100){break;}
        //     }
        // }

    }

    public function export()
    {
        // dd(request());
        // return Excel::download(new OrdersExport, 'your_export_file.xlsx');
        if(request('order') != null){
            $pdfExport = new OrdersExport();
            $pdfExport->generatePdf();
        }
            if(request('ordersheet') != null){
                return Excel::download(new OrdersheetExport, 'orders.xlsx');
            // echo "<script>window.close();</script>";
        }
        if(request('picklist') != null){
            $pdfExport = new PickListExport();
            $pdfExport->generatePdf();
        }
    }
    public function export_label()
    {
        // return Excel::download(new OrdersExport, 'your_export_file.xlsx');
        // dd(request('ids'));
        $pdfExport = new LabelsExport();
        $pdfExport->generatePdf();
    }
    public function export_note()
    {
        // return Excel::download(new OrdersExport, 'your_export_file.xlsx');

        $pdfExport = new DeliveryNotesExport();
        $pdfExport->generatePdf();
    }
    public function track_order($order_id){
        $order = Order_model::find($order_id);
        $orderObj = $this->updateBMOrder($order->reference_id, false, null, true);
        return redirect($orderObj->tracking_url);
    }
    public function getLabel($order_id)
    {

        $bm = new BackMarketAPIController();
        $this->updateBMOrder($order_id);
        $bm->getOrderLabel($order_id);
        return redirect()->back();

    }
    public function getapiorders($page = null)
    {

        if($page == 1){
            for($i = 1; $i <= 10; $i++){
                $j = $i*20;
                echo $url = url(session('url').'refresh_order').'/'.$j;
                echo '<script>
                var newTab1 = window.open("'.$url.'", "_blank");
                </script>';
            }
            $this->updateBMOrdersAll($page);
        }else if($page){
            $this->updateBMOrdersAll($page);

        }else{
            $this->updateBMOrdersAll();

        }



            echo '<script>window.close();</script>';



    }

    public function updateBMOrdersNew($return = false)
    {
        $bm = new BackMarketAPIController();
        $resArray = $bm->getNewOrders();
        $orders = [];
        if ($resArray !== null) {
            foreach ($resArray as $orderObj) {
                if (!empty($orderObj)) {
                    foreach($orderObj->orderlines as $orderline){
                        $this->validateOrderlines($orderObj->order_id, $orderline->listing);
                    }
                    $orders[] = $orderObj->order_id;
                }
            }
            foreach($orders as $or){
                $this->updateBMOrder($or);
            }

        } else {
            echo 'No new orders (in state 0 or 1) exist!';
        }
        $orders2 = Order_model::whereIn('status',[0,1])->where('order_type_id',3)->get();
        foreach($orders2 as $order){
            $this->updateBMOrder($order->reference_id);
        }


        $last_id = Order_item_model::where('care_id','!=',null)->orderBy('reference_id','desc')->first()->care_id;
        $care = $bm->getAllCare(false, ['last_id'=>$last_id,'page-size'=>50]);
        // $care = $bm->getAllCare(false, ['page-size'=>50]);
        // print_r($care);
        $care_line = collect($care)->pluck('id','orderline')->toArray();
        $care_keys = array_keys($care_line);


        // Assuming $care_line is already defined from the previous code
        $careLineKeys = array_keys($care_line);

        // Construct the raw SQL expression for the CASE statement
        // $caseExpression = "CASE ";
        foreach ($care_line as $id => $care) {
            // $caseExpression .= "WHEN reference_id = $id THEN $care ";
            Order_item_model::where('reference_id',$id)->update(['care_id' => $care]);
        }

        if($return = true){
            session()->put('success',count($orders).' Orders Loaded Successfull');
            return redirect()->back();
        }


    }
    public function updateBMOrder($order_id = null, $invoice = false, $tester = null, $data = false){
        if(request('reference_id')){
            $order_id = request('reference_id');
        }
        $bm = new BackMarketAPIController();

        $order_model = new Order_model();
        $order_item_model = new Order_item_model();
        $currency_codes = Currency_model::pluck('id','code');
        $country_codes = Country_model::pluck('id','code');

        $orderObj = $bm->getOneOrder($order_id);
        if(isset($orderObj->delivery_note)){

            if($orderObj->delivery_note == null){
                $orderObj = $bm->getOneOrder($order_id);
            }

            $order_model->updateOrderInDB($orderObj, $invoice, $bm, $currency_codes, $country_codes);

            $order_item_model->updateOrderItemsInDB($orderObj, $tester, $bm);
        }else{
            session()->put('error','Order not Found');
        }
        if($data == true){
            return $orderObj;
        }else{
            return redirect()->back();
        }



    }
    public function updateBMOrdersAll($page = 1)
    {

        $bm = new BackMarketAPIController();

        $order_model = new Order_model();
        $order_item_model = new Order_item_model();
        $currency_codes = Currency_model::pluck('id','code');
        $country_codes = Country_model::pluck('id','code');



        $resArray = $bm->getAllOrders($page, ['page-size'=>50]);
        if ($resArray !== null) {
            // print_r($resArray);
            foreach ($resArray as $orderObj) {
                if (!empty($orderObj)) {
                // print_r($orderObj);
                $order_model->updateOrderInDB($orderObj, false, $bm, $currency_codes, $country_codes);
                $order_item_model->updateOrderItemsInDB($orderObj,null,$bm);
                // $this->updateOrderItemsInDB($orderObj);
                }
                // print_r($orderObj);
                // if($i == 0){ break; } else { $i++; }
            }
        } else {
            echo 'No orders have been modified in 3 months!';
        }
    }

    private function validateOrderlines($order_id, $sku, $validated = true)
    {
        $bm = new BackMarketAPIController();
        $end_point = 'orders/' . $order_id;
        $new_state = 2;

        // construct the request body
        $request = ['order_id' => $order_id, 'new_state' => $new_state, 'sku' => $sku];
        $request_JSON = json_encode($request);

        $result = $bm->apiPost($end_point, $request_JSON);

        return $result;
    }


}
