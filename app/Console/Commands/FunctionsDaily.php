<?php

namespace App\Console\Commands;

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

class Functions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Functions:daily';

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
        $this->remove_extra_customers();
    }
    private function remove_extra_customers(){

        $data['customers'] = Customer_model::where('is_vendor',null)->get();

        foreach($data['customers'] as $customer){
            if($customer->orders->count() == 0){
                $customer->delete();
                $customer->forceDelete();
            }
        }

    }

    private function duplicate_orders(){




    }
}
