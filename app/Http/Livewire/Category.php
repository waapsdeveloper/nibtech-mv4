<?php

namespace App\Http\Livewire;

use App\Models\Country_model;
use Livewire\Component;
use App\Models\Category_model;


class Category extends Component
{
    public function render()
    {


        $data['title_page'] = "Category";
        session()->put('page_title', $data['title_page']);
        $data['categories'] = Category_model::all();

        return view('livewire.category')->with($data);
    }
    public function add_category()
    {

        $data['countries'] = Country_model::all();
        return view('livewire.add-category')->with($data);
    }

    public function insert_category()
    {


        Category_model::insert(['name'=>request('name')]);
        session()->put('success',"category has been added successfully");
        return redirect('category');
    }

    public function edit_category($id)
    {

        $data['title_page'] = "Edit category";
        session()->put('page_title', $data['title_page']);

        $data['category'] = Category_model::where('id',$id)->first();

        // dd($orders);

        return view('livewire.edit-category')->with($data);
    }
    public function update_category($id)
    {

        Category_model::where('id',$id)->update(['name'=>request('name')]);
        session()->put('success',"Category has been updated successfully");
        return redirect('category');
    }
}
