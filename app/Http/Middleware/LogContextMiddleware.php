<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class LogContextMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        Log::withContext([
            'url' => $request->fullUrl(),
            'user_id' => optional(Auth::user())->id,
            'ip' => $request->ip(),
            'request_data' => $request->except(['password', 'password_confirmation']), // Exclude sensitive data
        ]);

        return $next($request);
    }
}
