<?php

namespace App\Console\Commands;

use App\Jobs\UpdateOrderInDB;
use App\Http\Controllers\BackMarketAPIController;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Customer_model;
use App\Models\Currency_model;
use App\Models\Country_model;
use App\Models\Variation_model;
use App\Models\Stock_model;
use Carbon\Carbon;


use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class RefreshLatest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Refresh:latest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {
        $domains = DB::connection('master')->table('domains')->get();

        foreach ($domains as $domain) {
            // Dynamically update database connection
            Config::set('database.connections.mysql.host', $domain->db_host);
            Config::set('database.connections.mysql.port', $domain->db_port);
            Config::set('database.connections.mysql.database', $domain->db_name);
            Config::set('database.connections.mysql.username', $domain->db_username);
            Config::set('database.connections.mysql.password', $domain->db_password);
            // App Configuration
            Config::set('app.url', 'https://' . $domain->domain);
            Config::set('app.name', $domain->app_name);
            Config::set('app.logo', $domain->app_logo);
            Config::set('app.status', $domain->app_status);

            // SMTP Configuration
            Config::set('mail.mailer', 'smtp');
            Config::set('mail.host', $domain->smtp_host);
            Config::set('mail.port', $domain->smtp_port);
            Config::set('mail.username', $domain->smtp_username);
            Config::set('mail.password', $domain->smtp_password);
            Config::set('mail.encryption', $domain->smtp_encryption);

            // Backmarket API Configuration
            Config::set('backmarket.api_key_1', $domain->backmarket_api_key_1);
            Config::set('backmarket.api_key_2', $domain->backmarket_api_key_2);


            DB::purge('mysql'); // Clear cached database connection
            DB::reconnect('mysql'); // Reconnect to the updated database

            $this->info("Running cron for domain: {$domain->domain}");

            // Execute tenant-specific logic
            $this->runTenantSpecificJobs();
        }

        $this->info('Tenant cron completed for all domains.');
    }
    public function runTenantSpecificJobs()
    {

        $bm = new BackMarketAPIController();
        $order_item_model = new Order_item_model();


        $order_item_model->get_latest_care($bm);

    }

}
