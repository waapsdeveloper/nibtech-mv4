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


class Topup extends Component
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

        $data['title_page'] = "Topup Listed Stock";
        session()->put('page_title', $data['title_page']);

        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 20;
        }

        $data['batches'] = Process_model::where('process_type_id', 22)
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
        return view('livewire.topup')->with($data);
    }

    public function start_topup(){
        $latest_ref = Process_model::where('process_type_id', 22)->latest()->first();
        if($latest_ref != null){
            $latest_ref = $latest_ref->reference_id + 1;
        }else{
            $latest_ref = 40001;
        }
        $process = new Process_model();
        $process->reference_id = $latest_ref;
        $process->process_type_id = 22;
        $process->status = 1;
        $process->admin_id = session('user_id');
        $process->quantity = request('quantity');
        $process->save();

        return redirect()->to(url('topup/detail').'/'.$process->id)->with('success', 'Topup Started Started');
    }

    public function close_process($process_id){
        $process = Process_model::find($process_id);
        $process->description = request('description');

        if(request('approve') == 1){
            $process->status = 2;
        }

        $process->save();

        if(request('approve') == 1){
            return redirect()->back();
        }else{
            return "Updated";
        }
        // return redirect()->back();
    }
    public function topup_detail($process_id){

        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 300);
        ini_set('pdo_mysql.max_input_vars', '10000');

        if(str_contains(url()->previous(),url('topup')) && !str_contains(url()->previous(),'detail')){
            session()->put('previous', url()->previous());
        }
        $data['title_page'] = "Topup Detail";
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

        $last_ten = Process_stock_model::where('process_id',$process_id)->orderBy('id','desc')->limit($per_page)->get();
        $data['last_ten'] = $last_ten;


        $data['all_variations'] = Variation_model::whereNotNull('sku')->get();
        $data['process'] = Process_model::find($process_id);

        $data['process_id'] = $process_id;

        return view('livewire.topup_detail')->with($data);

    }

    public function undo_topup($id){
        $listed_stocks = Process_stock_model::where('process_id', $id)->get();
        $listingController = new ListingController();
        foreach($listed_stocks as $listed_stock){
            if($listed_stock->variation_id != null){
                $listingController->add_quantity($listed_stock->variation_id, $listed_stock->qty_from);
            }
        }
        session()->put('success', 'Topup Undoned');
        return redirect()->back();
    }

    public function topup_progress() {

        $data['title_page'] = "Inventory Verification Progress";
        session()->put('page_title', $data['title_page']);
        $all_verified_stocks = [];
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }

        if(request('pss') != null){
            $pss = Product_storage_sort_model::find(request('pss'));
            request()->merge(['storage' => $pss->storage, 'product' => $pss->product_id]);
        }

        $data['vendors'] = Customer_model::whereNotNull('is_vendor')->pluck('first_name','id');
        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['products'] = Products_model::pluck('model','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $data['categories'] = Category_model::get();
        $data['brands'] = Brand_model::get();


        $active_inventory_verification = Process_model::where(['process_type_id'=>20,'status'=>1])->first();
        if($active_inventory_verification != null){
            $all_verified_stocks = Process_stock_model::where('process_id', $active_inventory_verification->id)->where('status',1)->pluck('stock_id')->toArray();
            $verified_stocks = Process_stock_model::where('process_id', $active_inventory_verification->id)
            ->when(request('vendor') != '', function ($q) {
                return $q->whereHas('stock.order', function ($q) {
                    $q->where('customer_id', request('vendor'));
                });
            })
            ->when(request('status') != '', function ($q) {
                return $q->whereHas('stock.order', function ($q) {
                    $q->where('status', request('status'));
                });
            })
            ->when(request('storage') != '', function ($q) {
                return $q->whereHas('stock.variation', function ($q) {
                    $q->where('storage', request('storage'));
                });
            })
            ->when(request('color') != '', function ($q) {
                return $q->whereHas('stock.variation', function ($q) {
                    $q->where('color', request('color'));
                });
            })
            ->when(request('category') != '', function ($q) {
                return $q->whereHas('stock.variation.product', function ($q) {
                    $q->where('category', request('category'));
                });
            })
            ->when(request('brand') != '', function ($q) {
                return $q->whereHas('stock.variation.product', function ($q) {
                    $q->where('brand', request('brand'));
                });
            })
            ->when(request('product') != '', function ($q) {
                return $q->whereHas('stock.variation', function ($q) {
                    $q->where('product_id', request('product'));
                });
            })
            ->when(request('grade') != [], function ($q) {
                return $q->whereHas('stock.variation', function ($q) {
                    // print_r(request('grade'));
                    $q->whereIn('grade', request('grade'));
                });
            })
            ->when(request('sub_grade') != [], function ($q) {
                return $q->whereHas('stock.variation', function ($q) {
                    // print_r(request('sub_grade'));
                    $q->whereIn('sub_grade', request('sub_grade'));
                });
            })
            // ->orderBy('product_id','ASC')
            ->orderByDesc('id')
            ->paginate($per_page)
            ->onEachSide(5)
            ->appends(request()->except('page'));
            $data['verified_stocks'] = $verified_stocks;
            $last_ten = Process_stock_model::where('process_id', $active_inventory_verification->id)->where('admin_id',session('user_id'))->where('status',1)->orderBy('id','desc')->limit(10)->get();
            $data['last_ten'] = $last_ten;
            $scanned_total = Process_stock_model::where('process_id', $active_inventory_verification->id)->where('admin_id',session('user_id'))->orderBy('id','desc')->count();
            $data['scanned_total'] = $scanned_total;
        }
        $data['active_inventory_verification'] = $active_inventory_verification;
        $data['last_verification_date'] = Process_model::where(['process_type_id'=>20,'status'=>2])->latest()->first()->created_at;

        $data['stocks'] = Stock_model::
            with(['variation','variation.product','order','latest_operation','latest_return','admin'])
            ->
            whereNotIn('stock.id',$all_verified_stocks)
            // ->whereNotIn('stock.id',$repaired)
            // ->whereNotIn('stock.id',$recent_operations)
            ->where('stock.status', 1)


            ->when(request('variation') != '', function ($q) {
                return $q->where('variation_id', request('variation'));
            })
            ->when(request('stock_status') != '', function ($q) {
                return $q->where('stock.status', request('stock_status'));
            })
            // ->when(request('stock_status') == '', function ($q) {
            //     return $q
            // })
            ->when(request('vendor') != '', function ($q) {
                return $q->whereHas('order', function ($q) {
                    $q->where('orders.customer_id', request('vendor'));
                });
            })
            ->when(request('batch') != '', function ($q) {
                return $q->whereHas('order', function ($q) {
                    $q->where('orders.reference_id', request('batch'));
                });
            })
            ->when(request('status') != '', function ($q) {
                return $q->whereHas('order', function ($q) {
                    $q->where('orders.status', request('status'));
                });
            })
            ->when(request('storage') != '', function ($q) {
                return $q->whereHas('variation', function ($q) {
                    $q->where('storage', request('storage'));
                });
            })
            ->when(request('color') != '', function ($q) {
                return $q->whereHas('variation', function ($q) {
                    $q->where('color', request('color'));
                });
            })
            ->when(request('category') != '', function ($q) {
                return $q->whereHas('variation.product', function ($q) {
                    $q->where('category', request('category'));
                });
            })
            ->when(request('brand') != '', function ($q) {
                return $q->whereHas('variation.product', function ($q) {
                    $q->where('brand', request('brand'));
                });
            })
            ->when(request('product') != '', function ($q) {
                return $q->whereHas('variation', function ($q) {
                    $q->where('product_id', request('product'));
                });
            })
            ->when(request('grade') != [], function ($q) {
                return $q->whereHas('variation', function ($q) {
                    // print_r(request('grade'));
                    $q->whereIn('grade', request('grade'));
                });
            })
            ->orderBy('order_id','ASC')
            ->orderBy('updated_at','ASC')
            ->paginate($per_page)
            ->onEachSide(5)
            ->appends(request()->except('page'));


        if(!session('counter')){
            session()->put('counter', 0);
        }
        if(request('verify') == 1){
            foreach($data['stocks'] as $stock){

                $last_item = $stock->last_item();

                $items2 = Order_item_model::where(['stock_id'=>$stock->id,'linked_id'=>null])->whereHas('order', function ($query) {
                    $query->where('order_type_id', 1)->where('reference_id','<=',10009);
                })->orderBy('id','asc')->get();
                if($items2->count() > 1){
                    $i = 0;
                    foreach($items2 as $item2){
                        $i ++;
                        if($i == 1){
                            $stock->order_id = $item2->order_id;
                            $stock->save();
                        }else{
                            $item2->delete();
                        }
                    }
                    $last_item = $stock->last_item();
                }
                if($stock->purchase_item){

                    $items3 = Order_item_model::where(['stock_id'=>$stock->id, 'linked_id' => $stock->purchase_item->id])->whereHas('order', function ($query) {
                        $query->whereIn('order_type_id', [5,3]);
                    })->orderBy('id','asc')->get();
                    if($items3->count() > 1){
                        $i = 0;
                        foreach($items3 as $item3){
                            $i ++;
                            if($i == 1){
                            }else{
                                $item3->linked_id = null;
                                $item3->save();
                            }
                        }
                    }

                }
                $items4 = Order_item_model::where(['stock_id'=>$stock->id])->whereHas('order', function ($query) {
                    $query->whereIn('order_type_id', [5,3]);
                })->orderBy('id','asc')->get();
                if($items4->count() == 1){
                    foreach($items4 as $item4){
                        if($stock->purchase_item){
                            if($item4->linked_id != $stock->purchase_item->id && $item4->linked_id != null){
                                $item4->linked_id = $stock->purchase_item->id;
                                $item4->save();
                            }
                        }
                    }
                }
                $items5 = Order_item_model::where(['stock_id'=>$stock->id,'linked_id'=>null])->whereHas('order', function ($query) {
                    $query->whereIn('order_type_id', [2,3,4,5,6]);
                })->orderBy('id','asc')->get();
                if($items5->count() == 1){
                    foreach($items5 as $item5){
                        if($stock->last_item()){

                            $last_item = $stock->last_item();
                            $item5->linked_id = $last_item->id;
                            $item5->save();
                        }
                    }
                        $last_item = $stock->last_item();
                }
                    // if(session('user_id') == 1){
                    //     dd($last_item);
                    // }

                    $stock_id = $stock->id;

                $process_stocks = Process_stock_model::where('stock_id', $stock_id)->whereHas('process', function ($query) {
                    $query->where('process_type_id', 9);
                })->orderBy('id','desc')->get();
                $data['process_stocks'] = $process_stocks;

                if($last_item){

                    if(in_array($last_item->order->order_type_id,[1,4,6])){
                        $message = 'IMEI is Available';
                        // if($stock->status == 2){
                            if($process_stocks->where('status',1)->count() == 0){
                                $stock->status = 1;
                                $stock->save();
                            }else{
                                $stock->status = 2;
                                $stock->save();

                                $message = "IMEI sent for repair";
                            }
                        // }else{

                        // }
                    }else{
                        $message = "IMEI Sold";
                        if($stock->status == 1){
                            $stock->status = 2;
                            $stock->save();
                        }
                    }
                        session()->put('success', $message);
                }
            }
        // dd($data['vendor_average_cost']);
        }
        return view('livewire.verification_progress')->with($data);
    }

    public function push_topup($id) {


        $aftersale = Order_item_model::whereHas('order', function ($q) {
            $q->where('order_type_id',4)->where('status','<',3);
        })->pluck('stock_id')->toArray();

        $remaining_stocks = Stock_model::where('status', 1)->whereHas('order', function ($q) {
            $q->where('status', 3);
        })->whereNotIn('id', $aftersale)->whereNotIn('id', Process_stock_model::where('process_id', $id)->pluck('stock_id')->toArray())->get();

        $client = new Client();

        $stock_imeis = $remaining_stocks->whereNotNull('imei')->pluck('imei')->toArray();
        $stock_imeis += $remaining_stocks->whereNotNull('serial_number')->pluck('serial_number')->toArray();

        $imeis = implode(" ",$stock_imeis);
        $client->request('POST', url('move_inventory/change_grade'), [
            'form_params' => [
                'imei' => $imeis,
                'grade' => 17,
                'description' => 'Missing Stock',
            ]
        ]);

        $remaining_stocks = Stock_model::where('status', 1)->whereHas('order', function ($q) {
            $q->where('status', 3);
        })->whereNotIn('id', $aftersale)->whereNotIn('id', Process_stock_model::where('process_id', $id)->pluck('stock_id')->toArray())->get();

        foreach($remaining_stocks as $stock){
            $process_stock = Process_stock_model::firstOrNew(['process_id'=>$id, 'stock_id'=>$stock->id]);
            if($process_stock->id == null){
                $process_stock->variation_id = $stock->variation_id;
                $process_stock->admin_id = session('user_id');
                $process_stock->status = 2;
                $process_stock->description = 'Missing Stock';
                $process_stock->save();


            }
        }


        $verification = Process_model::find($id)->update(['status'=>2,'description'=>request('description')]);

        session()->put('success', 'Inventory Verification ended');
        return redirect()->back();
    }


}
