<?php

namespace App\Http\Livewire;

use App\Models\Admin_model;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Request;
use Livewire\Component;
use PragmaRX\Google2FA\Google2FA;

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
    
    public function authenticated(Request $request, $user)
    {
        if ($user->uses_two_factor_auth) {
            $google2fa = new Google2FA();
    
            if ($request->session()->has('2fa_passed')) {
                $request->session()->forget('2fa_passed');
            }
    
            $request->session()->put('2fa:user:id', $user->id);
            $request->session()->put('2fa:auth:attempt', true);
            $request->session()->put('2fa:auth:remember', $request->has('remember'));
    
            $otp_secret = $user->google2fa_secret;
            $one_time_password = $google2fa->getCurrentOtp($otp_secret);
    
            return redirect()->route('2fa')->with('one_time_password', $one_time_password);
        }
    
        return redirect()->intended($this->redirectPath());
    }
}
