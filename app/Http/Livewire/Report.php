<?php

namespace App\Http\Livewire;

use App\Exports\B2COrderReportExport;
use App\Exports\BatchInitialReportExport;
use App\Exports\BatchReportExport;
use App\Exports\OrderReportExport;
use App\Exports\ProjectedSalesExport;
use App\Models\Brand_model;
use App\Models\Category_model;
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Products_model;
use App\Models\Color_model;
use App\Models\Currency_model;
use App\Models\Customer_model;
use App\Models\Storage_model;
use App\Models\Grade_model;
use App\Models\Multi_type_model;
use App\Models\Process_stock_model;
use App\Models\Product_storage_sort_model;
use App\Models\Variation_model;
use App\Models\Stock_model;
use Symfony\Component\HttpFoundation\Request;
use Maatwebsite\Excel\Facades\Excel;
use TCPDF;

class Report extends Component
{
    public function mount()
    {

    }
    public function render(Request $request)
    {
        if(!session()->has('rep') && session('user_id') != 1){
            redirect('report/pass');
        }
        DB::statement("SET SESSION group_concat_max_len = 1500000;");


        $data['title_page'] = "Reports";
        session()->put('page_title', $data['title_page']);
        // dd('Hello2');
        $user_id = session('user_id');

        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 20;
        }
        $data['purchase_status'] = [2 => '(Pending)', 3 => ''];
        $data['purchase_orders'] = Order_model::where('order_type_id',1)->pluck('reference_id','id');
        $data['vendors'] = Customer_model::where('is_vendor',1)->pluck('first_name','id');
        $data['categories'] = Category_model::pluck('name','id');
        $data['brands'] = Brand_model::pluck('name','id');
        $data['products'] = Products_model::orderBy('model','asc')->get();
        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::pluck('name','id');

        $start_date = Carbon::now()->startOfMonth();
        // $start_date = date('Y-m-d 00:00:00',);
        $end_date = date('Y-m-d 23:59:59');
        if (request('start_date') != NULL && request('end_date') != NULL) {
            $start_date = request('start_date') . " 00:00:00";
            $end_date = request('end_date') . " 23:59:59";
        }

        $variation_ids = [];
        // if(request('data') == 1){

