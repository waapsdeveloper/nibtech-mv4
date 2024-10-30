<?php

namespace App\Http\Livewire;
    use App\Http\Controllers\BackMarketAPIController;
use App\Models\Brand_model;
use App\Models\Category_model;
use Livewire\Component;
    use App\Models\Listing_model;
    use App\Models\Products_model;
    use App\Models\Color_model;
use App\Models\Currency_exchange_model;
use App\Models\ExchangeRate;
use App\Models\Storage_model;
    use App\Models\Grade_model;
use App\Models\Order_item_model;
use App\Models\Order_status_model;
use App\Models\Variation_model;
use Google\Service\Books\Category;

class Listing extends Component
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

        $data['title_page'] = "Listings";
        // $this->refresh_stock();
        $user_id = session('user_id');
        $data['order_statuses'] = Order_status_model::get();
        $data['bm'] = new BackMarketAPIController();

        $data['eur_gbp'] = ExchangeRate::where('target_currency','GBP')->first()->rate;
        $data['categories'] = Category_model::all();
        $data['brands'] = Brand_model::all();
        $data['products'] = Products_model::all();
        $data['storages'] = Storage_model::pluck('name','id');
        $data['colors'] = Color_model::pluck('name','id');
        $data['grades'] = Grade_model::where('id',"<",6)->pluck('name','id')->toArray();

        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }
        $data['per_page'] = $per_page;
        // $data['variations'] = Variation_model::with('listings', 'listings.country_id', 'listings.currency', 'product','available_stocks','pending_orders')
        //     ->join('products', 'products.id', '=', 'variations.product_id')
        //     ->when(request('reference_id') != '', function ($q) {
        //         return $q->where('reference_id', request('reference_id'));
        //     })
        //     ->when(request('category') != '', function ($q) {
        //         return $q->whereHas('product', function ($q) {
        //             $q->where('category', request('category'));
        //         });
        //     })
        //     ->when(request('brand') != '', function ($q) {
        //         return $q->whereHas('product', function ($q) {
        //             $q->where('brand', request('brand'));
        //         });
        //     })
        //     ->when(request('product') != '', function ($q) {
        //         return $q->where('product_id', request('product'));
        //     })
        //     ->when(request('sku') != '', function ($q) {
        //         return $q->where('sku', request('sku'));
        //     })
        //     ->when(request('color') != '', function ($q) {
        //         return $q->where('color', request('color'));
        //     })
        //     ->when(request('storage') != '', function ($q) {
        //         return $q->where('storage', request('storage'));
        //     })
        //     // ->when(request('grade') != '', function ($q) {
        //     //     return $q->where('grade', request('grade'));
        //     // })

        //     ->when(request('grade') != [], function ($q) {
        //         return $q->whereIn('grade', request('grade'));
        //     })
        //     ->when(request('listed_stock') != '', function ($q) {
        //         if(request('listed_stock') == 1){
        //             return $q->where('listed_stock', '>', 0);
        //         }elseif(request('listed_stock') == 2){
        //             return $q->where('listed_stock', '<=', 0);
        //         }
        //     })
        //     ->when(request('available_stock') != '', function ($q) {
        //         if(request('available_stock') == 1){
        //             return $q->whereHas('available_stocks');
        //         }elseif(request('available_stock') == 2){
        //             return $q->whereDoesntHave('available_stocks');
        //         }
        //     })
        //     ->when(request('state') != '', function ($q) {
        //         return $q->where('state', request('state'));
        //     })
        //     ->where('sku', '!=', null)
        //     ->orderBy('listed_stock', 'desc')
        //     ->paginate(10)
        //     ->onEachSide(5)
        //     ->appends(request()->except('page'));

        $data['variations'] = Variation_model::with('listings', 'listings.country_id', 'listings.currency', 'product', 'available_stocks', 'pending_orders')
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
            ->onEachSide(5)
            ->appends(request()->except('page'));



        return view('livewire.listing')->with($data);
    }
    public function get_last_week_average($id){
        $order_items = Order_item_model::where('variation_id',$id)->whereHas('order', function($q){
            $q->whereBetween('created_at', [now()->subDays(7), now()])->where('order_type_id',3);
        })->avg('price');

        return "7 days average: €".$order_items;
    }
    public function get_sales($id){
        $week = $this->get_last_week_average($id);

        return $week;
    }
    public function update_quantity($id){
        $variation = Variation_model::find($id);
        $variation->listed_stock = request('stock');
        $variation->save();
        $bm = new BackMarketAPIController();
        $response = $bm->updateOneListing($variation->reference_id,json_encode(['quantity'=>request('stock')]));

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
            return $response->min_price;
        }elseif(request('price')){
            return $response->price;
        }
    }
    public function get_competitors($id){
        $listing = Listing_model::find($id);
        $bm = new BackMarketAPIController();
        $response = $bm->getListingCompetitors($listing->variation->reference_id, $listing->country_id->market_code);
        dd($response);
        echo "Hello";
    }
    public function refresh_stock(){
        $listings = Listing_model::where('reference_id','!=',NULL)->pluck('reference_id','id');
        $bm = new BackMarketAPIController();
        foreach($listings as $id => $reference_id){
            $var = $bm->getOneListing($reference_id);
            // echo $id." ".$reference_id;
            // dd($var);

            listing_model::where('id', $id)->update([
                'sku' => $var->sku,
                'stock' => $var->quantity,
            ]);
        }

    }

}
