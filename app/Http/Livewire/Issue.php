<?php

namespace App\Http\Livewire;

use App\Exports\InventorysheetExport;
use Livewire\Component;
use App\Models\Color_model;
use App\Models\Storage_model;
use App\Models\Grade_model;
use App\Models\Category_model;
use App\Models\Brand_model;
use App\Models\Customer_model;
use App\Models\Order_item_model;
use App\Models\Stock_model;
use App\Models\Products_model;
use App\Models\Variation_model;
use Maatwebsite\Excel\Facades\Excel;

class Issue extends Component
{

    public function render()
    {;
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }

        $data['vendors'] = Customer_model::where('is_vendor',1)->pluck('first_name','id');
        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $data['categories'] = Category_model::get();
        $data['brands'] = Brand_model::get();
        $items = Order_item_model::where('linked_id','!=',null)->where(['status'=>3])
        ->whereHas('order', function ($q) {
            $q->whereIn('order_type_id', [2,3,5]);
        })
        ->orderBy('id','DESC')->get();
        foreach($items as $item){
            if($item->linked && $item->variation_id != $item->linked->variation_id &&
            $item->variation->product_id != $item->linked->variation->product_id &&
            $item->variation->storage != $item->linked->variation->storage){
                // print_r($item);
                // print_r($item->stock);
                // print_r($item->linked);
                // echo " <br><br>";
                $data['items'][] = $item;
            }
        }
        // die;


        // dd($data['vendor_average_cost']);

        return view('livewire.issue')->with($data);
    }
    public function get_products(){


        $category = request('category');
        $brand = request('brand');

        $products = Products_model::where(['category' => $category, 'brand' => $brand])->orderBy('model','asc')->get();

        return response()->json($products);
    }
    public function get_variations($id){

        $variation = Variation_model::where('product_id',$id)->orderBy('storage','asc')->orderBy('color','asc')->orderBy('grade','asc')->get();

        return response()->json($variation);
    }


    public function update_product($id){

        Products_model::where('id', $id)->update(request('update'));
        return redirect()->back();
    }

    public function export(){

        return Excel::download(new InventorysheetExport, 'inventory.xlsx');
    }
}
