<?php

namespace App\Http\Livewire;
    use App\Http\Controllers\BackMarketAPIController;
    use Livewire\Component;
    use App\Models\Variation_model;
    use App\Models\Products_model;
    use App\Models\Color_model;
    use App\Models\Storage_model;
    use App\Models\Grade_model;
use App\Models\Listing_model;
use App\Models\Order_item_model;
use App\Models\Order_status_model;
use App\Models\Process_model;
use App\Models\Stock_model;
use App\Models\Stock_operations_model;
use Illuminate\Support\Facades\DB;

class Variation extends Component
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

        $data['title_page'] = "Variations";
        // $this->refresh_stock();
        $user_id = session('user_id');
        $data['order_statuses'] = Order_status_model::get();

            if(request('per_page') != null){
                $per_page = request('per_page');
            }else{
                $per_page = 10;
            }


        $data['products'] = Products_model::all();
        $data['colors'] = Color_model::all();
        $data['storages'] = Storage_model::all();
        $data['grades'] = Grade_model::all();
        $data['variations'] = Variation_model::
        when(request('reference_id') != '', function ($q) {
            return $q->where('reference_id', request('reference_id'));
        })
        ->when(request('product') != '', function ($q) {
            return $q->where('product_id', request('product'));
        })
        ->when(request('sku') != '', function ($q) {
            return $q->where('sku', 'LIKE', '%'.request('sku').'%');
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
        ->withCount('available_stocks')
        ->orderBy('name','desc')
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));

        return view('livewire.variation')->with($data);
    }

    public function update_product($id){

        Variation_model::where('id', $id)->update(request('update'));
        return redirect()->back();
    }
    public function merge($id){
        if(request('variation')){
            $duplicate = Variation_model::find($id);
            $new = Variation_model::find(request('variation'));
            if($duplicate != null && $new != null){

                // Update related records to point to the original variation
                Listing_model::where('variation_id', $duplicate->id)->update(['variation_id' => $new->id]);
                Order_item_model::where('variation_id', $duplicate->id)->update(['variation_id' => $new->id]);
                Process_model::where('old_variation_id', $duplicate->id)->update(['old_variation_id' => $new->id]);
                Process_model::where('new_variation_id', $duplicate->id)->update(['new_variation_id' => $new->id]);
                Stock_model::where('variation_id', $duplicate->id)->update(['variation_id' => $new->id]);
                Stock_operations_model::where('old_variation_id', $duplicate->id)->update(['old_variation_id' => $new->id]);
                Stock_operations_model::where('new_variation_id', $duplicate->id)->update(['new_variation_id' => $new->id]);

                // Soft delete the duplicate
                $duplicate->delete();
                session()->put('success', 'Variation Merged');
            }else{
                session()->put('error', 'Variation Not Found');

            }
        }else{
            session()->put('error', 'Variation Not selected');
        }

        return redirect()->back();
    }

    public function refresh_stock(){
        $variations = Variation_model::where('reference_id','!=',NULL)->pluck('reference_id','id');
        $bm = new BackMarketAPIController();
        foreach($variations as $id => $reference_id){
            $var = $bm->getOneListing($reference_id);
            // echo $id." ".$reference_id;
            // dd($var);

            Variation_model::where('id', $id)->update([
                'sku' => $var->sku,
                'stock' => $var->quantity,
            ]);
        }

    }

}
