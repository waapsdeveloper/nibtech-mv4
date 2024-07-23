<?php

// TwoFactorController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PragmaRX\Google2FALaravel\Support\Google2FA;
use Auth;
use Validator;
use Illuminate\Validation\ValidationException;


class TwoFactorController extends Controller
{
    
public function show(Request $request)
{
    return view('auth.2fa');
}

public function verify(Request $request)
{
    $request->validate([
        'one_time_password' => 'required|string',
    ]);

    $user_id = $request->session()->get('2fa:user:id');
    $remember = $request->session()->get('2fa:auth:remember', false);
    $attempt = $request->session()->get('2fa:auth:attempt', false);

    if (!$user_id || !$attempt) {
        return redirect()->route('login');
    }

    $user = User::find($user_id);

    if (!$user || !$user->uses_two_factor_auth) {
        return redirect()->route('login');
    }

    $google2fa = new Google2FA();
    $otp_secret = $user->google2fa_secret;

    if (!$google2fa->verifyKey($otp_secret, $request->one_time_password)) {
        throw ValidationException::withMessages([
          'one_time_password' => [__('The one time password is invalid.')],
        ]);
    }

    $guard = config('auth.defaults.guard');
    $credentials = [$user->getAuthIdentifierName() => $user->getAuthIdentifier(), 'password' => $user->getAuthPassword()];
    
    if ($remember) {
        $guard = config('auth.defaults.remember_me_guard', $guard);
    }
    
    if ($attempt) {
        $guard = config('auth.defaults.attempt_guard', $guard);
    }
    
    if (Auth::guard($guard)->attempt($credentials, $remember)) {
        $request->session()->remove('2fa:user:id');
        $request->session()->remove('2fa:auth:remember');
        $request->session()->remove('2fa:auth:attempt');
    
        return redirect()->intended('/');
    }
    
    return redirect()->route('login')->withErrors([
        'password' => __('The provided credentials are incorrect.'),
    ]);
}
    public function show2faForm(Request $request)
    {
        $user = Auth::user();
        $google2fa_url = "";

        if ($user->google2fa_secret) {
            $google2fa = app('pragmarx.google2fa');
            $google2fa_url = $google2fa->getQRCodeInline(
                config('app.name'),
                $user->email,
                $user->google2fa_secret
            );
        }

        return view('auth.2fa', ['user' => $user, 'google2fa_url' => $google2fa_url]);
    }

    public function setup2fa(Request $request)
    {
        $user = Auth::user();
        $google2fa = app('pragmarx.google2fa');
        $user->google2fa_secret = $google2fa->generateSecretKey();
        $user->save();

        return redirect()->route('2fa.form');
    }

    public function verify2fa(Request $request)
    {
        $user = Auth::user();
        $google2fa = app('pragmarx.google2fa');
        $valid = $google2fa->verifyKey($user->google2fa_secret, $request->one_time_password);

        if ($valid) {
            $user->google2fa_enabled = true;
            $user->save();

            return redirect()->route('home');
        } else {
            return redirect()->route('2fa.form')->withErrors(['Invalid OTP']);
        }
    }
}
