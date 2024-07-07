<?php

namespace App\Http\Livewire;
    use App\Http\Controllers\BackMarketAPIController;
use App\Models\Brand_model;
use App\Models\Category_model;
use Livewire\Component;
    use App\Models\listing_model;
    use App\Models\Products_model;
    use App\Models\Color_model;
use App\Models\Currency_exchange_model;
use App\Models\ExchangeRate;
use App\Models\Storage_model;
    use App\Models\Grade_model;
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
        $data['grades'] = Grade_model::pluck('name','id')->toArray();
        $data['variations'] = Variation_model::
        when(request('reference_id') != '', function ($q) {
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
        ->when(request('grade') != '', function ($q) {
            return $q->where('grade', request('grade'));
        })
        ->when(request('listed_stock') != '', function ($q) {
            if(request('listed_stock') == 1){
                return $q->where('listed_stock', '>', 0);
            }elseif(request('listed_stock') == 2){
                return $q->where('listed_stock', '<=', 0);
            }
        })
        ->when(request('available_stock') != '', function ($q) {
            if(request('available_stock') == 1){
                return $q->whereHas('available_stocks');
            }elseif(request('available_stock') == 2){
                return $q->whereDoesntHave('available_stocks');
            }
        })
        ->when(request('state') != '', function ($q) {
            return $q->where('state', request('state'));
        })
        ->with('listings')
        ->where('sku', '!=', null)
        ->orderBy('listed_stock', 'desc')
        ->paginate(10)
        ->onEachSide(5)
        ->appends(request()->except('page'));

        return view('livewire.listing')->with($data);
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
            $response = $bm->updateOneListing($listing->reference_id,json_encode(['min_price'=>request('min_price')]), $listing->country_id->market_code);
        }elseif(request('price')){
            $listing->price = request('price');
            $response = $bm->updateOneListing($listing->reference_id,json_encode(['price'=>request('price')]), $listing->country_id->market_code);
        }

        $listing->save();

        if(request('min_price')){
            return $response->min_price;
        }elseif(request('price')){
            return $response->price;
        }
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
