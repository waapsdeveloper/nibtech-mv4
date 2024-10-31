<?php

namespace App\Http\Livewire;
use App\Http\Controllers\BackMarketAPIController;
use App\Models\Brand_model;
use App\Models\Category_model;
use Livewire\Component;
use App\Models\Listing_model;
use App\Models\Products_model;
use App\Models\Color_model;
use App\Models\ExchangeRate;
use App\Models\Storage_model;
use App\Models\Grade_model;
use App\Models\Order_item_model;
use App\Models\Order_status_model;
use App\Models\Stock_model;
use App\Models\Variation_model;

class SearchListing extends Component
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
        $data['order_statuses'] = Order_status_model::get();
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


        return view('livewire.listings.search-listing')->with($data);
    }
}
