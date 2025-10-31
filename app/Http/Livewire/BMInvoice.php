<?php

namespace App\Http\Livewire;

use App\Exports\TopupsheetExport;
use Livewire\Component;
use App\Models\Variation_model;
use App\Models\Products_model;
use App\Models\Stock_model;
use App\Models\Order_item_model;
use App\Models\Customer_model;
use App\Models\Storage_model;
use App\Http\Controllers\ListingController;
use App\Models\Account_transaction_model;
use App\Models\Brand_model;
use App\Models\Category_model;
use App\Models\Color_model;
use App\Models\ExchangeRate;
use App\Models\Grade_model;
use App\Models\Listed_stock_verification_model;
use App\Models\Order_charge_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;
use App\Models\Product_storage_sort_model;
use App\Models\Stock_operations_model;
use Maatwebsite\Excel\Facades\Excel;

class BMInvoice extends Component
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

        $data['title_page'] = "BM Invoices Report";
        session()->put('page_title', $data['title_page']);

        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 20;
        }

        $data['batches'] = Process_model::where('process_type_id', 19)->with(['admin', 'transactions'])
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
        return view('livewire.bm_invoice')->with($data);
    }



    public function invoice_detail($process_id){

        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 300);
        ini_set('pdo_mysql.max_input_vars', '10000');

        if(str_contains(url()->previous(),url('bm_invoice')) && !str_contains(url()->previous(),'detail')){
            session()->put('previous', url()->previous());
        }
        $data['title_page'] = "BM Invoice Detail";
        session()->put('page_title', $data['title_page']);


        $transactions = Account_transaction_model::where(['process_id'=>$process_id])->get();

        $summary = Account_transaction_model::query()
            ->selectRaw('description, SUM(amount) AS transaction_total')
            ->where('process_id', $process_id)
            ->groupBy('description');

        $charges = Order_charge_model::query()
            ->selectRaw('order_id, charge_id, SUM(amount) AS charge_total')
            ->with('charge')
            ->whereIn('order_id', $transactions->pluck('order_id')->filter())
            ->groupBy('order_id', 'charge_id');

        $data['report'] = $summary->get()->map(function ($row) use ($charges) {
            $charge = $charges->first(fn ($c) => trim(optional($c->charge)->name) === trim($row->description));
            return [
                'description'        => $row->description,
                'transaction_total'  => $row->transaction_total,
                'charge_total'       => $charge->charge_total ?? 0,
                'difference'         => $row->transaction_total - ($charge->charge_total ?? 0),
            ];
        });
        dd($data['report']);
        return view('livewire.bm_invoice_detail')->with($data);

    }
    public function add_topup_item($process_id){
        $process = Process_model::find($process_id);
        if(request('reference') != null){
            session()->put('reference', request('reference'));
            $reference = request('reference');
        }else{
            $reference = null;
        }

        $imei = request('imei');
        $imeis = explode(" ", $imei);
        $error = '';
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
                // session()->put('error', 'IMEI Invalid / Not Found');
                // return redirect()->back();
                $error .= 'IMEI Invalid / Not Found: '.$imei.'<br>';
                continue;
            }

            $stock->availability();

            if(request('copy') == 1 || request('copy_grade') == 1 || request('dual-esim') == 1 || request('dual-sim') || request('new-battery') == 1){
                $variation = $stock->variation;

                if(session()->has('product') && session()->has('storage'))
                {
                    $product_id = session('product');
                    $storage_id = session('storage');
                    if($variation->product_id != $product_id){
                        // session()->forget('product');
                        // return redirect()->back()->with('error', 'Product ID does not match with the stock variation');
                        $error .= 'Product ID does not match with the stock variation '.$imei.'<br>';
                        continue;
                    }
                    if($variation->storage != $storage_id){
                        session()->forget('storage');
                        $error .= 'Storage ID does not match with the stock variation '.$imei.'<br>';
                        continue;
                    }
                }

                if(request('product') != null){
                    $product_id = request('product');
                }else{
                    $product_id = $variation->product_id;
                }
                if(request('storage') != null){
                    $storage_id = request('storage');
                    if($variation->storage != $storage_id){
                        $error .= 'Storage ID does not match with the stock variation '.$imei.'<br>';
                        continue;
                    }
                }else{
                    $storage_id = $variation->storage ?? 0;
                }
                if(request('color') != null && request('copy') == 1){
                    $color_id = request('color');
                }else{
                    $color_id = $variation->color;
                }
                if(request('dual-esim') != null){
                    $product = Products_model::find($product_id);
                    if(!str_contains(strtolower($product->model), 'dual esim')){
                        $new_product = Products_model::where(['model' => $product->model.' Dual eSIM'])->first();
                        if($new_product == null){
                            $error .= 'Dual eSIM Product Not Found: '.$imei.'<br>';
                            continue;
                        }

                        $product_id = $new_product->id;
                    }
                }
                if(request('dual-sim') != null){
                    $product = Products_model::find($product_id);
                    if(!str_contains(strtolower($product->model), 'dual sim')){
                        $new_product = Products_model::where(['model' => $product->model.' Dual Sim'])->first();
                        if($new_product == null){
                            $error .= 'Dual SIM Product Not Found: '.$imei.'<br>';
                            continue;
                        }
                        $product_id = $new_product->id;
                    }
                }
                if(request('new-battery') != null){
                    $product = Products_model::find($product_id);
                    if(!str_contains(strtolower($product->model), 'new battery')){
                        $new_product = Products_model::where(['model' => $product->model.' New Battery'])->first();
                        if($new_product == null){
                            $error .= 'New Battery Product Not Found: '.$imei.'<br>';
                            continue;
                        }
                        $product_id = $new_product->id;
                    }
                }


                if(request('grade') != null && request('copy_grade') == 1){
                    $grade_id = request('grade');
                    // if($variation->grade != $grade_id && request('copy_grade') != 1){
                    //     if(request('copy') == 1){
                    //         session()->put('error', 'Grade ID does not match with the stock variation');
                    //         $grade_id = $variation->grade;
                    //         session()->put('grade', $stock->variation->grade);
                    //     }else{
                    //         return redirect()->back()->with('error', 'Grade ID does not match with the stock variation');
                    //     }
                    // }
                }else{
                    $grade_id = $variation->grade;
                }
                $new_variation = Variation_model::where([
                    'product_id' => $product_id,
                    'storage' => $storage_id,
                    'color' => $color_id,
                    'grade' => $grade_id,
                ])->first();
                // dd($new_variation);
                if($new_variation == null){
                    $error .= 'Variation Not Found for the given Product, Storage, Color and Grade: '.$imei.'<br>';
                    continue;
                }
                if($new_variation->sku == null){
                    $error .= 'SKU Not Found for the new variation: '.$imei.'<br>';
                    continue;
                }
                if($stock->variation_id != $new_variation->id && $new_variation->sku != null){
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
                    session()->put('color', request('color'));
                }else{
                    session()->put('copy', 0);
                    session()->put('color', $stock->variation->color);
                }
                if(request('copy_grade') == 1){
                    session()->put('copy_grade', 1);
                    session()->put('grade', request('grade'));
                }else{
                    session()->put('copy_grade', 0);
                    session()->put('grade', $stock->variation->grade);
                }
                if(request('dual-esim') == 1){
                    session()->put('dual-esim', 1);
                }else{
                    session()->put('dual-esim', 0);
                }
                if(request('dual-sim') == 1){
                    session()->put('dual-sim', 1);
                }else{
                    session()->put('dual-sim', 0);
                }
                if(request('new-battery') == 1){
                    session()->put('new-battery', 1);
                }else{
                    session()->put('new-battery', 0);
                }
                    session()->put('product', request('product'));
                    session()->put('storage', request('storage'));
            }else{
                session()->put('copy', 0);
                session()->put('copy_grade', 0);
                session()->put('dual-esim', 0);
                session()->put('dual-sim', 0);
                session()->put('new-battery', 0);
                session()->put('product', $stock->variation->product_id);
                session()->put('storage', $stock->variation->storage);
                session()->put('color', $stock->variation->color);
                session()->put('grade', $stock->variation->grade);
            }
            $stock = Stock_model::find($stock->id);
            if($stock->variation->sku == null){
                // session()->put('error', 'SKU Not Found');
                // return redirect()->back();
                $error .= 'SKU Not Found: '.$imei.'<br>';
                continue;
            }
            if(!in_array($stock->variation->state, [0,1,2,3])){
                $error .= 'Ad State is not valid for Topup: '.$stock->variation->state.'<br>';
                continue;
            }
            if(session()->has('variation_id') && session('variation_id') != $stock->variation_id){
                session()->put('warning', 'Variation ID does not match with the stock variation');
            }
            session()->put('variation_id', $stock->variation_id);

            $process_stock = Process_stock_model::firstOrNew(['process_id'=>$process_id, 'stock_id'=>$stock->id]);
            $process_stock->admin_id = session('user_id');
            $process_stock->variation_id = $stock->variation_id;
            $process_stock->description = $reference;
            if($process_stock->id == null && $process->status == 1){
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
                if($process->status != 1 && $process_stock->id == null){
                    $error .= 'Topup is not in progress, please start a new Topup: '.$imei.'<br>';
                    continue;
                }

                if(request('copy') == 1 || request('copy_grade') == 1 || request('dual-esim') == 1 || request('dual-sim') == 1 || request('new-battery') == 1){
                    $process_stock->status = 1;
                    $process_stock->save();
                    session()->put('success', 'Stock ReAdded successfully SKU:'.$stock->variation->sku);
                }else{
                    // session()->put('error', 'Stock already Added SKU:'.$stock->variation->sku);
                    $error .= 'Stock already Added SKU:'.$stock->variation->sku.'<br>';
                    continue;
                }
            }
        }
        if($error != ''){
            session()->put('error', $error);
        }
        return redirect()->back();
    }

    public function verify_topup_item($process_id){

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

            $stock = Stock_model::find($stock->id);
            if($stock->variation->sku == null){
                session()->put('error', 'SKU Not Found');
                return redirect()->back();
            }

            $process_stock = Process_stock_model::where(['process_id'=>$process_id, 'stock_id'=>$stock->id])->first();
            if($process_stock == null){
                session()->put('error', 'Stock Not Found');
                return redirect()->back();
            }
            if($process_stock->status == 2){
                session()->put('error', 'Stock already Verified SKU:'.$stock->variation->sku);
                return redirect()->back();
            }
            $process_stock->verified_by = session('user_id');
            $process_stock->status = 2;
            $process_stock->save();

            // Check if the session variable 'counter' is set
            if (session()->has('counter')) {
                // Increment the counter
                session()->increment('counter');
            } else {
                // Initialize the counter if it doesn't exist
                session()->put('counter', 1);
            }

            session()->put('success', 'Stock Verified successfully SKU:'.$stock->variation->sku);

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

    public function delete_topup_imei($process_stock_id = null){
        if($process_stock_id != null){
            $process_stock = Process_stock_model::find($process_stock_id);
        }
        if(request('imei') != null){
            $imei = trim(request('imei'));
            $imeis = $imei;
            $imeis = explode(" ",$imeis);
            foreach($imeis as $imei){

                $stock = Stock_model::where('imei', $imei)->orWhere('serial_number', $imei)->first();

                if($stock == null){
                    session()->put('error', "IMEI Invalid / Not Found");
                    // return redirect()->back();
                    continue;
                }
                $process_stock = Process_stock_model::where('stock_id', $stock->id)->where('process_id', request('process_id'))->first();

                if($process_stock == null){
                    session()->put('error', "Stock not in this list");
                    // return redirect()->back();
                    continue;
                }
                // Access the variation through process_stock->stock->variation
                $variation = $process_stock->stock->variation;

                $process_stock->stock->status = 1;
                $process_stock->stock->save();

                $variation->stock += 1;
                $variation->save();

                // No variation record found or product_id and sku are both null, delete the order item

                // $process_stock->stock->delete();
                $process_stock->delete();
            }
        }

        // $orderItem->forceDelete();

        session()->put('success', 'Stock deleted successfully');
        return redirect(url('topup/detail').'/'.request('process_id').'?remove=1');

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

    public function export_topup(){
        if(!session('user')->hasPermission('topup_export')){
            session()->put('error', 'You do not have permission to export topup data');
            return redirect()->back();
        }
        $process_id = request('id');
        $process = Process_model::find($process_id);

        return Excel::download(new TopupsheetExport, 'topups_'.$process->reference_id.'_'.$process->description.'_'.$process->process_stocks->count().'pcs.xlsx');
    }


    public function undo_topup($id){
        $variation_change = [];
        $variation_listing = [];
        // $listed_stocks = Process_stock_model::where('process_id', $id)->get();
        $listed_stocks = Listed_stock_verification_model::where('process_id', $id)->get();
        $listingController = new ListingController();
        foreach($listed_stocks as $listed_stock){
            // if($listed_stock->variation_id != null){
            //     $listingController->add_quantity($listed_stock->variation_id, -$listed_stock->qty_change, $id);
            // }
            $change = $listed_stock->qty_to - $listed_stock->qty_from;
            if($change == 0){
                $listed_stock->delete();
                continue;
            }
            if(!isset($variation_change[$listed_stock->variation_id])){
                $variation_change[$listed_stock->variation_id] = $change;
                $variation_listing[$listed_stock->variation_id] = $listed_stock->id;
            }else{
                // if($variation_change[$listed_stock->variation_id] + $change == 0){
                //     $listed_stock->delete();
                //     Listed_stock_verification_model::where('id', $variation_listing[$listed_stock->variation_id])->delete();
                //     unset($variation_change[$listed_stock->variation_id]);
                //     unset($variation_listing[$listed_stock->variation_id]);
                // }else{
                //     $varification_changed = Listed_stock_verification_model::where('id', $variation_listing[$listed_stock->variation_id])->first();
                //     if($varification_changed){
                //         $variation_changed->qty_to -= $varification_changed->qty_from;
                //     }
                // }
            }

        }
        session()->put('success', 'Topup Undoned');
        return redirect()->back();
    }




}

