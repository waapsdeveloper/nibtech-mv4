<?php

namespace App\Http\Controllers;

use App\Models\Color_model;
use App\Models\Country_model;
use App\Models\Customer_model;
use App\Models\ExchangeRate;
use App\Models\Grade_model;
use App\Models\Listing_model;
use App\Models\Order_item_model;
use App\Models\Order_model;
use App\Models\Products_model;
use App\Models\Stock_model;
use App\Models\Storage_model;
use App\Models\Variation_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListingController extends Controller
{
    //
    public function index()
    {

        $data['title_page'] = "Listings";
        session()->put('page_title', $data['title_page']);
        $data['bm'] = new BackMarketAPIController();
        $data['storages'] = Storage_model::pluck('name','id');
        $data['colors'] = Color_model::pluck('name','id');
        $data['grades'] = Grade_model::where('id',"<",6)->pluck('name','id')->toArray();
        $data['eur_gbp'] = ExchangeRate::where('target_currency','GBP')->first()->rate;
        $countries = Country_model::all();
        foreach($countries as $country){
            $data['countries'][$country->id] = $country;
        }



        return view('listings')->with($data);
    }
    public function get_variations(){
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }

        if(request('product_name') != null){
            $product_name = trim(request('product_name'));

            $arr = explode(" ", $product_name);
            $last = end($arr);


            $storage_search = Storage_model::where('name', 'like', $last.'%')->pluck('id');

            if($storage_search->count() > 0){
                // dd($storage_search);
                array_pop($arr);
                $product_name = implode(" ", $arr);
            }else{
                $storage_search = [];
            }
            $product_search = Products_model::where('model', 'like', '%'.$product_name.'%')->pluck('id');


        }else{
            $product_search = [];
            $storage_search = [];
        }
        // dd($product_search, $storage_search);

        return Variation_model::with('listings', 'listings.country_id', 'listings.currency', 'product', 'available_stocks', 'pending_orders')
        ->when(request('reference_id') != '', function ($q) {
            return $q->where('reference_id', request('reference_id'));
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
        ->when(count($product_search) > 0, function ($q) use ($product_search) {
            return $q->whereIn('product_id', $product_search);
        })
        ->when(count($storage_search) > 0, function ($q) use ($storage_search) {
            return $q->whereIn('storage', $storage_search);
        })
        ->when(request('sku') != '', function ($q) {
            return $q->where('sku', request('sku'));
        })
        ->when(request('color') != '', function ($q) {
            return $q->where('color', request('color'));
        })
        ->when(request('storage') != '', function ($q) {
            return $q->where('storage', request('storage'));
        })
        ->when(request('grade') != [], function ($q) {
            return $q->whereIn('grade', request('grade'));
        })
        ->when(request('listed_stock') != '', function ($q) {
            if (request('listed_stock') == 1) {
                return $q->where('listed_stock', '>', 0);
            } elseif (request('listed_stock') == 2) {
                return $q->where('listed_stock', '<=', 0);
            }
        })
        ->when(request('available_stock') != '', function ($q) {
            if (request('available_stock') == 1) {
                return $q->whereHas('available_stocks');
            } elseif (request('available_stock') == 2) {
                return $q->whereDoesntHave('available_stocks');
            }
        })
        ->when(request('state') == '', function ($q) {
            return $q->whereIn('state', [2, 3]);
        })
        ->when(request('state') != '' && request('state') != 10, function ($q) {
            return $q->where('state', request('state'));

        })
        ->when(request('handler_status') != '', function ($q) {
            return $q->whereHas('listings', function ($q) {
                $q->where('handler_status', request('handler_status'));
            });
        })
        ->where('sku', '!=', null)
        ->when(request('sort') == 4, function ($q) {
            return $q->join('products', 'variation.product_id', '=', 'products.id') // Join the products table
                ->orderBy('products.model', 'asc') // Order by product model in ascending order
                ->orderBy('variation.storage', 'asc') // Secondary order by storage
                ->orderBy('variation.color', 'asc') // Secondary order by color
                ->orderBy('variation.grade', 'asc') // Secondary order by grade
                // ->orderBy('listed_stock', 'desc') // Secondary order by listed stock
                ->select('variation.*'); // Select only the variation columns
        })
        ->when(request('sort') == 3, function ($q) {
            return $q->join('products', 'variation.product_id', '=', 'products.id') // Join the products table
                ->orderBy('products.model', 'desc') // Order by product model in descending order
                ->orderBy('variation.storage', 'asc') // Secondary order by storage
                ->orderBy('variation.color', 'asc') // Secondary order by color
                ->orderBy('variation.grade', 'asc') // Secondary order by grade
                // ->orderBy('listed_stock', 'desc') // Secondary order by listed stock
                ->select('variation.*'); // Select only the variation columns
        })
        ->when(request('sort') == 2, function ($q) {
            return $q->orderBy('listed_stock', 'asc') // Order by listed stock in ascending order
                ->orderBy('variation.storage', 'asc') // Secondary order by storage
                ->orderBy('variation.color', 'asc') // Secondary order by color
                ->orderBy('variation.grade', 'asc'); // Secondary order by grade
        })
        ->when(request('sort') == 1 || request('sort') == null, function ($q) {
            return $q->orderBy('listed_stock', 'desc') // Order by listed stock in descending order
                ->orderBy('variation.storage', 'asc') // Secondary order by storage
                ->orderBy('variation.color', 'asc') // Secondary order by color
                ->orderBy('variation.grade', 'asc'); // Secondary order by grade
        })
        ->paginate($per_page)
        ->appends(request()->except('page'));
    }

    public function get_target_variations(){
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }

        if(request('product_name') != null){
            $product_name = trim(request('product_name'));

            $arr = explode(" ", $product_name);
            $last = end($arr);


            $storage_search = Storage_model::where('name', 'like', $last.'%')->pluck('id');

            if($storage_search->count() > 0){
                // dd($storage_search);
                array_pop($arr);
                $product_name = implode(" ", $arr);
            }else{
                $storage_search = [];
            }
            $product_search = Products_model::where('model', 'like', '%'.$product_name.'%')->pluck('id');


        }else{
            $product_search = [];
            $storage_search = [];
        }
        // dd($product_search, $storage_search);

        return Variation_model::when(request('reference_id') != '', function ($q) {
            return $q->where('reference_id', request('reference_id'));
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
        ->when(count($product_search) > 0, function ($q) use ($product_search) {
            return $q->whereIn('product_id', $product_search);
        })
        ->when(count($storage_search) > 0, function ($q) use ($storage_search) {
            return $q->whereIn('storage', $storage_search);
        })
        ->when(request('sku') != '', function ($q) {
            return $q->where('sku', request('sku'));
        })
        // ->when(request('color') != '', function ($q) {
        //     return $q->where('color', request('color'));
        // })
        ->when(request('storage') != '', function ($q) {
            return $q->where('storage', request('storage'));
        })
        ->when(request('grade') != [], function ($q) {
            return $q->whereIn('grade', request('grade'));
        })
        ->when(request('listed_stock') != '', function ($q) {
            if (request('listed_stock') == 1) {
                return $q->where('listed_stock', '>', 0);
            } elseif (request('listed_stock') == 2) {
                return $q->where('listed_stock', '<=', 0);
            }
        })
        ->when(request('available_stock') != '', function ($q) {
            if (request('available_stock') == 1) {
                return $q->whereHas('available_stocks');
            } elseif (request('available_stock') == 2) {
                return $q->whereDoesntHave('available_stocks');
            }
        })
        ->when(request('state') == '', function ($q) {
            return $q->whereIn('state', [2, 3]);
        })
        ->when(request('state') != '' && request('state') != 10, function ($q) {
            return $q->where('state', request('state'));

        })
        ->when(request('handler_status') != '', function ($q) {
            return $q->whereHas('listings', function ($q) {
                $q->where('handler_status', request('handler_status'));
            });
        })
        ->where('sku', '!=', null)
        ->whereNull('variation.deleted_at')
        ->whereNull('products.deleted_at')
        ->join('products', 'variation.product_id', '=', 'products.id') // Join the products table
        ->join('storage', 'variation.storage', '=', 'storage.id') // Join the storage table
        ->join('grade', 'variation.grade', '=', 'grade.id')  // Join the grade table
        ->join('listings', function($join){
            $join->on('variation.id', '=', 'listings.variation_id')
            ->where('listings.country', 73);
        })
        ->orderBy('products.model', 'asc') // Order by product model in ascending order
        ->orderBy('variation.storage', 'asc') // Secondary order by storage
        // ->orderBy('variation.color', 'asc') // Secondary order by color
        ->orderBy('variation.grade', 'asc') // Secondary order by grade
        // ->orderBy('listed_stock', 'desc') // Secondary order by listed stock
        // ->select('variation.*') // Select only the variation columns
        ->groupBy(['variation.product_id', 'variation.storage', 'variation.grade'])
        ->select('variation.product_id', 'products.model as product_name', 'variation.storage', 'storage.name as storage_name', 'variation.grade', 'grade.name as grade_name', DB::raw('GROUP_CONCAT(variation.id) as ids'))
        ->paginate(300)
        ->appends(request()->except('page'));
        // ->get();
    }
    public function get_variation_available_stocks($id){
        $variation = Variation_model::find($id);
        if ($variation->product->brand == 2) {
            $variation_ids = Variation_model::where('product_storage_sort_id', $variation->product_storage_sort_id)->whereIn('grade',[1,2,3,4,5,7,9])->pluck('id');
            $stocks = Stock_model::whereIn('variation_id', $variation_ids)->where('status', 1)->whereHas('active_order')->get();
        } elseif ($variation->product->category == 6) {
            $variation_ids = Variation_model::where('product_storage_sort_id', $variation->product_storage_sort_id)->whereIn('grade',[1,2,3,4,5,7,9])->pluck('id');
            $stocks = Stock_model::whereIn('variation_id', $variation_ids)->where('status', 1)->whereHas('active_order')->get();
        } else {
            $stocks = Stock_model::where('variation_id', $id)->where('status', 1)->get();
        }

        $stock_costs = Order_item_model::whereHas('order', function($q){
            $q->where('order_type_id',1);
        })->whereIn('stock_id',$stocks->pluck('id'))->pluck('price','stock_id');

        $vendors = Customer_model::whereNotNull('is_vendor')->pluck('last_name','id');

        $po = Order_model::where('order_type_id',1)->pluck('customer_id','id');

        return response()->json(['stocks'=>$stocks, 'stock_costs'=>$stock_costs, 'vendors'=>$vendors, 'po'=>$po]);

    }

    public function get_stock_cost($id){
        $stock = Stock_model::find($id);
        return $stock->purchase_item->price;
    }
    public function get_stock_price($id){
        $stock = Stock_model::find($id);
        return $stock->last_item()->price;
    }
    public function get_today_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->startOfDay(), now()])->where('order_type_id',3);
        })->avg('price');
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->startOfDay(), now()])->where('order_type_id',3);
        })->count();

        return "Today: €".number_format($order_items,2)." (".$order_items_count.")";
    }
    public function get_yesterday_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->yesterday()->startOfDay(), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->avg('price');
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->yesterday()->startOfDay(), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->count();

        return "Yesterday: €".number_format($order_items,2)." (".$order_items_count.")";
    }
    public function get_last_week_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(7), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->avg('price');
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(7), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->count();

        return "7 days: €".number_format($order_items,2)." (".$order_items_count.")";
    }
    public function get_2_week_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(14), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->avg('price');
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(14), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->count();

        return "14 days: €".number_format($order_items,2)." (".$order_items_count.")";
    }
    public function get_30_days_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(30), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->avg('price');
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(30), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->count();

        return "30 days: €".number_format($order_items,2)." (".$order_items_count.")";
    }

    public function get_sales($id){
        $week = $this->get_today_average($id);
        $week .= " - ".$this->get_yesterday_average($id);
        $week .= "<br>".$this->get_last_week_average($id);
        $week .= " - ".$this->get_2_week_average($id);
        $week .= " - ".$this->get_30_days_average($id);

        return "Avg: ".$week;
    }
    public function getUpdatedQuantity($variationId)
    {
        $bm = new BackMarketAPIController();
        // Call update_qty on the variation instance
        $variation = Variation_model::findOrFail($variationId);
        $updatedQuantity = $variation->update_qty($bm);
        return response()->json(['updatedQuantity' => $updatedQuantity]);
    }
    public function getCompetitors($id, $no_check = 0){
        $error = "";
        $variation = Variation_model::find($id);
        // if($no_check = 0){

            $bm = new BackMarketAPIController();
            $responses = $bm->getListingCompetitors($variation->reference_uuid);
            if(is_string($responses) || is_int($responses)){
                $error = $responses;
                $error .= " - ".$variation->reference_uuid;
                Log::error($error);
            }
            foreach($responses as $list){
                if(is_string($list) || is_int($list)){
                    $error .= $list;
                    continue;
                }
                if(is_array($list)){
                    $error .= json_encode($list);
                    continue;
                }
                $country = Country_model::where('code',$list->market)->first();
                $listings = Listing_model::where('variation_id',$id)->where('country',$country->id)->get();
                if($listings->count() > 1){
                    $listings->each(function($listing, $key) {
                        if ($key > 0) {
                            $listing->delete();
                        }
                    });
                }
                $listing = Listing_model::firstOrNew(['variation_id'=>$id, 'country'=>$country->id]);
                $listing->reference_uuid = $list->product_id;
                if($list->price != null){
                    $listing->price = $list->price->amount;
                }
                if($list->min_price != null){
                    $listing->min_price = $list->min_price->amount;
                }
                $listing->buybox = $list->is_winning;
                $listing->buybox_price = $list->price_to_win->amount;
                $listing->buybox_winner_price = $list->winner_price->amount;
                $listing->save();
            }
            if($no_check == 1){
                return $responses;
            }
        // }
        $listings = Listing_model::where('variation_id',$id)->get();
        return response()->json(['listings'=>$listings, 'error'=>$error]);
    }
    public function update_quantity($id){
        $variation = Variation_model::find($id);
        $bm = new BackMarketAPIController();
        $updatedQuantity = $variation->update_qty($bm);
        $response = $bm->updateOneListing($variation->reference_id,json_encode(['quantity'=>request('stock')]));
        if($response->quantity != null){
            $variation->listed_stock = $response->quantity;
            $variation->save();
        }
        return $response->quantity;
    }
    public function add_quantity($id){
        $variation = Variation_model::find($id);
        $bm = new BackMarketAPIController();
        $updatedQuantity = $variation->update_qty($bm);
        $response = $bm->updateOneListing($variation->reference_id,json_encode(['quantity'=>$updatedQuantity + request('stock')]));
        if($response->quantity != null){
            $variation->listed_stock = $response->quantity;
            $variation->save();
        }
        return $response->quantity;
    }
    public function update_price($id){
        $listing = Listing_model::find($id);
        $bm = new BackMarketAPIController();
        if(request('min_price')){
            $listing->min_price = request('min_price');
            $response = $bm->updateOneListing($listing->variation->reference_id,json_encode(['min_price'=>request('min_price')]), $listing->country_id->market_code);
        }elseif(request('price')){
            $listing->price = request('price');
            $response = $bm->updateOneListing($listing->variation->reference_id,json_encode(['price'=>request('price')]), $listing->country_id->market_code);
        }

        $listing->save();
        // print_r($response);
        // die;
        if(request('min_price')){
            return $response;
        }elseif(request('price')){
            return $response;
        }
    }
    public function update_limit($id){
        $listing = Listing_model::find($id);
        $listing->min_price_limit = request('min_price_limit');
        $listing->price_limit = request('price_limit');
        if($listing->min_price_limit == null && $listing->price_limit == null){
            $listing->handler_status = 0;
        }
        if($listing->min_price_limit != null || $listing->price_limit != null){
            $listing->handler_status = 1;
        }


        $listing->save();
        // print_r($response);
        // die;
        return $listing;
    }
    public function update_target($id){
        $listing = Listing_model::find($id);
        $listing->target_price = request('target');
        $listing->target_percentage = request('percent');

        $listing->save();
        // print_r($response);
        // die;
        return $listing;
    }

}
