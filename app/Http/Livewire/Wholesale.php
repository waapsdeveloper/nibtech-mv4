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
use App\Models\Grade_model;
use App\Models\Product_model;
use App\Models\Storage_model;
    use GuzzleHttp\Psr7\Request;
    use Carbon\Carbon;
    use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use TCPDF;

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
        // dd($item_id);
        $orderItem = Order_item_model::find($item_id);

        // Access the variation through orderItem->stock->variation
        $variation = $orderItem->stock->variation;

        $variation->stock += 1;
        $variation->save();

        // No variation record found or product_id and sku are both null, delete the order item

        // $orderItem->stock->delete();
        Stock_model::find($orderItem->stock_id)->update(['status'=>1]);
        $orderItem->delete();
        // $orderItem->forceDelete();


        return redirect()->back();
    }
    public function wholesale_detail($order_id){

        // $data['imeis'] = Stock_model::whereIn('status',[1,3])->orderBy('serial_number','asc')->orderBy('imei','asc')->get();
        $data['storages'] = Storage_model::pluck('name','id');
        $data['products'] = Products_model::pluck('model','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $data['colors'] = Color_model::pluck('name','id');
        $variations = Variation_model::with([
            'stocks' => function ($query) use ($order_id) {
                $query->whereHas('order_item', function ($query) use ($order_id) {
                    $query->where('order_id', $order_id);
                });
            },
            'stocks.order_item'
        ])
        ->whereHas('stocks', function ($query) use ($order_id) {
            $query->whereHas('order_item', function ($query) use ($order_id) {
                $query->where('order_id', $order_id);
            });
        })
        ->orderBy('grade', 'desc')
        ->get();

        // Remove variations with no associated stocks
        $variations = $variations->filter(function ($variation) {
            return $variation->stocks->isNotEmpty();
        });


        $data['variations'] = $variations;
        $last_ten = Order_item_model::where('order_id',$order_id)->orderBy('id','desc')->limit(10)->get();
        $data['last_ten'] = $last_ten;
        $data['all_variations'] = Variation_model::where('grade',9)->get();
        $data['order'] = Order_model::find($order_id);
        $data['order_id'] = $order_id;
        $data['currency'] = $data['order']->currency_id->sign;


        // echo "<pre>";
        // // print_r($items->stocks);
        // print_r($items);

        // echo "</pre>";
        // dd($data['variations']);
        return view('livewire.wholesale_detail')->with($data);

    }

    public function update_prices(){
        print_r(request('item_ids'));
        echo request('unit_price');

        if(request('unit_price') > 0){
            foreach(request('item_ids') as $item_id){
                Order_item_model::find($item_id)->update(['price'=>request('unit_price')]);
            }
        }

        return redirect()->back();
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
        if(request('imei') == '' || !$stock){
            session()->put('error', 'IMEI Invalid / Not Found');
            return redirect()->back();

        }
        $variation = Variation_model::where(['id' => $stock->variation_id])->first();
        if($stock->status != 1){
            session()->put('error', 'Stock already sold');
            return redirect()->back();
        }
        if(request('bypass_check') == 1){
            session('bypass_check', 1);
        }else{
            session()->forget('bypass_check');
            if($variation->grade != 11){

                // dd('hello');
                echo "<script>
                if (confirm('This IMEI does not belong to wholesale, are you sure you want to continue?')) {
                    // User clicked OK, do nothing or perform any other action
                } else {
                    // User clicked Cancel, redirect to the previous page
                    window.history.back();
                }
                </script>";
            }
        }
        $stock->status = 2;
        $stock->save();

        $variation->stock -= 1;
        $variation->save();


        $order_item = new Order_item_model();
        $order_item->order_id = $order_id;
        $order_item->variation_id = $variation->id;
        $order_item->stock_id = $stock->id;
        $order_item->quantity = 1;
        $order_item->price = $stock->purchase_item->price;
        $order_item->status = 3;
        $order_item->save();

        session()->put('success', 'Stock added successfully');


        echo "<script>

            window.history.back();

        </script>";
        // Delete the temporary file
        // Storage::delete($filePath);

        // return redirect()->back();
    }


    public function export_bulksale_invoice($order_id)
    {

        // Find the order
        // $order = Order_model::with('customer', 'order_items')->find($order_id);

        $data = DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('variation', 'order_items.variation_id', '=', 'variation.id')
            ->join('products', 'variation.product_id', '=', 'products.id')
            ->join('color', 'variation.color', '=', 'color.id')
            ->join('storage', 'variation.storage', '=', 'storage.id')
            ->join('grade', 'variation.grade', '=', 'grade.id')
            ->select(
                'variation.sku',
                'products.model',
                'color.name as color',
                'storage.name as storage',
                'grade.name as grade_name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('AVG(order_items.price) as average_price'),
                DB::raw('SUM(order_items.quantity) as total_quantity')
            )
            ->where('orders.id',$order_id)
            ->groupBy('variation.sku', 'products.model', 'color.name', 'storage.name', 'grade.name')
            ->orderBy('products.model', 'ASC')
            ->get();
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
        $html = view('export.bulksale_invoice', $data)->render();
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
}
