<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class TwoFactorAuthentication
{
    public function handle($request, Closure $next)
    {
        if (Auth::check() && !session('2fa') && !in_array($request->route()->getName(), ['2fa.form', '2fa.verify', '2fa.setup', 'logout'])) {
            return redirect()->route('2fa.form');
        }

        return $next($request);
    }
}
