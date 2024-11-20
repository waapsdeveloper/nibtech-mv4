<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
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
        // Load default `.env` first
        $host = request()->getHost(); // Get the current domain

        $envFile = match ($host) {
            'sdpos.nibritaintech.com' => '.env.sdpos',
            'egpos.nibritaintech.com' => '.env.egpos',
            default => '.env',
        };

        // Load the domain-specific `.env` file if it exists
        $filePath = base_path($envFile);
        if (file_exists($filePath)) {
            $dotenv = \Dotenv\Dotenv::createImmutable(base_path(), $envFile);
            $dotenv->load();

            // Update Laravel's config values based on the newly loaded `.env`
            foreach ($_ENV as $key => $value) {
                Config::set($key, $value);
            }

            // Update Laravel's database configuration dynamically
            Config::set('database.connections.mysql.host', env('DB_HOST'));
            Config::set('database.connections.mysql.port', env('DB_PORT'));
            Config::set('database.connections.mysql.database', env('DB_DATABASE'));
            Config::set('database.connections.mysql.username', env('DB_USERNAME'));
            Config::set('database.connections.mysql.password', env('DB_PASSWORD'));
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrapFive();
        Paginator::useBootstrapFour();
        date_default_timezone_set("Europe/London");

        // Handle locale settings
        view()->composer('*', function ($view) {
            $locale = session()->get('locale', 'en');
            app()->setLocale($locale);
            $view->with('locale', $locale);
        });
    }
}
