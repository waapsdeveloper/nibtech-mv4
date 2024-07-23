<?php

// namespace App\Http\Controllers;

// use Illuminate\Http\Request;
// use PragmaRX\Google2FA\Google2FA;
// use Illuminate\Support\Facades\Auth;

// class TwoFactorController extends Controller
// {
//     public function show2faForm(Request $request)
//     {
//         $google2fa = new Google2FA();
//         $user = Auth::user();
//         $secret = $google2fa->generateSecretKey();
//         $inlineUrl = $google2fa->getQRCodeInline(
//             config('app.name'),
//             $user->email,
//             $secret
//         );

//         return view('auth.2fa', ['inlineUrl' => $inlineUrl, 'secret' => $secret]);
//     }

//     public function setup2fa(Request $request)
//     {
//         $request->validate([
//             'secret' => 'required|string',
//             'one_time_password' => 'required|string',
//         ]);

//         $google2fa = new Google2FA();

//         $user = Auth::user();
//         $valid = $google2fa->verifyKey($request->secret, $request->one_time_password);

//         if ($valid) {
//             $user->google2fa_secret = $request->secret;
//             $user->save();

//             return redirect('/home')->with('success', '2FA is enabled successfully.');
//         }

//         return redirect()->back()->with('error', 'Invalid OTP.');
//     }

//     public function verify2fa(Request $request)
//     {
//         $request->validate([
//             'one_time_password' => 'required|string',
//         ]);

//         $google2fa = new Google2FA();
//         $user = Auth::user();
//         $secret = $user->google2fa_secret;

//         $valid = $google2fa->verifyKey($secret, $request->one_time_password);

//         if ($valid) {
//             session(['2fa' => true]);
//             return redirect('/home');
//         }

//         return redirect()->back()->with('error', 'Invalid OTP.');
//     }
// }
