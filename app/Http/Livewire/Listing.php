<?php

namespace App\Http\Livewire;
    use App\Http\Controllers\BackMarketAPIController;
use App\Models\Brand_model;
use App\Models\Category_model;
use Livewire\Component;
    use App\Models\listing_model;
    use App\Models\Products_model;
    use App\Models\Color_model;
    use App\Models\Storage_model;
    use App\Models\Grade_model;
    use App\Models\Order_status_model;
use App\Models\Variation_listing_qty_model;
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
                return $q->whereHas('variation_listing_qty', function ($q) {
                    $q->where('quantity', '>', 0);
                });
            }elseif(request('listed_stock') == 2){
                return $q->whereHas('variation_listing_qty', function ($q) {
                    $q->where('quantity', '<=', 0);
                });
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
        ->with('variation_listing_qty', 'listings')
        ->where('sku', '!=', null)
        ->orderBy('product_id', 'desc')
        ->paginate(10)
        ->onEachSide(5)
        ->appends(request()->except('page'));

        return view('livewire.listing')->with($data);
    }

    public function update_product($id){

        Listing_model::where('id', $id)->update(request('update'));
        return redirect()->back();
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
