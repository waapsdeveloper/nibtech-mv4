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
use App\Exports\RepairsheetExport;
use Maatwebsite\Excel\Facades\Excel;
    use TCPDF;
    use App\Mail\InvoiceMail;
use App\Models\Color_model;
use App\Models\Grade_model;
use App\Models\Order_issue_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;
use App\Models\Stock_operations_model;
use Illuminate\Support\Facades\DB;
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

        $data['latest_reference'] = Process_model::where('process_type_id',9)->orderBy('reference_id','DESC')->first()->reference_id;
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
        ->when(request('status'), function ($q) {
            return $q->where('status', request('status'));
        })
        ->orderBy('reference_id', 'desc') // Secondary order by reference_id
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));


        // dd($data['orders']);
        return view('livewire.repair')->with($data);
    }
    public function repair_ship($repair_id){
        $repair = Process_model::find($repair_id);
        $repair->description = request('tracking_number');
        $repair->status = 2;
        $repair->save();

        return redirect()->back();
    }
    public function delete_repair($process_id){


        $items = Process_stock_model::where('process_id',$process_id)->get();
        foreach($items as $orderItem){
            if($orderItem->stock){
                // Access the variation through orderItem->stock->variation
                $variation = $orderItem->stock->variation;

                $variation->stock += 1;
                Stock_model::find($orderItem->stock_id)->update([
                    'status' => 1
                ]);
            }
            $orderItem->delete();
        }
        Process_model::where('id',$process_id)->delete();
        Process_stock_model::where('process_id',$process_id)->delete();
        session()->put('success', 'Order deleted successfully');
        return redirect()->back();

    }
    public function delete_repair_item($process_stock_id){

        $process_stock = Process_stock_model::find($process_stock_id);

        // Access the variation through process_stock->stock->variation
        $variation = $process_stock->stock->variation;

        $process_stock->stock->status = 1;
        $process_stock->stock->save();

        $variation->stock += 1;
        $variation->save();

        // No variation record found or product_id and sku are both null, delete the order item

        // $process_stock->stock->delete();
        $process_stock->delete();
        // $orderItem->forceDelete();

        session()->put('success', 'Stock deleted successfully');

        return redirect()->back();

    }
    public function repair_detail($process_id){

        // $data['imeis'] = Stock_model::whereIn('status',[1,3])->orderBy('serial_number','asc')->orderBy('imei','asc')->get();
        $data['storages'] = Storage_model::pluck('name','id');
        $data['products'] = Products_model::pluck('model','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $data['colors'] = Color_model::pluck('name','id');
        $variations = Variation_model::with([
            'stocks' => function ($query) use ($process_id) {
                $query->whereHas('process_stocks', function ($query) use ($process_id) {
                    $query->where(['process_id'=> $process_id,'status'=>1]);
                });
            },
            'stocks.process_stocks'
        ])
        ->whereHas('stocks', function ($query) use ($process_id) {
            $query->whereHas('process_stocks', function ($query) use ($process_id) {
                $query->where(['process_id'=> $process_id,'status'=>1]);
            });
        })
        ->orderBy('grade', 'desc')
        ->get();

        // Remove variations with no associated stocks
        $variations = $variations->filter(function ($variation) {
            return $variation->stocks->isNotEmpty();
        });

        // $order_issues = Order_issue_model::where('order_id',$process_id)->select(
        //     DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.name")) AS name'),
        //     'message',
        //     DB::raw('COUNT(*) as count'),
        //     DB::raw('GROUP_CONCAT(JSON_OBJECT("id", id, "order_id", order_id, "data", data, "message", message, "created_at", created_at, "updated_at", updated_at)) AS all_rows')
        // )
        // ->groupBy('name', 'message')
        // ->get();
        // // dd($order_issues);

        // $data['order_issues'] = $order_issues;
        $data['variations'] = $variations;
        $last_ten = Process_stock_model::where('process_id',$process_id)->orderBy('id','desc')->limit(10)->get();
        $data['last_ten'] = $last_ten;
        $processed_stocks = Process_stock_model::where(['process_id'=>$process_id,'status'=>2])->get();
        $data['processed_stocks'] = $processed_stocks;

        $data['all_variations'] = Variation_model::where('grade',9)->get();
        $data['process'] = Order_model::find($process_id);
        $data['currency'] = $data['process']->currency_id->sign;

        // $data['imei'] = request('imei');

        // $data['grades'] = Grade_model::all();
        $data['process'] = Process_model::find($process_id);

        $data['process_id'] = $process_id;


        return view('livewire.repair_detail')->with($data);

    }
    public function add_repair(){

        $repair = (object) request('repair');
        $error = "";

        $process = Process_model::firstOrNew(['reference_id' => $repair->reference_id, 'process_type_id' => 5 ]);
        $process->customer_id = $repair->repairer;
        $process->status = 1;
        $process->currency = 4;
        $process->process_type_id = 9;
        $process->save();


        return redirect(url('repair/detail').'/'.$process->id);
    }

    public function receive_repair_item($process_id, $imei = null, $back = null){

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
        $process_stock = Process_stock_model::where(['process_id'=>$process_id,'stock_id'=>$stock->id])->first();
        if(!$process_stock){
            session()->put('error', "Stock not found in this sheet");
            if($back != 1){
                return redirect()->back();
            }else{
                return 1;
            }
        }
        if($process_stock->status == 2){
            session()->put('error', "Stock already added");
            if($back != 1){
                return redirect()->back();
            }else{
                return 1;
            }
        }
        $process_stock->status = 2;
        $process_stock->save();


        $product_id = $stock->variation->product_id;
        $storage = $stock->variation->storage;
        $color = $stock->variation->color;
        $grade = 9;

        $new_variation = Variation_model::firstOrNew([
            'product_id' => $product_id,
            'storage' => $storage,
            'color' => $color,
            'grade' => $grade,
        ]);
        $new_variation->save();
        $stock_operation = Stock_operations_model::create([
            'stock_id' => $stock->id,
            'old_variation_id' => $stock->variation_id,
            'new_variation_id' => $new_variation->id,
            'description' => "Repaired Externally",
            'admin_id' => session('user_id'),
        ]);
        $stock->variation_id = $new_variation->id;

        $stock->status = 1;
        $stock->save();


        if($back != 1){
            return redirect(url('repair/detail').'/'.$process_id);
        }else{
            return 1;
        }
    }
    public function check_repair_item($process_id, $imei = null, $back = null){
        $issue = [];
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

        if($stock->status != 1){
            session()->put('error', "Stock Already Sold");
            if($back != 1){
                return redirect()->back();
            }else{
                return 1;
            }
        }
        if($stock->order->status == 2){
            session()->put('error', "Stock List Awaiting Approval");
            if($back != 1){
                return redirect()->back();
            }else{
                return 1;
            }
        }
        if($stock->status != 1){
            session()->put('error', 'Stock already sold');
            if($back != 1){
                return redirect()->back();
            }else{
                return 1;
            }
        }

        // if($stock->variation->grade != 8){
        //     session()->put('error', 'Stock not in Repair');
        //     if($back != 1){
        //         return redirect()->back();
        //     }else{
        //         return 1;
        //     }
        // }

        // if(request('bypass_check') == 1){

            $this->add_repair_item($process_id, $imei, $back);
            session()->put('bypass_check', 1);
            request()->merge(['bypass_check'=> 1]);
            if($back != 1){
                return redirect()->back();
            }else{
                return 1;
            }
        // }else{
        //     session()->forget('bypass_check');
        //     // request()->merge(['bypass_check' => null]);
        //     if($stock->variation->grade != 8){
        //         echo "<p>This IMEI does not belong to Repair. Do you want to continue?</p>";
        //         echo "<form id='continueForm' action='" . url('add_repair_item') . "/" . $process_id . "' method='POST'>";
        //         echo "<input type='hidden' name='_token' value='" . csrf_token() . "'>";
        //         echo "<input type='hidden' name='process_id' value='" . $process_id . "'>";
        //         echo "<input type='hidden' name='imei' value='" . $imei . "'>";
        //         echo "</form>";
        //         echo "<a href='javascript:history.back()'>Cancel</a> ";
        //         echo "<button onclick='submitForm()'>Continue</button>";
        //         echo "<script>
        //             function submitForm() {
        //                 document.getElementById('continueForm').submit();
        //             }
        //         </script>";
        //         exit;
        //     }else{

        //         $this->add_repair_item($process_id, $imei, $back);
        //         if($back != 1){
        //             return redirect()->back();
        //         }else{
        //             return 1;
        //         }
        //     }
        // }

    }
    public function add_repair_item($process_id, $imei = null, $back = null){
        if(request('imei')){
            $imei = request('imei');
        }
        if(request('variation')){
            $variation_id = request('variation');
        }
        if(!request('bypass_check')){
            session()->forget('bypass_check');
        }
        if(ctype_digit($imei)){
            $i = $imei;
            $s = null;
        }else{
            $i = null;
            $s = $imei;
        }

        $stock = Stock_model::where(['imei' => $i, 'serial_number' => $s])->first();

        $variation = Variation_model::where(['id' => $stock->variation_id])->first();

        $stock->status = 2;
        $stock->save();

        $variation->stock -= 1;
        $variation->save();

        $process_stock = new Process_stock_model();
        $process_stock->process_id = $process_id;
        $process_stock->stock_id = $stock->id;
        $process_stock->admin_id = session('user_id');
        $process_stock->status = 1;
        $process_stock->save();

        session()->put('success', 'Stock added successfully');


        // echo "<script>

        //     window.history.back();

        // </script>";
        // Delete the temporary file
        // Storage::delete($filePath);

        if($back != 1){
            return redirect(url('repair/detail').'/'.$process_id);
        }else{
            return 1;
        }
        // return redirect()->back();
    }
    public function add_repair_sheet($process_id){
        $issue = [];
        $storages = Storage_model::pluck('name','id')->toArray();

        $products = Products_model::pluck('model','id')->toArray();
        request()->validate([
            'sheet' => 'required|file|mimes:xlsx,xls',
        ]);

        // Store the uploaded file in a temporary location
        $filePath = request()->file('sheet')->store('temp');

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
        $imei = array_search('imei', $arrayLower);
        if(!$imei){
            print_r($dh);
            session()->put('error', "Heading not Found(imei)");
            return redirect()->back();
            // die;
        }
        // echo $name;
        // echo $imei;


        foreach($data as $dr => $d){
            // $name = ;
            // echo $dr." ";
            // print_r($d);
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

            if($i != null || $s != null){


                $stock = Stock_model::where(['imei' => $i, 'serial_number' => $s])->first();
                if($stock == null ){
                    $issue[$dr]['data']['row'] = $dr;
                    $issue[$dr]['data']['imei'] = $i.$s;
                    $issue[$dr]['message'] = 'Stock Not Found';

                    continue;


                }
                if($stock->order->status == 2){
                    $issue[$dr]['data']['row'] = $dr;
                    $issue[$dr]['data']['imei'] = $i.$s;
                    $issue[$dr]['message'] = 'Stock Awaiting Approval';

                    continue;
                }
                $variation = Variation_model::where(['id' => $stock->variation_id])->first();
                if($stock->id != null && $stock->status == 2){
                    $issue[$dr]['data']['row'] = $dr;
                    $issue[$dr]['data']['imei'] = $i.$s;
                    $issue[$dr]['message'] = 'Item Already Sold';


                }elseif($stock->id == null ){
                    $issue[$dr]['data']['row'] = $dr;
                    $issue[$dr]['data']['imei'] = $i.$s;
                    $issue[$dr]['message'] = 'Stock Not Found';



                }else{
                    $stock->status = 2;
                    $stock->save();

                    $variation->stock -= 1;
                    $variation->save();

                    $process_stock = Process_stock_model::firstOrNew(['process_id' =>$process_id, 'stock_id'=>$stock->id]);
                    $process_stock->admin_id = session('user_id');
                    $process_stock->status = 1;
                    $process_stock->save();



                }

            }else{
                    $issue[$dr]['data']['row'] = $dr;
                    $issue[$dr]['data']['imei'] = $i.$s;
                    $issue[$dr]['message'] = 'IMEI/Serial Not Found';

            }

        }


        if($issue != []){
            foreach($issue as $row => $datas){
                Order_issue_model::create([
                    'process_id' => $process_id,
                    'data' => json_encode($datas['data']),
                    'message' => $datas['message'],
                ]);
            }
        }

        return redirect()->back();
    }


    public function export_repair_invoice($process_id)
    {

        // Find the order
        $process = Process_model::with('customer', 'process_stocks')->find($process_id);

        $process_stock = Process_stock_model::
            join('stock', 'process_stock.stock_id', '=', 'stock.id')
            ->join('variation', 'stock.variation_id', '=', 'variation.id')
            ->join('products', 'variation.product_id', '=', 'products.id')
            ->select(
                'variation.id as variation_id',
                'products.model',
                'variation.color',
                'variation.storage',
                'variation.grade',
                DB::raw('AVG(process_stock.price) as average_price'),
                DB::raw('COUNT(process_stock.id) as total_quantity'),
                DB::raw('SUM(process_stock.price) as total_price')
            )
            ->where('process_stock.process_id',$process_id)
            ->groupBy('variation.id','products.model', 'variation.color', 'variation.storage', 'variation.grade')
            ->orderBy('products.model', 'ASC')
            ->get();

            // dd($order);
        // Generate PDF for the invoice content
        $data = [
            'process' => $process,
            'customer' => $process->customer,
            'process_stock' =>$process_stock
        ];
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $data['colors'] = Color_model::pluck('name','id');

        // Create a new TCPDF instance
        $pdf = new TCPDF();

        // Set document inforepairtion
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetTitle('Invoice');
        // $pdf->SetHeaderData('', 0, 'Invoice', '');

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('times', '', 12);

        // Additional content from your view
        if(request('packlist') == 1){

            $html = view('export.repair_packlist', $data)->render();
        }elseif(request('packlist') == 2){

            return Excel::download(new RepairsheetExport, 'repairs_'.$process->reference_id.'.xlsx');
        }else{
            $html = view('export.repair_invoice', $data)->render();
        }

        $pdf->writeHTML($html, true, false, true, false, '');

        // dd($pdfContent);
        // Send the invoice via email
        // Mail::to($order->customer->email)->send(new InvoiceMail($data));

        // Optionally, save the PDF locally
        // file_put_contents('invoice.pdf', $pdfContent);

        // Get the PDF content
        $pdf->Output('', 'I');

        // $pdfContent = $pdf->Output('', 'S');
        // Return a response or redirect

        // Pass the PDF content to the view
        // return view('livewire.show_pdf')->with(['pdfContent'=> $pdfContent, 'delivery_note'=>$order->delivery_note_url]);
    }



    public function internal_repair(){

        $data['imei'] = request('imei');

        $data['grades'] = Grade_model::all();


        $repair_stocks = Stock_model::
        whereHas('variation', function ($query) {
            $query->where('grade', 8);
        })
        ->when(request('stock_status'), function ($q) {
            return $q->where('status', request('stock_status'));
        })
        ->orderBy('updated_at','desc')->get();
        $data['repair_stocks'] = $repair_stocks;

        $repaired_stocks = Stock_operations_model::where('created_at','>=',now()->forepairt('Y-m-d')." 00:00:00")->where('admin_id',session('user_id'))->orderBy('id','desc')->get();

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
    public function add_internal_repair_item(){
        $repair = request('repair');
        $description = $repair['description'];
        if($repair['grade']){
            session()->put('grade',$repair['grade']);
        }
        session()->put('description',$repair['description']);


        if ($repair['stock_id']) {
            $stock = Stock_model::find($repair['stock_id']);
            if (!$stock ||  $stock->variation->grade != 8) {
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
}
