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
    use Illuminate\Support\Facades\DB;
    use Maatwebsite\Excel\Facades\Excel;
    use TCPDF;
    use App\Mail\InvoiceMail;
use App\Models\Order_issue_model;
use Illuminate\Support\Facades\Mail;


class Order extends Component
{
    public $currency_codes;
    public $country_codes;

    public function mount()
    {
        $this->currency_codes = Currency_model::pluck('id','code');
        $this->country_codes = Country_model::pluck('id','code');
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect('index');
        }
    }
    public function render()
    {

        $data['last_hour'] = Carbon::now()->subHour(2);
        $data['admins'] = Admin_model::where('id','!=',1)->get();
        $user_id = session('user_id');
        $data['user_id'] = $user_id;
        $data['pending_orders_count'] = Order_model::where('status',2)->count();
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
        $orders = Order_model::join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->join('variation', 'order_items.variation_id', '=', 'variation.id')
        ->join('products', 'variation.product_id', '=', 'products.id')
        ->with(['order_items.variation', 'order_items.variation.grade_id', 'order_items.stock'])
        ->where('orders.order_type_id',3)
        ->when(request('start_date') != '', function ($q) {
            return $q->where('orders.created_at', '>=', request('start_date', 0));
        })
        ->when(request('end_date') != '', function ($q) {
            return $q->where('orders.created_at', '<=', request('end_date', 0) . " 23:59:59");
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
            return $q->where('order_items.care_id', '!=', null);
        })
        ->when(request('order_id') != '', function ($q) {
            return $q->where('orders.reference_id', 'LIKE', request('order_id') . '%');
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
            return $q->whereHas('order_items.stock', function ($q) {
                $q->where('tracking_number', 'LIKE', '%' . request('tracking_number') . '%');
            });
        })
        ->orderBy($sort, $by) // Order by variation name
        ->orderBy('orders.reference_id', 'desc') // Secondary order by reference_id
        // ->orderBy('order_items.quantity', 'desc') // Secondary order by reference_id
        ->select('orders.*');


        if(request('bulk_invoice') && request('bulk_invoice') == 1){

            $data['orders2'] = $orders
            ->get();
            foreach($data['orders2'] as $order){

                $data2 = [
                    'order' => $order,
                    'customer' => $order->customer,
                    'orderItems' => $order->order_items,
                ];
                echo "Hello";
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
        return view('livewire.order')->with($data);
    }
    public function purchase()
    {

        $data['latest_reference'] = Order_model::where('order_type_id',1)->orderBy('reference_id','DESC')->first()->reference_id;
        $data['vendors'] = Customer_model::where('is_vendor',1)->pluck('first_name','id');
        $data['order_statuses'] = Order_status_model::get();
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 20;
        }

        $data['orders'] = Order_model::select(
            'orders.id',
            'orders.reference_id',
            'orders.customer_id',
            DB::raw('SUM(order_items.price) as total_price'),
            DB::raw('COUNT(order_items.id) as total_quantity'),
            DB::raw('COUNT(CASE WHEN stock.status = 1 THEN order_items.id END) as available_stock'),
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
        ->groupBy('orders.id', 'orders.reference_id', 'orders.customer_id', 'orders.created_at')
        ->orderBy('orders.reference_id', 'desc') // Secondary order by reference_id
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));


        // dd($data['orders']);
        return view('livewire.purchase')->with($data);
    }
    public function purchase_approve($order_id){
        $order = Order_model::find($order_id);
        $order->tracking_number = request('tracking_number');
        $order->status = 3;
        $order->save();
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
        return redirect()->back();
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

        $data['storages'] = Storage_model::pluck('name','id');
        $data['variations'] = Variation_model::with(['stocks' => function ($query) use ($order_id) {
            $query->where('order_id', $order_id);
        }, 'stocks.order_item' => function ($query) use ($order_id) {
            $query->where('order_id', $order_id);
        }])
        ->whereHas('stocks', function ($query) use ($order_id) {
            $query->where('order_id', $order_id);
        })
        // ->whereHas('stocks.order_item', function ($query) use ($order_id) {
        //     $query->where('order_id', $order_id);
        // })
        ->orderBy('grade', 'desc')
        ->get();

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
        // echo $cost;
        $grade = 9;


        $order = Order_model::firstOrNew(['reference_id' => $purchase->reference_id, 'order_type_id' => $purchase->type ]);
        $order->customer_id = $purchase->vendor;
        $order->status = 2;
        $order->currency = 4;
        $order->order_type_id = $purchase->type;
        $order->processed_by = session('user_id');
        $order->created_at = now()->format('Y-m-d H:i:s');
        $order->save();

        $storages = Storage_model::pluck('name','id')->toArray();

        $products = Products_model::pluck('model','id')->toArray();

        // $variations = Variation_model::where('grade',$grade)->get();

        foreach($data as $dr => $d){
            // $name = ;
            // echo $dr." ";
            // print_r($d);
            $n = trim($d[$name]);
            if(ctype_digit($d[$imei])){
                $i = $d[$imei];
                $s = null;
            }else{
                $i = null;
                $s = $d[$imei];
            }
            if(trim($d[$imei]) == ''){
                continue;
            }
            if(trim($n) == ''){
                continue;
            }
            $c = $d[$cost];
            if(trim($c) == ''){
                continue;
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
            // $last2 = end($names);
            // if($last2 == "5G"){
            //     array_pop($names);
            //     $n = implode(" ", $names);
            // }
            if(in_array(strtolower($n), array_map('strtolower',$products)) && ($i != null || $s != null)){
                $product = array_search(strtolower($n), array_map('strtolower',$products));
                $storage = $gb;

                // echo $product." ".$grade." ".$storage." | ";

                $variation = Variation_model::firstOrNew(['product_id' => $product, 'grade' => $grade, 'storage' => $storage]);
                $stock = Stock_model::firstOrNew(['imei' => $i, 'serial_number' => $s]);
                if($stock->id != null && $stock->status == 1){
                    if(isset($storages[$gb])){$st = $storages[$gb];}else{$st = null;}
                    $issue[$dr]['data']['row'] = $dr;
                    $issue[$dr]['data']['name'] = $n;
                    $issue[$dr]['data']['storage'] = $st;
                    $issue[$dr]['data']['imei'] = $i.$s;
                    $issue[$dr]['data']['cost'] = $c;
                    if($stock->order_id == $order->id){
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
    private function insert_purchase_item($products, $storages, $order, $n, $c, $i, $s, $g = null, $dr = null){

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
                    $issue['message'] = 'IMEI Repurchase';
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
    public function add_purchase_item($order_id, $imei = null, $variation_id = null, $price = null){
        $issue = [];
        if(request('imei')){
            $imei = request('imei');
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
        $variation->stock += 1;
        $variation->status = 1;
        $variation->save();

        $stock = Stock_model::firstOrNew(['imei' => $i, 'serial_number' => $s]);
        if($stock->id){
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
        if(request('imei') != null){
            return redirect()->back();
        }else{
            return $issue;
        }

    }
    public function remove_issues(){
        // dd(request('ids'));
        $ids = request('ids');
        $issues = Order_issue_model::whereIn('id',$ids)->get();
        if(request('remove_entries') == 1){
            $issues->delete();
        }
        if(request('insert_variation') == 1){
            $variation = request('variation');
            foreach($issues as $issue){
                $data = json_decode($issue->data);
                // echo $variation." ".$data->imei." ".$data->cost;

                if($this->add_purchase_item($issue->order_id, $data->imei, $variation, $data->cost) == 1){
                    $issue->delete();
                }

            }
        }
        return redirect()->back();

    }
    public function export_invoice($orderId)
    {

        // Find the order
        $order = Order_model::with('customer', 'order_items')->find($orderId);

        // Generate PDF for the invoice content
        $data = [
            'order' => $order,
            'customer' => $order->customer,
            'orderItems' => $order->order_items,
        ];

        // Create a new TCPDF instance
        $pdf = new TCPDF();

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetTitle('Invoice');
        // $pdf->SetHeaderData('', 0, 'Invoice', '');

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('times', '', 12);

        // Additional content from your view
        $html = view('export.invoice', $data)->render();
        $pdf->writeHTML($html, true, false, true, false, '');

        // dd($pdfContent);
        // Send the invoice via email
        Mail::to($order->customer->email)->send(new InvoiceMail($data));

        // Optionally, save the PDF locally
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
                // if (ctype_digit($imei)) {

                //     $stock[$i] = Stock_model::where('imei',trim($imei))->first();
                    if(!$stock[$i]){
                        session()->put('error', "Stock not Found");
                        return redirect()->back();

                    }
                    if($stock[$i]->status != 1){
                        session()->put('error', "Stock Already Sold");
                        return redirect()->back();
                    }
                    if($stock[$i]->order->status == 2){
                        session()->put('error', "Stock List Awaiting Approval");
                        return redirect()->back();
                    }
                    // if($stock[$i]){
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
                        if(($stock[$i]->variation->product_id == $variant->product_id) || ($variant->product_id == 144 && $stock[$i]->variation->product_id == 229)){
                        }else{
                            session()->put('error', "Product Model not matched");
                            return redirect()->back();
                        }
                        if(($stock[$i]->variation->storage == $variant->storage) || ($variant->storage == 5 && $stock[$i]->variation->storage == 0 && $variant->product->brand == 2) || ($variant->product_id == 78 && $variant->storage == 4 && $stock[$i]->variation->storage == 5)){
                        }else{
                            session()->put('error', "Product Storage not matched");
                            return redirect()->back();
                        }
                        echo "<script>
                        if (confirm('System Model: " . $stock[$i]->variation->product->model . " - " . $storage . $color . $stock[$i]->variation->grade_id->name . "\\nRequired Model: " . $variant->product->model . " - " . $storage2 . $color2 . $variant->grade_id->name . "')) {
                            // User clicked OK, do nothing or perform any other action
                        } else {
                            // User clicked Cancel, redirect to the previous page
                            window.history.back();
                        }
                        </script>";
                    // }
                    // $serial = false;
                    // dd($stock[$i]);
                // }else{

                //     $stock[$i] = Stock_model::where('serial_number',trim($imei))->first();
                //     if(!$stock[$i]){
                //         session()->put('error', "Stock not Found");
                //         return redirect()->back();
                //     }
                //     if($stock[$i]->status != 1){
                //         session()->put('error', "Stock Already Sold");
                //         return redirect()->back();
                //     }
                //     if($stock[$i]->order->status == 2){
                //         session()->put('error', "Stock List Awaiting Approval");
                //         return redirect()->back();
                //     }
                //     if($stock[$i]){
                //         if($stock[$i]->variation->storage != null){
                //             $storage = $stock[$i]->variation->storage_id->name . " - ";
                //         }else{
                //             $storage = null;
                //         }
                //         if($stock[$i]->variation->color != null){
                //             $color = $stock[$i]->variation->color_id->name . " - ";
                //         }else{
                //             $color = null;
                //         }
                //         echo "<script>
                //         if (confirm('System Model: " . $stock[$i]->variation->product->model . " - " . $storage . $color . $stock[$i]->variation->grade_id->name . "\\nRequired Model: " . $variant->product->model . " - " . $storage2 . $color2 . $variant->grade_id->name . "')) {
                //             // User clicked OK, do nothing or perform any other action
                //         } else {
                //             // User clicked Cancel, redirect to the previous page
                //             window.history.back();
                //         }
                //         </script>";
                //     }
                //     $serial = true;
                // }
                // if(!$stock[$i]){
                //     if($serial == false){
                //         $add_imei = trim($imei);
                //         $add_serial = null;
                //     }else{
                //         $add_imei = null;
                //         $add_serial = trim($imei);
                //     }
                //     $stock[$i] = Stock_model::firstOrNew(['imei'=>$add_imei,'serial_number'=>$add_serial]);
                //     $stock[$i]->variation_id = $variant->id;
                // }
                // if($stock[$i]){
                    $stock[$i]->tester = $tester[$i];
                    $stock[$i]->status = 2;
                    $stock[$i]->save();
                // }

                // $orderObj = $bm->getOneOrder($order->reference_id);
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
            print_r($detail);

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
                    }else{
                        $new_item = $item;
                    }
                    $new_item->price = $item->price/count($each);
                    if($stock[$idt]){
                    $new_item->stock_id = $stock[$idt]->id;
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
    public function correction(){
        $item = Order_item_model::find(request('correction')['item_id']);
        if($item->order->processed_at > Carbon::now()->subHour(2) || session('user_id') == 1){

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
            $stock->variation_id = $item->variation_id;
            $stock->tester = request('correction')['tester'];
            $stock->added_by = session('user_id');
            if($stock->status == 1){
                $stock->status = 2;
            }
            $stock->save();

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

    public function sales_return($id)
    {
        $item = Order_item_model::find($id);
        $order = Order_model::find($item->order_id);

        $return = new Order_model();
        $return->reference_id = $order->reference_id;
        $return->customer_id = $order->customer_id;
        $return->order_type_id = 4;
        $return->currency = $order->currency;
        $return->price = $order->price;
        $return->delivery_note_url = request('message');
        $return->status = request('status');
        $return->save();

        $order->update(['reference_id'=>null]);

        $return_item = new Order_item_model();
        $return_item->order_id = $return->id;
        $return_item->reference_id = $item->reference_id;
        $return_item->variation_id = $item->variation_id;
        $return_item->stock_id = $item->stock_id;
        $return_item->quantity = 1;
        $return_item->price = $item->price;
        $return_item->status = request('status');
        $return_item->linked_id = $item->id;
        $return_item->save();

        $item->update(['reference_id'=>null]);

        if($item->stock->status == 2){
            $item->stock->update(['status'=>3]);
        }elseif($item->stock->status == null){
            $item->stock->delete();
        }else{}


    }

    public function recheck($order_id, $refresh = false, $invoice = false, $tester = null){

        $bm = new BackMarketAPIController();

        $order_model = new Order_model();
        $order_item_model = new Order_item_model();
        $currency_codes = Currency_model::pluck('id','code');
        $country_codes = Country_model::pluck('id','code');

        $orderObj = $bm->getOneOrder($order_id);

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
    public function updateBMOrder($order_id, $invoice = false, $tester = null, $data = false){
        $bm = new BackMarketAPIController();

        $order_model = new Order_model();
        $order_item_model = new Order_item_model();
        $currency_codes = Currency_model::pluck('id','code');
        $country_codes = Country_model::pluck('id','code');

        $orderObj = $bm->getOneOrder($order_id);
        if($orderObj->delivery_note == null){
            $orderObj = $bm->getOneOrder($order_id);
        }

        $order_model->updateOrderInDB($orderObj, $invoice, $bm, $currency_codes, $country_codes);

        $order_item_model->updateOrderItemsInDB($orderObj, $tester, $bm);
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
