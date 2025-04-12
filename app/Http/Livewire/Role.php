<?php

namespace App\Http\Livewire;

use App\Models\Country_model;
use Livewire\Component;
use App\Models\Role_model;


class Role extends Component
{
    public function render()
    {


        $data['title_page'] = "role";
        session()->put('page_title', $data['title_page']);
        $data['roles'] = Role_model::all();

        // foreach($data['roles'] as $role){
        //     if($role->orders->count() == 0){
        //         $role->delete();
        //         $role->forceDelete();
        //     }
        // }
        return view('livewire.role')->with($data);
    }
    public function add_role()
    {

        $data['countries'] = Country_model::all();
        return view('livewire.add-role')->with($data);
    }

    public function insert_role()
    {


        Role_model::insert(['name'=>request('name')]);
        session()->put('success',"role has been added successfully");
        return redirect('role');
    }

    public function edit_role($id)
    {

        $data['title_page'] = "Edit role";
        session()->put('page_title', $data['title_page']);

        $data['role'] = Role_model::where('id',$id)->first();

        // dd($orders);

        return view('livewire.edit-role')->with($data);
    }
    public function update_role($id)
    {

        Role_model::where('id',$id)->update(['name'=>request('name')]);
        session()->put('success',"role has been updated successfully");
        return redirect('role');
    }
}
