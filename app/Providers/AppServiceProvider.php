<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;

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

        // DB::disconnect() removed from global Queue::after / Queue::failing (see docs/DB_CONNECTION_ANALYSIS_LAST_WEEK.md).
        // It caused connect/disconnect churn: every job reconnects after disconnect. Long-running jobs
        // (ExecuteArtisanCommandJob, SyncMarketplaceStockJob) still disconnect in their own finally blocks.

        if ($this->app->environment('testing')) {
            return;
        }

        // Exclude live_migrations folder from automatic migration runs
        $this->app->bind('migrator', function ($app) {
            $repository = $app['migration.repository'];
            $files = $app['files'];

            return new class($repository, $app['db'], $files) extends Migrator {
                public function getMigrationFiles($paths)
                {
                    // Get all migration files using parent logic
                    $allFiles = parent::getMigrationFiles($paths);

                    // Filter out files in live_migrations folder
                    $filteredFiles = [];
                    foreach ($allFiles as $key => $file) {
                        if (strpos($file, 'live_migrations') === false) {
                            $filteredFiles[$key] = $file;
                        }
                    }

                    return $filteredFiles;
                }
            };
        });

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
