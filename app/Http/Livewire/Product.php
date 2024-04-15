<?php

namespace App\Http\Livewire;
    use App\Http\Controllers\BackMarketAPIController;
    use Livewire\Component;
    use App\Models\Merchant_model;
    use App\Models\Category_model;
    use App\Models\Brand_model;
    use App\Models\Variation_model;
    use App\Models\Products_model;
    use App\Models\Stock_model;
    use App\Models\Order_model;
    use App\Models\Order_item_model;
    use App\Models\Order_status_model;
    use App\Models\Customer_model;
    use App\Models\Currency_model;
    use App\Models\Country_model;
    use App\Models\Storage_model;
    use App\Models\Color_model;
    use GuzzleHttp\Psr7\Request;
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Session;
    use App\Exports\OrdersExport;
    use App\Exports\PickListExport;
    use App\Exports\LabelsExport;
    use App\Exports\DeliveryNotesExport;
    use Illuminate\Support\Facades\DB;
    use Maatwebsite\Excel\Facades\Excel;



class Product extends Component
{
    public function mount()
    {
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect()->route('login');
        }
    }
    public function render()
    {
        // $this->import_sku();
        // die;

        $user_id = session('user_id');
        $data['order_statuses'] = Order_status_model::get();
            if(request('per_page') != null){
                $per_page = request('per_page');
            }else{
                $per_page = 10;
            }

            switch (request('sort')){
                case 2: $sort = "model"; $by = "DESC"; break;
                default: $sort = "model"; $by = "ASC";
            }

        $data['categories'] = Category_model::get();
        $data['brands'] = Brand_model::get();
        $data['products'] = Products_model::

        when(request('model') != '', function ($q) {
                $q->where('model', 'LIKE', '%' . request('model') . '%');
        })

        ->when(request('category') != '', function ($q) {
            return $q->where('category', request('category'));
        })
        ->when(request('brand') != '', function ($q) {
            return $q->where('brand', request('brand'));
        })
        ->orderBy($sort, $by) // Order by product name
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));

        return view('livewire.product')->with($data);
    }
    public function update_product($id){

        Products_model::where('id', $id)->update(request('update'));
        return redirect()->back();
    }

    public function add_product(){
        // dd(request('product'));
        $product = (object) request('product');
        // dd($product);
        $products = new Products_model();
        $products->brand = $product->brand;
        $products->category = $product->category;
        $products->model = $product->model;
        $products->description = $product->description;
        $products->save();

        return redirect()->back();
    }

    public function import_product(){

        $excelFilePath = storage_path('app\listing.xlsx');

        $data = Excel::toArray([], $excelFilePath)[0];
        $dh = $data[0];
        unset($data[0]);
        $arrayLower = array_map('strtolower', $dh);
        // print_r($arrayLower);
        $category = 0;
        $reference_id = 1;
        $stock = 2;
        $sku = 3;
        $status = 4;
        $model = 5;
        $grade = 6;
        $storage = 7;
        $color = 8;
        $brand = 9;

        $storages = Storage_model::pluck('name','id')->toArray();
        $colors = Color_model::pluck('name','id')->toArray();

        $products = Products_model::pluck('model','id')->toArray();

        foreach($data as $dr => $d){

            if(in_array(strtolower(trim($d[$model])), array_map('strtolower',$products))){

                $product_id = array_search(strtolower(trim($d[$model])), array_map('strtolower',$products));
            }else{
                $product = new Products_model();
                $product->category = trim($d[$category]);
                $product->brand = trim($d[$brand]);
                $product->model = trim($d[$model]);
                $product->save();

                $product_id = $product->id;

                $products = Products_model::pluck('model','id')->toArray();
            }
            if(in_array(strtolower(trim($d[$storage])), array_map('strtolower',$storages))){

                $storage_id = array_search(strtolower(trim($d[$storage])), array_map('strtolower',$storages));
            }else{
                if(trim($d[$storage]) == ''){
                    $storage_id = null;
                }
            }
            if(in_array(strtolower(trim($d[$color])), array_map('strtolower',$colors))){

                $color_id = array_search(strtolower(trim($d[$color])), array_map('strtolower',$colors));
            }else{
                if(trim($d[$color]) == ''){
                    $color_id = null;
                }else{
                    $color_new = new Color_model();
                    $color_new->name = trim($d[$color]);
                    $color_new->save();

                    $color_id = $color_new->id;

                    $colors = Color_model::pluck('name','id')->toArray();
                }
            }
            $variation = Variation_model::firstOrNew(['product_id' => $product_id, 'reference_id' => trim($d[$reference_id]), 'grade' => trim($d[$grade]), 'storage' => $storage_id, 'color' =>$color_id]);
            $variation->sku = $d[$sku];
            $variation->stock += trim($d[$stock]);
            $variation->status = trim($d[$status]);
            $variation->save();
        }

    }
    public function import_sku(){

        $excelFilePath = storage_path('app\listing.xlsx');

        $data = Excel::toArray([], $excelFilePath)[0];
        $dh = $data[0];
        unset($data[0]);
        $arrayLower = array_map('strtolower', $dh);
        // print_r($arrayLower);
        $category = 0;
        $reference_id = 1;
        $stock = 2;
        $sku = 3;
        $status = 4;
        $model = 5;
        $grade = 6;
        $storage = 7;
        $color = 8;
        $brand = 9;

        $storages = Storage_model::pluck('name','id')->toArray();
        $colors = Color_model::pluck('name','id')->toArray();

        $products = Products_model::pluck('model','id')->toArray();

        foreach($data as $dr => $d){

            $variation = Variation_model::where(['reference_id' => trim($d[$reference_id])])->update(['sku'=>trim($d[$sku])]);
            print_r($variation);
        }

    }
}
