<?php

namespace App\Http\Livewire\V2;

use Livewire\Component;
use App\Models\Marketplace_model;

class Marketplace extends Component
{
    public function mount()
    {
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect()->route('login');
        }
    }

    public function render()
    {
        $data['title_page'] = "Marketplaces";
        session()->put('page_title', $data['title_page']);
        $data['marketplaces'] = Marketplace_model::orderBy('name', 'ASC')->get();

        return view('livewire.v2.marketplace')->with($data);
    }

    public function add_marketplace()
    {
        $data['title_page'] = "Add Marketplace";
        session()->put('page_title', $data['title_page']);
        return view('livewire.v2.add-marketplace')->with($data);
    }

    public function insert_marketplace()
    {
        request()->validate([
            'name' => 'required|string|max:255',
        ]);

        Marketplace_model::create([
            'name' => request('name'),
            'description' => request('description'),
            'status' => request('status', 1),
            'api_key' => request('api_key'),
            'api_secret' => request('api_secret'),
            'api_url' => request('api_url'),
        ]);

        session()->put('success', "Marketplace has been added successfully");
        return redirect('v2/marketplace');
    }

    public function edit_marketplace($id)
    {
        $data['title_page'] = "Edit Marketplace";
        session()->put('page_title', $data['title_page']);
        $data['marketplace'] = Marketplace_model::where('id', $id)->first();

        if (!$data['marketplace']) {
            session()->put('error', "Marketplace not found");
            return redirect('v2/marketplace');
        }

        return view('livewire.v2.edit-marketplace')->with($data);
    }

    public function update_marketplace($id)
    {
        request()->validate([
            'name' => 'required|string|max:255',
        ]);

        $marketplace = Marketplace_model::find($id);

        if (!$marketplace) {
            session()->put('error', "Marketplace not found");
            return redirect('v2/marketplace');
        }

        $marketplace->update([
            'name' => request('name'),
            'description' => request('description'),
            'status' => request('status', 1),
            'api_key' => request('api_key'),
            'api_secret' => request('api_secret'),
            'api_url' => request('api_url'),
        ]);

        session()->put('success', "Marketplace has been updated successfully");
        return redirect('v2/marketplace');
    }

    public function delete_marketplace($id)
    {
        $marketplace = Marketplace_model::find($id);

        if (!$marketplace) {
            session()->put('error', "Marketplace not found");
            return redirect('v2/marketplace');
        }

        $marketplace->delete();
        session()->put('success', "Marketplace has been deleted successfully");
        return redirect('v2/marketplace');
    }
}

