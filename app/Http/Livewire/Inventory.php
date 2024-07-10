<?php

namespace App\Http\Livewire;

use App\Exports\InventorysheetExport;
use Livewire\Component;
use App\Models\Color_model;
use App\Models\Storage_model;
use App\Models\Grade_model;
use App\Models\Category_model;
use App\Models\Brand_model;
use App\Models\Customer_model;
use App\Models\Order_item_model;
use App\Models\Order_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;
use App\Models\Stock_model;
use App\Models\Products_model;
use App\Models\Stock_operations_model;
use App\Models\Variation_model;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class Inventory extends Component
{

    public function render()
    {

        $data['title_page'] = "Inventory";
        $all_verified_stocks = [];
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }

        $data['vendors'] = Customer_model::where('is_vendor',1)->pluck('first_name','id');
        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $data['categories'] = Category_model::get();
        $data['brands'] = Brand_model::get();

        if(request('replacement') == 1){
            $replacements = Order_item_model::where(['order_id'=>8974])->where('reference_id','!=',null)->pluck('reference_id')->toArray();
        }else{
            $replacements = [];
        }
        if(request('rma') == 1){
            $rmas = Order_model::where(['order_type_id'=>2])->pluck('id')->toArray();
        }else{
            $rmas = [];
        }

        if(request('aftersale') != 1){

            $aftersale = Order_item_model::whereHas('order', function ($q) {
                $q->where('order_type_id',4)->where('status','<',3);
            })->pluck('stock_id')->toArray();
        }else{
            $aftersale = [];
        }


        $active_inventory_verification = Process_model::where(['process_type_id'=>20,'status'=>1])->first();
        if($active_inventory_verification != null){
            $all_verified_stocks = Process_stock_model::where('process_id', $active_inventory_verification->id)->pluck('stock_id')->toArray();
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
            // ->orderBy('product_id','ASC')
            ->paginate($per_page)
            ->onEachSide(5)
            ->appends(request()->except('page'));
            $data['verified_stocks'] = $verified_stocks;
        }
        $data['active_inventory_verification'] = $active_inventory_verification;

        if(request('replacement') == 1){
            $replacements = Order_item_model::where(['order_id'=>8974])->where('reference_id','!=',null)->pluck('reference_id')->toArray();
            // dd($replacements);
            $data['stocks'] = Stock_model::where('status', 1)
            ->whereHas('order_items.order', function ($q) use ($replacements) {
                $q->where(['status'=>3, 'order_type_id'=>3])
                ->whereNotIn('reference_id', $replacements);
            })
            ->orderBy('order_id','ASC')
            ->orderBy('updated_at','ASC')
            ->paginate($per_page)
            ->onEachSide(5)
            ->appends(request()->except('page'));
        }elseif(request('rma') == 1){
            $rmas = Order_model::where(['order_type_id'=>2])->pluck('id')->toArray();
            $data['stocks'] = Stock_model::whereDoesntHave('order_items', function ($q) use ($rmas) {
                    $q->whereIn('order_id', $rmas);
                })->whereHas('variation', function ($q) {
                    $q->where('grade', 10);
                })->Where('status',2)
            ->orderBy('order_id','ASC')
            ->orderBy('updated_at','ASC')
            ->paginate($per_page)
            ->onEachSide(5)
            ->appends(request()->except('page'));
        }else{
            $data['stocks'] = Stock_model::
            // with(['variation','variation.product','order','purchase_item','latest_operation','latest_return','admin'])
            // ->
            whereNotIn('stock.id',$all_verified_stocks)
            ->where('stock.status', 1)

            ->when(request('aftersale') != 1, function ($q) use ($aftersale) {
                return $q->whereNotIn('stock.id',$aftersale);
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
            ->when(request('status') != '', function ($q) {
                return $q->whereHas('order', function ($q) {
                    $q->where('orders.status', request('status'));
                });
            })
            ->when(request('replacement') != '', function ($q) use ($replacements) {
                return $q->whereHas('order_items.order', function ($q) use ($replacements) {
                    $q->where(['status'=>3, 'order_type_id'=>3])
                    ->whereNotIn('orders.reference_id', $replacements);
                });
            })

            ->when(request('rma') != '', function ($query) use ($rmas) {
                return $query->whereDoesntHave('order_items', function ($q) use ($rmas) {
                    $q->whereIn('order_items.order_id', $rmas);
                })->whereHas('variation', function ($q) {
                    $q->where('variation.grade', 10);
                })->where('stock.status',2);
            })
            ->when(request('storage') != '', function ($q) {
                return $q->whereHas('variation', function ($q) {
                    $q->where('storage', request('storage'));
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
        }

        $data['average_cost'] = Stock_model::where('stock.deleted_at',null)->where('order_items.deleted_at',null)

        ->when(request('stock_status') != '', function ($q) {
            return $q->where('stock.status', request('stock_status'));
        })
        ->when(request('stock_status') == '', function ($q) {
            return $q->where('stock.status', 1);
        })
        ->when(request('vendor') != '', function ($q) {
            return $q->whereHas('order', function ($q) {
                $q->where('customer_id', request('vendor'));
            });
        })
        ->when(request('status') != '', function ($q) {
            return $q->whereHas('order', function ($q) {
                $q->where('status', request('status'));
            });
        })
        ->when(request('replacement') != '', function ($q) use ($replacements) {
            return $q->whereHas('order_items.order', function ($q) use ($replacements) {
                $q->where(['status'=>3, 'order_type_id'=>3])
                ->whereNotIn('reference_id', $replacements);
            })->Where('stock.status',1);
        })

        ->when(request('rma') != '', function ($query) use ($rmas) {
            return $query->whereDoesntHave('order_items', function ($q) use ($rmas) {
                $q->whereIn('order_id', $rmas);
            })->whereHas('variation', function ($q) {
                $q->where('grade', 10);
            })->Where('stock.status',2);
        })
        ->when(request('storage') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('storage', request('storage'));
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
                $q->whereIn('grade', request('grade'));
            });
        })

        // ->join('order_items', 'stock.id', '=', 'order_items.stock_id')
        ->join('order_items', function ($join) {
            $join->on('stock.id', '=', 'order_items.stock_id')
                ->whereRaw('order_items.order_id = stock.order_id');
        })
        ->selectRaw('AVG(order_items.price) as average_price')
        ->selectRaw('SUM(order_items.price) as total_price')
        // ->pluck('average_price')
        ->first();

        // ->with(['orders' => function($q) {
        //     $q->where('order_type_id', 1)
        //         ->with(['order_items' => function($q) {
        //         $q->whereHas('stock', function ($q) {
        //             $q->where('status', 1);
        //         })->select(DB::raw('count(distinct id) as count'), DB::raw('sum(price) as total_price'));
        //     }]);
        // }])->first();


        // ->with(['orders.order_items' => function($q) {
        //     $q->whereHas('stock', function ($q) {
        //         $q->where('status', 1);
        //     })->select(DB::raw('count(id)'), DB::raw('sum(price)'));
        // }])->whereHas('orders', function ($q) {
        //     $q->where('order_type_id',1);

        // })->get();

        $data['vendor_average_cost'] = Stock_model::where('stock.deleted_at',null)->where('order_items.deleted_at',null)->where('orders.deleted_at',null)

        ->when(request('stock_status') != '', function ($q) {
            return $q->where('stock.status', request('stock_status'));
        })
        ->when(request('stock_status') == '', function ($q) {
            return $q->where('stock.status', 1);
        })
        ->when(request('vendor') != '', function ($q) {
            return $q->whereHas('order', function ($q) {
                $q->where('customer_id', request('vendor'));
            });
        })
        ->when(request('status') != '', function ($q) {
            return $q->whereHas('order', function ($q) {
                $q->where('status', request('status'));
            });
        })
        ->when(request('replacement') != '', function ($q) use ($replacements) {
            return $q->whereHas('order_items.order', function ($q) use ($replacements) {
                $q->where(['status'=>3, 'order_type_id'=>3])
                ->whereNotIn('reference_id', $replacements);
            })->Where('stock.status',1);
        })

        ->when(request('rma') != '', function ($query) use ($rmas) {
            return $query->whereDoesntHave('order_items', function ($q) use ($rmas) {
                $q->whereIn('order_id', $rmas);
            })->whereHas('variation', function ($q) {
                $q->where('grade', 10);
            })->Where('stock.status',2);
        })
        ->when(request('storage') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('storage', request('storage'));
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
                $q->whereIn('grade', request('grade'));
            });
        })
        // ->join('order_items', 'stock.id', '=', 'order_items.stock_id')
        ->join('order_items', function ($join) {
            $join->on('stock.id', '=', 'order_items.stock_id')
                ->whereRaw('order_items.order_id = stock.order_id');
        })
        ->join('orders', 'stock.order_id', '=', 'orders.id')
        ->select('orders.customer_id')
        ->selectRaw('AVG(order_items.price) as average_price')
        ->selectRaw('SUM(order_items.price) as total_price')
        ->selectRaw('COUNT(order_items.id) as total_qty')
        ->groupBy('orders.customer_id')
        ->get();


        $active_inventory_verification = Process_model::where(['process_type_id'=>20,'status'=>1])->first();
        if($active_inventory_verification != null){
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
            // ->orderBy('product_id','ASC')
            ->paginate($per_page)
            ->onEachSide(5)
            ->appends(request()->except('page'));
            $data['verified_stocks'] = $verified_stocks;
        }
        $data['active_inventory_verification'] = $active_inventory_verification;
        // dd($data['vendor_average_cost']);

        return view('livewire.inventory')->with($data);
    }
    public function get_products(){


        $category = request('category');
        $brand = request('brand');

        // $products = Products_model::where(['category' => $category, 'brand' => $brand])->orderBy('model','asc')->get();

        $products = Stock_model::select('products.model as model', 'variation.product_id as id', DB::raw('COUNT(*) as quantity'))
        ->where(['stock.status'=> 1, 'stock.deleted_at'=>null])->where(['products.category' => $category, 'products.brand' => $brand])
        ->join('variation', 'stock.variation_id', '=', 'variation.id')
        ->join('products', 'variation.product_id', '=', 'products.id')
        ->groupBy('variation.product_id', 'products.model')
        ->orderBy('variation.product_id')
        ->get();

        // dd($products);

        return response()->json($products);
    }
    public function get_variations($id){

        $variation = Variation_model::where('product_id',$id)->orderBy('storage','asc')->orderBy('color','asc')->orderBy('grade','asc')->get();

        return response()->json($variation);
    }


    public function update_product($id){

        Products_model::where('id', $id)->update(request('update'));
        return redirect()->back();
    }

    public function export(){

        return Excel::download(new InventorysheetExport, 'inventory.xlsx');
    }

    public function start_verification() {
        $last = Process_model::where('process_type_id',20)->orderBy('id','desc')->first();
        $verification = Process_model::firstOrNew(['process_type_id'=>20, 'status'=>1]);
        if($verification->id == null && $last != null){
            $verification->reference_id = $last->reference_id + 1;
        }elseif($verification->id == null && $last == null){
            $verification->reference_id = "8001";
        }else{
            session()->put('error', 'Inventory Verification already in progress');
        }
        if($verification->id == null){
            $verification->save();
            session()->put('success', 'Inventory Verification started');
        }
        return redirect()->back();
    }
    public function resume_verification() {
        $last = Process_model::where('process_type_id',20)->orderBy('id','desc')->first();
        $last->status = 1;
        $last->save();
        session()->put('success', 'Inventory Verification started');
        return redirect()->back();
    }

    public function end_verification() {
        $verification = Process_model::where(['process_type_id'=>20, 'status'=>1])->update(['status'=>2,'description'=>request('description')]);
        session()->put('success', 'Inventory Verification ended');
        return redirect()->back();
    }

    public function add_verification_imei($process_id) {

        if (ctype_digit(request('imei'))) {
            $i = request('imei');
            $s = null;
        } else {
            $i = null;
            $s = request('imei');
        }
        $stock = Stock_model::where(['imei' => $i, 'serial_number' => $s])->first();

        $process_stock = Process_stock_model::firstOrNew(['process_id'=>$process_id, 'stock_id'=>$stock->id]);
        $process_stock->admin_id = session('user_id');
        $process_stock->status = 1;
        if($process_stock->id == null){
            $process_stock->save();
            session()->put('success', 'Stock Verified successfully');
        }else{
            session()->put('error', 'Stock already verified');
        }
        return redirect()->back();
    }


    public function belfast_inventory(){


        $data['title_page'] = "Belfast Inventory";
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }
        $data['return_order'] = Order_model::where(['order_type_id'=>4,'status'=>1])->first();
        $data['vendors'] = Customer_model::where('is_vendor',1)->pluck('first_name','id');
        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $data['categories'] = Category_model::get();
        $data['brands'] = Brand_model::get();
        $data['stocks'] = Stock_model::
        when(request('vendor') != '', function ($q) {
            return $q->whereHas('order', function ($q) {
                $q->where('customer_id', request('vendor'));
            });
        })
        ->when(request('status') != '', function ($q) {
            return $q->where('status', request('status'));
        })
        ->when(request('storage') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('storage', request('storage'));
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
        ->whereHas('variation', function ($q) {
            $q->where('grade', 12);
        })
        ->orderBy('product_id','ASC')
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));


        $data['average_cost'] = Stock_model::where('stock.deleted_at',null)->where('order_items.deleted_at',null)

        ->when(request('vendor') != '', function ($q) {
            return $q->whereHas('order', function ($q) {
                $q->where('customer_id', request('vendor'));
            });
        })
        ->when(request('status') != '', function ($q) {
            return $q->where('stock.status', request('status'));
        })
        ->when(request('storage') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('storage', request('storage'));
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
        ->whereHas('variation', function ($q) {
            $q->where('grade', 12);
        })

        ->join('order_items', 'stock.id', '=', 'order_items.stock_id')
        ->selectRaw('AVG(order_items.price) as average_price')
        ->selectRaw('SUM(order_items.price) as total_price')
        // ->pluck('average_price')
        ->first();

        $data['vendor_average_cost'] = Stock_model::where('stock.deleted_at',null)
        ->where('order_items.deleted_at',null)->where('orders.deleted_at',null)

        ->when(request('vendor') != '', function ($q) {
            return $q->whereHas('order', function ($q) {
                $q->where('customer_id', request('vendor'));
            });
        })
        ->when(request('status') != '', function ($q) {
            return $q->where('stock.status', request('status'));
        })
        ->when(request('storage') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('storage', request('storage'));
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
        ->whereHas('variation', function ($q) {
            $q->where('grade', 12);
        })
        ->join('order_items', 'stock.id', '=', 'order_items.stock_id')
        ->join('orders', 'stock.order_id', '=', 'orders.id')
        ->select('orders.customer_id')
        ->selectRaw('AVG(order_items.price) as average_price')
        ->selectRaw('SUM(order_items.price) as total_price')
        ->selectRaw('COUNT(order_items.id) as total_qty')
        ->groupBy('orders.customer_id')
        // ->pluck('average_price')
        ->get();

        // dd($data['vendor_average_cost']);

        return view('livewire.belfast_inventory')->with($data);
    }

    public function aftersale_action($stock_id, $action){
        $stock = Stock_model::find($stock_id);
        $product_id = $stock->variation->product_id;
        $storage = $stock->variation->storage;
        $color = $stock->variation->color;
        $grade = $stock->variation->grade;

        if($action == 'resend'){
            $variation = $stock->last_item()->variation;

            $product_id = $variation->product_id;
            $storage = $variation->storage;
            $color = $variation->color;
            $grade = $variation->grade;

        }elseif($action == 'aftersale_repair'){
            $grade = 8;
        }
        $new_variation = Variation_model::firstOrNew([
            'product_id' => $product_id,
            'storage' => $storage,
            'color' => $color,
            'grade' => $grade,
        ]);
        $new_variation->status = 1;
        $stock_operation = Stock_operations_model::create([
            'stock_id' => $stock_id,
            'old_variation_id' => $stock->variation_id,
            'new_variation_id' => $new_variation->id,
            'description' => request('return')['description'],
            'admin_id' => session('user_id'),
        ]);

        $new_variation->save();
        $stock->variation_id = $new_variation->id;
        $stock->save();

        return redirect()->back();
    }
}

