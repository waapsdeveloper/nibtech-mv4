<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Fetch current domain
        $host = request()->getHost();

        if($host == 'egpos.nibritaintech.com'){
            dd(env('DB_HOST'));
        }
            // Query the master database for the current domain's database credentials
            $domainConfig = DB::connection('master')->table('domains')->where('domain', $host)->first();

            if ($domainConfig) {
                Config::set('database.connections.mysql.host', $domainConfig->db_host);
                Config::set('database.connections.mysql.port', $domainConfig->db_port);
                Config::set('database.connections.mysql.database', $domainConfig->db_name);
                Config::set('database.connections.mysql.username', $domainConfig->db_username);
                Config::set('database.connections.mysql.password', $domainConfig->db_password);
                // App Configuration
                Config::set('app.url', 'https://' . $host);
                Config::set('app.name', $domainConfig->app_name);
                Config::set('app.logo', $domainConfig->app_logo);
                Config::set('app.icon', $domainConfig->app_icon);
                Config::set('app.status', $domainConfig->app_status);
                session()->put('app_logo', $domainConfig->app_logo);
                session()->put('app_icon', $domainConfig->app_icon);
                // SMTP Configuration
                Config::set('mail.mailer', 'smtp');
                Config::set('mail.host', $domainConfig->smtp_host);
                Config::set('mail.port', $domainConfig->smtp_port);
                Config::set('mail.username', $domainConfig->smtp_username);
                Config::set('mail.password', $domainConfig->smtp_password);
                Config::set('mail.encryption', $domainConfig->smtp_encryption);

                // Backmarket API Configuration
                Config::set('backmarket.api_key_1', $domainConfig->backmarket_api_key_1);
                Config::set('backmarket.api_key_2', $domainConfig->backmarket_api_key_2);

                // Reconnect to the database with the updated configuration
                DB::purge('mysql');
                DB::reconnect('mysql');
            } else {
                dd(403, 'Unauthorized domain.');
            }
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
