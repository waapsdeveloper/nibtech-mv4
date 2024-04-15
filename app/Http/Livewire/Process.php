<?php

namespace App\Http\Livewire;
use Livewire\Component;

use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Variation_model;
use App\Models\Multi_type_model;
use Illuminate\Support\Facades\DB;
use App\Models\Order_status_model;
use App\Models\Customer_model;
use App\Models\Currency_model;
use App\Models\Process_model;

class Process extends Component
{

    public function mount()
    {

    }
    public function render()
    {

        $user_id = session('user_id');
        $data['process_types'] = Multi_type_model::where('table_name','process')->get();
        $data['vendors'] = Customer_model::where('is_vendor',1)->pluck('first_name','id');
        $data['currencies'] = Currency_model::pluck('sign','id');
        $data['order_statuses'] = Order_status_model::get();
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }

        $process_orders = Order_model::select('orders.id as id',DB::raw('COUNT(order_items.id) as total_quantity'),)
        ->where('orders.order_type_id',1)
        ->leftJoin('process', 'orders.id', '=', 'process.order_id')
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->whereNull('process.order_id')
        ->groupBy('orders.id')
        ->get();
        foreach($process_orders as $pro){
            $process = Process_model::firstOrNew(['order_id' => $pro->id,'process_type_id' => 6]);
            $process->quantity = $pro->total_quantity;
            $process->status = 1;
            $process->save();
        }

        // $process_sold = Order_item_model::select('order_items.stock_id')
        // ->join('orders', 'order_items.order_id', '=', 'orders.id')
        // ->whereIn('orders.order_type_id', [1, 3]) // Filter by the required order_type_id values
        // ->groupBy('order_items.stock_id')
        // ->havingRaw('COUNT(*) = 2') // Ensure there are exactly two occurrences
        // ->paginate(30);
        // dd($process_sold);
        $items = Order_item_model::where('linked_id', '!=', null)
        ->whereHas('order', function ($q) {
            $q->where('order_type_id', 3);
        })
        ->pluck('linked_id');

        $process_sold = Order_model::select('orders.id', DB::raw('COUNT(order_items.id) as total_quantity'))
        ->where('orders.order_type_id', 1)
        ->whereIn('order_items.id', $items)
        ->leftJoin('process', function($join) {
            $join->on('orders.id', '=', 'process.order_id')
                 ->where('process.process_type_id', '=', 18);
        })
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->groupBy('orders.id')
        ->get();


        // dd($process_sold);
        foreach($process_sold as $pro){
            $process = Process_model::firstOrNew(['order_id' => $pro->id,'process_type_id' => 18]);
            $process->quantity = $pro->total_quantity;
            $process->status = 1;
            $process->save();
        }

        $items_grade_a = Order_item_model::where('linked_id', '!=', null)
        ->join('orders', 'order_items.order_id', '=', 'orders.id')
        ->join('variation', 'order_items.variation_id', '=', 'variation.id')
        ->where('orders.order_type_id', 3)
        ->where('variation.grade', 2)
        ->pluck('linked_id');

        $process_grade_a = Order_model::select('orders.id', DB::raw('COUNT(order_items.id) as total_quantity'))
        ->where('orders.order_type_id', 1)
        ->whereIn('order_items.id', $items_grade_a)
        ->leftJoin('process', function($join) {
            $join->on('orders.id', '=', 'process.order_id')
                 ->where('process.process_type_id', '=', 14);
        })
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->join('variation', 'order_items.variation_id', '=', 'variation.id')
        ->groupBy('orders.id')
        ->limit(20)
        ->get();



        // dd($process_grade_a);
        foreach($process_grade_a as $pro){
            $process = Process_model::firstOrNew(['order_id' => $pro->id,'process_type_id' => 14]);
            $process->quantity = $pro->total_quantity;
            $process->status = 1;
            $process->save();
        }

