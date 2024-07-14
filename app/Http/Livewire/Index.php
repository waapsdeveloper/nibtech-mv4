<?php

namespace App\Http\Livewire;

use App\Models\Admin_model;
use App\Models\Brand_model;
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
use App\Models\Variation_model;
use App\Models\Stock_model;
use Symfony\Component\HttpFoundation\Request;

class Index extends Component
{
    public function mount()
    {

    }
    public function render(Request $request)
    {
        $data['title_page'] = "Dashboard";
        // dd('Hello2');
        $user_id = session('user_id');

        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }
        $data['purchase_status'] = [2 => '(Pending)', 3 => ''];
        $data['products'] = Products_model::select('id','model')->orderBy('model','asc')->get();
        $data['categories'] = Category_model::pluck('name','id');
        $data['brands'] = Brand_model::pluck('name','id');
        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::pluck('name','id');

        // New Added Variations
        $data['variations'] = Variation_model::withoutGlobalScope('Status_not_3_scope')
        ->where('product_id',null)
        ->orderBy('name','desc')
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));
        // New Added Variations

        $start_date = Carbon::now()->startOfDay();
        $end_date = date('Y-m-d 23:59:59');
        if (request('start_date') != NULL && request('end_date') != NULL) {
            $start_date = request('start_date') . " 00:00:00";
            $end_date = request('end_date') . " 23:59:59";
        }
        // $products = Products_model::get()->toArray();
        // Retrieve the top 10 selling products from the order_items table
        $variation_ids = [];
        if(request('submit') == 1){

            $variation_ids = Variation_model::withoutGlobalScope('Status_not_3_scope')->select('id')
            ->when(request('product') != '', function ($q) {
                return $q->where('product_id', '=', request('product'));
            })
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
            ->when(request('storage') != '', function ($q) {
                return $q->where('variation.storage', 'LIKE', request('storage') . '%');
            })
            ->when(request('color') != '', function ($q) {
                return $q->where('variation.color', 'LIKE', request('color') . '%');
            })
            ->when(request('grade') != '', function ($q) {
                return $q->where('variation.grade', 'LIKE', request('grade') . '%');
            })->pluck('id')->toArray();

        }
        $top_products = Order_item_model::when(request('submit') == 1, function($q) use ($variation_ids){
            return $q->whereIn('variation_id', $variation_ids);
        })
        ->whereHas('order', function ($q) use ($start_date, $end_date) {
            $q->where(['order_type_id'=>3, 'currency'=>4])
            ->whereBetween('created_at', [$start_date, $end_date]);
        })
        ->select('variation_id', DB::raw('SUM(quantity) as total_quantity_sold'), DB::raw('AVG(price) as average_price'))
        ->groupBy('variation_id')
        ->orderByDesc('total_quantity_sold')
        ->take($per_page)
        ->get();

        $data['top_products'] = $top_products;


        $data['total_orders'] = Order_model::whereBetween('created_at', [$start_date, $end_date])->where('order_type_id',3)
        ->whereHas('order_items', function ($q) use ($variation_ids) {
            $q->when(request('submit') == 1, function($q) use ($variation_ids){
                return $q->whereIn('variation_id', $variation_ids);
            });
        })
        ->count();

        $data['pending_orders'] = Order_model::whereBetween('created_at', [$start_date, $end_date])->where('order_type_id',3)->where('status','<',3)
        ->whereHas('order_items', function ($q) use ($variation_ids) {
            $q->when(request('submit') == 1, function($q) use ($variation_ids){
                return $q->whereIn('variation_id', $variation_ids);
            });
        })
        ->count();
        $data['invoiced_orders'] = Order_model::where('processed_at', '>=', $start_date)->where('processed_at', '<=', $end_date)->where('order_type_id',3)
        ->whereHas('order_items', function ($q) use ($variation_ids) {
            $q->when(request('submit') == 1, function($q) use ($variation_ids){
                return $q->whereIn('variation_id', $variation_ids);
            });
        })
        ->count();


        $data['total_conversations'] = Order_item_model::whereBetween('created_at', [$start_date, $end_date])->where('care_id','!=',null)
        ->when(request('submit') == 1, function($q) use ($variation_ids){
            return $q->whereIn('variation_id', $variation_ids);
        })->whereHas('sale_order')
        ->count();

        $data['average'] = Order_item_model::whereBetween('created_at', [$start_date, $end_date])
        ->whereHas('order', function ($q) {
            $q->where('currency',4);
        })->when(request('submit') == 1, function($q) use ($variation_ids){
            return $q->whereIn('variation_id', $variation_ids);
        })
        ->avg('price');


        $aftersale = Order_item_model::whereHas('order', function ($q) {
            $q->where('order_type_id',4)->where('status','<',3);
        })->pluck('stock_id')->toArray();
        $data['returns_in_progress'] = count($aftersale);
        $rmas = Order_model::where(['order_type_id'=>2])->pluck('id')->toArray();
        $rma = Stock_model::whereDoesntHave('order_items', function ($q) use ($rmas) {
                $q->whereIn('order_id', $rmas);
            })->whereHas('variation', function ($q) {
                $q->where('grade', 10);
            })->Where('status',2)->count();
        $data['rma'] = $rma;

        $data['graded_inventory'] = Stock_model::select('grade.name as grade', 'variation.grade as grade_id', 'orders.status as status_id', DB::raw('COUNT(*) as quantity'))
        ->whereNotIn('stock.id', $aftersale)
        ->where('stock.status', 1)
        ->join('variation', 'stock.variation_id', '=', 'variation.id')
        ->join('grade', 'variation.grade', '=', 'grade.id')
        ->join('orders', 'stock.order_id', '=', 'orders.id')
        ->groupBy('variation.grade', 'grade.name', 'orders.status')
        ->orderBy('grade_id')
        ->get();
        $data['aftersale_inventory'] = Stock_model::select('grade.name as grade', 'variation.grade as grade_id', 'orders.status as status_id', 'stock.status as stock_status', DB::raw('COUNT(*) as quantity'))
        ->where('stock.status', 2)
        ->join('variation', 'stock.variation_id', '=', 'variation.id')
        ->join('grade', 'variation.grade', '=', 'grade.id')
        ->whereIn('grade.id',[8,12,17])
        ->join('orders', 'stock.order_id', '=', 'orders.id')
        ->groupBy('variation.grade', 'grade.name', 'orders.status', 'stock.status')
        ->orderBy('grade_id')
        ->get();

        $data['listed_inventory'] = Variation_model::where('listed_stock','>',0)->sum('listed_stock');
        $replacements = Order_item_model::where(['order_id'=>8974])->where('reference_id','!=',null)->pluck('reference_id')->toArray();
        // dd($replacements);
        $data['awaiting_replacement'] = Stock_model::where('status', 1)
        ->whereHas('order_items.order', function ($q) use ($replacements) {
            $q->where(['status'=>3, 'order_type_id'=>3])
            ->whereNotIn('reference_id', $replacements);
        })
        ->count();




        $testing_count = Admin_model::withCount(['stock_operations' => function($q) use ($start_date,$end_date) {
            $q->select(DB::raw('count(distinct stock_id)'))->where('description','LIKE','%DrPhone')->whereBetween('created_at', [$start_date, $end_date]);
        }])->get();
        $data['testing_count'] = $testing_count;

        $order = [];
        $dates = [];
        for ($i = 1; $i <= date('d'); $i++) {
            $start = date('Y-m-' . $i . ' 00:00:00');
            $end = date('Y-m-' . $i . ' 23:59:59');
            $orders = Order_model::where('created_at', '>', $start)->where('order_type_id',3)
                ->where('created_at', '<=', $end)->count();
            $order[$i] = $orders;
            $dates[$i] = $i;
        }
        echo '<script> sessionStorage.setItem("approved", "' . implode(',', $order) . '");</script>';
        echo '<script> sessionStorage.setItem("dates", "' . implode(',', $dates) . '");</script>';
        if (session('user')->hasPermission('10_day_sales_chart')){
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

            if($i == 0 && $today > 25){
                $k++;
                $start = date('Y-m-25 23:00:00', strtotime("-".$i." months"));
                $end = date('Y-m-5 22:59:59', strtotime("+1 months"));
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
                $dates[$k] = date('25 M', strtotime("-".$i." months")) . " - " . date('05 M', strtotime("+1 months"));
            }
        }
        echo '<script> sessionStorage.setItem("total2", "' . implode(',', $order) . '");</script>';
        echo '<script> sessionStorage.setItem("approved2", "' . implode(',', $eur) . '");</script>';
        echo '<script> sessionStorage.setItem("failed2", "' . implode(',', $gbp) . '");</script>';
        echo '<script> sessionStorage.setItem("dates2", "' . implode(',', $dates) . '");</script>';

        }
        $data['pending_orders_count'] = Order_model::where('status',2)->groupBy('order_type_id')->select('order_type_id', DB::raw('COUNT(id) as count'), DB::raw('SUM(price) as price'))->orderBy('order_type_id','asc')->get();



        $data['start_date'] = date('Y-m-d', strtotime($start_date));
        $data['end_date'] = date("Y-m-d", strtotime($end_date));
        return view('livewire.index')->with($data);
    }
}
