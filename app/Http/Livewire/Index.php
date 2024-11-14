<?php

namespace App\Http\Livewire;

use App\Exports\StockSummeryExport;
use App\Http\Controllers\BackMarketAPIController;
use App\Models\Admin_model;
use App\Models\Brand_model;
use App\Models\Category_model;
use App\Models\Charge_value_model;
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
use App\Models\Order_charge_model;
use App\Models\Product_storage_sort_model;
use App\Models\Variation_model;
use App\Models\Stock_model;

class Index extends Component
{
    public function mount()
    {

    }
    public function render()
    {
        session()->forget('rep');
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
            $data['missing_imei'] = Order_item_model::whereHas('order', function ($q) use ($start_date, $end_date) {
                $q->where('processed_at', '>=', $start_date)->where('processed_at', '<=', $end_date)->where('order_type_id',3);
            })->where('stock_id',0)->count();

            $data['total_conversations'] = Order_item_model::whereBetween('created_at', [$start_date, $end_date])->where('care_id','!=',null)
            ->when(request('data') == 1, function($q) use ($variation_ids){
                return $q->whereIn('variation_id', $variation_ids);
            })->whereHas('sale_order')->count();

            $data['total_order_items'] = Order_item_model::whereBetween('order_items.created_at', [$start_date, $end_date])
                ->when(request('data') == 1, function($q) use ($variation_ids){
                    return $q->whereIn('variation_id', $variation_ids);
                })

                ->selectRaw('AVG(CASE WHEN orders.currency = 4 THEN order_items.price END) as average_eur')
                ->selectRaw('SUM(CASE WHEN orders.currency = 4 THEN order_items.price END) as total_eur')
                ->selectRaw('SUM(CASE WHEN orders.currency = 5 THEN order_items.price END) as total_gbp')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.order_type_id',3)
                ->Where('orders.deleted_at',null)
                ->Where('order_items.deleted_at',null)
                ->first();

            $data['ttl_average'] = $data['total_order_items']->average_eur;
            $data['ttl_eur'] = $data['total_order_items']->total_eur;
            $data['ttl_gbp'] = $data['total_order_items']->total_gbp;


            $data['order_items'] = Order_item_model::whereBetween('orders.processed_at', [$start_date, $end_date])
                ->when(request('data') == 1, function($q) use ($variation_ids){
                    return $q->whereIn('variation_id', $variation_ids);
                })

                ->selectRaw('AVG(CASE WHEN orders.currency = 4 THEN order_items.price END) as average_eur')
                ->selectRaw('SUM(CASE WHEN orders.currency = 4 THEN order_items.price END) as total_eur')
                ->selectRaw('SUM(CASE WHEN orders.currency = 5 THEN order_items.price END) as total_gbp')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.order_type_id',3)
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
            ->whereDoesntHave('sale_order', function ($query) {
                $query->where('customer_id', 3955);
            })
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
    public function refresh_sales_chart() {
        $order = [];
        $eur = [];
        $gbp = [];
        $dates = [];
        $k = 0;

        // Loop for the last 3 weeks
        for ($i = 8; $i >= 0; $i--) {
            $k++;

            // Week 1: Wednesday to Tuesday

            $start = date('Y-m-d 00:00:00', strtotime('last Wednesday - ' . ($i * 7) . ' days'));
            $end = date('Y-m-d 23:59:59', strtotime('next Tuesday - ' . ($i * 7) . ' days'));
            // If today is Wednesday
            if (date('w') == 3) {
                $start = date('Y-m-d 00:00:00', strtotime('this Wednesday - ' . ($i * 7) . ' days'));
            }
            if (date('w') == 2) {
                $end = date('Y-m-d 23:59:59', strtotime('this Tuesday - ' . ($i * 7) . ' days'));
            }

            // Fetch orders and prices in Euros and Pounds
            $orders = Order_model::where('processed_at', '>=', $start)
                ->where('processed_at', '<=', $end)
                ->where('order_type_id', 3)
                ->whereIn('status', [3, 6])
                ->count();

            $euro = Order_item_model::whereHas('order', function ($q) use ($start, $end) {
                $q->where('processed_at', '>=', $start)
                  ->where('processed_at', '<=', $end)
                  ->where('order_type_id', 3)
                  ->whereIn('status', [3, 6])
                  ->where('currency', 4);
            })->sum('price');

            $pound = Order_item_model::whereHas('order', function ($q) use ($start, $end) {
                $q->where('processed_at', '>=', $start)
                  ->where('processed_at', '<=', $end)
                  ->where('order_type_id', 3)
                  ->whereIn('status', [3, 6])
                  ->where('currency', 5);
            })->sum('price');

            // Store the data
            $order[$k] = $orders;
            $eur[$k] = $euro;
            $gbp[$k] = $pound;
            $dates[$k] = date('d M', strtotime($start)) . " - " . date('d M', strtotime($end));
        }

        // Output the data as a script
        echo '<script> ';
        echo 'sessionStorage.setItem("total2", "' . implode(',', $order) . '");';
        echo 'sessionStorage.setItem("approved2", "' . implode(',', $eur) . '");';
        echo 'sessionStorage.setItem("failed2", "' . implode(',', $gbp) . '");';
        echo 'sessionStorage.setItem("dates2", "' . implode(',', $dates) . '");';
        echo 'window.location.href = document.referrer; </script>';
    }

    public function refresh_7_days_chart()
    {
        $order = [];
        $dates = [];

        // Get today's day of the week (1 = Monday, ..., 7 = Sunday)
        $today = date('w');

        // Calculate the start and end of the week (Wednesday to Tuesday)
        if ($today == 0) { // Sunday is considered as the 0th day in PHP
            $today = 7;
        }

        $days_since_wednesday = $today - 3; // 3 is for Wednesday
        if ($days_since_wednesday < 0) {
            $days_since_wednesday += 7;
        }

        $start = date('Y-m-d', strtotime('-' . $days_since_wednesday . ' days'));
        $end = date('Y-m-d', strtotime($start . ' +6 days'));

        $i = $start;
        while (true) {
            // Handle day, month, and year transitions
            $date_str = $i;
            $start_time = date('Y-m-d 00:00:00', strtotime($date_str));
            $end_time = date('Y-m-d 23:59:59', strtotime($date_str));

            $orders = Order_model::where('created_at', '>=', $start_time)
                ->where('created_at', '<=', $end_time)
                ->where('order_type_id', 3)
                ->count();

            $order[] = $orders;
            $dates[] = date('d-m-Y', strtotime($date_str));

            // Move to the next day
            if ($i == $end) {
                break;
            }

            $i = date('Y-m-d', strtotime($i . ' +1 day'));
        }

        $order_data = implode(',', $order);
        $dates_data = implode(',', $dates);

        echo '<script>
            sessionStorage.setItem("total3", "' . $order_data . '");
            sessionStorage.setItem("dates3", "' . $dates_data . '");
        </script>';

        // Second set of data for comparison (last 7 days, Wednesday to Tuesday)
        $order2 = [];
        $dates2 = [];

        // Get the previous week's Wednesday as the start day
        $start2 = date('Y-m-d', strtotime($start . ' -7 days'));
        $end2 = date('Y-m-d', strtotime($start2 . ' +6 days'));

        $i = $start2;
        while (true) {
            $date_str = $i;
            $start_time = date('Y-m-d 00:00:00', strtotime($date_str));
            $end_time = date('Y-m-d 23:59:59', strtotime($date_str));

            $orders2 = Order_model::where('created_at', '>=', $start_time)
                ->where('created_at', '<=', $end_time)
                ->where('order_type_id', 3)
                ->count();

            $order2[] = $orders2;
            $dates2[] = date('d-m-Y', strtotime($date_str));

            if ($i == $end2) {
                break;
            }

            $i = date('Y-m-d', strtotime($i . ' +1 day'));
        }

        $order_data2 = implode(',', $order2);
        $dates_data2 = implode(',', $dates2);

        echo '<script>
            sessionStorage.setItem("total32", "' . $order_data2 . '");
            sessionStorage.setItem("dates32", "' . $dates_data2 . '");
            window.location.href = document.referrer;
        </script>';
    }
    public function refresh_7_days_progressive_chart()
    {
        $order = [];
        $dates = [];
        $cumulative_orders = 0; // Track progressive sum

        // Get today's day of the week (1 = Monday, ..., 7 = Sunday)
        $today = date('w');

        // Calculate the start and end of the week (Wednesday to Tuesday)
        if ($today == 0) { // Sunday is considered as the 0th day in PHP
            $today = 7;
        }

        $days_since_wednesday = $today - 3; // 3 is for Wednesday
        if ($days_since_wednesday < 0) {
            $days_since_wednesday += 7;
        }

        $start = date('Y-m-d', strtotime('-' . $days_since_wednesday . ' days'));
        $end = date('Y-m-d', strtotime($start . ' +6 days'));

        $i = $start;
        while (true) {
            // Handle day, month, and year transitions
            $date_str = $i;
            $start_time = date('Y-m-d 00:00:00', strtotime($date_str));
            $end_time = date('Y-m-d 23:59:59', strtotime($date_str));

            $daily_orders = Order_model::where('created_at', '>=', $start_time)
                ->where('created_at', '<=', $end_time)
                ->where('order_type_id', 3)
                ->count();

            // Add daily orders to cumulative count
            $cumulative_orders += $daily_orders;

            // Store the progressive order count
            $order[] = $cumulative_orders;

            // Store the date
            $dates[] = date('d-m-Y', strtotime($date_str));

            // Move to the next day
            if ($i == $end) {
                break;
            }
            if($i == date('Y-m-d')){
                break;
            }
            $i = date('Y-m-d', strtotime($i . ' +1 day'));
        }

        // Prepare data for sessionStorage
        $order_data = implode(',', $order);
        $dates_data = implode(',', $dates);

        // Store the data in sessionStorage
        echo '<script>
            sessionStorage.setItem("total4", "' . $order_data . '");
            sessionStorage.setItem("dates4", "' . $dates_data . '");
        </script>';

        // Second set of data for comparison (previous 7 days, Wednesday to Tuesday)
        $order2 = [];
        $dates2 = [];
        $cumulative_orders2 = 0; // Track progressive sum for previous week

        // Get the previous week's Wednesday as the start day
        $start2 = date('Y-m-d', strtotime($start . ' -7 days'));
        $end2 = date('Y-m-d', strtotime($start2 . ' +6 days'));

        $i = $start2;
        while (true) {
            $date_str = $i;
            $start_time = date('Y-m-d 00:00:00', strtotime($date_str));
            $end_time = date('Y-m-d 23:59:59', strtotime($date_str));

            $daily_orders2 = Order_model::where('created_at', '>=', $start_time)
                ->where('created_at', '<=', $end_time)
                ->where('order_type_id', 3)
                ->count();

            // Add daily orders to cumulative count for previous week
            $cumulative_orders2 += $daily_orders2;

            // Store the progressive order count for previous week
            $order2[] = $cumulative_orders2;

            // Store the date
            $dates2[] = date('d-m-Y', strtotime($date_str));

            if ($i == $end2) {
                break;
            }

            $i = date('Y-m-d', strtotime($i . ' +1 day'));
        }

        // Prepare data for sessionStorage
        $order_data2 = implode(',', $order2);
        $dates_data2 = implode(',', $dates2);

        // Store the data for previous week in sessionStorage
        echo '<script>
            sessionStorage.setItem("total42", "' . $order_data2 . '");
            sessionStorage.setItem("dates42", "' . $dates_data2 . '");
            window.location.href = document.referrer;
        </script>';
    }

    public function stock_cost_summery(){

        $pdf = new StockSummeryExport();
        $pdf->generatePdf();

    }

    public function test(){
        // ini_set('max_execution_time', 1200);
        // ini_set('memory_limit', '2048M');
        // ini_set('group_concat_max_len', 4294967295);
        // $orders = Order_model::where('order_type_id',3)->where('status',3)->where('processed_at','>=','2024--08-01')->pluck('price');
        // echo "Orders: ".$orders->count()."<br>";
        // echo "Total Orders: ".array_sum($orders->toArray())."<br>";

        // $charge_values = Charge_value_model::whereHas('charge', function($q){
        //     $q->where('name','LIKE','%Payment Method Charge%');
        // })->pluck('id');
        // print_r($charge_values);
        // // dd($charge_values);
        // echo "Payment Charges: ".$charge_values->count()."<br>";
        // $order_charges = Order_charge_model::whereIn('charge_value_id', $charge_values->toArray())
        // ->whereHas('order', function($q) use ($orders){
        //     $q->where('order_type_id',3)->where('status',3)->where('processed_at','>=','2024--08-01');
        // })->get();
        // print_r($order_charges);

        // $all_charges = Order_charge_model::whereHas('order', function($q) use ($orders){
        //     $q->where('order_type_id',3)->where('status',3)->where('processed_at','>=','2024--08-01');
        // })->pluck('amount');
        // // echo "Payment Charges: ".$order_charges->count()."<br>";
        // echo "All Charges: ".$all_charges->count()."<br>";
        // echo "Total Payment Charges: ".array_sum($all_charges->toArray())."<br>";

        ini_set('max_execution_time', 1200);
        Variation_model::where('product_storage_sort_id',null)->each(function($variation){
            $pss = Product_storage_sort_model::firstOrNew(['product_id'=>$variation->product_id,'storage'=>$variation->storage]);
            if($pss->id == null){
                $pss->save();
            }
            $variation->product_storage_sort_id = $pss->id;
            $variation->save();
        });
        $order_c = new Order();
        Order_model::where('scanned',null)->where('order_type_id',3)->where('tracking_number', '!=', null)->whereBetween('created_at', ['2024-05-01 00:00:00', now()->subDays(1)->format('Y-m-d H:i:s')])
        ->orderByDesc('id')->each(function($order) use ($order_c){
            $order_c->getLabel($order->reference_id, false, true);
        });

        $bm = new BackMarketAPIController();
        $resArray = $bm->getlabelData();

        $orders = [];
        $deliveries = [];
        if ($resArray !== null) {
            foreach ($resArray as $data) {
                if (!empty($data) && $data->hubScanned == true && !in_array($data->order, $orders)) {
                    $orders[] = $data->order;

                }
                if (!empty($data) && !isset($deliveries[$data->order]) && $data->dateDelivery != null) {
                    $deliveries[$data->order] = Carbon::parse($data->dateDelivery);
                }
            }
        }

        if($orders != []){

            Order_model::whereIn('reference_id',$orders)->update(['scanned' => 1]);

        }
        if($deliveries != []){

            foreach($deliveries as $order => $delivery){
                Order_model::where('reference_id',$order)->update(['delivered_at' => $delivery]);
            }

        }


    }

}
