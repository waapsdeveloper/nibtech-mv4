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

            // Query the master database for the current domain's database credentials
            $domainConfig = DB::connection('master')->table('domains')->where('domain', $host)->first();

            if ($domainConfig) {
                Config::set('database.connections.mysql.host', $domainConfig->db_host);
                Config::set('database.connections.mysql.port', $domainConfig->db_port);
                Config::set('database.connections.mysql.database', $domainConfig->db_name);
                Config::set('database.connections.mysql.username', $domainConfig->db_username);
                Config::set('database.connections.mysql.password', $domainConfig->db_password);

                // Reconnect to the database with the updated configuration
                DB::purge('mysql');
                DB::reconnect('mysql');
            } else {
                abort(403, 'Unauthorized domain.');
            }
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
