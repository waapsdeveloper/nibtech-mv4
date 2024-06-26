<?php

namespace App\Console\Commands;

use App\Http\Controllers\BackMarketAPIController;
use App\Models\Api_request_model;
use App\Models\Color_model;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Customer_model;
use App\Models\Currency_model;
use App\Models\Country_model;
use App\Models\Grade_model;
use App\Models\Listing_model;
use App\Models\Products_model;
use App\Models\Variation_model;
use App\Models\Stock_model;
use App\Models\Stock_operations_model;
use App\Models\Storage_model;
use Carbon\Carbon;


use Illuminate\Console\Command;
use GuzzleHttp\Client;

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
                $variation = Variation_model::where('reference_id',$list->listing_id)->first();
                $currency = Currency_model::where('code',$list->currency)->first();
                if($variation == null){
                    echo $list->sku." ";
                }else{
                    $listing = Listing_model::firstOrNew(['country' => $country, 'variation_id' => $variation->id]);
                    $listing->max_price = $list->max_price;
                    $listing->min_price = $list->min_price;
                    $listing->quantity = $list->quantity;
                    $listing->price = $list->price;
                    $listing->currency_id = $currency->id;
                    // ... other fields
                    $listing->save();
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
                    $listing->quantity = $list->quantity;
                    $listing->price = $list->price;
                    $listing->buybox = $list->same_merchant_winner;
                    $listing->buybox_price = $list->price_for_buybox;
                    $listing->currency_id = $currency->id;
                    // ... other fields
                    $listing->save();
                }
            }
        }
        // $list = $bm->getOneListing($itemObj->listing_id);
    }
}
