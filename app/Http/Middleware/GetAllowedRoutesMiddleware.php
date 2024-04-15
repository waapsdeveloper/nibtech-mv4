<?php

namespace App\Http\Middleware;

use App\Models\Allowed_routes_model;
use App\Models\Routes_model;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GetAllowedRoutesMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {

        $route = Routes_model::where('url',$request->path())->first();
        if(Auth::check() || ($route != NULL && $route->route_name == 'login')){

            if($route != NULL && $route->route_name != 'login' && Allowed_routes_model::where(['parties_id' => 1, 'keeper_id' => Auth::user()->id,'route_id'=>$route->id])->first() == NULL){
                return redirect()->back();
            }

        }else{
            return redirect()->away('https://www.google.com');
        }

        return $next($request);
    }
}
