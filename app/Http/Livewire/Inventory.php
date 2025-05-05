<?php

namespace App\Http\Livewire;

use App\Exports\InventorysheetExport;
use Livewire\Component;
use App\Models\Color_model;
use App\Models\Storage_model;
use App\Models\Grade_model;
use App\Models\Category_model;
use App\Models\Brand_model;
use App\Models\Currency_model;
use App\Models\Customer_model;
use App\Models\Order_item_model;
use App\Models\Order_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;
use App\Models\Product_storage_sort_model;
use App\Models\Stock_model;
use App\Models\Products_model;
use App\Models\Stock_operations_model;
use App\Models\Variation_model;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class Inventory extends Component
{

    public function render()
    {

        $data['title_page'] = "Inventory";
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


        if(session('user')->hasPermission('view_inventory_summery') && request('summery') && request('summery') == 1){

            ini_set('memory_limit', '2048M');
            ini_set('max_execution_time', 300);
            ini_set('pdo_mysql.max_input_vars', '10000');

            $variation_ids = Variation_model::whereHas('stocks', function ($query) use ($aftersale) {
                    $query->where('status', 1)->when(request('aftersale') != 1, function ($q) use ($aftersale) {
                        return $q->whereNotIn('stock.id',$aftersale);
                    });
                })
                ->when(request('variation') != '', function ($q) {
                    return $q->where('id', request('variation'));
                })
                ->when(request('storage') != '', function ($q) {
                    return $q->where('storage', request('storage'));
                })
                ->when(request('color') != '', function ($q) {
                    return $q->where('color', request('color'));
                })
                ->when(request('sku') != '', function ($q) {
                    return $q->where('sku', request('sku'));
                })
                ->when(request('category') != '', function ($q) {
                    return $q->whereHas('product', function ($q) {
                        $q->where('category', request('category'));
                    });
                })
                ->when(request('brand') != '', function ($q) {
                    return $q->whereHas('product', function ($q) {
                        $q->where('brand', request('brand'));
                    });
                })
                ->when(request('product') != '', function ($q) {
                    return $q->where('product_id', request('product'));
                })
                ->when(request('grade') != [], function ($q) {
                    return $q->whereIn('grade', request('grade'));
                })
                ->when(request('sub_grade') != [], function ($q) {
                    return $q->whereIn('sub_grade', request('sub_grade'));
                })->pluck('id');
            $order_ids = Order_model::when(request('vendor') != '', function ($q) {
                    $q->where('customer_id', request('vendor'));
                })->when(request('status') != '', function ($q) {
                    $q->where('status', request('status'));
                })->where('order_type_id',1)->pluck('id');
            $product_storage_sort = Product_storage_sort_model::whereHas('stocks', function($q) use ($variation_ids){
                $q->whereIn('stock.variation_id', $variation_ids)->where('stock.deleted_at',null);
            })->orderBy('product_id')->orderBy('storage')->get();

            $result = [];
            foreach($product_storage_sort as $pss){
                $product = $pss->product;
                $storage = $pss->storage_id->name ?? null;

                $stocks = $pss->stocks->where('deleted_at',null)->whereNotIn('id',$aftersale)->whereIn('order_id', $order_ids)->whereIn('variation_id',$variation_ids)->where('status',1);
                $stock_ids = $stocks->pluck('id');
                $stock_imeis = $stocks->whereNotNull('imei')->pluck('imei');
                $stock_serials = $stocks->whereNotNull('serial_number')->pluck('serial_number');


                $purchase_items = Order_item_model::whereIn('stock_id', $stock_ids)->whereIn('order_id', $order_ids)->whereHas('order', function ($q) {
                    $q->where('order_type_id', 1);
                })->sum('price');

                if(count($stock_ids) == 0){
                    continue;
                }
                $datas = [];
                $datas['pss_id'] = $pss->id;
                $datas['product_id'] = $pss->product_id;
                $datas['storage'] = $pss->storage;
                $datas['model'] = $product->model.' '.$storage;
                $datas['quantity'] = count($stock_ids);
                $datas['stock_ids'] = $stock_ids->toArray();
                $datas['stock_imeis'] = $stock_imeis->toArray();
                $datas['stock_serials'] = $stock_serials->toArray();
                // $datas['average_cost'] = $purchase_items->avg('price');
                $datas['total_cost'] = $purchase_items;

                $result[] = $datas;
            }

            $data['available_stock_summery'] = $result;

        }else{


        $data['last_verification_date'] = Process_model::where(['process_type_id'=>20,'status'=>2])->latest()->first()->created_at ?? null;

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

            // $repaired = Process_stock_model::whereHas('process', function ($q) {
            //     $q->where('process_type_id', 9);
            // })->where('status',2)->where('updated_at','>=',date('Y-m-01 00:00:00'))->pluck('stock_id')->toArray();
            // $recent_operations = Stock_operations_model::where('created_at','>=',date('Y-m-01 00:00:00'))->pluck('stock_id')->toArray();
            $data['stocks'] = Stock_model::
            with(['variation','variation.product','order','latest_operation','latest_return','admin'])
            ->
            whereNotIn('stock.id',$all_verified_stocks)
            // ->whereNotIn('stock.id',$repaired)
            // ->whereNotIn('stock.id',$recent_operations)
            ->where('stock.status', 1)

            ->when(request('aftersale') != 1, function ($q) use ($aftersale) {
                return $q->whereNotIn('stock.id',$aftersale);
            })

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
            ->when(request('sku') != '', function ($q) {
                return $q->whereHas('variation', function ($q) {
                    $q->where('sku', request('sku'));
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
        }
        // dd($data['vendor_average_cost']);
        }
        return view('livewire.inventory')->with($data);
    }

    public function verification(){

        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }

        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['products'] = Products_model::pluck('model','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $active_inventory_verification = Process_model::where(['process_type_id'=>20,'status'=>1])->first();

        $data['active_inventory_verification'] = $active_inventory_verification;
        $last_ten = Process_stock_model::where('process_id', $active_inventory_verification->id)->where('admin_id',session('user_id'))->orderBy('id','desc')
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));

        $data['last_ten'] = $last_ten;
        $scanned_total = Process_stock_model::where('process_id', $active_inventory_verification->id)->where('admin_id',session('user_id'))->orderBy('id','desc')->count();
        $data['scanned_total'] = $scanned_total;
        if(!session('counter')){
            session()->put('counter', 0);
        }
        return view('livewire.inventory_verification_new')->with($data);
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

    public function color_graded_count($pss_id){
        $pss = Product_storage_sort_model::find($pss_id);
        $variations = $pss->variations;
        $stocks = $pss->stocks->where('status', 1);
        $all_po = Order_model::where('order_type_id', 1)->pluck('id')->toArray();
        $grades = Grade_model::pluck('name', 'id');
        $colors = Color_model::whereIn('id', $variations->pluck('color'))->pluck('name', 'id');
        $graded_count = [];

        $total_graded_count = [
            'quantity' => 0,
            'total_cost' => 0,
            'average_cost' => 0,
        ];

        foreach ($colors as $color_id => $color) {
            foreach ($grades as $grade_id => $grade) {
                $graded_variations = $variations->where('grade', $grade_id)->where('color', $color_id);
                $graded_stock_ids = $stocks->whereIn('variation_id', $graded_variations->pluck('id'))->pluck('id')->toArray();
                $total_cost = Order_item_model::whereIn('stock_id', $graded_stock_ids)->whereIn('order_id', $all_po)->sum('price');
                $average_cost = $graded_stock_ids ? $total_cost / count($graded_stock_ids) : 0;

                if (count($graded_stock_ids) == 0) {
                    continue;
                }
                $graded_count[$color_id . '.' . $grade_id] = [
                    'quantity' => count($graded_stock_ids),
                    'grade' => $grade,
                    'grade_id' => $grade_id,
                    'color' => $color,
                    'color_id' => $color_id,
                    'stock_ids' => $graded_stock_ids,
                    'total_cost' => $total_cost,
                    'average_cost' => $average_cost,
                ];
                $total_graded_count['quantity'] += count($graded_stock_ids);
                $total_graded_count['total_cost'] += $total_cost;
                $total_graded_count['average_cost'] += $average_cost;
            }
        }

        $data['graded_count'] = $graded_count;

        $data['total_graded_count'] = [
            'quantity' => $total_graded_count['quantity'],
            'total_cost' => amount_formatter($total_graded_count['total_cost']),
            'average_cost' => amount_formatter($total_graded_count['average_cost']),
        ];

        return response()->json($data);
    }

    public function inventoryGetVendorWiseAverage(){

        if(request('aftersale') != 1){

            $aftersale = Order_item_model::whereHas('order', function ($q) {
                $q->where('order_type_id',4)->where('status','<',3);
            })->pluck('stock_id')->toArray();
        }else{
            $aftersale = [];
        }
        if(request('brand') != '' || request('category') != '' ){
            $product_ids = Products_model::
            when(request('category') != '', function ($q) {
                return $q->where('category', request('category'));
            })
            ->when(request('brand') != '', function ($q) {
                return $q->where('brand', request('brand'));
            })->pluck('id')->toArray();
        }else{
            $product_ids = [];
        }
        $data['vendor_average_cost'] = Stock_model::where('stock.deleted_at',null)->where('order_items.deleted_at',null)->where('orders.deleted_at',null)
            ->when(request('aftersale') != 1, function ($q) use ($aftersale) {
                return $q->whereNotIn('stock.id',$aftersale);
            })

            ->when(request('variation') != '', function ($q) {
                return $q->where('stock.variation_id', request('variation'));
            })
            ->when(request('stock_status') != '', function ($q) {
                return $q->where('stock.status', request('stock_status'));
            })
            ->when(request('stock_status') == '', function ($q) {
                return $q->where('stock.status', 1);
            })

            ->whereHas('order', function ($q) {
                $q->when(request('vendor') != '', function ($q) {
                    return $q->where('customer_id', request('vendor'));
                })
                ->when(request('status') != '', function ($q) {
                    return $q->where('status', request('status'));
                });
            })
            ->whereHas('variation', function ($q) use ($product_ids) {
                $q->when(request('category') != '' || request('brand') != '', function ($q) use ($product_ids) {
                    return $q->whereIn('product_id', $product_ids);
                })
                ->when(request('storage') != '', function ($q) {
                    return $q->where('storage', request('storage'));
                })
                ->when(request('color') != '', function ($q) {
                    return $q->where('color', request('color'));
                })
                ->when(request('product') != '', function ($q) {
                    return $q->where('product_id', request('product'));
                })
                ->when(request('grade') != '', function ($q) {
                    $grades = json_decode(html_entity_decode(request('grade')));
                    if($grades != null){
                        $q->whereIn('grade', $grades);
                    }
                });
            })

            // ->join('order_items', 'stock.id', '=', 'order_items.stock_id')
            ->join('order_items', function ($join) {
                $join->on('stock.id', '=', 'order_items.stock_id')
                    ->whereRaw('order_items.order_id = stock.order_id');
            })
            ->join('orders', 'stock.order_id', '=', 'orders.id')
            ->select('orders.customer_id')
            ->selectRaw('COUNT(order_items.id) as total_qty')
            ->selectRaw('AVG(order_items.price) as average_price')
            ->selectRaw('SUM(order_items.price) as total_price')
            ->selectRaw('COUNT(order_items.id) as total_qty')
            ->groupBy('orders.customer_id')
            ->get();

        $data['vendor_average_cost'] = $data['vendor_average_cost']->map(function ($item) {
            $item->total_price = amount_formatter($item->total_price);
            $item->average_price = amount_formatter($item->average_price);
            return $item;
        });

        return response()->json($data);
    }

    public function inventoryGetAverageCost(){

        if(request('aftersale') != 1){

            $aftersale = Order_item_model::whereHas('order', function ($q) {
                $q->where('order_type_id',4)->where('status','<',3);
            })->pluck('stock_id')->toArray();
        }else{
            $aftersale = [];
        }
        if(request('brand') != '' || request('category') != '' ){
            $product_ids = Products_model::
            when(request('category') != '', function ($q) {
                return $q->where('category', request('category'));
            })
            ->when(request('brand') != '', function ($q) {
                return $q->where('brand', request('brand'));
            })->pluck('id')->toArray();
        }else{
            $product_ids = [];
        }
        $data['average_cost'] = Stock_model::where('stock.deleted_at',null)->where('order_items.deleted_at',null)


            ->when(request('aftersale') != 1, function ($q) use ($aftersale) {
                return $q->whereNotIn('stock.id',$aftersale);
            })

            ->when(request('variation') != '', function ($q) {
                return $q->where('stock.variation_id', request('variation'));
            })
            ->when(request('stock_status') != '', function ($q) {
                return $q->where('stock.status', request('stock_status'));
            })
            ->when(request('stock_status') == '', function ($q) {
                return $q->where('stock.status', 1);
            })
            ->whereHas('order', function ($q) {
                $q->when(request('vendor') != '', function ($q) {
                    return $q->where('customer_id', request('vendor'));
                })
                ->when(request('status') != '', function ($q) {
                    return $q->where('status', request('status'));
                });
            })
            ->whereHas('variation', function ($q) use ($product_ids) {
                $q->when(request('category') != '' || request('brand') != '', function ($q) use ($product_ids) {
                    return $q->whereIn('product_id', $product_ids);
                })
                ->when(request('storage') != '', function ($q) {
                    return $q->where('storage', request('storage'));
                })
                ->when(request('color') != '', function ($q) {
                    return $q->where('color', request('color'));
                })
                ->when(request('product') != '', function ($q) {
                    return $q->where('product_id', request('product'));
                })
                ->when(request('grade') != '', function ($q) {
                    $grades = json_decode(html_entity_decode(request('grade')));
                    if($grades != null){
                        $q->whereIn('grade', $grades);
                    }
                });
            })
            // ->join('order_items', 'stock.id', '=', 'order_items.stock_id')
            ->join('order_items', function ($join) {
                $join->on('stock.id', '=', 'order_items.stock_id')
                    ->where('order_items.deleted_at', null)
                    ->whereRaw('order_items.order_id = stock.order_id');
            })
            ->selectRaw('COUNT(order_items.id) as total_qty')
            ->selectRaw('AVG(order_items.price) as average_price')
            ->selectRaw('SUM(order_items.price) as total_price')
            // ->pluck('average_price')
            ->first();

        $data['average_cost'] = $data['average_cost']->map(function ($item) {
            $item->total_price = amount_formatter($item->total_price);
            $item->average_price = amount_formatter($item->average_price);
            return $item;
        });

        return response()->json($data);
    }

    public function update_product($id){

        Products_model::where('id', $id)->update(request('update'));
        return redirect()->back();
    }

    public function get_stock_cost($id){
        $stock = Stock_model::find($id);
        return $stock->purchase_item->price;
    }
    public function get_stock_price($id){
        $stock = Stock_model::find($id);
        return $stock->last_item()->price;
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
        if(request('reset_counter') == 1){
            session()->put('counter', 0);
            return redirect()->back();
        }
        $last = Process_model::where('process_type_id',20)->orderBy('id','desc')->first();
        $last->status = 1;
        $last->save();
        session()->put('success', 'Inventory Verification started');
        return redirect()->back();
    }

    public function end_verification() {


        $aftersale = Order_item_model::whereHas('order', function ($q) {
            $q->where('order_type_id',4)->where('status','<',3);
        })->pluck('stock_id')->toArray();

        $verification = Process_model::where(['process_type_id'=>20, 'status'=>1])->first();


        // $repaired = Process_stock_model::whereHas('process', function ($q) {
        //     $q->where('process_type_id', 9);
        // })->where('status',2)->where('updated_at','>=',date('Y-m-01 00:00:00'))->pluck('stock_id')->toArray();
        // $recent_operations = Stock_operations_model::where('created_at','>=',date('Y-m-01 00:00:00'))->pluck('stock_id')->toArray();

        $remaining_stocks = Stock_model::where('status', 1)->whereHas('order', function ($q) {
            $q->where('status', 3);
        })->whereNotIn('id', $aftersale)
        // ->whereNotIn('id', $repaired)
        // ->whereNotIn('id', $recent_operations)
        ->whereNotIn('id', Process_stock_model::where('process_id', $verification->id)->pluck('stock_id')->toArray())->get();
        // dd($remaining_stocks);


        $stock_imeis = $remaining_stocks->whereNotNull('imei')->pluck('imei')->toArray();
        $stock_imeis += $remaining_stocks->whereNotNull('serial_number')->pluck('serial_number')->toArray();

        $imeis = implode(" ",$stock_imeis);

        $move = new MoveInventory();

        $move->change_grade(false, $imeis);




        $remaining_stocks = Stock_model::whereIn('id', $remaining_stocks->pluck('id'))->get();

        foreach($remaining_stocks as $stock){
            $process_stock = Process_stock_model::firstOrNew(['process_id'=>$verification->id, 'stock_id'=>$stock->id]);
            if($process_stock->id == null){
                $process_stock->variation_id = $stock->variation_id;
                $process_stock->admin_id = session('user_id');
                $process_stock->status = 2;
                $process_stock->description = 'Missing Stock';
                $process_stock->save();


            }
        }



        $verification = Process_model::where(['process_type_id'=>20, 'status'=>1])->update(['status'=>2,'description'=>request('description')]);
        session()->put('success', 'Inventory Verification ended');
        return redirect()->back();
    }
    public function delete_verification_item($id) {
        if(session('user')->hasPermission('delete_verification_item')){}else{
            session()->put('error', 'Permission Denied');
            return redirect()->back();
        }
        $verification = Process_stock_model::find($id);
        $verification->delete();
        session()->put('success', 'Verification Item deleted');
        return redirect()->back();
    }

    public function add_verification_imei($process_id) {
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
            // if (ctype_digit(request('imei'))) {
            //     $i = request('imei');
            //     $s = null;
            // } else {
            //     $i = null;
            //     $s = request('imei');
            // }
            // $stock = Stock_model::where(['imei' => $i, 'serial_number' => $s])->first();
            if($stock == null){
                session()->put('error', 'IMEI Invalid / Not Found');
                return redirect()->back();

            }

            $stock->availability();


            if(request('copy') == 1){
                $variation = $stock->variation;
                if(request('product_id') != null){
                    $product_id = request('product_id');
                }else{
                    $product_id = $variation->product_id;
                }
                if(request('storage') != null){
                    $storage_id = request('storage');
                }else{
                    $storage_id = $variation->storage;
                }
                if(request('color') != null){
                    $color_id = request('color');
                }else{
                    $color_id = $variation->color;
                }
                if(request('grade') != null){
                    $grade_id = request('grade');
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
                        'description' => 'Variation changed during inventory verification',
                        'admin_id' => session('user_id'),
                    ]);
                    session()->put('success', 'Stock Variation changed successfully from '.$stock->variation_id.' to '.$new_variation->id);
                    $stock->variation_id = $new_variation->id;
                    $stock->save();
                }
                    session()->put('copy', 1);
                    session()->put('color', request('color'));
                    session()->put('grade', request('grade'));
            }else{
                session()->put('copy', 0);
                // session()->put('product_id', $stock->variation->product_id);
                // session()->put('storage', $stock->variation->storage);
                session()->put('color', $stock->variation->color);
                session()->put('grade', $stock->variation->grade);
            }

            $process_stock = Process_stock_model::firstOrNew(['process_id'=>$process_id, 'stock_id'=>$stock->id]);
            $process_stock->admin_id = session('user_id');
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

                session()->put('success', 'Stock Verified successfully: '.$model.' - '.$storage.' - '.$color.' - '.$grade);
            }else{
                if($process_stock->status == 2){
                    $process_stock->status = 1;
                    $process_stock->save();
                    session()->put('success', 'Stock ReVerified successfully');
                }else{
                    session()->put('error', 'Stock already verified');
                }
            }
        }
        return redirect()->back();
    }


    public function belfast_inventory(){


        $data['title_page'] = "Belfast Inventory";
        session()->put('page_title', $data['title_page']);
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }
        $data['return_order'] = Order_model::where(['order_type_id'=>4,'status'=>1])->first();
        if($data['return_order'] == null){
            session()->put('error', 'No Open Sales Return Order found');
            return redirect()->back();
        }
        $data['vendors'] = Customer_model::whereNotNull('is_vendor')->pluck('first_name','id');
        $data['products'] = Products_model::pluck('model','id');
        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $data['currencies'] = Currency_model::pluck('sign','id');
        $data['categories'] = Category_model::get();
        $data['brands'] = Brand_model::get();
        $data['stocks'] = Stock_model::with(['variation','order','latest_operation'])
        ->when(request('vendor') != '', function ($q) {
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

