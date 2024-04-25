<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Color_model;
use App\Models\Storage_model;
use App\Models\Grade_model;
use App\Models\Category_model;
use App\Models\Brand_model;
use App\Models\Stock_model;
use App\Models\Products_model;
use App\Models\Variation_model;

class Inventory extends Component
{

    public function render()
    {;
        if(request('per_page') != null){
            $per_page = request('per_page');
        }else{
            $per_page = 10;
        }

        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::pluck('name','id');
        $data['categories'] = Category_model::get();
        $data['brands'] = Brand_model::get();
        $stocks = Stock_model::where('stock.status',1)

        ->when(request('storage') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('storage', request('storage'));
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
        ->when(request('grade') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('grade', request('grade'));
            });
        });


        // ->orderBy($sort, $by) // Order by product name
        $data['stocks'] = $stocks
        ->paginate($per_page)
        ->onEachSide(5)
        ->appends(request()->except('page'));


        $data['average_cost'] = $stocks->join('order_items', 'stock.id', '=', 'order_items.stock_id')
        ->selectRaw('AVG(order_items.price) as average_price')
        ->pluck('average_price')
        ->first();

        return view('livewire.inventory')->with($data);
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
}
