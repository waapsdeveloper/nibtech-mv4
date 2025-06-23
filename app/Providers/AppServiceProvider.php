<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrapFive();
        date_default_timezone_set("Europe/London");

        // Handle locale settings
        view()->composer('*', function ($view) {



            if (!Session::has('dropdown_data')) {
                Session::put('dropdown_data', [
                    'products' => \App\Models\Products_model::orderBy('model','asc')->pluck('model','id'),
                    'categories' => \App\Models\Category_model::pluck('name','id'),
                    'brands' => \App\Models\Brand_model::pluck('name','id'),
                    'colors' => \App\Models\Color_model::pluck('name','id'),
                    'storages' => \App\Models\Storage_model::pluck('name','id'),
                    'grades' => \App\Models\Grade_model::pluck('name','id'),
                    'admins' => \App\Models\Admin_model::pluck('first_name','id'),
                ]);
            }
        });


    }
}
