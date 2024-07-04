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
use App\Models\Variation_listing_qty_model;
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
    // public function get_listings(){
    //     $bm = new BackMarketAPIController();

    //     // Fetch listings
    //     $listings = $bm->getAllListings();

    //     foreach ($listings as $country => $lists) {
    //         foreach ($lists as $list) {
    //             // Ensure consistent trimming of listing_id and sku
    //             $trimmedListingId = trim($list->listing_id);
    //             $trimmedSku = trim($list->sku);

    //             // Debugging: Log trimmed input data
    //             echo "Processing Listing ID: [" . $trimmedListingId . "] SKU: [" . $trimmedSku . "]\n";

    //             // Check if the variation already exists
    //             $variation = Variation_model::where(['reference_id' => $trimmedListingId, 'sku' => $trimmedSku])->first();

    //             $state = $list->publication_state;
    //             if ($variation == null) {
    //                 echo "No variation found, creating new one for Listing ID: " . $trimmedListingId . "\n";

    //                 // Fetch the latest listing details
    //                 $listDetails = $bm->getOneListing($list->listing_id);

    //                 // Debugging: Log fetched details
    //                 echo "Fetched List Details: " . json_encode($listDetails) . "\n";

    //                 // Create or retrieve a new variation record
    //                 $variation = Variation_model::firstOrNew(['reference_id' => $trimmedListingId, 'sku' => $trimmedSku]);

    //                 // Update fields
    //                 $variation->name = $listDetails->title;
    //                 $variation->grade = $listDetails->state + 1;
    //                 $variation->status = 1;
    //                 $state = $listDetails->publication_state;
    //                 // ... other fields

    //                 try {
    //                     $variation->save();
    //                     echo "New variation created for Listing ID: " . $trimmedListingId . "\n";
    //                 } catch (\Exception $e) {
    //                     echo "Error creating variation: " . $e->getMessage() . "\n";
    //                 }
    //             } else {
    //                 echo "Existing variation found for Listing ID: " . $trimmedListingId . "\n";
    //             }
    //             $variation->state = $state;
    //             $variation->save();

    //             $currency = Currency_model::where('code', $list->currency)->first();
    //             $variation_listing_qty = Variation_listing_qty_model::firstOrNew(['variation_id' => $variation->id]);

    //             if ($variation == null) {
    //                 echo $list->sku . " ";
    //             } else {
    //                 $listing = Listing_model::firstOrNew(['country' => $country, 'variation_id' => $variation->id]);
    //                 $listing->max_price = $list->max_price;
    //                 $listing->min_price = $list->min_price;
    //                 $variation_listing_qty->quantity = $list->quantity;
    //                 $listing->price = $list->price;
    //                 $listing->currency_id = $currency->id;
    //                 // ... other fields
    //                 $listing->save();
    //                 $variation_listing_qty->save();
    //             }
    //         }
    //     }
    //     echo "Script execution completed.\n";
    // }

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
                    $variation->grade = (int)$list->state + 1;
                    $variation->state = $list->publication_state;
                    $variation->status = 1;
                    // ... other fields
                    $variation->save();
                    echo $list->listing_id." ";
                }
                $currency = Currency_model::where('code',$list->currency)->first();
                // echo $list->currency;
                $variation_listing_qty = Variation_listing_qty_model::firstOrNew(['variation_id'=>$variation->id]);
                if($variation == null){
                    echo $list->sku." ";
                }else{
                    $listing = Listing_model::firstOrNew(['country' => $country, 'variation_id' => $variation->id]);
                    $listing->max_price = $list->max_price;
                    $listing->min_price = $list->min_price;
                    $variation_listing_qty->quantity = $list->quantity;
                    $listing->price = $list->price;
                    $listing->currency_id = $currency->id;
                    // ... other fields
                    $listing->save();
                    $variation_listing_qty->save();
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
                $variation_listing_qty = Variation_listing_qty_model::firstOrNew(['variation_id'=>$variation->id]);
                if($variation == null){
                    echo $list->sku." ";
                }else{
                    $listing = Listing_model::firstOrNew(['country' => $country, 'variation_id' => $variation->id]);
                    $variation_listing_qty->quantity = $list->quantity;
                    $listing->price = $list->price;
                    $listing->buybox = $list->same_merchant_winner;
                    $listing->buybox_price = $list->price_for_buybox;
                    $listing->currency_id = $currency->id;
                    // ... other fields
                    $listing->save();
                    $variation_listing_qty->save();
                }
            }
        }
        // $list = $bm->getOneListing($itemObj->listing_id);
    }
}