        $variation_ids = Variation_model::select('id')
            ->whereHas('product', function ($q) {
                $q->when(request('category') != '', function ($qu) {
                    return $qu->where('category', '=', request('category'));
                })
                ->when(request('brand') != '', function ($qu) {
                    return $qu->where('brand', '=', request('brand'));
                });
            })
            ->whereHas('stocks.order', function ($q) {
                $q->when(request('vendor') != '', function ($qu) {
                    return $qu->where('customer_id', '=', request('vendor'));
                })
                ->when(request('batch') != '', function ($qu) {
                    return $qu->where('reference_id', '=', request('batch'));
                });
            })
            ->when(request('product') != '', function ($q) {
                return $q->where('product_id', '=', request('product'));
            })
            ->when(request('storage') != '', function ($q) {
                return $q->where('storage', request('storage'));
            })
            ->when(request('color') != '', function ($q) {
                return $q->where('color', request('color'));
            })
            ->when(request('grade') != '', function ($q) {
                return $q->where('grade', request('grade'));
            })
            ->pluck('id')->toArray();


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
                DB::raw('SUM(orders.charges) as charges'),
                DB::raw('SUM(CASE WHEN orders.currency = 4 OR orders.order_type_id = 5 THEN order_items.price ELSE 0 END) as eur_items_sum'),
                DB::raw('SUM(CASE WHEN orders.currency = 5 AND orders.order_type_id = 3 THEN orders.price ELSE 0 END) as gbp_items_sum'),
                DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
                DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
            )
            ->where(function ($query) use ($start_date, $end_date) {
                $query->where(function ($subQuery) use ($start_date, $end_date) {
                    // Where order_type_id is 3, filter by processed_at
                    $subQuery->where('orders.order_type_id', 3)
                             ->whereBetween('orders.processed_at', [$start_date, $end_date]);
                })
                ->orWhere(function ($subQuery) use ($start_date, $end_date) {
                    // For other order_type_ids, filter by created_at
                    $subQuery->where('orders.order_type_id', '!=', 3)
                             ->whereBetween('order_items.created_at', [$start_date, $end_date]);
                });
            })
            // ->whereBetween('orders.processed_at', [$start_date, $end_date])
            ->whereIn('variation.id', $variation_ids)
            ->whereIn('orders.order_type_id', [2,3,5])
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
                DB::raw('SUM(CASE WHEN order_items.currency is null OR order_items.currency = 4 THEN order_items.price ELSE 0 END) as eur_items_sum'),
                DB::raw('SUM(CASE WHEN order_items.currency = 5 THEN order_items.price ELSE 0 END) as gbp_items_sum'),
                DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
                DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
            )
            ->whereBetween('order_items.created_at', [$start_date, $end_date])
            ->whereIn('variation.id', $variation_ids)
            ->whereIn('orders.order_type_id', [4,6])
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


        $data['batch_grade_reports'] = Stock_model::select('variation.grade as grade', 'orders.id as order_id', 'orders.reference_id as reference_id', 'orders.reference as reference', 'customer.first_name as vendor', DB::raw('COUNT(*) as quantity'))
        ->join('variation', 'stock.variation_id', '=', 'variation.id')
        ->join('orders', 'stock.order_id', '=', 'orders.id')
        ->join('customer', 'orders.customer_id', '=', 'customer.id')
        ->groupBy('variation.grade', 'orders.id', 'orders.reference_id', 'orders.reference', 'customer.first_name')
        ->orderByDesc('order_id')
        ->get();
        // ->paginate($per_page)
        // ->onEachSide(5)
        // ->appends(request()->except('page'));

        // dd($data['Vendor_grade_report']);





        $data['pending_orders_count'] = Order_model::where('order_type_id',3)->where('status',2)->count();

        $data['start_date'] = date('Y-m-d', strtotime($start_date));
        $data['end_date'] = date("Y-m-d", strtotime($end_date));
        return view('livewire.report')->with($data);
    }
    public function projected_sales(){
        $pdf = new ProjectedSalesExport();
        $pdf->generatePdf();
    }

    public function b2c_orders_report()
    {
        $purchase_order_ids = Order_model::where('order_type_id',1)->pluck('id')->toArray();

        $start_date = Carbon::now()->startOfMonth();
        $end_date = date('Y-m-d 23:59:59');

        if (request('start_date') != NULL) {
            $start_date = request('start_date') . " 00:00:00";
        }
        if (request('end_date') != NULL) {
            $end_date = request('end_date') . " 23:59:59";
        }

        // dd($start_date, $end_date);

        $data['start_date'] = date('Y-m-d', strtotime($start_date));
        $data['end_date'] = date("Y-m-d", strtotime($end_date));

        $orders_1 = Order_model::where('order_type_id',3)
            ->whereBetween('processed_at', [$start_date, $end_date])
            ->where('status', 1)
            ->get();

        $sales_count_1 = $orders_1->count();
        $sales_eur_1 = $orders_1->where('currency',4)->sum('price');
        $sales_gbp_1 = $orders_1->where('currency',5)->sum('price');
        $sales_charge_1 = $orders_1->sum('charges');
        $order_ids_1 = $orders_1->pluck('id')->toArray();

        $order_items_1 = Order_item_model::whereIn('order_id',$order_ids_1)->get();
        $items_count_1 = $order_items_1->count();


        $data['sales_count_1'] = $sales_count_1;
        $data['sales_eur_1'] = $sales_eur_1;
        $data['sales_gbp_1'] = $sales_gbp_1;
        $data['sales_charge_1'] = $sales_charge_1;
        $data['items_count_1'] = $items_count_1;

        $orders_2 = Order_model::where('order_type_id',3)
            ->whereBetween('processed_at', [$start_date, $end_date])
            ->where('status', 2)
            ->get();

        $sales_count_2 = $orders_2->count();
        $sales_eur_2 = $orders_2->where('currency',4)->sum('price');
        $sales_gbp_2 = $orders_2->where('currency',5)->sum('price');
        $sales_charge_2 = $orders_2->sum('charges');
        $order_ids_2 = $orders_2->pluck('id')->toArray();

        $order_items_2 = Order_item_model::whereIn('order_id',$order_ids_2)->get();
        $items_count_2 = $order_items_2->count();


        $data['sales_count_2'] = $sales_count_2;
        $data['sales_eur_2'] = $sales_eur_2;
        $data['sales_gbp_2'] = $sales_gbp_2;
        $data['sales_charge_2'] = $sales_charge_2;
        $data['items_count_2'] = $items_count_2;

        $orders_3 = Order_model::where('order_type_id',3)
            ->whereBetween('processed_at', [$start_date, $end_date])
            ->where('status', 3)
            ->get();

        $sales_count_3 = $orders_3->count();
        $sales_eur_3 = $orders_3->where('currency',4)->sum('price');
        $sales_gbp_3 = $orders_3->where('currency',5)->sum('price');
        $sales_charge_3 = $orders_3->sum('charges');
        $order_ids_3 = $orders_3->pluck('id')->toArray();

        $order_items_3 = Order_item_model::whereIn('order_id',$order_ids_3)->get();
        $items_count_3 = $order_items_3->count();
        $stock_ids_3 = $order_items_3->pluck('stock_id')->toArray();
        $stock_count_3 = $order_items_3->pluck('stock_id')->unique()->count();
        $stock_duplicate_ids = $order_items_3->pluck('stock_id')->duplicates()->toArray();
        $stock_duplicate_ids = Stock_model::whereIn('id',$stock_duplicate_ids)->pluck('imei', 'id')->toArray();
        $purchase_items_3 = Order_item_model::whereIn('stock_id',$stock_ids_3)->whereIn('order_id',$purchase_order_ids)->get();
        $purchase_cost_3 = $purchase_items_3->sum('price');
        $purchase_count_3 = $purchase_items_3->count();


        $data['sales_count_3'] = $sales_count_3;
        $data['sales_eur_3'] = $sales_eur_3;
        $data['sales_gbp_3'] = $sales_gbp_3;
        $data['sales_charge_3'] = $sales_charge_3;
        $data['items_count_3'] = $items_count_3;
        $data['stock_count_3'] = $stock_count_3;
        $data['stock_duplicate_ids'] = $stock_duplicate_ids;
        $data['purchase_cost_3'] = $purchase_cost_3;
        $data['purchase_count_3'] = $purchase_count_3;

        $orders_4 = Order_model::where('order_type_id',3)
            ->whereBetween('processed_at', [$start_date, $end_date])
            ->where('status', 4)
            ->get();

        $sales_count_4 = $orders_4->count();
        $sales_eur_4 = $orders_4->where('currency',4)->sum('price');
        $sales_gbp_4 = $orders_4->where('currency',5)->sum('price');
        $sales_charge_4 = $orders_4->sum('charges');
        $order_ids_4 = $orders_4->pluck('id')->toArray();

        $order_items_4 = Order_item_model::whereIn('order_id',$order_ids_4)->get();
        $items_count_4 = $order_items_4->count();
        $stock_ids_4 = $order_items_4->pluck('stock_id')->toArray();
        $stock_count_4 = $order_items_4->pluck('stock_id')->unique()->count();
        $purchase_items_4 = Order_item_model::whereIn('stock_id',$stock_ids_4)->whereIn('order_id',$purchase_order_ids)->get();
        $purchase_cost_4 = $purchase_items_4->sum('price');
        $purchase_count_4 = $purchase_items_4->count();


        $data['sales_count_4'] = $sales_count_4;
        $data['sales_eur_4'] = $sales_eur_4;
        $data['sales_gbp_4'] = $sales_gbp_4;
        $data['sales_charge_4'] = $sales_charge_4;
        $data['items_count'] = $items_count_4;
        $data['stock_count_4'] = $stock_count_4;
        $data['purchase_cost_4'] = $purchase_cost_4;
        $data['purchase_count_4'] = $purchase_count_4;

        $orders_5 = Order_model::where('order_type_id',3)
            ->whereBetween('processed_at', [$start_date, $end_date])
            ->where('status', 5)
            ->get();

        $sales_count_5 = $orders_5->count();
        $sales_eur_5 = $orders_5->where('currency',4)->sum('price');
        $sales_gbp_5 = $orders_5->where('currency',5)->sum('price');
        $sales_charge_5 = $orders_5->sum('charges');
        $order_ids_5 = $orders_5->pluck('id')->toArray();

        $order_items_5 = Order_item_model::whereIn('order_id',$order_ids_5)->get();
        $items_count_5 = $order_items_5->count();
        $stock_ids_5 = $order_items_5->pluck('stock_id')->toArray();
        $stock_count_5 = $order_items_5->pluck('stock_id')->unique()->count();
        $purchase_items_5 = Order_item_model::whereIn('stock_id',$stock_ids_5)->whereIn('order_id',$purchase_order_ids)->get();
        $purchase_cost_5 = $purchase_items_5->sum('price');
        $purchase_count_5 = $purchase_items_5->count();


        $data['sales_count_5'] = $sales_count_5;
        $data['sales_eur_5'] = $sales_eur_5;
        $data['sales_gbp_5'] = $sales_gbp_5;
        $data['sales_charge_5'] = $sales_charge_5;
        $data['items_count_5'] = $items_count_5;
        $data['stock_count_5'] = $stock_count_5;
        $data['purchase_cost_5'] = $purchase_cost_5;
        $data['purchase_count_5'] = $purchase_count_5;

        $orders_6 = Order_model::where('order_type_id',3)
            ->whereBetween('processed_at', [$start_date, $end_date])
            ->where('status', 6)
            ->get();

        $sales_count_6 = $orders_6->count();
        $sales_eur_6 = $orders_6->where('currency',4)->sum('price');
        $sales_gbp_6 = $orders_6->where('currency',5)->sum('price');
        $sales_charge_6 = $orders_6->sum('charges');
        $order_ids_6 = $orders_6->pluck('id')->toArray();

        $order_items_6 = Order_item_model::whereIn('order_id',$order_ids_6)->get();
        $items_count_6 = $order_items_6->count();
        $stock_ids_6 = $order_items_6->pluck('stock_id')->toArray();
        $stock_count_6 = $order_items_6->pluck('stock_id')->unique()->count();
        $purchase_items_6 = Order_item_model::whereIn('stock_id',$stock_ids_6)->whereIn('order_id',$purchase_order_ids)->get();
        $purchase_cost_6 = $purchase_items_6->sum('price');
        $purchase_count_6 = $purchase_items_6->count();


        $data['sales_count_6'] = $sales_count_6;
        $data['sales_eur_6'] = $sales_eur_6;
        $data['sales_gbp_6'] = $sales_gbp_6;
        $data['sales_charge_6'] = $sales_charge_6;
        $data['items_count_6'] = $items_count_6;
        $data['stock_count_6'] = $stock_count_6;
        $data['purchase_cost_6'] = $purchase_cost_6;
        $data['purchase_count_6'] = $purchase_count_6;




        dd($data);

    }


    public function ecommerce_report()
    {
        $data['categories'] = Category_model::pluck('name','id');

        DB::statement("SET SESSION group_concat_max_len = 1500000;");
        $start_date = Carbon::now()->startOfMonth();
        // $start_date = date('Y-m-d 00:00:00',);
        $end_date = date('Y-m-d 23:59:59');
        if (request('start_date') != NULL && request('end_date') != NULL) {
            $start_date = request('start_date') . " 23:00:00";
            $end_date = request('end_date') . " 22:59:59";
        }
        $data['start_date'] = date('Y-m-d', strtotime($start_date));
        $data['end_date'] = date("Y-m-d", strtotime($end_date));

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
                DB::raw('SUM(orders.charges) as charges'),
                DB::raw('SUM(CASE WHEN orders.currency = 4 OR orders.order_type_id = 5 THEN order_items.price ELSE 0 END) as eur_items_sum'),
                DB::raw('SUM(CASE WHEN orders.currency = 5 AND orders.order_type_id = 3 THEN order_items.price ELSE 0 END) as gbp_items_sum'),
                DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
                DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
            )
            ->where(function ($query) use ($start_date, $end_date) {
                $query->where(function ($subQuery) use ($start_date, $end_date) {
                    // Where order_type_id is 3, filter by processed_at
                    $subQuery->where('orders.order_type_id', 3)
                             ->whereBetween('orders.processed_at', [$start_date, $end_date]);
                })
                ->orWhere(function ($subQuery) use ($start_date, $end_date) {
                    // For other order_type_ids, filter by created_at
                    $subQuery->where('orders.order_type_id', 5)
                             ->where('orders.reference_id', 999)
                             ->whereBetween('order_items.created_at', [$start_date, $end_date]);
                });
            })
            // ->whereBetween('orders.processed_at', [$start_date, $end_date])
            // ->whereIn('variation.id', $variation_ids)
            // ->whereIn('orders.order_type_id', [3,5])

            ->Where('orders.deleted_at',null)
            ->Where('order_items.deleted_at',null)
            ->Where('stock.deleted_at',null)
            ->Where('variation.deleted_at',null)
            ->Where('products.deleted_at',null)
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
                DB::raw('SUM(CASE WHEN order_items.currency is null OR order_items.currency = 4 THEN order_items.price ELSE 0 END) as eur_items_sum'),
                DB::raw('SUM(CASE WHEN order_items.currency = 5 THEN order_items.price ELSE 0 END) as gbp_items_sum'),
                DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
                DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
            )
            ->whereBetween('order_items.created_at', [$start_date, $end_date])
            // ->whereIn('variation.id', $variation_ids)
            ->whereIn('orders.order_type_id', [4])
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

        return view('livewire.report_new')->with($data);

    }
    public function export_batch_report($orderId)
    {
        $order = Order_model::find($orderId);
        if(request('type') == 1){
            return Excel::download(new BatchReportExport($orderId), $order->reference_id.'_batch_report.xlsx');
        }else{
            return Excel::download(new BatchInitialReportExport($orderId), $order->reference_id.'_batch_report.xlsx');
        }
        // return Excel::download(new BatchReportExport($orderId), $order->reference_id.'_batch_report.xlsx');
        // return Excel::download(new BatchInitialReportExport($orderId), $order->reference_id.'_batch_report.xlsx');
    }
    public function stock_report(){
        ini_set('memory_limit', '256M');
        ini_set('max_execution_time', '1200s');
        // DB::statement("SET SESSION group_concat_max_len = 1500000;");

        $data['products'] = Products_model::pluck('model','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['colors'] = Color_model::pluck('name','id');
        $data['grades'] = Grade_model::pluck('name','id');

        $data['vendors'] = Customer_model::where('type',1)->pluck('company','id');
        $data['currencies'] = Currency_model::pluck('sign','id');
        $data['order_types'] = Multi_type_model::where('table_name','orders')->pluck('name','id');

        // $start_date = Carbon::now()->startOfMonth();
        // // $start_date = date('Y-m-d 00:00:00',);
        // $end_date = date('Y-m-d 23:59:59');
        // if (request('start_date') != NULL && request('end_date') != NULL) {
        //     $start_date = request('start_date') . " 00:00:00";
        //     $end_date = request('end_date') . " 23:59:59";
        // }
        // $data['start_date'] = date('Y-m-d', strtotime($start_date));
        // $data['end_date'] = date("Y-m-d", strtotime($end_date));

        // $variation_ids = [];
        // if(request('data') == 1){

        // $variation_ids = Variation_model::withoutGlobalScope('Status_not_3_scope')->select('id')
        //     ->when(request('category') != '', function ($q) {
        //         return $q->whereHas('product', function ($qu) {
        //             $qu->where('category', '=', request('category'));
        //         });
        //     })
        //     ->when(request('brand') != '', function ($q) {
        //         return $q->whereHas('product', function ($qu) {
        //             $qu->where('brand', '=', request('brand'));
        //         });
        //     })
        //     ->when(request('product') != '', function ($q) {
        //         return $q->where('product_id', '=', request('product'));
        //     })
        //     ->when(request('storage') != '', function ($q) {
        //         return $q->where('storage', request('storage'));
        //     })
        //     ->when(request('color') != '', function ($q) {
        //         return $q->where('color', request('color'));
        //     })
        //     ->when(request('grade') != '', function ($q) {
        //         return $q->where('grade', request('grade'));
        //     })
        //     ->when(request('vendor') != '', function ($q) {
        //         return $q->whereHas('stocks.order', function ($q) {
        //             $q->where('customer_id', request('vendor'));
        //         });
        //     })
        //     ->when(request('batch') != '', function ($q) {
        //         return $q->whereHas('stocks.order', function ($q) {
        //             $q->where('reference_id', request('batch'));
        //         });
        //     })
        //     ->pluck('id')->toArray();

        // // }

        $stocks = Stock_model::whereIn('id',explode(',',request('stock_ids')))->with('variation','order_items','order')->get();
        $data['stocks'] = $stocks;

        // $aggregates = DB::table('variation')
        //     ->join('order_items', 'variation.id', '=', 'order_items.variation_id')
        //     ->join('orders', 'order_items.order_id', '=', 'orders.id')
        //     ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
        //     ->leftJoin('process_stock', 'stock.id', '=', 'process_stock.stock_id')
        //     ->leftJoin('process', 'process_stock.process_id', '=', 'process.id')
        //     ->select(
        //         'variation.product_id as product_id',
        //         'variation.storage as storage',
        //         DB::raw('COUNT(orders.id) as orders_qty'),
        //         DB::raw('SUM(CASE WHEN orders.status = 3 THEN 1 ELSE 0 END) as approved_orders_qty'),
        //         DB::raw('SUM(CASE WHEN orders.currency = 4 OR orders.order_type_id = 5 THEN order_items.price ELSE 0 END) as eur_items_sum'),
        //         DB::raw('SUM(CASE WHEN orders.currency = 5 AND orders.order_type_id = 3 THEN order_items.price ELSE 0 END) as gbp_items_sum'),
        //         DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
        //         DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
        //     )
        //     ->whereBetween('orders.processed_at', [$start_date, $end_date])
        //     ->whereIn('variation.id', $variation_ids)
        //     ->whereIn('orders.order_type_id', [3,5])
        //     ->Where('orders.deleted_at',null)
        //     ->Where('order_items.deleted_at',null)
        //     ->Where('stock.deleted_at',null)
        //     ->Where('variation.deleted_at',null)
        //     ->Where('process_stock.deleted_at',null)
        //     ->whereIn('orders.status', [3,6])
        //     ->whereIn('order_items.status', [3,6])
        //     // ->groupBy('products.id')
        //     ->groupBy('variation.product_id', 'variation.storage')
        //     ->get();

        // $aggregated_cost = [];
        // foreach ($aggregates as $agg) {

        //     if (empty($agg->stock_ids)) {
        //         $aggregated_cost[$agg->product_id][$agg->storage] = 0;
        //         continue;
        //     }
        //     $aggregated_cost[$agg->product_id][$agg->storage] = Order_item_model::whereIn('stock_id',explode(',',$agg->stock_ids))->whereHas('order', function ($q) {
        //         $q->where('order_type_id',1);
        //     })->sum('price');
        // }

        // $data['aggregated_sales'] = $aggregates;
        // $data['aggregated_sales_cost'] = $aggregated_cost;

        // $aggregate_returns = DB::table('variation')
        //     ->join('order_items', 'variation.id', '=', 'order_items.variation_id')
        //     ->join('orders', 'order_items.order_id', '=', 'orders.id')
        //     ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
        //     ->leftJoin('process_stock', 'stock.id', '=', 'process_stock.stock_id')
        //     ->leftJoin('process', 'process_stock.process_id', '=', 'process.id')
        //     ->select(
        //         'variation.product_id as product_id',
        //         'variation.storage as storage',
        //         DB::raw('COUNT(orders.id) as orders_qty'),
        //         DB::raw('SUM(CASE WHEN orders.status = 3 THEN 1 ELSE 0 END) as approved_orders_qty'),
        //         DB::raw('SUM(CASE WHEN order_items.currency is null OR order_items.currency = 4 THEN order_items.price ELSE 0 END) as eur_items_sum'),
        //         DB::raw('SUM(CASE WHEN order_items.currency = 5 THEN order_items.price ELSE 0 END) as gbp_items_sum'),
        //         DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
        //         DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
        //     )
        //     ->whereBetween('order_items.created_at', [$start_date, $end_date])
        //     ->whereIn('variation.id', $variation_ids)
        //     ->whereIn('orders.order_type_id', [4,6])
        //     ->Where('orders.deleted_at',null)
        //     ->Where('order_items.deleted_at',null)
        //     ->Where('stock.deleted_at',null)
        //     ->Where('process_stock.deleted_at',null)
        //     // ->whereIn('orders.status', [3,6])
        //     // ->groupBy('products.id')
        //     ->groupBy('variation.product_id', 'variation.storage')
        //     ->get();
        // // $costs = Category_model::select(
        //     'category.'
        // )
        // $aggregated_return_cost = [];
        // foreach ($aggregate_returns as $agg) {

        //     if (empty($agg->stock_ids)) {
        //         $aggregated_return_cost[$agg->product_id][$agg->storage] = 0;
        //         continue;
        //     }
        //     $aggregated_return_cost[$agg->product_id][$agg->storage] = Order_item_model::whereIn('stock_id',explode(',',$agg->stock_ids))->whereHas('order', function ($q) {
        //         $q->where('order_type_id',1);
        //     })->sum('price');
        // }

        // $data['aggregated_returns'] = $aggregate_returns;
        // $data['aggregated_return_cost'] = $aggregated_return_cost;

        return view('livewire.stock_report_new')->with($data);
    }
    public function pnl(){
        if(request('bp') == 1){
            $data = $this->pnl_by_product();
        }
        if(request('bc') == 1){
            $data = $this->pnl_by_customer();
        }
        if(request('bv') == 1){
            $data = $this->pnl_by_vendor();
        }
        return view('livewire.pnl_new')->with($data);
    }
    private function pnl_by_product(){
        DB::statement("SET SESSION group_concat_max_len = 1500000;");

        $data['products'] = Products_model::pluck('model','id');
        $data['storages'] = Storage_model::pluck('name','id');

        $start_date = Carbon::now()->startOfMonth();
        // $start_date = date('Y-m-d 00:00:00',);
        $end_date = date('Y-m-d 23:59:59');
        if (request('start_date') != NULL && request('end_date') != NULL) {
            $start_date = request('start_date') . " 00:00:00";
            $end_date = request('end_date') . " 23:59:59";
        }
        $data['start_date'] = date('Y-m-d', strtotime($start_date));
        $data['end_date'] = date("Y-m-d", strtotime($end_date));

        $variation_ids = [];
        // if(request('data') == 1){

        $variation_ids = Variation_model::withoutGlobalScope('Status_not_3_scope')->select('id')
            ->when(request('category') != '', function ($q) {
                return $q->whereHas('product', function ($qu) {
                    $qu->where('category', '=', request('category'));
                });
            })
            ->when(request('brand') != '', function ($q) {
                return $q->whereHas('product', function ($qu) {
                    $qu->where('brand', '=', request('brand'));
                });
            })
            ->when(request('product') != '', function ($q) {
                return $q->where('product_id', '=', request('product'));
            })
            ->when(request('storage') != '', function ($q) {
                return $q->where('storage', request('storage'));
            })
            ->when(request('color') != '', function ($q) {
                return $q->where('color', request('color'));
            })
            ->when(request('grade') != '', function ($q) {
                return $q->where('grade', request('grade'));
            })
            ->when(request('vendor') != '', function ($q) {
                return $q->whereHas('stocks.order', function ($q) {
                    $q->where('customer_id', request('vendor'));
                });
            })
            ->when(request('batch') != '', function ($q) {
                return $q->whereHas('stocks.order', function ($q) {
                    $q->where('reference_id', request('batch'));
                });
            })
            ->pluck('id')->toArray();

        // }

        $aggregates = DB::table('variation')
            ->join('order_items', 'variation.id', '=', 'order_items.variation_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
            ->leftJoin('process_stock', 'stock.id', '=', 'process_stock.stock_id')
            ->leftJoin('process', 'process_stock.process_id', '=', 'process.id')
            ->select(
                'variation.product_id as product_id',
                'variation.storage as storage',
                DB::raw('COUNT(orders.id) as orders_qty'),
                DB::raw('SUM(orders.charges) as charges'),
                DB::raw('SUM(CASE WHEN orders.status = 3 THEN 1 ELSE 0 END) as approved_orders_qty'),
                DB::raw('SUM(CASE WHEN orders.currency = 4 OR orders.order_type_id = 5 THEN order_items.price ELSE 0 END) as eur_items_sum'),
                DB::raw('SUM(CASE WHEN orders.currency = 5 AND orders.order_type_id = 3 THEN order_items.price ELSE 0 END) as gbp_items_sum'),
                DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
                DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
            )
            ->whereBetween('orders.processed_at', [$start_date, $end_date])
            ->whereIn('variation.id', $variation_ids)
            ->whereIn('orders.order_type_id', [3,5])
            ->Where('orders.deleted_at',null)
            ->Where('order_items.deleted_at',null)
            ->Where('stock.deleted_at',null)
            ->Where('variation.deleted_at',null)
            ->Where('process_stock.deleted_at',null)
            ->whereIn('orders.status', [3,6])
            ->whereIn('order_items.status', [3,6])
            // ->groupBy('products.id')
            ->groupBy('variation.product_id', 'variation.storage')
            ->get();

        $aggregated_cost = [];
        foreach ($aggregates as $agg) {

            if (empty($agg->stock_ids)) {
                $aggregated_cost[$agg->product_id][$agg->storage] = 0;
                continue;
            }
            $aggregated_cost[$agg->product_id][$agg->storage] = Order_item_model::whereIn('stock_id',explode(',',$agg->stock_ids))->whereHas('order', function ($q) {
                $q->where('order_type_id',1);
            })->sum('price');
        }

        $data['aggregated_sales'] = $aggregates;
        $data['aggregated_sales_cost'] = $aggregated_cost;

        $aggregate_returns = DB::table('variation')
            ->join('order_items', 'variation.id', '=', 'order_items.variation_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
            ->leftJoin('process_stock', 'stock.id', '=', 'process_stock.stock_id')
            ->leftJoin('process', 'process_stock.process_id', '=', 'process.id')
            ->select(
                'variation.product_id as product_id',
                'variation.storage as storage',
                DB::raw('COUNT(orders.id) as orders_qty'),
                DB::raw('SUM(CASE WHEN orders.status = 3 THEN 1 ELSE 0 END) as approved_orders_qty'),
                DB::raw('SUM(CASE WHEN order_items.currency is null OR order_items.currency = 4 THEN order_items.price ELSE 0 END) as eur_items_sum'),
                DB::raw('SUM(CASE WHEN order_items.currency = 5 THEN order_items.price ELSE 0 END) as gbp_items_sum'),
                DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
                DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
            )
            ->whereBetween('order_items.created_at', [$start_date, $end_date])
            ->whereIn('variation.id', $variation_ids)
            ->whereIn('orders.order_type_id', [4,6])
            ->Where('orders.deleted_at',null)
            ->Where('order_items.deleted_at',null)
            ->Where('stock.deleted_at',null)
            ->Where('process_stock.deleted_at',null)
            // ->whereIn('orders.status', [3,6])
            // ->groupBy('products.id')
            ->groupBy('variation.product_id', 'variation.storage')
            ->get();
        // $costs = Category_model::select(
        //     'category.'
        // )
        $aggregated_return_cost = [];
        foreach ($aggregate_returns as $agg) {

            if (empty($agg->stock_ids)) {
                $aggregated_return_cost[$agg->product_id][$agg->storage] = 0;
                continue;
            }
            $aggregated_return_cost[$agg->product_id][$agg->storage] = Order_item_model::whereIn('stock_id',explode(',',$agg->stock_ids))->whereHas('order', function ($q) {
                $q->where('order_type_id',1);
            })->sum('price');
        }

        $data['aggregated_returns'] = $aggregate_returns;
        $data['aggregated_return_cost'] = $aggregated_return_cost;

        return $data;
    }
    private function pnl_by_customer(){
        DB::statement("SET SESSION group_concat_max_len = 1500000;");

        $data['customers'] = Customer_model::where('type',2)->pluck('company','id');
        $start_date = Carbon::now()->startOfMonth();
        // $start_date = date('Y-m-d 00:00:00',);
        $end_date = date('Y-m-d 23:59:59');
        if (request('start_date') != NULL && request('end_date') != NULL) {
            $start_date = request('start_date') . " 00:00:00";
            $end_date = request('end_date') . " 23:59:59";
        }
        $data['start_date'] = date('Y-m-d', strtotime($start_date));
        $data['end_date'] = date("Y-m-d", strtotime($end_date));

        $variation_ids = [];
        // if(request('data') == 1){

        $variation_ids = Variation_model::withoutGlobalScope('Status_not_3_scope')->select('id')
            ->when(request('category') != '', function ($q) {
                return $q->whereHas('product', function ($qu) {
                    $qu->where('category', '=', request('category'));
                });
            })
            ->when(request('brand') != '', function ($q) {
                return $q->whereHas('product', function ($qu) {
                    $qu->where('brand', '=', request('brand'));
                });
            })
            ->when(request('product') != '', function ($q) {
                return $q->where('product_id', '=', request('product'));
            })
            ->when(request('storage') != '', function ($q) {
                return $q->where('storage', request('storage'));
            })
            ->when(request('color') != '', function ($q) {
                return $q->where('color', request('color'));
            })
            ->when(request('grade') != '', function ($q) {
                return $q->where('grade', request('grade'));
            })
            ->when(request('vendor') != '', function ($q) {
                return $q->whereHas('stocks.order', function ($q) {
                    $q->where('customer_id', request('vendor'));
                });
            })
            ->when(request('batch') != '', function ($q) {
                return $q->whereHas('stocks.order', function ($q) {
                    $q->where('reference_id', request('batch'));
                });
            })
            ->pluck('id')->toArray();

        // }

        $aggregates = DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
            ->join('variation', 'stock.variation_id', '=', 'variation.id')
            ->leftJoin('process_stock', 'stock.id', '=', 'process_stock.stock_id')
            ->leftJoin('process', 'process_stock.process_id', '=', 'process.id')
            ->select(
                'orders.customer_id as customer_id',
                DB::raw('COUNT(orders.id) as orders_qty'),
                DB::raw('SUM(orders.charges) as charges'),
                DB::raw('SUM(order_items.price) as eur_items_sum'),
                DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
                DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
            )
            ->whereBetween('orders.created_at', [$start_date, $end_date])
            ->whereIn('variation.id', $variation_ids)
            ->where('orders.order_type_id', 5)
            ->Where('orders.deleted_at',null)
            ->Where('order_items.deleted_at',null)
            ->Where('stock.deleted_at',null)
            ->Where('variation.deleted_at',null)
            ->Where('process_stock.deleted_at',null)
            ->whereIn('orders.status', [3,6])
            ->whereIn('order_items.status', [3,6])
            // ->groupBy('products.id')
            ->groupBy('orders.customer_id')
            ->get();

        $aggregated_cost = [];
        foreach ($aggregates as $agg) {

            if (empty($agg->stock_ids)) {
                $aggregated_cost[$agg->customer_id] = 0;
                continue;
            }
            $aggregated_cost[$agg->customer_id] = Order_item_model::whereIn('stock_id',explode(',',$agg->stock_ids))->whereHas('order', function ($q) {
                $q->where('order_type_id',1);
            })->sum('price');
        }

        $data['aggregated_sales'] = $aggregates;
        $data['aggregated_sales_cost'] = $aggregated_cost;

        $aggregate_returns = DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
            ->join('variation', 'stock.variation_id', '=', 'variation.id')
            ->leftJoin('process_stock', 'stock.id', '=', 'process_stock.stock_id')
            ->leftJoin('process', 'process_stock.process_id', '=', 'process.id')
            ->select(
                'orders.customer_id as customer_id',
                DB::raw('COUNT(orders.id) as orders_qty'),
                DB::raw('SUM(CASE WHEN orders.status = 3 THEN 1 ELSE 0 END) as approved_orders_qty'),
                DB::raw('SUM(CASE WHEN order_items.currency is null OR order_items.currency = 4 THEN order_items.price ELSE 0 END) as eur_items_sum'),
                DB::raw('SUM(CASE WHEN order_items.currency = 5 THEN order_items.price ELSE 0 END) as gbp_items_sum'),
                DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
                DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
            )
            ->whereBetween('order_items.created_at', [$start_date, $end_date])
            ->whereIn('variation.id', $variation_ids)
            ->where('orders.order_type_id', 6)
            ->Where('orders.deleted_at',null)
            ->Where('order_items.deleted_at',null)
            ->Where('stock.deleted_at',null)
            ->Where('process_stock.deleted_at',null)
            // ->whereIn('orders.status', [3,6])
            // ->groupBy('products.id')
            ->groupBy('orders.customer_id')
            ->get();
        // $costs = Category_model::select(
        //     'category.'
        // )
        $aggregated_return_cost = [];
        foreach ($aggregate_returns as $agg) {

            if (empty($agg->stock_ids)) {
                $aggregated_return_cost[$agg->customer_id] = 0;
                continue;
            }
            $aggregated_return_cost[$agg->customer_id] = Order_item_model::whereIn('stock_id',explode(',',$agg->stock_ids))->whereHas('order', function ($q) {
                $q->where('order_type_id',1);
            })->sum('price');
        }

        $data['aggregated_returns'] = $aggregate_returns;
        $data['aggregated_return_cost'] = $aggregated_return_cost;
        // dd($data);
        return $data;

    }

    private function pnl_by_vendor(){
        DB::statement("SET SESSION group_concat_max_len = 1500000;");

        $data['vendors'] = Customer_model::where('type',1)->pluck('company','id');
        $start_date = Carbon::now()->startOfMonth();
        // $start_date = date('Y-m-d 00:00:00',);
        $end_date = date('Y-m-d 23:59:59');
        if (request('start_date') != NULL && request('end_date') != NULL) {
            $start_date = request('start_date') . " 00:00:00";
            $end_date = request('end_date') . " 23:59:59";
        }
        $data['start_date'] = date('Y-m-d', strtotime($start_date));
        $data['end_date'] = date("Y-m-d", strtotime($end_date));

        $variation_ids = [];
        // if(request('data') == 1){

        $variation_ids = Variation_model::withoutGlobalScope('Status_not_3_scope')->select('id')
            ->when(request('category') != '', function ($q) {
                return $q->whereHas('product', function ($qu) {
                    $qu->where('category', '=', request('category'));
                });
            })
            ->when(request('brand') != '', function ($q) {
                return $q->whereHas('product', function ($qu) {
                    $qu->where('brand', '=', request('brand'));
                });
            })
            ->when(request('product') != '', function ($q) {
                return $q->where('product_id', '=', request('product'));
            })
            ->when(request('storage') != '', function ($q) {
                return $q->where('storage', request('storage'));
            })
            ->when(request('color') != '', function ($q) {
                return $q->where('color', request('color'));
            })
            ->when(request('grade') != '', function ($q) {
                return $q->where('grade', request('grade'));
            })
            ->when(request('vendor') != '', function ($q) {
                return $q->whereHas('stocks.order', function ($q) {
                    $q->where('customer_id', request('vendor'));
                });
            })
            ->when(request('batch') != '', function ($q) {
                return $q->whereHas('stocks.order', function ($q) {
                    $q->where('reference_id', request('batch'));
                });
            })
            ->pluck('id')->toArray();

        // }

        $aggregates = DB::table('orders')
            // ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('order_items', function ($join) {
                $join->on('orders.id', '=', 'order_items.order_id')
                     ->whereIn('order_items.id', function ($query) {
                         $query->selectRaw('MAX(order_items2.id)')
                               ->from('order_items as order_items2')
                               ->groupBy('order_items2.stock_id');
                     });
            })
            ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
            ->leftJoin('orders as purchase_order', 'stock.order_id', '=', 'purchase_order.id')
            ->join('variation', 'stock.variation_id', '=', 'variation.id')
            ->leftJoin('process_stock', 'stock.id', '=', 'process_stock.stock_id')
            ->leftJoin('process', 'process_stock.process_id', '=', 'process.id')
            ->select(
                'purchase_order.customer_id as customer_id',
                DB::raw('COUNT(orders.id) as orders_qty'),
                DB::raw('SUM(orders.charges) as charges'),
                DB::raw('SUM(CASE WHEN orders.currency = 4 OR orders.order_type_id = 5 THEN order_items.price ELSE 0 END) as eur_items_sum'),
                DB::raw('SUM(CASE WHEN orders.currency = 5 AND orders.order_type_id = 3 THEN order_items.price ELSE 0 END) as gbp_items_sum'),
                DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
                DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
            )
            ->where(function ($query) use ($start_date, $end_date) {
                $query->where(function ($subQuery) use ($start_date, $end_date) {
                    // Where order_type_id is 3, filter by processed_at
                    $subQuery->where('orders.order_type_id', 3)
                             ->whereBetween('orders.processed_at', [$start_date, $end_date]);
                })
                ->orWhere(function ($subQuery) use ($start_date, $end_date) {
                    // For other order_type_ids, filter by created_at
                    $subQuery->where('orders.order_type_id', '!=', 3)
                             ->whereBetween('orders.created_at', [$start_date, $end_date]);
                });
            })
            // ->whereBetween('orders.created_at', [$start_date, $end_date])
            ->whereIn('variation.id', $variation_ids)
            ->whereIn('orders.order_type_id', [2,3,5])
            ->where('stock.status',2)
            ->Where('orders.deleted_at',null)
            ->Where('order_items.deleted_at',null)
            ->Where('stock.deleted_at',null)
            ->Where('variation.deleted_at',null)
            ->Where('process_stock.deleted_at',null)
            ->whereIn('orders.status', [3,6])
            ->whereIn('order_items.status', [3,6])
            // ->groupBy('products.id')
            ->groupBy('purchase_order.customer_id')
            // ->orderByDesc('order_items.created_at')
            ->get();

        $aggregated_cost = [];
        foreach ($aggregates as $agg) {

            if (empty($agg->stock_ids)) {
                $aggregated_cost[$agg->customer_id] = 0;
                continue;
            }
            $aggregated_cost[$agg->customer_id] = Order_item_model::whereIn('stock_id',explode(',',$agg->stock_ids))->whereHas('order', function ($q) {
                $q->where('order_type_id',1);
            })->sum('price');
        }

        $data['aggregated_sales'] = $aggregates;
        $data['aggregated_sales_cost'] = $aggregated_cost;

        $aggregate_returns = DB::table('orders')
            ->join('order_items', function ($join) {
                $join->on('orders.id', '=', 'order_items.order_id')
                    ->whereIn('order_items.id', function ($query) {
                        $query->selectRaw('MAX(order_items2.id)')
                            ->from('order_items as order_items2')
                            ->groupBy('order_items2.stock_id');
                    });
            })
            ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
            ->leftJoin('orders as purchase_order', 'stock.order_id', '=', 'purchase_order.id')
            // ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            // ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
            ->join('variation', 'stock.variation_id', '=', 'variation.id')
            ->leftJoin('process_stock', 'stock.id', '=', 'process_stock.stock_id')
            ->leftJoin('process', 'process_stock.process_id', '=', 'process.id')
            ->select(
                // 'orders.customer_id as customer_id',
                'purchase_order.customer_id as customer_id',
                DB::raw('COUNT(orders.id) as orders_qty'),
                DB::raw('SUM(CASE WHEN order_items.currency is null OR order_items.currency = 4 THEN order_items.price ELSE 0 END) as eur_items_sum'),
                DB::raw('SUM(CASE WHEN order_items.currency = 5 THEN order_items.price ELSE 0 END) as gbp_items_sum'),
                DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
                DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
            )
            ->whereBetween('order_items.created_at', [$start_date, $end_date])
            ->whereIn('variation.id', $variation_ids)
            ->whereIn('orders.order_type_id', [4,6])
            ->Where('orders.deleted_at',null)
            ->Where('order_items.deleted_at',null)
            ->Where('stock.deleted_at',null)
            ->Where('process_stock.deleted_at',null)
            // ->whereIn('orders.status', [3,6])
            // ->groupBy('products.id')
            ->groupBy('purchase_order.customer_id')
            ->get();
        // $costs = Category_model::select(
        //     'category.'
        // )
        $aggregated_return_cost = [];
        foreach ($aggregate_returns as $agg) {

            if (empty($agg->stock_ids)) {
                $aggregated_return_cost[$agg->customer_id] = 0;
                continue;
            }
            $aggregated_return_cost[$agg->customer_id] = Order_item_model::whereIn('stock_id',explode(',',$agg->stock_ids))->whereHas('order', function ($q) {
                $q->where('order_type_id',1);
            })->sum('price');
        }

        $data['aggregated_returns'] = $aggregate_returns;
        $data['aggregated_return_cost'] = $aggregated_return_cost;
        // dd($data);
        return $data;

    }

    public function vendor_report($vendor_id){

        $data['title_page'] = 'Vendor Report';
        session()->put('page_title', $data['title_page']);

        ini_set('memory_limit', '2560M');

        $start_date = request('start_date') ?? Carbon::now()->startOfMonth();
        $end_date = request('end_date') ?? Carbon::now()->endOfMonth();

        $vendor = Customer_model::withCount([

            'orders as purchase_qty' => function ($query) {
                $query->where('orders.order_type_id', 1)->join('order_items', 'orders.id', '=', 'order_items.order_id')
                    ->select(DB::raw('SUM(order_items.quantity)'));
            },
            'orders as purchase_cost' => function ($query) {
                $query->where('orders.order_type_id', 1)->join('order_items', 'orders.id', '=', 'order_items.order_id')
                    ->select(DB::raw('SUM(order_items.price)'));
            },
            'orders as rma_qty' => function ($query) {
                $query->where('orders.order_type_id', 2)->join('order_items', 'orders.id', '=', 'order_items.order_id')
                    ->select(DB::raw('SUM(order_items.quantity)'));
            },
            'orders as rma_price' => function ($query) {
                $query->where('orders.order_type_id', 2)->join('order_items', 'orders.id', '=', 'order_items.order_id')
                    ->select(DB::raw('SUM(order_items.price)'));
            },

        ])
        ->find($vendor_id);
        $data['vendor'] = $vendor;


        $order_ids = Order_model::where('customer_id', $vendor_id)->pluck('id');
        $purchase_order_ids = Order_model::where('customer_id', $vendor_id)->where('order_type_id', 1)->pluck('id');
        $rma_order_ids = Order_model::where('customer_id', $vendor_id)->where('order_type_id', 2)->pluck('id');


        $available_stock_ids = Stock_model::whereIn('order_id', $purchase_order_ids)->where('status',1)->pluck('id');
        $sold_stock_ids = Stock_model::whereIn('order_id', $order_ids)->where('status',2)->pluck('id');

        $available_stock_count = $available_stock_ids->count();
        $sold_stock_count = $sold_stock_ids->count();

        $available_stock_cost = Order_item_model::whereIn('stock_id', $available_stock_ids)->whereIn('order_id', $purchase_order_ids)->orderByDesc('id')->sum('price');
        $sold_stock_price = Order_item_model::whereIn('stock_id', $sold_stock_ids)->whereNotIn('order_id', $order_ids)->orderBy('id')->pluck('price','stock_id')->sum();


        $data['available_stock_count'] = $available_stock_count;
        $data['sold_stock_count'] = $sold_stock_count;
        $data['available_stock_cost'] = $available_stock_cost;
        $data['sold_stock_cost'] = $sold_stock_price;


        $total_external_repair = Process_stock_model::whereHas('stock.order',function ($q) use ($vendor_id){
            $q->where('customer_id', $vendor_id);
        })->whereHas('process', function ($q){
            $q->where('process_type_id', 9);
        })->pluck('stock_id');

        $total_2x = Stock_model::whereHas('order',function ($q) use ($vendor_id){
            $q->where('customer_id', $vendor_id);
        })->whereHas('stock_operations.new_variation', function ($q){
            $q->where('grade', 6);
        })->whereNotIn('id', $total_external_repair)->pluck('id');

        $total_unknown_part = Stock_model::whereHas('order',function ($q) use ($vendor_id){
            $q->where('customer_id', $vendor_id);
        })->whereHas('stock_operations.new_variation', function ($q){
            $q->where('grade', 20);
        })->whereNotIn('id', $total_external_repair)->whereNotIn('id', $total_2x)->pluck('id');

        $total_repair = Stock_model::whereNotIn('id', $total_external_repair)->whereNotIn('id', $total_2x)->whereNotIn('id', $total_unknown_part)->whereHas('order',function ($q) use ($vendor_id){
            $q->where('customer_id', $vendor_id);
        })->whereHas('stock_operations.new_variation', function ($q){
            $q->where('grade', 7);
        })->pluck('id');

        $total_battery = Stock_model::whereHas('order',function ($q) use ($vendor_id){
            $q->where('customer_id', $vendor_id);
        })->whereHas('stock_operations.new_variation', function ($q){
            $q->where('grade', 21);
        })->whereNotIn('id', $total_external_repair)->whereNotIn('id', $total_2x)->whereNotIn('id', $total_unknown_part)->whereNotIn('id', $total_repair)->pluck('id');

        $total_external_repair_cost = Process_stock_model::whereIn('stock_id', $total_external_repair)->sum('price');


        $data['total_repair'] = $total_repair->count();
        $data['total_external_repair'] = $total_external_repair->count();
        $data['total_battery'] = $total_battery->count();
        $data['total_external_repair_cost'] = $total_external_repair_cost;
        $data['total_2x'] = $total_2x->count();
        $data['total_unknown_part'] = $total_unknown_part->count();


        $vendor_time = Customer_model::withCount([

            'orders as purchase_qty' => function ($query) use ($start_date, $end_date) {
                $query->where('orders.order_type_id', 1)->join('order_items', 'orders.id', '=', 'order_items.order_id')
                    ->whereBetween('orders.created_at', [$start_date, $end_date])
                    ->select(DB::raw('SUM(order_items.quantity)'));
            },
            'orders as purchase_cost' => function ($query) use ($start_date, $end_date) {
                $query->where('orders.order_type_id', 1)->join('order_items', 'orders.id', '=', 'order_items.order_id')
                    ->whereBetween('orders.created_at', [$start_date, $end_date])
                    ->select(DB::raw('SUM(order_items.price)'));
            },
            'orders as rma_qty' => function ($query) use ($start_date, $end_date) {
                $query->where('orders.order_type_id', 2)->join('order_items', 'orders.id', '=', 'order_items.order_id')
                    ->whereBetween('orders.created_at', [$start_date, $end_date])
                    ->select(DB::raw('SUM(order_items.quantity)'));
            },
            'orders as rma_price' => function ($query) use ($start_date, $end_date) {
                $query->where('orders.order_type_id', 2)->join('order_items', 'orders.id', '=', 'order_items.order_id')
                    ->whereBetween('orders.created_at', [$start_date, $end_date])
                    ->select(DB::raw('SUM(order_items.price)'));
            },

        ])
        ->find($vendor_id);
        $data['vendor_time'] = $vendor_time;


        $order_ids_time = Order_model::where('customer_id', $vendor_id)->whereBetween('created_at', [$start_date, $end_date])->pluck('id');
        $purchase_order_ids_time = Order_model::where('customer_id', $vendor_id)->where('order_type_id', 1)
        ->whereBetween('created_at', [$start_date, $end_date])->pluck('id');


        $available_stock_ids_time = Stock_model::whereIn('order_id', $purchase_order_ids_time)->where('status',1)->pluck('id');
        $sold_stock_ids_time = Stock_model::whereIn('order_id', $order_ids_time)->where('status',2)->pluck('id');

        $available_stock_count_time = $available_stock_ids_time->count();
        $sold_stock_count_time = $sold_stock_ids_time->count();

        $available_stock_cost_time = Order_item_model::whereIn('stock_id', $available_stock_ids_time)->whereIn('order_id', $purchase_order_ids_time)->sum('price');
        $sold_stock_price_time = Order_item_model::whereIn('stock_id', $sold_stock_ids_time)->whereNotIn('order_id', $order_ids_time)->orderBy('id')->pluck('price','stock_id')->sum();


        $data['available_stock_count_time'] = $available_stock_count_time;
        $data['sold_stock_count_time'] = $sold_stock_count_time;
        $data['available_stock_cost_time'] = $available_stock_cost_time;
        $data['sold_stock_cost_time'] = $sold_stock_price_time;


        $total_external_repair_time = Process_stock_model::whereHas('stock',function ($q) use ($purchase_order_ids_time){
            $q->whereIn('order_id', $purchase_order_ids_time);
        })->whereHas('process', function ($q){
            $q->where('process_type_id', 9);
        })->pluck('stock_id');

        $total_2x_time = Stock_model::whereIn('order_id',$purchase_order_ids_time)->whereHas('stock_operations.new_variation', function ($q){
            $q->where('grade', 6);
        })->whereNotIn('id', $total_external_repair_time)->pluck('id');

        $total_unknown_part_time = Stock_model::whereIn('order_id',$purchase_order_ids_time)->whereHas('stock_operations.new_variation', function ($q){
            $q->where('grade', 20);
        })->whereNotIn('id', $total_external_repair_time)->whereNotIn('id', $total_2x_time)->pluck('id');

        $total_repair_time = Stock_model::whereNotIn('id', $total_external_repair_time)->whereNotIn('id', $total_2x_time)->whereNotIn('id', $total_unknown_part_time)->wherein('order_id',$purchase_order_ids_time)->whereHas('stock_operations.new_variation', function ($q){
            $q->where('grade', 7);
        })->pluck('id');

        $total_battery_time = Stock_model::whereIn('order_id',$purchase_order_ids_time)->whereHas('stock_operations.new_variation', function ($q){
            $q->where('grade', 21);
        })->whereNotIn('id', $total_external_repair_time)->whereNotIn('id', $total_2x_time)->whereNotIn('id', $total_unknown_part_time)->whereNotIn('id', $total_repair_time)->pluck('id');

        $total_external_repair_cost_time = Process_stock_model::whereIn('stock_id', $total_external_repair_time)->sum('price');


        $data['total_repair_time'] = $total_repair_time->count();
        $data['total_external_repair_time'] = $total_external_repair_time->count();
        $data['total_battery_time'] = $total_battery_time->count();
        $data['total_external_repair_cost_time'] = $total_external_repair_cost_time;
        $data['total_2x_time'] = $total_2x_time->count();
        $data['total_unknown_part_time'] = $total_unknown_part_time->count();





        // dd($repair_report);
        return view('livewire.vendor_report_new')->with($data);
    }

    public function vendor_purchase_report($vendor_id){

        $order_ids = Order_model::where('customer_id', $vendor_id)
        ->when(request('start_date') != NULL && request('end_date') != NULL, function ($q) {
            $q->whereBetween('created_at', [request('start_date'), request('end_date')]);
        })->pluck('id');

        $product_storage_sort = Product_storage_sort_model::whereHas('stocks', function ($q) use ($order_ids){
            $q->whereIn('order_id', $order_ids);
        })->orderBy('product_id')->orderBy('storage')->get();

        $result = [];
        $i = 0;
        $available = 0;
        $sold = 0;
        $cost = 0;
        foreach($product_storage_sort as $pss){
            $product = $pss->product;
            $storage = $pss->storage_id->name ?? null;

            $datas = [];
            $datas['sr_no'] = ++$i;
            $datas['pss_id'] = $pss->id;
            $datas['model'] = $product->model.' '.$storage;
            $datas['available_stock_count'] = $pss->stocks->whereIn('order_id',$order_ids)->where('status',1)->count();
            $available += $datas['available_stock_count'];
            $datas['sold_stock_count'] = $pss->stocks->whereIn('order_id',$order_ids)->where('status',2)->count();
            $sold += $datas['sold_stock_count'];
            $datas['count'] = $datas['sold_stock_count'] .' + ' . $datas['available_stock_count'] .  ' = ' . ($datas['available_stock_count'] + $datas['sold_stock_count']);
            $datas['stock_cost'] = '€'.amount_formatter($pss->stocks->whereIn('order_id',$order_ids)->sum('purchase_item.price'));
            $cost += $pss->stocks->whereIn('order_id',$order_ids)->sum('purchase_item.price');

            $result[] = $datas;
        }
        $purchase_report = $result;

        $purchase_report[] = [
            'sr_no' => '',
            'model' => 'Total',
            'available_stock_count' => $available,
            'sold_stock_count' => $sold,
            'count' => $available . ' + ' . $sold . ' = ' .  $available + $sold,
            'stock_cost' => '€'.amount_formatter($cost),
        ];

        return response()->json($purchase_report);
    }

    public function vendor_rma_report($vendor_id){

        $rma_report = Stock_model::whereHas('order',function ($q) use ($vendor_id){
            $q->where('customer_id', $vendor_id)
            ->when(request('start_date') != NULL && request('end_date') != NULL, function ($q) {
                $q->whereBetween('created_at', [request('start_date'), request('end_date')]);
            });
        })->whereHas('variation', function ($q){
            $q->where('grade', 10);
        })->with('latest_operation')
        ->get()
        ->groupBy('latest_operation.description');

        $i = 0;
        $j = 0;
        $count = 0;
        $data = [];
        foreach ($rma_report as $key => $value){
            $datas = [];
            $j++;

            $imeis = implode(" ", [implode(" ",$value->pluck('imei')->unique()->toArray()), implode(" ",$value->pluck('serial_number')->unique()->toArray())]);
            $imeis2 = $value->pluck('imei')->unique()->toArray()+$value->pluck('serial_number')->unique()->toArray();
            $datas['sr_no'] = ++$i;
            $datas['description'] = $key;
            $datas['count'] = count($value);
            $count += count($value);
            $datas['imeis'] = $imeis;
            $datas['actions'] = '<a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18" style="font-size: 20px;"></i></a>
            <div class="dropdown-menu">
                <a class="dropdown-item" id="test'.$j.'" href="#" onClick="open_all('.json_encode($imeis2).')">Open All</a>
                <a class="dropdown-item" id="change_entry_message_'.$j.'" href="#" onclick="var newMessage = prompt(\'Please enter new message\'); if(newMessage != null){window.location.href = \''.url('move_inventory/change_grade/1').'\?imei='.$imeis.'&description=\'+newMessage}"
                >Change All</a>
            </div>';

            $data[] = $datas;
        }

        $data[] = [
            'sr_no' => '',
            'description' => 'Total',
            'count' => $count,
            'imeis' => '',
            'actions' => ''
        ];


        return response()->json($data);
    }

    public function vendor_repair_report($vendor_id){

        ini_set('memory_limit', '2560M');

        $start_date = (request('start_date') ?? Carbon::now()->startOfMonth()).' 00:00:00';
        $end_date = (request('end_date') ?? Carbon::now()->endOfMonth()).' 23:59:59';

        $purchase_order_ids = Order_model::where('customer_id', $vendor_id)->where('order_type_id', 1)->whereBetween('created_at', [$start_date, $end_date])->pluck('id');

        $repair_report = Stock_model::whereIn('order_id',$purchase_order_ids)
        ->whereHas('stock_operations.new_variation', function ($q){
            $q->where('grade', 8);
        })->with(['stock_operations'=> function ($q) {
            $q->whereHas('new_variation', function ($qq) {
                $qq->where('grade',8);
            });
        }])
        ->get();
        dd($purchase_order_ids);
        $repair_report = $repair_report->groupBy(function($stock) {
            return $stock->stock_operations->first()->description ?? 'no_description';
        });

        $i = 0;
        $j = 0;
        $data = [];
        $count = 0;
        foreach ($repair_report as $key => $value){
            $datas = [];
            $j++;

            $imeis = implode(" ", [implode(" ",$value->pluck('imei')->unique()->toArray()), implode(" ",$value->pluck('serial_number')->unique()->toArray())]);
            $imeis2 = $value->pluck('imei')->unique()->toArray()+$value->pluck('serial_number')->unique()->toArray();
            $datas['sr_no'] = ++$i;
            $datas['description'] = $key;
            $datas['count'] = count($value);
            $count += count($value);
            $datas['imeis'] = $imeis;
            $datas['actions'] = '<a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18" style="font-size: 20px;"></i></a>
            <div class="dropdown-menu">
                <a class="dropdown-item" id="test'.$j.'" href="#" onClick="open_all('.json_encode($imeis2).')">Open All</a>
                <a class="dropdown-item" id="change_entry_message_'.$j.'" href="#" onclick="var newMessage = prompt(\'Please enter new message\'); if(newMessage != null){window.location.href = \''.url('move_inventory/change_grade/1').'\?imei='.$imeis.'&description=\'+newMessage}"
                >Change All</a>
            </div>';

            $data[] = $datas;
        }

        $data[] = [
            'sr_no' => '',
            'description' => 'Total',
            'count' => $count,
            'imeis' => '',
            'actions' => ''
        ];



        return response()->json($data);
    }

    public function export_report()
    {
        ini_set('memory_limit', '256M');


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
        }elseif(request('report') == 'B2C'){
            return Excel::download(new B2COrderReportExport, 'B2C_Report_'.request('start_date').'-'.request('end_date').'.xlsx');
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

    public function pass()
    {
        return view('livewire.report_password');
    }

    public function set_password()
    {
        if(request('old_password') == null){
            session()->put('error', 'Input old password');
            return redirect()->back();
        }
        if(request('new_password') == null){
            session()->put('error', 'Input new password');
            return redirect()->back();
        }
        if(request('new_password') != request('confirm_password')){
            session()->put('error', 'Passwords do not match');
            return redirect()->back();
        }
        if(file_exists('rep_pass.txt')){
            $password = file_get_contents('rep_pass.txt');
            if(session('user_id') != 1 && request('old_password') != $password){
                session()->put('error', 'Incorrect old password');
                return redirect()->back();
            }
        }
        echo file_put_contents('rep_pass.txt', request('new_password'));

        session()->put('success', 'Password set successfully');

        return redirect()->back();
    }
    public function check_password()
    {
        $password = file_get_contents('rep_pass.txt');
        // echo $password;
        // echo request('password');
        // file_put_contents('rep_pass.txt', request('password'));
        // die;
        if(request('password') == $password){
            session()->put('rep', true);
            return redirect('/report');
        }else{
            session()->put('error', 'Incorrect password');
            return redirect()->back();
        }
    }
}
