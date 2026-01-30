<?php

namespace App\Console\Commands;

use App\Http\Controllers\BackMarketAPIController;
use App\Models\Order_item_model;
use Illuminate\Console\Command;

class RefreshLatest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh:latest';

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
        $bm = new BackMarketAPIController();
        $order_item_model = new Order_item_model();


        $order_item_model->get_latest_care($bm);

    }

}
