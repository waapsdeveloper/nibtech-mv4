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

        $data['title_page'] = "Profile";
        $admin = Admin_model::where('id',session('user_id'))->first();
        if(request('update_profile') && $admin->id != 1){
            $admin->first_name = request('first_name');
            $admin->last_name = request('last_name');
            $admin->email = request('email');
        }
        $data = array(
            'admin' => $admin,
        );
        return view('livewire.profile')->with($data);
    }
}
