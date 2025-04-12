<?php

namespace App\Http\Livewire;

use App\Models\Country_model;
use Livewire\Component;
use App\Models\Storage_model;


class Storage extends Component
{
    public function render()
    {


        $data['title_page'] = "Storage";
        session()->put('page_title', $data['title_page']);
        $data['storages'] = Storage_model::all();

        // foreach($data['storages'] as $storage){
        //     if($storage->orders->count() == 0){
        //         $storage->delete();
        //         $storage->forceDelete();
        //     }
        // }
        return view('livewire.storage')->with($data);
    }
    public function add_storage()
    {

        $data['countries'] = Country_model::all();
        return view('livewire.add-storage')->with($data);
    }

    public function insert_storage()
    {


        Storage_model::insert(['name'=>request('name')]);
        session()->put('success',"storage has been added successfully");
        return redirect('storage');
    }

    public function edit_storage($id)
    {

        $data['title_page'] = "Edit storage";
        session()->put('page_title', $data['title_page']);

        $data['storage'] = Storage_model::where('id',$id)->first();

        // dd($orders);

        return view('livewire.edit-storage')->with($data);
    }
    public function update_storage($id)
    {

        Storage_model::where('id',$id)->update(['name'=>request('name')]);
        session()->put('success',"storage has been updated successfully");
        return redirect('storage');
    }
}
