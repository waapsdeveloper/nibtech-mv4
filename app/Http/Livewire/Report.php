<?php

namespace App\Http\Livewire;

use App\Exports\OrderReportExport;
use App\Http\Controllers\GoogleController;
use App\Models\Admin_model;
use App\Models\Category_model;
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Products_model;
use App\Models\Color_model;
use App\Models\Storage_model;
use App\Models\Grade_model;
use App\Models\Process_stock_model;
use App\Models\Variation_model;
use App\Models\Stock_model;
use App\Models\Stock_operations_model;
use Symfony\Component\HttpFoundation\Request;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use TCPDF;

class Report extends Component
{
    public function mount()
    {

    }
    public function render(Request $request)
    {
        DB::statement("SET SESSION group_concat_max_len = 1000000;");


        $data['title_page'] = "Reports";
        // dd('Hello2');
        $user_id = session('user_id');

        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }
        $data['purchase_status'] = [2 => '(Pending)', 3 => ''];
        $data['categories'] = Category_model::pluck('name','id');
        $data['products'] = Products_model::orderBy('model','asc')->get();
        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $data['variations'] = Variation_model::where('product_id',null)
        ->orderBy('name','desc')
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));

        $start_date = Carbon::now()->startOfMonth();
        // $start_date = date('Y-m-d 00:00:00',);
        $end_date = date('Y-m-d 23:59:59');
        if (request('start_date') != NULL && request('end_date') != NULL) {
            $start_date = request('start_date') . " 00:00:00";
            $end_date = request('end_date') . " 23:59:59";
        }


        $aggregates = DB::table('category')
            ->join('products', 'category.id', '=', 'products.category')
            ->join('variation', 'products.id', '=', 'variation.product_id')
            ->join('order_items', 'variation.id', '=', 'order_items.variation_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
            ->leftJoin('process_stock', 'stock.id', '=', 'process_stock.stock_id')
            ->leftJoin('process', 'process_stock.process_id', '=', 'process.id')
            ->select(
                'category.id as category_id',
                DB::raw('COUNT(orders.id) as orders_qty'),
                DB::raw('SUM(CASE WHEN orders.status = 3 THEN 1 ELSE 0 END) as approved_orders_qty'),
                DB::raw('SUM(CASE WHEN orders.currency = 4 THEN order_items.price ELSE 0 END) as eur_items_sum'),
                DB::raw('SUM(CASE WHEN orders.currency = 4 AND orders.status = 3 THEN order_items.price ELSE 0 END) as eur_approved_items_sum'),
                DB::raw('SUM(CASE WHEN orders.currency = 5 THEN order_items.price ELSE 0 END) as gbp_items_sum'),
                DB::raw('SUM(CASE WHEN orders.currency = 5 AND orders.status = 3 THEN order_items.price ELSE 0 END) as gbp_approved_items_sum'),
                DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
                // DB::raw('SUM(CASE WHEN orders.currency = 5 AND orders.status = 3 THEN order_items.price ELSE 0 END) as gbp_approved_items_sum'),
                // DB::raw('SUM(purchase_items.price) as items_cost_sum'),
                DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
            )
            ->whereBetween('orders.processed_at', [$start_date, $end_date])
            ->where('orders.order_type_id', 3)
            ->Where('orders.deleted_at',null)
            ->Where('order_items.deleted_at',null)
            ->Where('stock.deleted_at',null)
            ->Where('process_stock.deleted_at',null)
            ->whereIn('orders.status', [3,6])
            ->whereIn('order_items.status', [3,6])
            ->groupBy('category.id')
            ->get();
        // $costs = Category_model::select(
        //     'category.'
        // )
        $aggregated_cost = [];
        foreach ($aggregates as $agg) {

            if (empty($agg->stock_ids)) {
                $aggregated_cost[$agg->category_id] = 0;
                continue;
            }
            $aggregated_cost[$agg->category_id] = Order_item_model::whereIn('stock_id',explode(',',$agg->stock_ids))->whereHas('order', function ($q) {
                $q->where('order_type_id',1);
            })->sum('price');
        }

        $data['aggregated_sales'] = $aggregates;
        $data['aggregated_sales_cost'] = $aggregated_cost;

        $aggregate_returns = DB::table('category')
            ->join('products', 'category.id', '=', 'products.category')
            ->join('variation', 'products.id', '=', 'variation.product_id')
            ->join('order_items', 'variation.id', '=', 'order_items.variation_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
            ->leftJoin('process_stock', 'stock.id', '=', 'process_stock.stock_id')
            ->leftJoin('process', 'process_stock.process_id', '=', 'process.id')
            ->select(
                'category.id as category_id',
                DB::raw('COUNT(orders.id) as orders_qty'),
                DB::raw('SUM(CASE WHEN orders.status = 3 THEN 1 ELSE 0 END) as approved_orders_qty'),
                DB::raw('SUM(CASE WHEN orders.currency = 4 THEN order_items.price ELSE 0 END) as eur_items_sum'),
                DB::raw('SUM(CASE WHEN orders.currency = 4 AND orders.status = 3 THEN order_items.price ELSE 0 END) as eur_approved_items_sum'),
                DB::raw('SUM(CASE WHEN orders.currency = 5 THEN order_items.price ELSE 0 END) as gbp_items_sum'),
                DB::raw('SUM(CASE WHEN orders.currency = 5 AND orders.status = 3 THEN order_items.price ELSE 0 END) as gbp_approved_items_sum'),
                DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
                // DB::raw('SUM(CASE WHEN orders.currency = 5 AND orders.status = 3 THEN order_items.price ELSE 0 END) as gbp_approved_items_sum'),
                // DB::raw('SUM(purchase_items.price) as items_cost_sum'),
                DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
            )
            ->whereBetween('orders.created_at', [$start_date, $end_date])
            ->where('orders.order_type_id', 4)
            ->Where('orders.deleted_at',null)
            ->Where('order_items.deleted_at',null)
            ->Where('stock.deleted_at',null)
            ->Where('process_stock.deleted_at',null)
            // ->whereIn('orders.status', [3,6])
            ->groupBy('category.id')
            ->get();
        // $costs = Category_model::select(
        //     'category.'
        // )
        $aggregated_return_cost = [];
        foreach ($aggregate_returns as $agg) {

            if (empty($agg->stock_ids)) {
                $aggregated_return_cost[$agg->category_id] = 0;
                continue;
            }
            $aggregated_return_cost[$agg->category_id] = Order_item_model::whereIn('stock_id',explode(',',$agg->stock_ids))->whereHas('order', function ($q) {
                $q->where('order_type_id',1);
            })->sum('price');
        }

        $data['aggregated_returns'] = $aggregate_returns;
        $data['aggregated_return_cost'] = $aggregated_return_cost;


        $order = [];
        $dates = [];
        $k = 0;
        $today = date('d');
        for ($i = 5; $i >= 0; $i--) {
            $j = $i+1;
            $k++;
            $start = date('Y-m-26 00:00:00', strtotime("-".$j." months"));
            $end = date('Y-m-5 23:59:59', strtotime("-".$i." months"));
            $orders = Order_model::where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->count();
            $euro = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',4);
            })->sum('price');
            $pound = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',5);
            })->sum('price');
            $order[$k] = $orders;
            $eur[$k] = $euro;
            $gbp[$k] = $pound;
            $dates[$k] = date('26 M', strtotime("-".$j." months")) . " - " . date('05 M', strtotime("-".$i." months"));
            if($i == 0 && $today < 6){
                continue;
            }
            $k++;
            $start = date('Y-m-6 00:00:00', strtotime("-".$i." months"));
            $end = date('Y-m-15 23:59:59', strtotime("-".$i." months"));
            $orders = Order_model::where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->count();
            $euro = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',4);
            })->sum('price');
            $pound = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',5);
            })->sum('price');
            $order[$k] = $orders;
            $eur[$k] = $euro;
            $gbp[$k] = $pound;
            $dates[$k] = date('06 M', strtotime("-".$i." months")) . " - " . date('15 M', strtotime("-".$i." months"));
            if($i == 0 && $today < 16){
                continue;
            }
            $k++;
            $start = date('Y-m-16 00:00:00', strtotime("-".$i." months"));
            $end = date('Y-m-25 23:59:59', strtotime("-".$i." months"));
            $orders = Order_model::where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->count();
            $euro = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',4);
            })->sum('price');
            $pound = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',5);
            })->sum('price');
            $order[$k] = $orders;
            $eur[$k] = $euro;
            $gbp[$k] = $pound;
            $dates[$k] = date('16 M', strtotime("-".$i." months")) . " - " . date('25 M', strtotime("-".$i." months"));

        }
        echo '<script> sessionStorage.setItem("total2", "' . implode(',', $order) . '");</script>';
        echo '<script> sessionStorage.setItem("approved2", "' . implode(',', $eur) . '");</script>';
        echo '<script> sessionStorage.setItem("failed2", "' . implode(',', $gbp) . '");</script>';
        echo '<script> sessionStorage.setItem("dates2", "' . implode(',', $dates) . '");</script>';


        $data['pending_orders_count'] = Order_model::where('order_type_id',3)->where('status',2)->count();

        $data['start_date'] = date('Y-m-d', strtotime($start_date));
        $data['end_date'] = date("Y-m-d", strtotime($end_date));
        return view('livewire.report')->with($data);
    }

    public function export_report()
    {

        // Find the order
        // $order = Order_model::with('customer', 'order_items')->find($order_id);

        // $order_items = Order_item_model::
        //     join('variation', 'order_items.variation_id', '=', 'variation.id')
        //     ->join('products', 'variation.product_id', '=', 'products.id')
        //     ->select(
        //         // 'variation.id as variation_id',
        //         'products.model',
        //         // 'variation.color',
        //         'variation.storage',
        //         // 'variation.grade',
        //         DB::raw('AVG(order_items.price) as average_price'),
        //         DB::raw('SUM(order_items.quantity) as total_quantity'),
        //         DB::raw('SUM(order_items.price) as total_price')
        //     )
        //     ->where('order_items.order_id',$order_id)
        //     ->groupBy('products.model', 'variation.storage')
        //     ->orderBy('products.model', 'ASC')
        //     ->get();

            // dd($order);
        // Generate PDF for the invoice content
        // $data = [
        //     'order' => $order,
        //     'customer' => $order->customer,
        //     'order_items' =>$order_items,
        //     'invoice' => $invoice
        // ];
        $data['storages'] = Storage_model::pluck('name','id');
        // $data['grades'] = Grade_model::pluck('name','id');
        // $data['colors'] = Color_model::pluck('name','id');

        // Create a new TCPDF instance
        $pdf = new TCPDF();

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        // $pdf->SetTitle('Invoice');
        // $pdf->SetHeaderData('', 0, 'Invoice', '');

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('times', '', 12);

        // Additional content from your view
        if(request('packlist') == 1){

            $html = view('export.bulksale_packlist', $data)->render();
        }elseif(request('packlist') == 2){

            return Excel::download(new OrderReportExport, 'Report.xlsx');
        }else{
            $html = view('export.bulksale_invoice', $data)->render();
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
}
