<?php

namespace App\Http\Livewire;

use App\Exports\B2COrderReportExport;
use App\Exports\BatchInitialReportExport;
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

class SalesReport extends Component
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

        // $data['purchase_status'] = [2 => '(Pending)', 3 => ''];
        // $data['purchase_orders'] = Order_model::where('order_type_id',1)->pluck('reference_id','id');
        // $data['vendors'] = Customer_model::where('is_vendor',1)->pluck('first_name','id');
        $data['categories'] = Category_model::pluck('name','id');
        // $data['brands'] = Brand_model::pluck('name','id');
        // $data['products'] = Products_model::orderBy('model','asc')->get();
        // $data['colors'] = Color_model::pluck('name','id');
        // $data['storages'] = Storage_model::pluck('name','id');
        // $data['grades'] = Grade_model::pluck('name','id');
        // $data['variations'] = Variation_model::where('product_id',null)
        // ->orderBy('name','desc')
        // ->paginate($per_page)
        // ->onEachSide(5)
        // ->appends(request()->except('page'));

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

        $data['start_date'] = date('Y-m-d', strtotime($start_date));
        $data['end_date'] = date("Y-m-d", strtotime($end_date));
        return view('livewire.reports.sales-report')->with($data);
    }
}
