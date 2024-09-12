<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Route;
use App\Models\Admin_model;
use App\Models\Grade_model;
use App\Models\Ip_address_model;
use Illuminate\Foundation\Inspiring;

class CheckIPMiddleware
{
    public function handle($request, Closure $next, ...$permissions)
    {
        // Get the current route name
        $currentRoute = Route::currentRouteName();
        // Retrieve the user's ID from the session
        $userId = session('user_id');
        if($userId != null){

            $user = Admin_model::find($userId);
            $all_grades = Grade_model::all();
            session()->put('user',$user);
            session()->put('all_grades',$all_grades);
        }
        // If the current route is the login route or sign-in route, bypass the middleware
        if ($currentRoute == 'login' || $currentRoute == 'signin' || $currentRoute == 'error') {
            return $next($request);
        }

        // Redirect to the sign-in page if user ID is null
        if ($userId == null) {
            return redirect('signin');
        }

        if(!$user->hasPermission('add_ip')){
            $ip = $request->ip();
            $ip_address = Ip_address_model::where('ip',$ip)->where('status',1)->first();
            if($ip_address == null){
                abort(407, 'Quote of the day: '.Inspiring::just_quote());
            }
        }
        // If the user has the required permission, proceed to the next middleware
        return $next($request);
    }
}
