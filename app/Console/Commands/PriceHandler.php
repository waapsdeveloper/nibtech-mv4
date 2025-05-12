<?php

namespace App\Console\Commands;

use App\Http\Controllers\BackMarketAPIController;
use App\Http\Controllers\ListingController;
use App\Http\Livewire\Order;
use App\Models\Api_request_model;
use App\Models\Color_model;
use App\Models\Country_model;
use App\Models\Listing_model;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Product_storage_sort_model;
use App\Models\Stock_model;
use App\Models\Variation_model;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PriceHandler extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'price:handler';

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

        $this->recheck_inactive_handlers();

        $error = '';
        $bm = new BackMarketAPIController();
        $listingController = new ListingController();
        $listings = Listing_model::whereIn('handler_status', [1,3])
        ->where('buybox',  '!=', 1)
        ->where('min_price_limit', '>', 0)
        ->whereColumn('min_price_limit', '<=', 'buybox_price')
        ->whereColumn('min_price_limit', '<=', 'min_price')
        ->get();
        $variation_ids = $listings->pluck('variation_id');
        $variations = Variation_model::whereIn('id', $variation_ids)->get();

            echo "Hello";

        // print_r($listings);
        foreach ($variations as $variation) {

            // $responses = $bm->getListingCompetitors($variation->reference_uuid);
            $responses = $listingController->getCompetitors($variation->id, 1);
            if ($responses == null) {
                $error .= "No response for variation: " . $variation->sku . "\n";
                continue;
            }
            foreach($responses as $list){
                print_r($list);
                if(is_string($list) || is_int($list)){
                    $error .= $list;
                    continue;
                }
                if(is_array($list)){
                    $error .= json_encode($list);
                    $error .= "\n";
                    $error .= "Error in response for variation: " . $variation->sku . "\n";
                    // echo $error;
                    continue;
                }
                $country = Country_model::where('code',$list->market)->first();
                $listing = Listing_model::firstOrNew(['variation_id'=>$variation->id, 'country'=>$country->id]);
                $listing->reference_uuid = $list->product_id;
                if($list->price != null){
                    $listing->price = $list->price->amount;
                }
                if($list->min_price != null){
                    $listing->min_price = $list->min_price->amount;
                }
                $listing->buybox = $list->is_winning;
                $listing->buybox_price = $list->price_to_win->amount;
                $listing->buybox_winner_price = $list->winner_price->amount;
                $listing->save();

                print_r($listing);
                if($listing->handler_status == 1 && $listing->buybox !== 1 && $listing->buybox_price >= $listing->min_price_limit && ($listing->buybox_price <= $listing->price_limit || $listing->price_limit == 0)){
                    $new_min_price = $listing->buybox_price - 2;
                    if($new_min_price < $listing->min_price_limit){
                        $new_min_price = $listing->min_price_limit;
                    }

                    if($new_min_price > $listing->price || $new_min_price < $listing->price*0.92){
                        $new_price = $new_min_price / 0.92;
                    }else{
                        $new_price = $listing->price;
                    }
                    $new_min_price = round($new_min_price, 2);
                    $new_price = round($new_price, 2);
                    $response = $bm->updateOneListing($listing->variation->reference_id,json_encode(['min_price'=>$new_min_price, 'price'=>$new_price]), $listing->country_id->market_code);
                    print_r($response);
                    $listing->price = $new_price;
                    $listing->min_price = $new_min_price;
                }elseif($listing->handler_status == 1 && $listing->bybox !== 1 && ($listing->buybox_price < $listing->min_price_limit || $listing->buybox_price > $listing->price_limit)){
                    $listing->handler_status = 2;
                }
                $listing->save();
                if ($error != '') {
                    // return 1; // Return 1 to indicate an error occurred
                }

                // return 0; // Return 0 to indicate success
            }
        }
        if($error != ''){
            Log::info($error);
        }
        return 0;

    }
    public function recheck_inactive_handlers(){
        $listings = Listing_model::where('handler_status', 2)->where('min_price_limit', '>', 0)->where('min_price_limit', '<=', 'buybox_price')->where('min_price_limit', '<=', 'min_price')->get();
        $variations = Variation_model::whereIn('id', $listings->pluck('variation_id'))->where('listed_stock','>',0)->get();
        $listingController = new ListingController();
        foreach ($variations as $variation) {
            $json_data = $listingController->get_variation_available_stocks( $variation->id );
            if (json_decode($json_data) == null) {
                $json_data = json_encode(['breakeven_price' => 0]);
                Log::info("Handler: No data for variation: " . $variation->sku. " - " . $json_data);
                continue;
            }
            echo $json_data;
            $breakeven_price = json_decode($json_data)->breakeven_price;

            $listings->where('variation_id', $variation->id)->where('min_price_limit', '<=', $breakeven_price)->where('price_limit', '>=', $breakeven_price)->update(['handler_status' => 3]);
        }
    }
}
