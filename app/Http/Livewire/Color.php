<?php

namespace App\Http\Livewire;

use App\Models\Country_model;
use Livewire\Component;
use App\Models\Color_model;


class Color extends Component
{
    public function render()
    {


        $data['title_page'] = "Color";
        session()->put('page_title', $data['title_page']);
        $data['colors'] = Color_model::all();

        // foreach($data['colors'] as $color){
        //     if($color->orders->count() == 0){
        //         $color->delete();
        //         $color->forceDelete();
        //     }
        // }
        return view('livewire.color')->with($data);
    }
    public function add_color()
    {

        $data['countries'] = Country_model::all();
        return view('livewire.add-color')->with($data);
    }

    public function insert_color()
    {


        Color_model::insert(['name'=>request('name')]);
        session()->put('success',"color has been added successfully");
        return redirect('color');
    }

    public function edit_color($id)
    {

        $data['title_page'] = "Edit color";
        session()->put('page_title', $data['title_page']);

        $data['color'] = Color_model::where('id',$id)->first();

        // dd($orders);

        return view('livewire.edit-color')->with($data);
    }
    public function update_color($id)
    {

        Color_model::where('id',$id)->update(['name'=>request('name')]);
        session()->put('success',"color has been updated successfully");
        return redirect('color');
    }
}
