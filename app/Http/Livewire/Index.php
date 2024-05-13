<?php

namespace App\Http\Livewire;
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
use Illuminate\Support\Facades\Route;

class Index extends Component
{
    public function mount()
    {

    }
    public function render(Request $request)
    {
        // dd('Hello2');
        $user_id = session('user_id');

        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }
        $data['purchase_status'] = [2 => '(Pending)', 3 => ''];
        $data['products'] = Products_model::orderBy('model','asc')->get();
        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $data['variations'] = Variation_model::where('product_id',null)
        ->orderBy('name','desc')
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));

        $start_date = Carbon::now()->startOfDay();
        $end_date = date('Y-m-d 23:59:59');
        if (request('start_date') != NULL && request('end_date') != NULL) {
            $start_date = request('start_date') . " 00:00:00";
            $end_date = request('end_date') . " 23:59:59";
        }
        // $products = Products_model::get()->toArray();
        // Retrieve the top 10 selling products from the order_items table
        $data['top_products'] = Order_item_model::select('products.model as product_name', 'color.name as color', 'storage.name as storage', 'variation.sku as sku', 'grade.name as grade', DB::raw('SUM(order_items.quantity) as total_quantity_sold'), DB::raw('AVG(CASE WHEN orders.currency = 4 THEN order_items.price END) as average_price'))
        ->where('order_items.created_at', '>=', $start_date)->where('order_items.created_at', '<=', $end_date)->where('orders.order_type_id',3)
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
        $data['invoiced_orders'] = Order_model::where('processed_at', '>=', $start_date)->where('processed_at', '<=', $end_date)->where('order_type_id',3)->where('status',3)
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
        ->where('stock.status', '!=', 2)
        ->join('variation', 'stock.variation_id', '=', 'variation.id')
        ->join('grade', 'variation.grade', '=', 'grade.id')
        ->join('orders', 'stock.order_id', '=', 'orders.id')
        ->groupBy('variation.grade', 'grade.name', 'orders.status')
        ->orderBy('grade_id')
        ->get();


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



        $data['start_date'] = date('Y-m-d', strtotime($start_date));
        $data['end_date'] = date("Y-m-d", strtotime($end_date));
        return view('livewire.index')->with($data);
    }
}
