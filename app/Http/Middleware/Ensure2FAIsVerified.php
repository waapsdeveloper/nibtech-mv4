<?php

namespace App\Http\Middleware;

use App\Models\Admin_model;
use App\Models\Ip_address_model;
use Closure;
use Illuminate\Support\Facades\Auth;

class Ensure2FAIsVerified
{
    public function handle($request, Closure $next)
    {

        $admin = Admin_model::find(session('user_id'));

        if ($admin->is_2fa_enabled && !$request->session()->has('2fa_verified')) {
            $ip = $request->ip();
            $ip_address = Ip_address_model::where('ip',$ip)->where('status',1)->first();
            if($ip_address == null || $admin->two_factor_confirmed_at < now()->startOfDay()){
                return redirect()->route('admin.2fa');
            }
        }

        return $next($request);
    }
}
