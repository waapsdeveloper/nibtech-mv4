<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

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
        // Manually refresh Laravel's configuration if the environment file changes
        if ($envFile === '.env.egpos') {
            foreach ($_ENV as $key => $value) {
                Config::set($key, $value);
            }
            dd($_ENV);
            // Verify the new values
            // dd(env('APP_NAME'), config('app.name'));
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
