<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\App;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        Paginator::useBootstrapFive();
        Paginator::useBootstrapFour();
        date_default_timezone_set("Europe/London");
        view()->composer('*', function ($view) {
            $locale = session()->get('locale', 'en');
            app()->setLocale($locale);
            $view->with('locale', $locale);
        });
    }
}
