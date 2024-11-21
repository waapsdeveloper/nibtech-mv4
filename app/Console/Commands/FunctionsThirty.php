<?php

namespace App\Console\Commands;

use App\Http\Controllers\BackMarketAPIController;
use App\Models\Currency_model;
use App\Models\Listing_model;
use App\Models\Variation_model;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class FunctionsThirty extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'functions:thirty';

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

        ini_set('max_execution_time', 1200);
        $this->get_listings();
        $this->get_listingsBi();
    }
    public function get_listings(){
        $bm = new BackMarketAPIController();

        // print_r($bm->getAllListingsBi(['min_quantity'=>0]));
        $listings = $bm->getAllListings();

        foreach($listings as $country => $lists){
            foreach($lists as $list){

                $variation = Variation_model::where(['reference_id'=>trim($list->listing_id), 'sku' => trim($list->sku)])->first();
                if($variation == null){
                    // $list = $bm->getOneListing($list->listing_id);
                    $variation = Variation_model::firstOrNew(['reference_id' => trim($list->listing_id), 'sku' => trim($list->sku)]);
                    $variation->name = $list->title;
                    $variation->reference_uuid = $list->id;
                    $variation->grade = (int)$list->state + 1;
                    $variation->state = $list->publication_state;
                    $variation->status = 1;
                    // ... other fields
                    $variation->save();
                    echo $list->listing_id." ";
                }
                $currency = Currency_model::where('code',$list->currency)->first();
                // echo $list->currency;
                if($variation == null){
                    echo $list->sku." ";
                }else{
                    $listing = Listing_model::firstOrNew(['country' => $country, 'variation_id' => $variation->id]);
                    $listing->max_price = $list->max_price;
                    $listing->min_price = $list->min_price;
                    $variation->listed_stock = $list->quantity;
                    $listing->price = $list->price;
                    $listing->currency_id = $currency->id;
                    if($listing->name == null){
                        $listing->name = $list->title;
                    }
                    // ... other fields
                    $listing->save();
                    if($variation->reference_uuid == null){
                        $variation->reference_uuid = $list->id;
                    }
                    $variation->save();
                }
            }
        }
        // $list = $bm->getOneListing($itemObj->listing_id);
    }
    public function get_listingsBi(){
        $bm = new BackMarketAPIController();

        // print_r($bm->getAllListingsBi(['min_quantity'=>0]));
        $listings = $bm->getAllListingsBi();

        foreach($listings as $country => $lists){
            foreach($lists as $list){
                $variation = Variation_model::where('sku',$list->sku)->first();
                $currency = Currency_model::where('code',$list->currency)->first();
                if($variation == null){
                    echo $list->sku." ";
                }else{
                    $listing = Listing_model::firstOrNew(['country' => $country, 'variation_id' => $variation->id]);
                    $variation->listed_stock = $list->quantity;
                    $listing->price = $list->price;
                    $listing->buybox = $list->same_merchant_winner;
                    $listing->buybox_price = $list->price_for_buybox;
                    $listing->currency_id = $currency->id;
                    // ... other fields
                    $listing->save();
                    $variation->save();
                }
            }
        }
        // $list = $bm->getOneListing($itemObj->listing_id);
    }
}
