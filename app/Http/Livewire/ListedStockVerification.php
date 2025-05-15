<?php

namespace App\Http\Livewire;
use Livewire\Component;
use App\Models\Variation_model;
use App\Models\Products_model;
use App\Models\Stock_model;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Customer_model;
use App\Models\Currency_model;
use App\Models\Storage_model;
use App\Exports\RepairsheetExport;
use App\Http\Controllers\ListingController;
use Maatwebsite\Excel\Facades\Excel;
use TCPDF;
use App\Models\Api_request_model;
use App\Models\Brand_model;
use App\Models\Category_model;
use App\Models\Color_model;
use App\Models\ExchangeRate;
use App\Models\Grade_model;
use App\Models\Listed_stock_verification_model;
use App\Models\Order_issue_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;
use App\Models\Product_storage_sort_model;
use App\Models\Stock_operations_model;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;


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
    public function close_verification($process_id){
        $process = Process_model::find($process_id);
        $process->description = request('description');

        if(request('approve') == 1){
            $process->status = 2;
        }


        $process_stocks = Process_stock_model::where('process_id', $process_id)->get();
        $sold_stocks = Process_stock_model::where('process_id', $process_id)
            ->whereHas('stock', function ($query) {
                $query->where('status', 2);
            })
            ->pluck('stock_id')
            ->toArray();

        $process_stocks = $process_stocks->whereNotIn('stock_id', $sold_stocks);
        if($process_stocks->count() > 0){

                $variation_qty = Process_stock_model::where('process_id', $process_id)->whereNotIn('stock_id', $sold_stocks)->groupBy('variation_id')->selectRaw('variation_id, Count(*) as total')->get();

                $wrong_variations = Variation_model::whereIn('id', $variation_qty->pluck('variation_id')->toArray())->whereNull('sku')->where('grade', '<', 6)->get();
                if($wrong_variations->count() > 0){
                    $error = 'Please add SKU for the following variations:';
                    // session()->put('error', 'Please add SKU for the following variations:');
                    foreach($wrong_variations as $variation){
                        $error .= ' '.$variation->product->model.' - '.$variation->storage_id->name.' - '.$variation->color_id->name.' - '.$variation->grade_id->name;
                        // session()->put('error', $variation->product->model.' - '.$variation->storage_id->name.' - '.$variation->color_id->name.' - '.$variation->grade_id->name);
                    }
                    session()->put('error', $error);
                    return redirect()->back();
                }

                dd($variation_qty);
                $listingController = new ListingController();
                foreach($variation_qty as $variation){
                    $listed_stock = Listed_stock_verification_model::where('process_id', $process->id)->where('variation_id', $variation->variation_id)->first();
                    if($listed_stock == null){
                        echo $listingController->add_quantity($variation->variation_id, $variation->total, $process->id);
                    }elseif($listed_stock->qty_change != $variation->total){
                        $new_qty = $variation->total - $listed_stock->qty_change;
                        echo $listingController->add_quantity($variation->variation_id, $new_qty, $process->id);
                    }
                }

                $scanned_total = Process_stock_model::where('process_id', $process_id)->count();
                $pushed_total = Listed_stock_verification_model::where('process_id', $process_id)->sum('qty_change');
                if($scanned_total == $pushed_total){
                    $process->status = 3;
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
        $data['storages'] = Storage_model::pluck('name','id');
        $data['products'] = Products_model::pluck('model','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $data['colors'] = Color_model::pluck('name','id');

        $last_ten = Listed_stock_verification_model::where('process_id',$process_id)->orderBy('id','desc')->limit($per_page)->get();
        $data['last_ten'] = $last_ten;

        $changed_listed_stocks = Listed_stock_verification_model::where(['process_id'=>$process_id])
        ->whereColumn('qty_from', '!=', 'qty_to')
        ->orderByDesc('updated_at')->get();
        $data['changed_listed_stocks'] = $changed_listed_stocks;
        $same_listed_stocks = Listed_stock_verification_model::where(['process_id'=>$process_id])
        ->whereColumn('qty_from', 'qty_to')
        ->orderByDesc('updated_at')->get();
        $data['same_listed_stocks'] = $same_listed_stocks;

        $data['all_variations'] = Variation_model::whereNotNull('sku')->get();
        $data['process'] = Process_model::find($process_id);

        $data['process_id'] = $process_id;


        if(request('show') != null){
            $stocks = Stock_model::whereIn('id',$data['process']->process_stocks->pluck('stock_id')->toArray())->where('status',1)->get();
            // $variations = Variation_model::whereIn('id',$stocks->pluck('variation_id')->toArray())->get();
            $variation_ids = Process_stock_model::where('process_id', $process_id)->pluck('variation_id')->unique();
            $variations = Variation_model::whereIn('variation.id', $variation_ids)
            ->join('products', 'products.id', '=', 'variation.product_id')
            ->orderBy('products.model', 'asc')
            ->orderBy('variation.storage', 'asc')
            ->orderBy('variation.color', 'asc')
            ->orderBy('variation.grade', 'asc')
            ->select('variation.*')
            ->get();
            $data['variations'] = $variations;
            $data['stocks'] = $stocks;

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
