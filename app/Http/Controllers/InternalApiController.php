<?php

namespace App\Http\Controllers;

use App\Models\Country_model;
use App\Models\Listing_model;
use App\Models\Order_item_model;
use App\Models\Stock_model;
use App\Models\Variation_model;
use Illuminate\Http\Request;

class InternalApiController extends Controller
{
    //
    public function get_variations(){
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }
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
        ->when(request('state') != '', function ($q) {
            return $q->where('state', request('state'));
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
    public function get_variation_available_stocks($id){
        $stocks = Stock_model::where('variation_id',$id)->where('status',1)->get();

        $stock_costs = Order_item_model::whereHas('order', function($q){
            $q->where('order_type_id',1);
        })->whereIn('stock_id',$stocks->pluck('id'))->pluck('price','stock_id');

        return response()->json(['stocks'=>$stocks, 'stock_costs'=>$stock_costs]);

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

        return "Today: €".amount_formatter($order_items)." (".$order_items_count.")";
    }
    public function get_last_week_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(7), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->avg('price');
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(7), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->count();

        return "7 days: €".amount_formatter($order_items)." (".$order_items_count.")";
    }
    public function get_2_week_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(14), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->avg('price');
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(14), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->count();

        return "14 days: €".amount_formatter($order_items)." (".$order_items_count.")";
    }
    public function get_30_days_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(30), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->avg('price');
        $order_items_count = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(30), now()->yesterday()->endOfDay()])->where('order_type_id',3);
        })->count();

        return "30 days: €".amount_formatter($order_items)." (".$order_items_count.")";
    }

    public function get_sales($id){
        $week = $this->get_today_average($id);
        $week .= " - Previous - ".$this->get_last_week_average($id);
        $week .= " - ".$this->get_2_week_average($id);
        $week .= " - ".$this->get_30_days_average($id);

        return "Average: ".$week;
    }
    public function getUpdatedQuantity($variationId)
    {
        $bm = new BackMarketAPIController();
        // Call update_qty on the variation instance
        $variation = Variation_model::findOrFail($variationId);
        $updatedQuantity = $variation->update_qty($bm);
        return response()->json(['updatedQuantity' => $updatedQuantity]);
    }
    public function getCompetitors($id){
        $error = "";
        $variation = Variation_model::find($id);
        $bm = new BackMarketAPIController();
        $responses = $bm->getListingCompetitors($variation->reference_uuid);
        foreach($responses as $list){
            if(is_string($list)){
                $error .= $list;
                continue;
            }
            $country = Country_model::where('code',$list->market)->first();
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
        $listings = Listing_model::where('variation_id',$id)->get();
        return response()->json(['listings'=>$listings, 'error'=>$error]);
    }

    public function inventoryGetVendorWiseAverage(){

        if(request('aftersale') != 1){

            $aftersale = Order_item_model::whereHas('order', function ($q) {
                $q->where('order_type_id',4)->where('status','<',3);
            })->pluck('stock_id')->toArray();
        }else{
            $aftersale = [];
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
                    if (request('grade') !== null) {
                        $grades = json_decode(html_entity_decode(request('grade')));
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
            ->selectRaw('AVG(order_items.price) as average_price')
            ->selectRaw('SUM(order_items.price) as total_price')
            ->selectRaw('COUNT(order_items.id) as total_qty')
            ->groupBy('orders.customer_id')
            ->get();

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
        return request()->all();
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
                    if (request('grade') !== null) {
                        $grades = json_decode(html_entity_decode(request('grade')));
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
            ->selectRaw('AVG(order_items.price) as average_price')
            ->selectRaw('SUM(order_items.price) as total_price')
            // ->pluck('average_price')
            ->first();

        return response()->json($data);
    }
}
