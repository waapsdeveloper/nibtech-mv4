<?php

namespace App\Http\Livewire;

use App\Models\Admin_model;
use App\Models\Color_model;
use Livewire\Component;
use App\Models\Grade_model;
use App\Models\Order_item_model;
use App\Models\Order_model;
use App\Models\Products_model;
use App\Models\Return_model;
use App\Models\Storage_model;
use Barryvdh\DomPDF\Facade\Pdf as FacadePdf;

class FortnightReturn extends Component
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

        $data['title_page'] = "Fortnight Return";

        $data['admins'] = Admin_model::where('id','!=',1)->get();
        $data['products'] = Products_model::orderBy('model','asc')->get();
        $data['colors'] = Color_model::pluck('name','id');
        $data['storages'] = Storage_model::pluck('name','id');
        $data['grades'] = Grade_model::all();

        $latest_items = Order_item_model::whereHas('variation', function ($q) {
            $q->where('grade',10);
        })
        ->whereHas('order', function ($q) {
            $q->where('order_type_id',4);
        })->whereHas('refund_order')->orderBy('created_at','desc')
        ->get();

        foreach($latest_items as $item){
            $return = Return_model::firstOrNew([
                'order_id' => $item->refund_order->id,
                'stock_id' => $item->stock_id,
                'processed_by' => $item->refund_order->processed_by,
                'tested_by' => $item->stock->tester
            ])->save();
        }

        $data['latest_items'] = $latest_items;

        if(request('print') == 1){


        }
        return view('livewire.fortnight_return', $data); // Return the Blade view instance with data
    }

    public function print(){
        $latest_items = Order_item_model::whereHas('variation', function ($q) {
            $q->where('grade',10);
        })
        ->whereHas('order', function ($q) {
            $q->where('order_type_id',4);
        })->whereHas('refund_order')->orderBy('created_at','desc')
        ->get();

        $data['latest_items'] = $latest_items;

        $pdf = FacadePdf::loadView('export.fortnight_return', compact('latest_items'));
        return $pdf->download('fortnight_returns.pdf');

    }



}
