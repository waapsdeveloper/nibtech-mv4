<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Admin_model;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

class Profile extends Component
{

    public function mount(Request $request)
    {
        // dd(session()->all());
        $user_id = session('user_id');
        if($user_id == NULL){
            return redirect()->route('login');
        }
    }
    public function render()
    {
        $admin = Admin_model::where('id',session('user_id'))->first();
        $data = array(
            'admin' => $admin,
        );
        return view('livewire.profile')->with($data);
    }
}
