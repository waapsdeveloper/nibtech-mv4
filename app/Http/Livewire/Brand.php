<?php

namespace App\Http\Livewire;

use App\Models\Country_model;
use Livewire\Component;
use App\Models\Brand_model;


class Brand extends Component
{
    public function render()
    {


        $data['title_page'] = "Brand";
        session()->put('page_title', $data['title_page']);
        $data['brands'] = Brand_model::all();

        return view('livewire.brand')->with($data);
    }
    public function add_brand()
    {

        $data['countries'] = Country_model::all();
        return view('livewire.add-brand')->with($data);
    }

    public function insert_brand()
    {


        Brand_model::insert(['name'=>request('name')]);
        session()->put('success',"brand has been added successfully");
        return redirect('brand');
    }

    public function edit_brand($id)
    {

        $data['title_page'] = "Edit brand";
        session()->put('page_title', $data['title_page']);

        $data['brand'] = Brand_model::where('id',$id)->first();

        // dd($orders);

        return view('livewire.edit-brand')->with($data);
    }
    public function update_brand($id)
    {

        Brand_model::where('id',$id)->update(['name'=>request('name')]);
        session()->put('success',"brand has been updated successfully");
        return redirect('brand');
    }
}
