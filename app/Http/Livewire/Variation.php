<?php

namespace App\Http\Livewire;
    use App\Http\Controllers\BackMarketAPIController;
    use Livewire\Component;
    use App\Models\Merchant_model;
    use App\Models\Category_model;
    use App\Models\Brand_model;
    use App\Models\Variation_model;
    use App\Models\Products_model;
    use App\Models\Color_model;
    use App\Models\Storage_model;
    use App\Models\Grade_model;
    use App\Models\Stock_model;
    use App\Models\Order_model;
    use App\Models\Order_item_model;
    use App\Models\Order_status_model;
    use App\Models\Customer_model;
    use App\Models\Currency_model;
    use App\Models\Country_model;
    use GuzzleHttp\Psr7\Request;
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Session;
    use App\Exports\OrdersExport;
    use App\Exports\PickListExport;
    use App\Exports\LabelsExport;
    use App\Exports\DeliveryNotesExport;
    use Illuminate\Support\Facades\DB;
    use Maatwebsite\Excel\Facades\Excel;



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
        // $this->refresh_stock();
        $user_id = session('user_id');
        $data['order_statuses'] = Order_status_model::get();

            if(request('per_page') != null){
                $per_page = request('per_page');
            }else{
                $per_page = 10;
            }

            switch (request('sort')){
                case 2: $sort = "reference_id"; $by = "ASC"; break;
                case 3: $sort = "variation.name"; $by = "DESC"; break;
                case 4: $sort = "variation.name"; $by = "ASC"; break;
                default: $sort = "reference_id"; $by = "DESC";
            }

        $data['products'] = Products_model::all();
        $data['colors'] = Color_model::all();
        $data['storages'] = Storage_model::all();
        $data['grades'] = Grade_model::all();
        $data['variations'] = Variation_model::
        when(request('grade') != '', function ($q) {
            return $q->where('grade', request('grade'));
        })
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
