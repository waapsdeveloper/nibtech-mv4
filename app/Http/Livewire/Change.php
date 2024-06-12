<?php

namespace App\Http\Livewire;

use App\Http\Controllers\GoogleController;
use Livewire\Component;
use App\Models\Admin_model;
use Illuminate\Http\Request;
use App\Mail\ResetMail;
use DB;
use Mail;
use Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Session\Session;
class Change extends Component
{
    public function mount(Request $request)
    {

    }
    public function render(Request $request)
    {

        // return view('livewire.wish-list');
    }

    public function change_password(Request $request)
    {

        if($request->type == "password"){
            $admin = Admin_model::where('id',session('user_id'))->first();
            if($admin->email == $request->email){
                $code = random_int(1000,10000);
                $request->session()->put('code',$code);
                session()->save();
                $body = "OTP for changr your password.". session('code');
                $mailData = [
                    'title' => 'Mail from Britain Tech Ltd',
                    'body' => $body
                ];
                // Mail::to($request->email)->send(new ResetMail($mailData));
                $recipientEmail = $request->email;
                $subject = 'Reset Your Password';

                app(GoogleController::class)->sendEmail($recipientEmail, $subject, new ResetMail($mailData));
                return redirect('OTP/password');
            }else{
                session()->forget('error');
                $request->session()->put('error', "Invalid Email!");
                return redirect('profile');
            }
        }
    }

    public function otp(Request $request,$param)
    {
        // dd(session('code'));
        $title = $param;
        return view('livewire.otp',compact('title'));
    }
    public function reset_page(Request $request)
    {
        $code = $request->txt1.$request->txt2.$request->txt3.$request->txt4;
        $type = $request->type;
        if(session('code') == $code){
            return redirect('page');
        }else{
            session()->put('error','Invalid OTP');
            return redirect()->back();
        }

    }
    public function page()
    {
        return view('livewire.reset_page');
    }

    public function reset_pass(Request $request)
    {
        $new = $request->new_pass;
        $confirm = $request->confirm_pass;
        $type = $request->type;
        if ($new == $confirm) {
            // Hash the new password before storing it
            $hashedPassword = Hash::make($confirm);

            // Use parameterized query to prevent SQL injection
            $update = DB::table('admin')
                ->where('id', session('user_id'))
                ->update([$type => $hashedPassword]);

            if ($update) {
                // If the update was successful, flush the session and redirect to the login page
                session()->flush();
                return redirect()->route('login');
            } else {
                // Handle update failure
                session()->put('error', 'Password update failed.');
                return redirect()->back();
            }
        } else {
            // Passwords didn't match
            session()->put('error', "Passwords didn't match!");
            return redirect()->back();
        }
    }
}
