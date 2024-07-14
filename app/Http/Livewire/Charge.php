<?php

namespace App\Http\Livewire;

use App\Http\Controllers\GoogleController;
use App\Models\Charge_frequency_model;
use App\Models\Charge_model;
use App\Models\Charge_value_model;
use App\Models\Country_model;
use Livewire\Component;
use App\Models\Multi_type_model;
use App\Models\Order_model;
use App\Models\Payment_method_model;
use App\Models\Role_model;


class Charge extends Component
{
    public function render()
    {


        $data['title_page'] = "Charge";
        $data['charge_frequencies'] = Charge_frequency_model::pluck('name','id');
        $data['order_types'] = Multi_type_model::where('table_name','orders')->pluck('name','id');
        $data['payment_methods'] = Payment_method_model::pluck('name','id');



        $data['charges'] = Charge_model::all();

        // foreach($data['charges'] as $charge){
        //     if($charge->orders->count() == 0){
        //         $charge->delete();
        //         $charge->forceDelete();
        //     }
        // }
        return view('livewire.charge')->with($data);
    }
    public function add_charge()
    {
        $data = request('charge');
        $charge = Charge_model::firstOrNew(['charge_frequency_id'=>$data['charge_frequency'],'order_type_id'=>$data['order_type'],'payment_method_id'=>$data['payment_method'],'name'=>$data['name'],'amount_type'=>$data['amount_type']]);
        $charge->description = $data['description'];
        $charge->status = 1;
        $charge->save();

        $charge_value = Charge_value_model::firstOrNew(['charge_id'=>$charge->id, 'ended_at'=>null]);
        if($charge_value->id != null){
            $charge_value->ended_at = Date('Y-m-d H:i:s', strtotime('-1 second', strtotime($data['start_date'])));
            $charge_value->save();

            $charge_value = Charge_value_model::firstOrNew(['charge_id'=>$charge->id, 'ended_at'=>null]);
            if($charge_value->id != null){
                $charge_value->ended_at = Date('Y-m-d H:i:s', strtotime('-1 second', strtotime($data['start_date'])));
                $charge_value->save();

                $charge_value = Charge_value_model::firstOrNew(['charge_id'=>$charge->id, 'ended_at'=>null]);
            }
        }
        $charge_value->value = $data['value'];
        $charge_value->started_at = $data['start_date'];
        $charge_value->save();

        session()->put('success',"Charge has been added successfully");
        return redirect()->back();
    }

    public function edit_charge($id)
    {

        $data['title_page'] = "Edit Charge";

        $data['charge'] = Charge_model::where('id',$id)->first();

        // dd($orders);

        return view('livewire.edit-charge')->with($data);
    }
    public function update_charge($id)
    {

        Charge_model::where('id',$id)->update(['name'=>request('name')]);
        session()->put('success',"Charge has been updated successfully");
        return redirect('charge');
    }
}
