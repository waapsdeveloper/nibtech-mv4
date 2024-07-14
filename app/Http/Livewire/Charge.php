<?php

namespace App\Http\Livewire;

use App\Http\Controllers\GoogleController;
use App\Models\Charge_frequency_model;
use App\Models\Charge_model;
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

        $data['countries'] = Country_model::all();
        return view('livewire.add-charge')->with($data);
    }

    public function insert_charge()
    {


        Charge_model::insert(['name'=>request('name')]);
        session()->put('success',"Charge has been added successfully");
        return redirect('charge');
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
