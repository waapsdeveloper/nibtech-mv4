<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class TenantCron extends Command
{
    protected $signature = 'tenant:cron';
    protected $description = 'Run cron jobs for all tenants';

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

            DB::purge('mysql'); // Clear cached database connection
            DB::reconnect('mysql'); // Reconnect to the updated database

            $this->info("Running cron for domain: {$domain->domain}");

            // Execute tenant-specific logic
            $this->runTenantSpecificJobs($domain);
        }

        $this->info('Tenant cron completed for all domains.');
    }

    protected function runTenantSpecificJobs($domain)
    {
        // Example: Run scheduled tasks or business logic
        Log::info("Cron job executed for {$domain->domain}");

        Artisan::call('schedule:run'); // Run Laravel's scheduler
        // Add your domain-specific jobs here
    }
}
