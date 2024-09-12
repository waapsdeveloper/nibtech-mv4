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
use App\Models\Ip_address_model;
use App\Models\Variation_model;
use App\Models\Stock_model;
use DateTime;
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
        $data['products'] = Products_model::orderBy('model','asc')->pluck('model','id');
        $data['categories'] = Category_model::pluck('name','id');
        $data['brands'] = Brand_model::pluck('name','id');
        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::pluck('name','id');

        if(session('user')->hasPermission('add_ip')){
            if(Ip_address_model::where('ip',request()->ip())->where('status',1)->count() == 0){
                $data['add_ip'] = 1;
            }
        }
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
        if(request('data') == 1){

            $variation_ids = Variation_model::withoutGlobalScope('Status_not_3_scope')->select('id')
            ->when(request('product') != '', function ($q) {
                return $q->where('product_id', '=', request('product'));
            })
            ->when(request('sku') != '', function ($q) {
                return $q->where('sku', 'LIKE', '%'.request('sku').'%');
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

        if(session('user')->hasPermission('dashboard_top_selling_products')){
            $top_products = Order_item_model::when(request('data') == 1, function($q) use ($variation_ids){
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
        }

        if(session('user')->hasPermission('dashboard_view_total_orders')){
            $data['total_orders'] = Order_model::whereBetween('created_at', [$start_date, $end_date])->where('order_type_id',3)
            ->whereHas('order_items', function ($q) use ($variation_ids) {
                $q->when(request('data') == 1, function($q) use ($variation_ids){
                    return $q->whereIn('variation_id', $variation_ids);
                });
            })
            ->count();

            $data['pending_orders'] = Order_model::whereBetween('created_at', [$start_date, $end_date])->where('order_type_id',3)->where('status','<',3)
            ->whereHas('order_items', function ($q) use ($variation_ids) {
                $q->when(request('data') == 1, function($q) use ($variation_ids){
                    return $q->whereIn('variation_id', $variation_ids);
                });
            })
            ->count();
            $data['invoiced_orders'] = Order_model::where('processed_at', '>=', $start_date)->where('processed_at', '<=', $end_date)->where('order_type_id',3)
            ->whereHas('order_items', function ($q) use ($variation_ids) {
                $q->when(request('data') == 1, function($q) use ($variation_ids){
                    return $q->whereIn('variation_id', $variation_ids);
                });
            })
            ->count();
            $data['invoiced_items'] = Order_item_model::whereHas('order', function ($q) use ($start_date, $end_date) {
                $q->where('processed_at', '>=', $start_date)->where('processed_at', '<=', $end_date)->where('order_type_id',3);
            })->where('stock_id','!=',null)
            ->when(request('data') == 1, function($q) use ($variation_ids){
                    return $q->whereIn('variation_id', $variation_ids);
                })
            ->count();


            $data['total_conversations'] = Order_item_model::whereBetween('created_at', [$start_date, $end_date])->where('care_id','!=',null)
            ->when(request('data') == 1, function($q) use ($variation_ids){
                return $q->whereIn('variation_id', $variation_ids);
            })->whereHas('sale_order')
            ->count();
            $data['order_items'] = Order_item_model::whereBetween('order_items.created_at', [$start_date, $end_date])
                ->when(request('data') == 1, function($q) use ($variation_ids){
                    return $q->whereIn('variation_id', $variation_ids);
                })
                ->selectRaw('AVG(CASE WHEN orders.currency = 4 THEN order_items.price END) as average_eur')
                ->selectRaw('SUM(CASE WHEN orders.currency = 4 THEN order_items.price END) as total_eur')
                ->selectRaw('SUM(CASE WHEN orders.currency = 5 THEN order_items.price END) as total_gbp')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->Where('orders.deleted_at',null)
                ->Where('order_items.deleted_at',null)
                ->first();

            $data['average'] = $data['order_items']->average_eur;
            $data['total_eur'] = $data['order_items']->total_eur;
            $data['total_gbp'] = $data['order_items']->total_gbp;
        }
        if(session('user')->hasPermission('dashboard_view_testing')){
            $testing_count = Admin_model::withCount(['stock_operations' => function($q) use ($start_date,$end_date) {
                // $q->select(DB::raw('count(distinct stock_id)'))->where('description','LIKE','%DrPhone')->whereBetween('created_at', [$start_date, $end_date]);
                $q->select(DB::raw('count(distinct stock_id)'))->where('process_id',1)->whereBetween('created_at', [$start_date, $end_date]);
            }])->get();
            $data['testing_count'] = $testing_count;
        }

        $aftersale = Order_item_model::whereHas('order', function ($q) {
            $q->where('order_type_id',4)->where('status','<',3);
        })->pluck('stock_id')->toArray();

        if (session('user')->hasPermission('dashboard_view_aftersale_inventory')){
            $data['returns_in_progress'] = count($aftersale);
            $rmas = Order_model::whereIn('order_type_id',[2,5])->pluck('id')->toArray();
            $rma = Stock_model::whereDoesntHave('order_items', function ($q) use ($rmas) {
                    $q->whereIn('order_id', $rmas);
                })->whereHas('variation', function ($q) {
                    $q->where('grade', 10);
                })->Where('status',2)->count();
            $data['rma'] = $rma;
            $data['aftersale_inventory'] = Stock_model::select('grade.name as grade', 'variation.grade as grade_id', 'orders.status as status_id', 'stock.status as stock_status', DB::raw('COUNT(*) as quantity'))
            ->where('stock.status', 2)
            ->join('variation', 'stock.variation_id', '=', 'variation.id')
            ->join('grade', 'variation.grade', '=', 'grade.id')
            ->whereIn('grade.id',[8,12,17])
            ->join('orders', 'stock.order_id', '=', 'orders.id')
            ->groupBy('variation.grade', 'grade.name', 'orders.status', 'stock.status')
            ->orderBy('grade_id')
            ->get();

            $replacements = Order_item_model::where(['order_id'=>8974])->where('reference_id','!=',null)->pluck('reference_id')->toArray();
            // dd($replacements);
            $data['awaiting_replacement'] = Stock_model::where('status', 1)
            ->whereHas('order_items.order', function ($q) use ($replacements) {
                $q->where(['status'=>3, 'order_type_id'=>3])
                ->whereNotIn('reference_id', $replacements);
            })
            ->count();

        }
        if (session('user')->hasPermission('dashboard_view_inventory')){
            $data['graded_inventory'] = Stock_model::select('grade.name as grade', 'variation.grade as grade_id', 'orders.status as status_id', DB::raw('COUNT(*) as quantity'))
            ->whereNotIn('stock.id', $aftersale)
            ->where('stock.status', 1)
            ->join('variation', 'stock.variation_id', '=', 'variation.id')
            ->join('grade', 'variation.grade', '=', 'grade.id')
            ->join('orders', 'stock.order_id', '=', 'orders.id')
            ->groupBy('variation.grade', 'grade.name', 'orders.status')
            ->orderBy('grade_id')
            ->get();
        }
        if (session('user')->hasPermission('dashboard_view_listing_total')){
            $data['listed_inventory'] = Variation_model::where('listed_stock','>',0)->sum('listed_stock');
        }
        if (session('user')->hasPermission('dashboard_view_pending_orders')){
            $data['pending_orders_count'] = Order_model::where('status',2)->groupBy('order_type_id')->select('order_type_id', DB::raw('COUNT(id) as count'), DB::raw('SUM(price) as price'))->orderBy('order_type_id','asc')->get();
        }


        if (session('user')->hasPermission('monthly_sales_chart')){
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
        }



        $data['start_date'] = date('Y-m-d', strtotime($start_date));
        $data['end_date'] = date("Y-m-d", strtotime($end_date));
        return view('livewire.index')->with($data);
    }
    public function toggle_amount_view(){
        if(session('amount_view') == 1){
            session()->put('amount_view',0);
        }else{
            session()->put('amount_view',1);
        }
        return redirect()->back();
    }
    public function add_ip(){
        $ip = request()->ip();
        $ip_address = new Ip_address_model();
        $ip_address->admin_id = session('user_id');
        $ip_address->ip = $ip;
        $ip_address->status = 1;
        $ip_address->save();
        return redirect()->back();
    }
    public function refresh_sales_chart(){
        $order = [];
        $dates = [];
        $k = 0;
        $today = date('d');
        for ($i = 2; $i >= 0; $i--) {
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
        echo '<script> ';
        echo 'sessionStorage.setItem("total2", "' . implode(',', $order) . '");';
        echo 'sessionStorage.setItem("approved2", "' . implode(',', $eur) . '");';
        echo 'sessionStorage.setItem("failed2", "' . implode(',', $gbp) . '");';
        echo 'sessionStorage.setItem("dates2", "' . implode(',', $dates) . '");';
        echo 'window.location.href = document.referrer; </script>';
        // sleep(2);
        // return redirect()->back();
    }
    // public function refresh_10_days_chart()
    // {
    //     $order = [];
    //     $eur = [];
    //     $gbp = [];
    //     $dates = [];
    //     $k = 0;
    //     $today = date('d');
    //     if ($today > 25) {
    //         $i = 25;
    //     } elseif ($today > 15) {
    //         $i = 15;
    //     } elseif ($today > 5) {
    //         $i = 5;
    //     } else {
    //         $i = 25;
    //     }
    //     for ($i; $i <= date('d'); $i++) {
    //         $l = $today - $i;
    //         $date = date('d-m-Y', strtotime("-" . $l . " days"));
    //         // Remove the unused variable $j
    //         $k++;
    //         $start = date('Y-m-d 00:00:00', strtotime("-" . $l . " days"));
    //         $end = date('Y-m-d 23:59:59', strtotime("-" . $l . " days"));
    //         if ($today < 6) {
    //             $month = date('m', strtotime("-1 months"));
    //             $start = date('Y-' . $month . '-d 00:00:00', strtotime("-" . $l . " days"));
    //         }
    //         $orders = Order_model::where('created_at', '>=', $start)
    //             ->where('order_type_id', 3)
    //             ->whereIn('status', [3, 6])
    //             ->where('created_at', '<=', $end)
    //             ->count();
    //         $euro = Order_item_model::whereHas('order', function ($q) use ($start, $end) {
    //             $q->where('processed_at', '>=', $start)
    //                 ->where('order_type_id', 3)
    //                 ->where('processed_at', '<=', $end)
    //                 ->whereIn('status', [3, 6])
    //                 ->where('currency', 4);
    //         })->whereIn('status', [3, 6])->sum('price');
    //         $pound = Order_item_model::whereHas('order', function ($q) use ($start, $end) {
    //             $q->where('processed_at', '>=', $start)
    //                 ->where('order_type_id', 3)
    //                 ->where('processed_at', '<=', $end)
    //                 ->whereIn('status', [3, 6])
    //                 ->where('currency', 5);
    //         })->whereIn('status', [3, 6])->sum('price');
    //         $order[$k] = $orders;
    //         $eur[$k] = $euro;
    //         $gbp[$k] = $pound;
    //         $dates[$k] = $date;
    //     }
    //     echo '<script> ';
    //     echo 'sessionStorage.setItem("total3", "' . implode(',', $order) . '");';
    //     echo 'sessionStorage.setItem("approved3", "' . implode(',', $eur) . '");';
    //     echo 'sessionStorage.setItem("failed3", "' . implode(',', $gbp) . '");';
    //     echo 'sessionStorage.setItem("dates3", "' . implode(',', $dates) . '");';
    //     echo 'window.location.href = document.referrer; </script>';
    // }
    public function refresh_10_days_chart()
    {
        $order = [];
        // $eur = [];
        // $gbp = [];
        $dates = [];

        $today = date('d');
        $current_month = date('m');
        $current_year = date('Y');

        // Determine the date range based on the current day
        if ($today >= 6 && $today <= 15) {
            $start_day = 6;
            $end_day = 15;
        } elseif ($today >= 16 && $today <= 25) {
            $start_day = 16;
            $end_day = 25;
        } else {
            // Handle the case for 26th to 5th of the next month
            $start_day = 26;
            $end_day = 5;

            // If today is between 1 and 5, set the start to the previous month
            if ($today <= 5) {
                $current_month = date('m', strtotime('-1 month'));
                $current_year = date('Y', strtotime('-1 month'));
            }
        }

        $i = $start_day;
        while (true) {
            // Handle day, month, and year transitions
            $date_str = "$current_year-$current_month-$i";
            $start = date('Y-m-d 00:00:00', strtotime($date_str));
            $end = date('Y-m-d 23:59:59', strtotime($date_str));

            $orders = Order_model::where('created_at', '>=', $start)
                ->where('created_at', '<=', $end)
                ->where('order_type_id', 3)
                // ->whereIn('status', [2, 3, 6])
                ->count();

            // $euro = Order_item_model::whereHas('order', function ($q) use ($start, $end) {
            //     $q->where('processed_at', '>=', $start)
            //         ->where('processed_at', '<=', $end)
            //         ->where('order_type_id', 3)
            //         ->whereIn('status', [3, 6])
            //         ->where('currency', 4);
            // })->whereIn('status', [3, 6])->sum('price');

            // $pound = Order_item_model::whereHas('order', function ($q) use ($start, $end) {
            //     $q->where('processed_at', '>=', $start)
            //         ->where('processed_at', '<=', $end)
            //         ->where('order_type_id', 3)
            //         ->whereIn('status', [3, 6])
            //         ->where('currency', 5);
            // })->whereIn('status', [3, 6])->sum('price');

            $order[] = $orders;
            // $eur[] = $euro;
            // $gbp[] = $pound;
            $dates[] = date('d-m-Y', strtotime($date_str));

            // Move to the next day
            if ($i == $end_day) {
                break;
            }

            $i++;
            // Handle end of month transition
            if ($i > date('t', strtotime("$current_year-$current_month-01"))) {
                $i = 1; // Reset day to 1
                $current_month = date('m', strtotime('+1 month', strtotime("$current_year-$current_month-01")));
                $current_year = date('Y', strtotime('+1 month', strtotime("$current_year-$current_month-01")));
            }

            // Handle start of month transition from 26th to 5th
            if ($start_day == 26 && $i == 6) {
                break;
            }
        }

        $order_data = implode(',', $order);
        // $eur_data = implode(',', $eur);
        // $gbp_data = implode(',', $gbp);
        $dates_data = implode(',', $dates);

        echo '<script>
            sessionStorage.setItem("total3", "' . $order_data . '");
            sessionStorage.setItem("dates3", "' . $dates_data . '");
        </script>';
        $order2 = [];
        // $eur = [];
        // $gbp = [];
        $dates2 = [];

        $today2 = $start_day-1;
        $current_month2 = date('m');
        $current_year2 = date('Y');

        // Determine the date range based on the current day
        if ($today2 >= 6 && $today2 <= 15) {
            $start_day = 6;
            $end_day = 15;
        } elseif ($today2 >= 16 && $today2 <= 25) {
            $start_day = 16;
            $end_day = 25;
                $current_month2 = date('m', strtotime('-1 month'));
                $current_year2 = date('Y', strtotime('-1 month'));
        } else {
            // Handle the case for 26th to 5th of the next month
            $start_day = 26;
            $end_day = 5;

            // If today2 is between 1 and 5, set the start to the previous month
            // if ($today2 <= 5 || $today2 <= 25) {
                $current_month2 = date('m', strtotime('-1 month'));
                $current_year2 = date('Y', strtotime('-1 month'));
            // }
        }

        $i = $start_day;
        while (true) {
            // Handle day, month, and year transitions
            $date_str = "$current_year2-$current_month2-$i";
            $start = date('Y-m-d 00:00:00', strtotime($date_str));
            $end = date('Y-m-d 23:59:59', strtotime($date_str));

            $orders2 = Order_model::where('created_at', '>=', $start)
                ->where('created_at', '<=', $end)
                ->where('order_type_id', 3)
                // ->whereIn('status', [2, 3, 6])
                ->count();

            // $euro = Order_item_model::whereHas('order', function ($q) use ($start, $end) {
            //     $q->where('processed_at', '>=', $start)
            //         ->where('processed_at', '<=', $end)
            //         ->where('order_type_id', 3)
            //         ->whereIn('status', [3, 6])
            //         ->where('currency', 4);
            // })->whereIn('status', [3, 6])->sum('price');

            // $pound = Order_item_model::whereHas('order', function ($q) use ($start, $end) {
            //     $q->where('processed_at', '>=', $start)
            //         ->where('processed_at', '<=', $end)
            //         ->where('order_type_id', 3)
            //         ->whereIn('status', [3, 6])
            //         ->where('currency', 5);
            // })->whereIn('status', [3, 6])->sum('price');

            $order2[] = $orders2;
            // $eur[] = $euro;
            // $gbp[] = $pound;
            $dates2[] = date('d-m-Y', strtotime($date_str));

            // Move to the next day
            if ($i == $end_day) {
                break;
            }

            $i++;
            // Handle end of month transition
            if ($i > date('t', strtotime("$current_year2-$current_month2-01"))) {
                $i = 1; // Reset day to 1
                $current_month2 = date('m');
                $current_year2 = date('Y');
                // $current_month = date('m', strtotime('+1 month', strtotime("$current_year-$current_month-01")));
                // $current_year = date('Y', strtotime('+1 month', strtotime("$current_year-$current_month-01")));
            }

            // Handle start of month transition from 26th to 5th
            if ($start_day == 26 && $i == 6) {
                break;
            }
        }

        $order_data2 = implode(',', $order2);
        // $eur_data = implode(',', $eur);
        // $gbp_data = implode(',', $gbp);
        $dates_data = implode(',', $dates2);

        echo '<script>
            sessionStorage.setItem("total32", "' . $order_data2 . '");
            sessionStorage.setItem("dates32", "' . $dates_data . '");
            window.location.href = document.referrer;
        </script>';
    }


    public function test(){
        $stocks = Stock_model::whereDoesntHave('stock_operations')->whereDoesntHave('process_stocks')->where('order_id',73262)->where('status',1)->get();
        foreach($stocks as $stock){
            $old_stock = $stock->onlyTrashed()->where('imei',$stock->imei)->where('serial_number',$stock->serial_number)->first();
            if($old_stock != null){
                print_r($old_stock);
                print_r($stock);
                $old_stock->restore();
                $old_stock->status = 1;
                $old_stock->order_id = $stock->order_id;
                $old_stock->save();
                foreach($stock->order_items as $item){
                    $item->stock_id = $old_stock->id;
                    $item->save();
                }
                $stock->delete();
                $stock->forceDelete();
            }
        }
    }

}
