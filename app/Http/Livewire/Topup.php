<?php

namespace App\Http\Livewire;
use Livewire\Component;
use App\Models\Variation_model;
use App\Models\Products_model;
use App\Models\Stock_model;
use App\Models\Order_item_model;
use App\Models\Customer_model;
use App\Models\Storage_model;
use App\Http\Controllers\ListingController;
use App\Models\Brand_model;
use App\Models\Category_model;
use App\Models\Color_model;
use App\Models\ExchangeRate;
use App\Models\Grade_model;
use App\Models\Listed_stock_verification_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;
use App\Models\Product_storage_sort_model;
use App\Models\Stock_operations_model;


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

    public function close_topup($process_id){
        $process = Process_model::find($process_id);
        $process->description = request('description');
        $process->quantity = request('quantity');

        if(request('approve') == 1){
            $process->status = 2;

            $variation_qty = Process_stock_model::where('process_id', $process_id)->groupBy('variation_id')->selectRaw('variation_id, Count(*) as total')->get();

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

            $listingController = new ListingController();
            foreach($variation_qty as $variation){
                $listed_stock = Listed_stock_verification_model::where('process_id', $process->id)->where('variation_id', $variation->variation_id)->first();
                if($listed_stock == null){
                    echo $listingController->add_quantity($variation->variation_id, $variation->total, $process->id);
                }
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

    public function update_min_prices($process_id){
        $process = Process_model::find($process_id);




        session()->put('success', 'Minimum Prices Updated');
        return redirect()->back();
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
            $per_page = 5;
        }
        $data['vendors'] = Customer_model::whereNotNull('is_vendor')->get();
        $data['exchange_rates'] = ExchangeRate::pluck('rate','target_currency');
        $data['colors'] = Color_model::pluck('name','id');
        $data['grades'] = Grade_model::where('id','<',6)->pluck('name','id');

        $last_ten = Process_stock_model::where('process_id',$process_id)->orderBy('id','desc')->limit($per_page)->with(['stock','stock.variation','stock.order.customer'])->where('admin_id',session('user_id'))->get();
        $data['last_ten'] = $last_ten;


        $data['all_variations'] = Variation_model::whereNotNull('sku')->get();
        $process = Process_model::with(['process_stocks'])->find($process_id);
        $data['process'] = $process;
        $data['scanned_total'] = Process_stock_model::where('process_id',$process_id)->where('admin_id',session('user_id'))->count();
        $data['process_id'] = $process_id;

        $data['products'] = Products_model::orderBy('model','asc')->pluck('model','id');
        $data['storages'] = Storage_model::pluck('name','id');
        if(request('show') != null){
            $stocks = Stock_model::whereIn('id',$data['process']->process_stocks->pluck('stock_id')->toArray())->get();
            // $variations = Variation_model::whereIn('id',$stocks->pluck('variation_id')->toArray())->get();
            $variation_ids = Process_stock_model::where('process_id', $process_id)->pluck('variation_id')->unique();
            $variations = Variation_model::whereIn('id', $variation_ids)->get();
            $data['variations'] = $variations->sortBy(function ($variation) use ($process_id) {
                return Process_stock_model::where('process_id', $process_id)
                    ->where('variation_id', $variation->id)
                    ->orderBy('id', 'asc')
                    ->value('id');
            });
            $data['stocks'] = $stocks;

        }
        // if($process->status == 2){
            $data['listed_stocks'] = Listed_stock_verification_model::where('process_id', $process_id)->get();
        // }

        return view('livewire.topup_detail')->with($data);

    }
    public function add_topup_item($process_id){
        if(request('reference') != null){
            session()->put('reference', request('reference'));
            $reference = request('reference');
        }else{
            $reference = null;
        }

        $imei = request('imei');
        $imeis = explode("\n", $imei);
        foreach($imeis as $imei){
            if (ctype_digit($imei)) {
                $i = $imei;
                $stock = Stock_model::where(['imei' => $i])->first();
            } else {
                $s = $imei;
                $t = mb_substr($imei,1);
                $stock = Stock_model::whereIn('serial_number', [$s, $t])->first();
            }

            if($stock == null){
                session()->put('error', 'IMEI Invalid / Not Found');
                return redirect()->back();

            }

            $stock->availability();


            if(request('copy') == 1 || request('copy_grade') == 1){
                $variation = $stock->variation;
                if(request('product') != null){
                    $product_id = request('product');
                    if($variation->product_id != $product_id){
                        return redirect()->back()->with('error', 'Product ID does not match with the stock variation');
                    }
                }else{
                    $product_id = $variation->product_id;
                }
                if(request('storage') != null){
                    $storage_id = request('storage');
                    if($variation->storage != $storage_id){
                        return redirect()->back()->with('error', 'Storage ID does not match with the stock variation');
                    }
                }else{
                    $storage_id = $variation->storage;
                }
                if(request('color') != null && request('copy') == 1){
                    $color_id = request('color');
                }else{
                    $color_id = $variation->color;
                }
                if(request('dual-esim') != null){
                    $product = Products_model::find($product_id);
                    if(!str_contains(strtolower($product->model), 'dual esim')){
                        $new_product = Products_model::firstOrNew(['model' => $product->model.' Dual eSIM']);
                        $new_product->category = $product->category;
                        $new_product->brand = $product->brand;
                        $new_product->save();
                        $product_id = $new_product->id;
                    }
                }
                if(request('dual-sim') != null){
                    $product = Products_model::find($product_id);
                    if(!str_contains(strtolower($product->model), 'dual sim')){
                        $new_product = Products_model::firstOrNew(['model' => $product->model.' Dual Sim']);
                        $new_product->category = $product->category;
                        $new_product->brand = $product->brand;
                        $new_product->save();
                        $product_id = $new_product->id;
                    }
                }


                if(request('grade') != null){
                    $grade_id = request('grade');
                    if($variation->grade != $grade_id && request('copy_grade') != 1){
                        return redirect()->back()->with('error', 'Grade ID does not match with the stock variation');
                    }
                }else{
                    $grade_id = $variation->grade;
                }
                $new_variation = Variation_model::firstOrNew([
                    'product_id' => $product_id,
                    'storage' => $storage_id,
                    'color' => $color_id,
                    'grade' => $grade_id,
                ]);
                // dd($new_variation);
                if($stock->variation_id != $new_variation->id){
                    $new_variation->status = 1;
                    $new_variation->stock += 1;
                    $new_variation->save();
                    $stock_operation = Stock_operations_model::create([
                        'stock_id' => $stock->id,
                        'old_variation_id' => $stock->variation_id,
                        'new_variation_id' => $new_variation->id,
                        'description' => 'Variation changed during TopUp',
                        'admin_id' => session('user_id'),
                    ]);
                    session()->put('success', 'Stock Variation changed successfully from '.$stock->variation_id.' to '.$new_variation->id);
                    $stock->variation_id = $new_variation->id;
                    $stock->save();
                }
                if(request('copy') == 1){
                        session()->put('copy', 1);
                }else{
                        session()->put('copy', 0);
                }
                    if(request('copy_grade') == 1){
                        session()->put('copy_grade', 1);
                    }else{
                        session()->put('copy_grade', 0);
                    }
                    session()->put('product', request('product'));
                    session()->put('storage', request('storage'));
                    session()->put('color', request('color'));
                    session()->put('grade', request('grade'));
            }else{
                session()->put('copy', 0);
                session()->put('copy_grade', 0);
                session()->put('product', $stock->variation->product_id);
                session()->put('storage', $stock->variation->storage);
                session()->put('color', $stock->variation->color);
                session()->put('grade', $stock->variation->grade);
            }
            $stock = Stock_model::find($stock->id);
            if($stock->variation->sku == null){
                session()->put('error', 'SKU Not Found');
                return redirect()->back();
            }

            $process_stock = Process_stock_model::firstOrNew(['process_id'=>$process_id, 'stock_id'=>$stock->id]);
            $process_stock->admin_id = session('user_id');
            $process_stock->variation_id = $stock->variation_id;
            $process_stock->description = $reference;
            if($process_stock->id == null){
                $process_stock->status = 1;
                $process_stock->save();
                // Check if the session variable 'counter' is set
                if (session()->has('counter')) {
                    // Increment the counter
                    session()->increment('counter');
                } else {
                    // Initialize the counter if it doesn't exist
                    session()->put('counter', 1);
                }
                $model = $stock->variation->product->model ?? '?';
                $storage = $stock->variation->storage_id->name ?? '?';
                $color = $stock->variation->color_id->name ?? '?';
                $grade = $stock->variation->grade_id->name ?? '?';

                session()->put('success', 'Stock Added successfully: SKU:'.$stock->variation->sku.' - '.$model.' - '.$storage.' - '.$color.' - '.$grade);
            }else{
                if(request('copy') == 1){
                    $process_stock->status = 1;
                    $process_stock->save();
                    session()->put('success', 'Stock ReAdded successfully SKU:'.$stock->variation->sku);
                }else{
                    session()->put('error', 'Stock already Added SKU:'.$stock->variation->sku);
                }
            }
        }
        return redirect()->back();
    }

    public function delete_topup_item($id){
        $process_stock = Process_stock_model::find($id);
        if($process_stock != null){
            $process_stock->delete();
            session()->put('success', 'Stock Deleted successfully');
        }else{
            session()->put('error', 'Stock Not Found');
        }
        return redirect()->back();
    }

    public function delete_topup($id){
        $process = Process_model::find($id);
        if($process != null){
            $process->process_stocks()->delete();
            $process->delete();
            session()->put('success', 'Topup Deleted successfully');
        }else{
            session()->put('error', 'Topup Not Found');
        }
        return redirect()->to(url('topup'))->with('success', 'Topup Deleted successfully');
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




}
