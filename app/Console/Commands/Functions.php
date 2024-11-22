<?php

namespace App\Console\Commands;

use App\Http\Controllers\BackMarketAPIController;
use App\Models\Api_request_model;
use App\Models\Color_model;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Product_storage_sort_model;
use App\Models\Stock_model;
use App\Models\Variation_model;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class Functions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'functions:ten';

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
        echo 1;
        $this->refund_currency();
        echo 2;
        $this->check_linked_orders();
        echo 3;
        $this->duplicate_orders();
        echo 4;
        $this->push_testing_api();
        echo 5;
        $this->misc();
    }
    private function refund_currency(){

        $items = Order_item_model::whereHas('order', function ($q) {
            $q->where('order_type_id',4);
        })
        ->where('currency',null)
        ->get();
        foreach($items as $item){
            $sale_order = Order_model::where('reference_id', $item->reference_id)->first();
            if($sale_order->order_type_id == 3){
                $item->currency = $sale_order->currency;
                $item->save();
            }
        }


    }
    private function check_linked_orders(){

        $items = Order_item_model::where(['linked_id'=>null])->whereNotNUll('stock_id')->whereHas('order', function ($q) {
            $q->whereIn('order_type_id',[3,5]);
        })->whereHas('stock', function ($q) {
            $q->whereNotNull('status');
        })->get();
        echo $items->count();
        foreach($items as $item){
            $it = Order_item_model::where(['stock_id'=>$item->stock_id])->whereHas('order', function ($q) {
                $q->whereIn('order_type_id',[1,4,6]);
            })->orderByDesc('id')->first();
            if($it != null){
                Order_item_model::where('id',$item->id)->update(['linked_id'=>$it->id]);
            }
        }

    }

    private function duplicate_orders(){

        // Subquery to get the IDs of duplicate orders based on reference_id
        $subquery = Order_model::select('id')->where('reference_id','!=',null)->where('order_type_id',3)
        ->selectRaw('ROW_NUMBER() OVER (PARTITION BY reference_id ORDER BY id) AS row_num');

        // Final query to delete duplicate orders
        Order_model::whereIn('id', function ($query) use ($subquery) {
            $query->select('id')->fromSub($subquery, 'subquery')->where('row_num', '>', 1);
        })->delete();

        // Subquery to get the IDs of duplicate orders based on reference_id
        $subquery = Order_item_model::select('id')->where('reference_id','!=',null)->whereHas('order', function ($query) {
            $query->where('order_type_id', 3);
        })
        ->selectRaw('ROW_NUMBER() OVER (PARTITION BY reference_id ORDER BY id) AS row_num');

        // Final query to delete duplicate orders
        Order_item_model::whereIn('id', function ($query) use ($subquery) {
            $query->select('id')->fromSub($subquery, 'subquery')->where('row_num', '>', 1);
        })->delete();

    }

    public function push_testing_api(){
        $testing = new Api_request_model();
        $testing->push_testing();
    }
    private function misc(){

        Variation_model::where('product_storage_sort_id',null)->each(function($variation){
            $pss = Product_storage_sort_model::firstOrNew(['product_id'=>$variation->product_id,'storage'=>$variation->storage]);
            if($pss->id == null){
                $pss->save();
            }
            $variation->product_storage_sort_id = $pss->id;
            $variation->save();
        });
        $order_c = new Order();
        Order_model::where('scanned',null)->where('order_type_id',3)->where('tracking_number', '!=', null)->whereBetween('created_at', ['2024-05-01 00:00:00', now()->subDays(1)->format('Y-m-d H:i:s')])
        ->orderByDesc('id')->each(function($order) use ($order_c){
            $order_c->getLabel($order->reference_id, false, true);
        });
    }

}
