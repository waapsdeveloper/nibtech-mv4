<?php

namespace App\Http\Livewire;
use Livewire\Component;
use App\Models\Variation_model;
use App\Models\Products_model;
use App\Models\Stock_model;
use App\Models\Customer_model;
use App\Http\Controllers\BackMarketAPIController;
use App\Http\Controllers\ListingController;
use App\Models\ExchangeRate;
use App\Models\Listed_stock_verification_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;


class ListedStockVerification extends Component
{

    public function mount()
    {
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect('index');
        }
    }
    public function render()
    {

        $data['title_page'] = "Listed Stock Verification";
        session()->put('page_title', $data['title_page']);

        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 20;
        }

        $data['batches'] = Process_model::where('process_type_id', 21)
        ->when(request('start_date'), function ($q) {
            return $q->where('created_at', '>=', request('start_date'));
        })
        ->when(request('end_date'), function ($q) {
            return $q->where('created_at', '<=', request('end_date') . " 23:59:59");
        })
        ->when(request('batch_id'), function ($q) {
            return $q->where('reference_id', 'LIKE', request('batch_id') . '%');
        })
        ->when(request('status'), function ($q) {
            return $q->where('status', request('status'));
        })
        ->orderBy('reference_id', 'desc') // Secondary order by reference_id
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));


        // dd($data['orders']);
        return view('livewire.listed_stock_verification')->with($data);
    }

    public function start_listing_verification(){

        $check_active_verification = Process_model::where('process_type_id',21)->where('status',1)->first();
        if($check_active_verification != null){
            session()->flash('error', 'There is already an active listing verification process.');
            return redirect()->back();
        }
        $last_process = Process_model::where('process_type_id',21)->orderBy('reference_id','desc')->first();
        if($last_process != null){
            $last_process = $last_process->reference_id;
        }else{
            $last_process = 9000;
        }
        $listing_verification = new Process_model();
        $listing_verification->description = "Listing verification";
        $listing_verification->process_type_id = 21;
        $listing_verification->reference_id = $last_process + 1;
        $listing_verification->admin_id = session('user_id');
        $listing_verification->status = 1;
        $listing_verification->save();


        return redirect()->to(url('listed_stock_verification/detail/'.$listing_verification->id))->with('success', 'Listing verification process started successfully.');
    }
    public function zero_listing_verification($id){

        $bm = new BackMarketAPIController();
        $variations = Variation_model::where('listed_stock','>',0)->whereNotNull('reference_id')->get();
        $listing_verification = Process_model::find($id);

        foreach($variations as $variation){
            $updatedQuantity = $variation->update_qty($bm);
            $listed_stock_verification = Listed_stock_verification_model::firstOrNew(['process_id'=>$listing_verification->id, 'variation_id'=>$variation->id]);
            $listed_stock_verification->qty_from = $updatedQuantity;
            $listed_stock_verification->admin_id = session('user_id');
            $listed_stock_verification->save();

            $response = $bm->updateOneListing($variation->reference_id,json_encode(['quantity'=>0]));
            if(is_array($response)){
                $response = (object)$response;
            }
            if($response->quantity != null){
                $variation->listed_stock = $response->quantity;
                $variation->save();
            }
        }


        return redirect()->back()->with('success', 'Listed stock quantities set to zero successfully.');
    }
    public function close_verification($process_id){

        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 3000);
        ini_set('pdo_mysql.max_input_vars', '10000');
        $process = Process_model::find($process_id);
        $process->description = request('description');

        if(request('close') == 1){
            $process->status = 2;
            $process->save();
            return redirect()->back()->with('success', 'Listed stock Verification closed successfully.');
        }
        if(request('approve') == 1){
            $process->status = 2;
        }

        $bm = new BackMarketAPIController();

        $process_stocks = Process_stock_model::where('process_id', $process_id)->where('status',1)->get();
        if($process_stocks->count() > 0){

                $variation_qty = Process_stock_model::where('process_id', $process_id)->where('status',1)->groupBy('variation_id')->selectRaw('variation_id, Count(*) as total')->get();

                $wrong_variations = Variation_model::whereIn('id', $variation_qty->pluck('variation_id')->toArray())->whereNull('sku')->where('grade', '<', 6)->get();
                if($wrong_variations->count() > 0){
                    $error = 'Please add SKU for the following variations:';
                    foreach($wrong_variations as $variation){
                        $error .= ' '.$variation->product->model.' - '.$variation->storage_id->name.' - '.$variation->color_id->name.' - '.$variation->grade_id->name;
                    }
                    session()->put('error', $error);
                    return redirect()->back();
                }
                $listingController = new ListingController();
                foreach($variation_qty as $variation){
                    echo $listingController->add_quantity($variation->variation_id, $variation->total, $process->id);
                }


        }
        $process->save();

        if(request('approve') == 1){
            return redirect()->back();
        }else{
            return "Updated";
        }
        // return redirect()->back();
    }
    public function verification_detail($process_id){

        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 300);
        ini_set('pdo_mysql.max_input_vars', '10000');

        if(str_contains(url()->previous(),url('listed_stock_verification')) && !str_contains(url()->previous(),'detail')){
            session()->put('previous', url()->previous());
        }
        $data['title_page'] = "Listed Stock Verification Detail";
        session()->put('page_title', $data['title_page']);
        // $data['imeis'] = Stock_model::whereIn('status',[1,3])->orderBy('serial_number','asc')->orderBy('imei','asc')->get();
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 20;
        }
        $data['vendors'] = Customer_model::whereNotNull('is_vendor')->get();
        $data['exchange_rates'] = ExchangeRate::pluck('rate','target_currency');
        $data['storages'] = session('dropdown_data')['storages'];
        $data['products'] = Products_model::pluck('model','id');
        $data['grades'] = session('dropdown_data')['grades'];
        $data['colors'] = session('dropdown_data')['colors'];

        $process = Process_model::with(['process_stocks'])->find($process_id);
        $last_ten = Listed_stock_verification_model::where('process_id',$process_id)
            ->with(['stock.variation', 'stock.order.customer', 'stock.latest_operation'])
            ->orderBy('id','desc')
            ->limit($per_page)
            ->get();
        $data['last_ten'] = $last_ten;

        $data['scanned_total'] = Process_stock_model::where('process_id',$process_id)->where('admin_id',session('user_id'))->count();
        if($process->status == 2){
            $data['verified_total'] = Process_stock_model::where('process_id',$process_id)->where('verified_by',session('user_id'))->count();
        }
        $listedStocks = Listed_stock_verification_model::where('process_id', $process_id)
            ->with(['variation', 'admin'])
            ->get();

        $changed_listed_stocks = Listed_stock_verification_model::where(['process_id'=>$process_id])
        // ->whereColumn('qty_from', '!=', 'qty_to')
        ->where(function($query) {
            $query->whereColumn('qty_from', '!=', 'qty_to')
                  ->orWhereNull('qty_from')
                  ->orWhereNull('qty_to');
        })
        ->with(['variation', 'admin'])
        ->orderByDesc('updated_at')->get();
        $data['changed_listed_stocks'] = $changed_listed_stocks;
        $same_listed_stocks = Listed_stock_verification_model::where(['process_id'=>$process_id])
        ->whereColumn('qty_from', 'qty_to')
        ->with(['variation', 'admin'])
        ->orderByDesc('updated_at')->get();
        $data['same_listed_stocks'] = $same_listed_stocks;

        // Only load all variations if absolutely needed - this can be very slow
        // $data['all_variations'] = Variation_model::whereNotNull('sku')->get();

        $data['process'] = Process_model::find($process_id);

        $data['process_id'] = $process_id;

        // Optimize process_stocks update - use bulk update instead of loop
        $process_stock_ids = $data['process']->process_stocks()
            ->whereHas('stock', function($q) {
                $q->where('status', 2);
            })
            ->where('status', '!=', 2)
            ->pluck('id');

        if($process_stock_ids->isNotEmpty()) {
            Process_stock_model::whereIn('id', $process_stock_ids)->update(['status' => 2]);
        }

        if(request('show') != null){
            // Optimize stocks query - get stock IDs directly from database instead of loading all process_stocks first
            $stock_ids = Process_stock_model::where('process_id', $process_id)
                ->where('status', 1)
                ->pluck('stock_id');
            $stocks = Stock_model::whereIn('id', $stock_ids)->get();

            // Get variation IDs more efficiently
            $variation_ids = Process_stock_model::where('process_id', $process_id)->pluck('variation_id')->unique();

            // Load variations with optimized eager loading
            $variations = Variation_model::whereIn('variation.id', $variation_ids)
            ->join('products', 'products.id', '=', 'variation.product_id')
            ->orderBy('products.model', 'asc')
            ->orderBy('variation.storage', 'asc')
            ->orderBy('variation.color', 'asc')
            ->orderBy('variation.grade', 'asc')
            ->select('variation.*')
            ->get();

            // Pre-calculate available and pending stocks using more efficient queries
            $variation_stats = [];

            // Get available stock counts in one query
            $available_counts = \DB::table('stock')
                ->whereIn('variation_id', $variation_ids)
                ->whereIn('status', [1, 3])
                ->groupBy('variation_id')
                ->pluck(\DB::raw('COUNT(*)'), 'variation_id');

            // Get pending order counts in one query
            $pending_counts = \DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('order_items.variation_id', $variation_ids)
                ->whereIn('orders.status', [1, 2, 3])
                ->groupBy('order_items.variation_id')
                ->pluck(\DB::raw('SUM(order_items.quantity)'), 'order_items.variation_id');

            foreach($variations as $variation) {
                $available_count = $available_counts[$variation->id] ?? 0;
                $pending_count = $pending_counts[$variation->id] ?? 0;
                $variation_stats[$variation->id] = [
                    'available_stock_count' => $available_count - $pending_count,
                    'pending_orders_count' => $pending_count
                ];
            }
            $data['variation_stats'] = $variation_stats;

            $data['variations'] = $variations;
            $data['stocks'] = $stocks;

            $data['listed_stock_totals_by_variation'] = $listedStocks
                ->groupBy('variation_id')
                ->map(function ($items) {
                    return $items->sum('qty_to');
                })
                ->toArray();
        }

        return view('livewire.listed_stock_verification_detail')->with($data);

    }

    public function undo_verification($id){
        $listed_stocks = Listed_stock_verification_model::where('process_id', $id)->get();
        $listingController = new ListingController();
        foreach($listed_stocks as $listed_stock){
            if($listed_stock->variation_id != null){
                $listingController->add_quantity($listed_stock->variation_id, $listed_stock->qty_from);
            }
        }
        session()->put('success', 'Listed Stock Verification Undoned');
        return redirect()->back();
    }

}
