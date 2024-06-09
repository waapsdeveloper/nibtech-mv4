<?php

namespace App\Http\Livewire;

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
        // $aggregated_cost = Order_item_model::whereIn('stock_id',$stock_ids)->whereHas('order', function ($q) {
        //     $q->where('order_type_id',1);
        // })->sum('price');

        // dd($aggregates);

        // $data = Category_model::with(['products.variations.order_items.order'=> function($q) use ($start_date, $end_date) {
        //     $q->whereBetween('processed_at', [$start_date, $end_date])
        //     ->where('order_type_id', 3)
        //     ->select(
        //         DB::raw('COUNT(*) as orders_qty'),
        //         DB::raw('SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as approved_orders_qty'),
        //         DB::raw('SUM(CASE WHEN currency = 4 THEN (SELECT SUM(price) FROM order_items WHERE order_items.order_id = orders.id) ELSE 0 END) as eur_items_sum'),
        //         DB::raw('SUM(CASE WHEN currency = 4 AND status = 3 THEN (SELECT SUM(price) FROM order_items WHERE order_items.order_id = orders.id) ELSE 0 END) as eur_approved_items_sum'),
        //         DB::raw('SUM(CASE WHEN currency = 5 THEN (SELECT SUM(price) FROM order_items WHERE order_items.order_id = orders.id) ELSE 0 END) as gbp_items_sum'),
        //         DB::raw('SUM(CASE WHEN currency = 5 AND status = 3 THEN (SELECT SUM(price) FROM order_items WHERE order_items.order_id = orders.id) ELSE 0 END) as gbp_approved_items_sum')
        //     );
        // }])->get();
        // $data['sales'] = Category_model::with(['products.variations.order_items.order'=> function($q) use ($start_date, $end_date) {
        //     $q->whereBetween('processed_at', [$start_date, $end_date])
        //         ->where('order_type_id', 3)
        //         ->select(
        //             DB::raw('COUNT(*) as orders_qty'),
        //             DB::raw('SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as approved_orders_qty'),
        //             DB::raw('SUM(CASE WHEN currency = 4 THEN (SELECT SUM(price) FROM order_items WHERE order_items.order_id = orders.id) ELSE 0 END) as eur_items_sum'),
        //             DB::raw('SUM(CASE WHEN currency = 4 AND status = 3 THEN (SELECT SUM(price) FROM order_items WHERE order_items.order_id = orders.id) ELSE 0 END) as eur_approved_items_sum'),
        //             DB::raw('SUM(CASE WHEN currency = 5 THEN (SELECT SUM(price) FROM order_items WHERE order_items.order_id = orders.id) ELSE 0 END) as gbp_items_sum'),
        //             DB::raw('SUM(CASE WHEN currency = 5 AND status = 3 THEN (SELECT SUM(price) FROM order_items WHERE order_items.order_id = orders.id) ELSE 0 END) as gbp_approved_items_sum')
        //         );
        // }])->get();

        // $data['sales'] = Category_model::with([
        //     'products.variations.order_items' => function ($q) use ($start_date, $end_date) {
        //         $q->whereHas('order', function ($query) use ($start_date, $end_date) {
        //             $query->whereBetween('processed_at', [$start_date, $end_date])
        //                   ->where('order_type_id', 3);
        //         });
        //     }
        // ])
        // ->withCount([
        //     'products.variations.order_items as orders_qty' => function ($q) use ($start_date, $end_date) {
        //         $q->whereHas('order', function ($query) use ($start_date, $end_date) {
        //             $query->whereBetween('processed_at', [$start_date, $end_date])
        //                   ->where('order_type_id', 3);
        //         });
        //     },
        //     'products.variations.order_items as approved_orders_qty' => function ($q) use ($start_date, $end_date) {
        //         $q->whereHas('order', function ($query) use ($start_date, $end_date) {
        //             $query->whereBetween('processed_at', [$start_date, $end_date])
        //                   ->where('order_type_id', 3)
        //                   ->where('status', 3);
        //         });
        //     }
        // ])
        // ->withSum([
        //     'products.variations.order_items as eur_items_sum' => function ($q) use ($start_date, $end_date) {
        //         $q->whereHas('order', function ($query) use ($start_date, $end_date) {
        //             $query->whereBetween('processed_at', [$start_date, $end_date])
        //                   ->where('order_type_id', 3)
        //                   ->where('currency', 4);
        //         });
        //     },
        //     'products.variations.order_items as eur_approved_items_sum' => function ($q) use ($start_date, $end_date) {
        //         $q->whereHas('order', function ($query) use ($start_date, $end_date) {
        //             $query->whereBetween('processed_at', [$start_date, $end_date])
        //                   ->where('order_type_id', 3)
        //                   ->where('status', 3)
        //                   ->where('currency', 4);
        //         });
        //     },
        //     'products.variations.order_items as gbp_items_sum' => function ($q) use ($start_date, $end_date) {
        //         $q->whereHas('order', function ($query) use ($start_date, $end_date) {
        //             $query->whereBetween('processed_at', [$start_date, $end_date])
        //                   ->where('order_type_id', 3)
        //                   ->where('currency', 5);
        //         });
        //     },
        //     'products.variations.order_items as gbp_approved_items_sum' => function ($q) use ($start_date, $end_date) {
        //         $q->whereHas('order', function ($query) use ($start_date, $end_date) {
        //             $query->whereBetween('processed_at', [$start_date, $end_date])
        //                   ->where('order_type_id', 3)
        //                   ->where('status', 3)
        //                   ->where('currency', 5);
        //         });
        //     }
        // ], 'price')
        // ->get();

