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
        $host = request()->getHost(); // Get the current domain
        $envFile = match ($host) {
            'sdpos.nibritaintech.com' => '.env.sdpos',
            'egpos.nibritaintech.com' => '.env.egpos',
            default => '.env',
        };

        $filePath = base_path($envFile);

        if (file_exists($filePath)) {
            $dotenv = \Dotenv\Dotenv::createImmutable(base_path(), $envFile);
            $dotenv->load();
        }

        if ($envFile == '.env.egpos') {
            // Refresh Laravel configuration
            // App::make('config')->set('app.name', env('APP_NAME'));
            // config()->flush();
            // Refresh Laravel configuration
            $dotenv = \Dotenv\Dotenv::createImmutable(base_path(), $envFile);
            $dotenv->load();

            foreach ($_ENV as $key => $value) {
                App::make('config')->set($key, $value);
            }

            dd(env('APP_NAME'), config('app.name'));



        }
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
