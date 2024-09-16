<?php

namespace App\Http\Livewire;

use App\Exports\BatchReportExport;
use App\Exports\OrderReportExport;
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
        // if(!session('rep')){
        //     return redirect('report/pass');
        // }
        DB::statement("SET SESSION group_concat_max_len = 1500000;");


        $data['title_page'] = "Reports";
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
        // $aggregates = DB::table('category')
        // ->join('products', 'category.id', '=', 'products.category')
        // ->join('variation', 'products.id', '=', 'variation.product_id')

        // ->join('order_items', 'variation.id', '=', 'order_items.variation_id')
        // // Filtered Orders Subquery
        // ->join(DB::raw('(
        //     SELECT orders.id, orders.currency, orders.order_type_id, orders.processed_at, orders.created_at
        //     FROM orders
        //     WHERE (
        //         (orders.order_type_id = 3 AND orders.processed_at BETWEEN "' . $start_date . '" AND "' . $end_date . '")
        //         OR (orders.order_type_id != 3 AND orders.created_at BETWEEN "' . $start_date . '" AND "' . $end_date . '")
        //     )
        //     AND orders.deleted_at IS NULL
        //     AND orders.status IN (3, 6)
        // ) as filtered_orders'), 'order_items.order_id', '=', 'filtered_orders.id')

        // // Continue with the joins after filtering orders
        // ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
        // ->leftJoin('process_stock', 'stock.id', '=', 'process_stock.stock_id')
        // ->leftJoin('process', 'process_stock.process_id', '=', 'process.id')

        // // Select fields with aggregations
        // ->select(
        //     'category.id as category_id',
        //     DB::raw('COUNT(filtered_orders.id) as orders_qty'),
        //     DB::raw('SUM(CASE WHEN (filtered_orders.currency = 4 OR filtered_orders.order_type_id = 5) THEN order_items.price ELSE 0 END) as eur_items_sum'),
        //     DB::raw('SUM(CASE WHEN filtered_orders.currency = 5 AND filtered_orders.order_type_id = 3 THEN order_items.price ELSE 0 END) as gbp_items_sum'),
        //     DB::raw('GROUP_CONCAT(stock.id) as stock_ids'),
        //     DB::raw('SUM(CASE WHEN process.process_type_id = 9 THEN process_stock.price ELSE 0 END) as items_repair_sum')
        // )

        // // Additional filtering
        // ->whereIn('variation.id', $variation_ids)
        // ->whereIn('filtered_orders.order_type_id', [2, 3, 5])
        // ->whereNull('order_items.deleted_at')
        // ->whereNull('stock.deleted_at')
        // ->whereNull('process_stock.deleted_at')
        // ->whereIn('order_items.status', [3, 6])
        // ->groupBy('category.id')
        // ->get();

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


        $order = [];
        $dates = [];
        $k = 0;
        $today = date('d');
        for ($i = 5; $i >= 0; $i--) {
            $j = $i+1;
            $k++;
            $start = date('Y-m-25 23:00:00', strtotime("-".$j." months"));
            $end = date('Y-m-5 22:59:59', strtotime("-".$i." months"));
            $orders = Order_model::where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->count();
            $euro = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',4);
            })->whereIn('status',[3,6])->sum('price');
            $pound = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',5);
            })->whereIn('status',[3,6])->sum('price');
            $order[$k] = $orders;
            $eur[$k] = $euro;
            $gbp[$k] = $pound;
            $dates[$k] = date('25 M', strtotime("-".$j." months")) . " - " . date('05 M', strtotime("-".$i." months"));
            if($i == 0 && $today < 6){
                continue;
            }
            $k++;
            $start = date('Y-m-5 23:00:00', strtotime("-".$i." months"));
            $end = date('Y-m-15 22:59:59', strtotime("-".$i." months"));
            $orders = Order_model::where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->count();
            $euro = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',4);
            })->whereIn('status',[3,6])->sum('price');
            $pound = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',5);
            })->whereIn('status',[3,6])->sum('price');
            $order[$k] = $orders;
            $eur[$k] = $euro;
            $gbp[$k] = $pound;
            $dates[$k] = date('05 M', strtotime("-".$i." months")) . " - " . date('15 M', strtotime("-".$i." months"));
            if($i == 0 && $today < 16){
                continue;
            }
            $k++;
            $start = date('Y-m-15 23:00:00', strtotime("-".$i." months"));
            $end = date('Y-m-25 22:59:59', strtotime("-".$i." months"));
            $orders = Order_model::where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->count();
            $euro = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',4);
            })->whereIn('status',[3,6])->sum('price');
            $pound = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->whereIn('status',[3,6])->where('currency',5);
            })->whereIn('status',[3,6])->sum('price');
            $order[$k] = $orders;
            $eur[$k] = $euro;
            $gbp[$k] = $pound;
            $dates[$k] = date('15 M', strtotime("-".$i." months")) . " - " . date('25 M', strtotime("-".$i." months"));

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
    public function export_batch_report($orderId)
    {
        $order = Order_model::find($orderId);
        return Excel::download(new BatchReportExport($orderId), $order->reference_id.'_batch_report.xlsx');
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


        $rma_report = Stock_model::whereHas('order',function ($q) use ($vendor_id){
                $q->where('customer_id', $vendor_id);
            })->whereHas('variation', function ($q){
                $q->where('grade', 10);
            })->with('latest_operation')
            ->get()
            ->groupBy('latest_operation.description');

        $data['rma_report'] = $rma_report;
        $repair_report = Stock_model::whereHas('order',function ($q) use ($vendor_id){
                $q->where('customer_id', $vendor_id);
            })->whereHas('stock_operations.new_variation', function ($q){
                $q->where('grade', 8);
            // })->whereHas('stock_operations', function ($q){
            //     $q->whereNotNull('description');
            })->with(['stock_operations'=> function ($q) {
                $q->whereHas('new_variation', function ($qq) {
                    $qq->where('grade',8);
                });
            }])
            ->get();

            // ->groupBy('stock_operations.description');

        // Group the results by the 'description' field of the first related stock_operation
        $repair_report = $repair_report->groupBy(function($stock) {
            return $stock->stock_operations->first()->description ?? 'no_description';
        });
        $data['repair_report'] = $repair_report;

        // dd($repair_report);
        return view('livewire.vendor_report_new')->with($data);
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
        // if(request('old_password') == null){
        //     session()->put('error', 'Input old password');
        //     return redirect()->back();
        // }
        if(request('new_password') == null){
            session()->put('error', 'Input new password');
            return redirect()->back();
        }
        if(request('new_password') != request('confirm_password')){
            session()->put('error', 'Passwords do not match');
            return redirect()->back();
        }
        // $password = file_get_contents('rep_pass.txt');
        // if(request('old_password') != $password){
        //     session()->put('error', 'Incorrect old password');
        //     return redirect()->back();
        // }

        file_put_contents('rep_pass.txt', request('password'));

        session()->put('message', 'Password set successfully');

        return redirect()->back();
    }
    public function check_password()
    {
        $password = file_get_contents('rep_pass.txt');
        if(request('password') == $password){
            session()->put('rep', true);
            return redirect('/report');
        }else{
            session()->put('error', 'Incorrect password');
            return redirect('/');
        }
    }
}