// Build the query
// $data['sales'] = Category_model::with(['products.variations.order_items.order' => function ($query) use ($start_date, $end_date) {
//     $query->whereBetween('processed_at', [$start_date, $end_date])
//           ->where('order_type_id', 3);
// }])
// ->get()
// ->map(function ($category) {
//     // Aggregate data within the category
//     $orders = $category->products->flatMap->variations->flatMap->order_items->pluck('order');

//     $orders_qty = $orders->count();
//     $approved_orders_qty = $orders->where('status', 3)->count();
//     $eur_items_sum = $orders->where('currency', 4)->sum(function ($order) {
//         return $order->order_items->sum('price');
//     });
//     $eur_approved_items_sum = $orders->where('currency', 4)->where('status', 3)->sum(function ($order) {
//         return $order->order_items->sum('price');
//     });
//     $gbp_items_sum = $orders->where('currency', 5)->sum(function ($order) {
//         return $order->order_items->sum('price');
//     });
//     $gbp_approved_items_sum = $orders->where('currency', 5)->where('status', 3)->sum(function ($order) {
//         return $order->order_items->sum('price');
//     });
//     // $items_cost_sum = $orders->where('status', '>=', 3)->flatMap->order_items->flatMap->stock->flatMap->purchase_item->sum('price');
//     // $items_repair_sum = $orders->where('status', '>=', 3)->flatMap->order_items->flatMap->stock->flatMap->process_stocks->sum('price');
//     // $items_repair_sum = $orders->sum(function ($order) {
//     //     return $order->order_items->stock->process_stock->whereHas('process',function ($q) { $q->where('process_type_id',9); })->sum('price');
//     // });