        $items_grade_b = Order_item_model::where('linked_id', '!=', null)
        ->join('orders', 'order_items.order_id', '=', 'orders.id')
        ->join('variation', 'order_items.variation_id', '=', 'variation.id')
        ->where('orders.order_type_id', 3)
        ->where('variation.grade', 3)
        ->pluck('linked_id');

        $process_grade_b = Order_model::select('orders.id', DB::raw('COUNT(order_items.id) as total_quantity'))
        ->where('orders.order_type_id', 1)
        ->whereIn('order_items.id', $items_grade_b)
        ->leftJoin('process', function($join) {
            $join->on('orders.id', '=', 'process.order_id')
                 ->where('process.process_type_id', '=', 15);
        })
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->join('variation', 'order_items.variation_id', '=', 'variation.id')
        ->groupBy('orders.id')
        ->limit(20)
        ->get();



        // dd($process_grade_b);
        foreach($process_grade_b as $pro){
            $process = Process_model::firstOrNew(['order_id' => $pro->id,'process_type_id' => 15]);
            $process->quantity = $pro->total_quantity;
            $process->status = 1;
            $process->save();
        }

        $items_grade_c = Order_item_model::where('linked_id', '!=', null)
        ->join('orders', 'order_items.order_id', '=', 'orders.id')
        ->join('variation', 'order_items.variation_id', '=', 'variation.id')
        ->where('orders.order_type_id', 3)
        ->where('variation.grade', 5)
        ->pluck('linked_id');

        $process_grade_c = Order_model::select('orders.id', DB::raw('COUNT(order_items.id) as total_quantity'))
        ->where('orders.order_type_id', 1)
        ->whereIn('order_items.id', $items_grade_c)
        ->leftJoin('process', function($join) {
            $join->on('orders.id', '=', 'process.order_id')
                 ->where('process.process_type_id', '=', 16);
        })
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->join('variation', 'order_items.variation_id', '=', 'variation.id')
        ->groupBy('orders.id')
        ->limit(20)
        ->get();



        // dd($process_grade_c);
        foreach($process_grade_c as $pro){
            $process = Process_model::firstOrNew(['order_id' => $pro->id,'process_type_id' => 16]);
            $process->quantity = $pro->total_quantity;
            $process->status = 1;
            $process->save();
        }

        // Get distinct process type IDs
        $process_types = Process_model::distinct()->pluck('process_type_id')->toArray();

        // Define select statements for each process type
        $selectStatements = [];
        foreach ($process_types as $process_type_id) {
            $selectStatements[] = DB::raw("SUM(CASE WHEN process.process_type_id = $process_type_id THEN process.quantity ELSE 0 END) AS total_quantity_$process_type_id");
        }

        // Add other select statements as needed
        $selectStatements[] = 'orders.id';
        $selectStatements[] = 'orders.reference_id';
        $selectStatements[] = 'orders.customer_id';
        $selectStatements[] = 'orders.created_at';

        // Retrieve data
        $data['orders'] = Order_model::select($selectStatements)
            ->where('orders.order_type_id', 1)
            ->leftJoin('process', 'orders.id', '=', 'process.order_id')
            ->groupBy('orders.id', 'orders.reference_id', 'orders.customer_id', 'orders.created_at')
            ->orderBy('orders.reference_id', 'desc')
            ->paginate($per_page)
            ->onEachSide(5)
            ->appends(request()->except('page'));

        // $data['items'] = Order_model::
        // whereDoesntHave('order_items', function ($query) use ($items) {
        //     $query->whereIn('id', $items);
        // })->where('order_type_id',1)->get();

        $data['items'] = Variation_model::whereHas('stocks.order_item', function($query) use ($items) {
            $query->whereNotIn('id', $items);
        })
        ->with('stocks','stocks.order_item','stocks.variation')
        ->orderBy('grade','desc')
        ->paginate($per_page)
        ->appends(request()->except('page2'));



        // dd($data['items']);
        return view('livewire.process')->with($data);
    }


}
