<?php

namespace App\Http\Livewire;

use App\Models\Admin_model;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Request;
use Livewire\Component;

class Signin extends Component
{
    public function render()
    {
        $data = [];
        if(session('error')){
            $data['error'] = session('error');
            session()->forget('error');
        }
        return view('livewire.signin')->with($data)
        ->layout('layouts.custom-app');
    }
    public function login(Request $request)
    {
        $login_detail = Admin_model::where('username',trim($request['username']))->first();
        if($login_detail == null){

            $error = "Incorrect Username ";
            $request->session()->put('error',$error);
            // echo "incorrect   ".Hash::make($request['password']);
            return redirect()->back();
        }
        // print_r($request['username']);
        if(Hash::check($request['password'],$login_detail->password)){
            $request->session()->put('user_id', $login_detail->id);
            $request->session()->put('fname', $login_detail->first_name);
            $request->session()->put('lname', $login_detail->last_name);
            $request->session()->put('our_id', 001);
            return redirect('/');
        }else{
            $error = "Incorrect Username or Password ";
            $request->session()->put('error',$error);
            // echo "incorrect   ".Hash::make($request['password']);
            return redirect()->back();
        }
    }
}