//     return [
//         'category' => $category->name,
//         'orders_qty' => $orders_qty,
//         'approved_orders_qty' => $approved_orders_qty,
//         'eur_items_sum' => $eur_items_sum,
//         'eur_approved_items_sum' => $eur_approved_items_sum,
//         'gbp_items_sum' => $gbp_items_sum,
//         'gbp_approved_items_sum' => $gbp_approved_items_sum,
//         // 'items_cost_sum' => $items_cost_sum,
//         // 'items_repair_sum' => $items_repair_sum,
//     ];
// });


    // dd($data);

        $data['top_products'] = Order_item_model::select('products.model as product_name', 'color.name as color', 'storage.name as storage', 'variation.sku as sku', 'grade.name as grade', DB::raw('SUM(order_items.quantity) as total_quantity_sold'), DB::raw('AVG(CASE WHEN orders.currency = 4 THEN order_items.price END) as average_price'))
        ->where('orders.created_at', '>=', $start_date)->where('orders.created_at', '<=', $end_date)->where('orders.order_type_id',3)
        ->join('variation', 'order_items.variation_id', '=', 'variation.id')
        ->join('orders', 'order_items.order_id', '=', 'orders.id')
        ->join('products', 'variation.product_id', '=', 'products.id')
        ->join('color', 'variation.color', '=', 'color.id')
        ->join('storage', 'variation.storage', '=', 'storage.id')
        ->join('grade', 'variation.grade', '=', 'grade.id')

        ->when(request('product') != '', function ($q) {
            return $q->where('products.id', '=', request('product'));
        })
        ->when(request('storage') != '', function ($q) {
            return $q->where('variation.storage', 'LIKE', request('storage') . '%');
        })
        ->when(request('color') != '', function ($q) {
            return $q->where('variation.color', 'LIKE', request('color') . '%');
        })
        ->when(request('grade') != '', function ($q) {
            return $q->where('variation.grade', 'LIKE', request('grade') . '%');
        })
        ->groupBy('order_items.variation_id', 'products.model', 'storage.name', 'color.name', 'variation.sku', 'grade.name')
        ->orderByDesc('total_quantity_sold')
        ->take($per_page)
        ->get();

        $data['total_orders'] = Order_model::where('created_at', '>=', $start_date)->where('created_at', '<=', $end_date)->where('order_type_id',3)

        ->when(request('product') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('product_id', '=', request('product'));
            });
        })
        ->when(request('storage') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('variation.storage', 'LIKE', request('storage') . '%');
            });
        })
        ->when(request('color') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('variation.color', 'LIKE', request('color') . '%');
            });
        })
        ->when(request('grade') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('variation.grade', 'LIKE', request('grade') . '%');
            });
        })
        ->count();
        $data['pending_orders'] = Order_model::where('created_at', '>=', $start_date)->where('created_at', '<=', $end_date)->where('order_type_id',3)->where('status','<',3)

        ->when(request('product') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('product_id', '=', request('product'));
            });
        })
        ->when(request('storage') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('variation.storage', 'LIKE', request('storage') . '%');
            });
        })
        ->when(request('color') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('variation.color', 'LIKE', request('color') . '%');
            });
        })
        ->when(request('grade') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('variation.grade', 'LIKE', request('grade') . '%');
            });
        })
        ->count();
        $data['invoiced_orders'] = Order_model::where('processed_at', '>=', $start_date)->where('processed_at', '<=', $end_date)->where('order_type_id',3)
        // ->whereHas('admin', function ($q) {
        //     $q->where('role_id', '<=', 5);
        // })
        ->when(request('product') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('product_id', '=', request('product'));
            });
        })
        ->when(request('storage') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('variation.storage', 'LIKE', request('storage') . '%');
            });
        })
        ->when(request('color') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('variation.color', 'LIKE', request('color') . '%');
            });
        })
        ->when(request('grade') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('variation.grade', 'LIKE', request('grade') . '%');
            });
        })
        ->count();
        $data['total_conversations'] = Order_item_model::where('created_at', '>=', $start_date)->where('created_at', '<=', $end_date)->where('care_id','!=',null)
        ->when(request('product') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('product_id', '=', request('product'));
            });
        })
        ->when(request('storage') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('variation.storage', 'LIKE', request('storage') . '%');
            });
        })
        ->when(request('color') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('variation.color', 'LIKE', request('color') . '%');
            });
        })
        ->when(request('grade') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('variation.grade', 'LIKE', request('grade') . '%');
            });
        })
        ->count();

        $data['graded_inventory'] = Stock_model::select('grade.name as grade', 'variation.grade as grade_id', 'orders.status as status_id', DB::raw('COUNT(*) as quantity'))
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


        $replacements = Order_item_model::where(['order_id'=>8974])->where('reference_id','!=',null)->pluck('reference_id')->toArray();
        // dd($replacements);
        $data['awaiting_replacement'] = Stock_model::where('status', 1)
        ->whereHas('order_items.order', function ($q) use ($replacements) {
            $q->where(['status'=>3, 'order_type_id'=>3])
            ->whereNotIn('reference_id', $replacements);
        })
        ->count();




        $testing_count = Admin_model::withCount(['stock_operations' => function($q) use ($start_date,$end_date) {
            $q->select(DB::raw('count(distinct stock_id)'))->where('description','LIKE','%DrPhone')->where('created_at', '>=', $start_date)->where('created_at', '<=', $end_date);
        }])->get();
        $data['testing_count'] = $testing_count;

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
                ->where('processed_at', '<=', $end)->where('status',3)->count();
            $euro = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->where('status',3)->where('currency',4);
            })->sum('price');
            $pound = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->where('status',3)->where('currency',5);
            })->sum('price');
            $order[$k] = $orders;
            $eur[$k] = $euro;
            $gbp[$k] = $pound;
            $dates[$k] = date('26-m-Y', strtotime("-".$j." months")) . " - " . date('05-m-Y', strtotime("-".$i." months"));
            if($i == 0 && $today < 6){
                continue;
            }
            $k++;
            $start = date('Y-m-6 00:00:00', strtotime("-".$i." months"));
            $end = date('Y-m-15 23:59:59', strtotime("-".$i." months"));
            $orders = Order_model::where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->where('status',3)->count();
            $euro = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->where('status',3)->where('currency',4);
            })->sum('price');
            $pound = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->where('status',3)->where('currency',5);
            })->sum('price');
            $order[$k] = $orders;
            $eur[$k] = $euro;
            $gbp[$k] = $pound;
            $dates[$k] = date('06-m-Y', strtotime("-".$i." months")) . " - " . date('15-m-Y', strtotime("-".$i." months"));
            if($i == 0 && $today < 16){
                continue;
            }
            $k++;
            $start = date('Y-m-16 00:00:00', strtotime("-".$i." months"));
            $end = date('Y-m-25 23:59:59', strtotime("-".$i." months"));
            $orders = Order_model::where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->where('status',3)->count();
            $euro = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->where('status',3)->where('currency',4);
            })->sum('price');
            $pound = Order_item_model::whereHas('order', function($q) use ($start,$end) {
                $q->where('processed_at', '>=', $start)->where('order_type_id',3)
                ->where('processed_at', '<=', $end)->where('status',3)->where('currency',5);
            })->sum('price');
            $order[$k] = $orders;
            $eur[$k] = $euro;
            $gbp[$k] = $pound;
            $dates[$k] = date('16-m-Y', strtotime("-".$i." months")) . " - " . date('25-m-Y', strtotime("-".$i." months"));

        }
        echo '<script> sessionStorage.setItem("total", "' . implode(',', $order) . '");</script>';
        echo '<script> sessionStorage.setItem("approved", "' . implode(',', $eur) . '");</script>';
        echo '<script> sessionStorage.setItem("failed", "' . implode(',', $gbp) . '");</script>';
        echo '<script> sessionStorage.setItem("dates", "' . implode(',', $dates) . '");</script>';


        $data['pending_orders_count'] = Order_model::where('order_type_id',3)->where('status',2)->count();

        $data['start_date'] = date('Y-m-d', strtotime($start_date));
        $data['end_date'] = date("Y-m-d", strtotime($end_date));
        return view('livewire.report')->with($data);
    }
}
