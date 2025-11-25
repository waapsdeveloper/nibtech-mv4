<?php

namespace App\Console\Commands;

use App\Http\Controllers\BackMarketAPIController;
use App\Http\Controllers\RefurbedAPIController;
use App\Models\Country_model;
use App\Models\Currency_model;
use App\Models\Listing_model;
use App\Models\Variation_model;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $this->get_refurbed_listings();
        $this->get_listings();
        echo 'sad';
        $this->get_listingsBi();

        return 0;
    }
    public function get_listings(){
        $bm = new BackMarketAPIController();

        // print_r($bm->getAllListingsBi(['min_quantity'=>0]));
        $listings = $bm->getAllListings();

        foreach($listings as $country => $lists){
            foreach($lists as $list){

                $variation = Variation_model::where(['reference_id'=>trim($list->listing_id), 'sku' => trim($list->sku)])->first();
                if( $list->publication_state == 4) {
                    // If the listing is archived, we skip it
                    continue;
                }
                if($variation == null){
                    // $list = $bm->getOneListing($list->listing_id);
                    $variation = Variation_model::firstOrNew(['reference_id' => trim($list->listing_id)]);
                    $variation->sku = trim($list->sku);
                    $variation->grade = (int)$list->state + 1;
                    if((int)$list->state == 9){
                        $variation->grade = 1;
                    }
                    $variation->status = 1;
                    // ... other fields
                    echo $list->listing_id." ";
                }
                if($variation->name == null){
                    $variation->name = $list->title;
                }
                if($variation->reference_uuid == null){
                    $variation->reference_uuid = $list->id;
                    echo $list->id." ";
                }
                if($variation->state != $list->publication_state){
                    $variation->state = $list->publication_state;
                    echo $list->publication_state." ";
                }
                $variation->save();

                $curr = $list->price->currency ?? $list->currency;
                $currency = Currency_model::where('code',$curr)->first();
                // echo $list->currency;
                if($variation == null){
                    echo $list->sku." ";
                }else{
                    $listing = Listing_model::firstOrNew(['country' => $country, 'variation_id' => $variation->id, 'marketplace_id' => 1]);
                    $listing->max_price = $list->max_price;
                    $listing->min_price = $list->min_price;
                    $variation->listed_stock = $list->quantity;
                    $listing->price = $list->price;
                    $listing->currency_id = $currency->id;
                    if($listing->name == null){
                        $listing->name = $list->title;
                    }
                    $listing->reference_uuid = $list->id;
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

        Log::info("Result from getAllListingsBi: " . json_encode($listings));

        foreach($listings as $country => $lists){
            foreach($lists as $list){
                $variation = Variation_model::where('sku',$list->sku)->first();
                $currency = Currency_model::where('code',$list->currency)->first();
                if($variation == null){
                    echo $list->sku." ";
                }else{
                    $listing = Listing_model::firstOrNew(['country' => $country, 'variation_id' => $variation->id, 'marketplace_id' => 1]);
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

    public function get_refurbed_listings(){
        $refurbed = new RefurbedAPIController();

        // marketplace_id = 4 for Refurbed (1=BackMarket, 2=BMPRO EUR, 3=BMPRO GBP)
        $marketplaceId = 4;

        try {
            echo "Fetching all Refurbed offers...\n";

            // Use the new getAllOffers method which handles pagination automatically
            $response = $refurbed->getAllOffers([], [], 100);

            $offers = $response['offers'] ?? [];
            // Log::info("Refurbed: Fetched offers", ['offer count' => count($offers), 'offers' => json_encode($offers)]);
            $totalOffers = $response['total'] ?? count($offers);

            Log::info("Refurbed: Fetched all offers", ['total' => $totalOffers]);
            echo "Total offers fetched: {".count($offers)."}\n";

            if (empty($offers)) {
                Log::info("Refurbed: No offers found");
                echo "No offers found\n";
                return;
            }
            echo "Processing offers...\n";

            $countryMap = Country_model::pluck('id', 'code')->toArray();
            if (empty($countryMap)) {
                Log::warning("Refurbed: No countries configured for listings");
                echo "No countries configured for Refurbed listings\n";
                return;
            }

            $offersByCountry = [];
            $processedListings = 0;

            foreach ($offers as $offer) {
                    try {
                        // Extract offer details (adjust field names based on actual API response)
                        $offerId = $offer['id'] ?? $offer['offer_id'] ?? null;
                        $sku = $offer['sku'] ?? $offer['merchant_sku'] ?? null;
                        $productId = $offer['product_id'] ?? null;
                        $state = $offer['state'] ?? 'ACTIVE';

                        // Price information (Refurbed uses price object similar to BackMarket)
                        $priceAmount = $offer['price']['amount'] ?? $offer['price'] ?? null;
                        $priceCurrency = $offer['price']['currency'] ?? $offer['currency'] ?? 'EUR';

                        // Stock information
                        $quantity = $offer['quantity'] ?? $offer['stock'] ?? 0;

                        // Product details
                        $title = $offer['title'] ?? $offer['product_title'] ?? null;
                        // $condition = $offer['condition'] ?? $offer['grade'] ?? null;

                        // Country/region (Refurbed might not have country-specific listings like BackMarket)
                        // Defaulting to a general country code, adjust as needed
                        $countryCode = $offer['country'] ?? $offer['region'] ?? 'DE'; // Germany as default for Refurbed

                        if (empty($sku)) {
                            Log::warning("Refurbed: Offer missing SKU", ['offer_id' => $offerId]);
                            continue;
                        }
                        if(ctype_digit($sku) && strlen($sku) < 4){
                            $sku = "00".$sku;
                        }
                        // Find or create variation based on SKU
                        $variation = Variation_model::where(['sku' => trim($sku)])->first();

                        if ($variation == null) {
                            Log::warning("Refurbed: Variation not found for SKU", ['sku' => $sku, 'offer_id' => $offerId]);
                            continue;
                        }

                        // Update variation fields
                        // if ($variation->name == null && $title) {
                        //     $variation->name = $title;
                        // }
                        // if ($variation->reference_uuid == null && $productId) {
                        //     $variation->reference_uuid = $productId;
                        // }

                        // Map Refurbed condition to grade (adjust mapping as needed)
                        // if ($condition) {
                        //     $gradeMap = [
                        //         'NEW' => 1,
                        //         'EXCELLENT' => 2,
                        //         'VERY_GOOD' => 3,
                        //         'GOOD' => 4,
                        //         'FAIR' => 5,
                        //     ];
                        //     $variation->grade = $gradeMap[strtoupper($condition)] ?? 3;
                        // }

                        // Set state based on offer state
                        // Refurbed uses enum values like OFFER_STATE_ACTIVE, OFFER_STATE_INACTIVE, etc.
                        // $stateMap = [
                        //     'OFFER_STATE_ACTIVE' => 1,
                        //     'ACTIVE' => 1,
                        //     'OFFER_STATE_INACTIVE' => 2,
                        //     'INACTIVE' => 2,
                        //     'OFFER_STATE_PAUSED' => 2,
                        //     'PAUSED' => 2,
                        //     'OFFER_STATE_OUT_OF_STOCK' => 3,
                        //     'OUT_OF_STOCK' => 3,
                        // ];
                        // $variation->state = $stateMap[$state] ?? 1;
                        // $variation->listed_stock = $quantity;

                        // $variation->save();

                        // Get currency
                        $currency = Currency_model::where('code', $priceCurrency)->first();
                        if (!$currency) {
                            Log::warning("Refurbed: Currency not found", ['currency' => $priceCurrency, 'sku' => $sku]);
                            continue;
                        }

                        $referencePrice = $offer['reference_price'] ?? null;
                        $referenceMinPrice = $offer['reference_min_price'] ?? null;

                        $offerMeta = [
                            'offer_id' => $offerId,
                            'variation_id' => $variation->id,
                            'title' => $title,
                            'reference_price' => $referencePrice,
                            'reference_min_price' => $referenceMinPrice,
                            'price_amount' => $priceAmount,
                            'min_price' => $offer['min_price'] ?? null,
                            'max_price' => $offer['max_price'] ?? null,
                            'price_limit' => $offer['price_limit'] ?? $offer['maximum_price'] ?? null,
                            'min_price_limit' => $offer['min_price_limit'] ?? $offer['minimum_price'] ?? null,
                            'currency_id' => $currency->id,
                            'sku' => $sku,
                        ];

                        // Collect market-specific price entries so each country gets its own listing
                        $marketEntries = [];

                        if (!empty($offer['market_price']) && is_array($offer['market_price'])) {
                            $marketCode = $offer['market_price']['market_code'] ?? null;
                            if ($marketCode) {
                                $marketEntries[$marketCode] = $offer['market_price'];
                            }
                        }

                        if (!empty($offer['set_market_prices']) && is_array($offer['set_market_prices'])) {
                            foreach ($offer['set_market_prices'] as $marketPrice) {
                                if (!is_array($marketPrice)) {
                                    continue;
                                }
                                $marketCode = $marketPrice['market_code'] ?? null;
                                if (!$marketCode) {
                                    continue;
                                }
                                $marketEntries[$marketCode] = $marketPrice;
                            }
                        }

                        if (!empty($offer['calculated_market_prices']) && is_array($offer['calculated_market_prices'])) {
                            foreach ($offer['calculated_market_prices'] as $marketPrice) {
                                if (!is_array($marketPrice)) {
                                    continue;
                                }
                                $marketCode = $marketPrice['market_code'] ?? null;
                                if (!$marketCode) {
                                    continue;
                                }
                                if (!isset($marketEntries[$marketCode])) {
                                    $marketEntries[$marketCode] = $marketPrice;
                                }
                            }
                        }

                        if (empty($marketEntries)) {
                            $marketEntries[$countryCode] = [
                                'market_code' => $countryCode,
                                'price' => $priceAmount,
                                'min_price' => $offer['min_price'] ?? null,
                                'max_price' => $offer['max_price'] ?? null,
                            ];
                        }

                        foreach ($marketEntries as $marketCode => $marketPriceData) {
                            if (!isset($countryMap[$marketCode])) {
                                // Log::info("Refurbed: Skipping offer for unsupported country", ['country' => $marketCode, 'sku' => $sku]);
                                continue;
                            }

                            $offersByCountry[$marketCode][] = [
                                'offer' => $offerMeta,
                                'market_price' => $marketPriceData,
                            ];
                        }

                    } catch (\Exception $e) {
                        Log::error("Refurbed: Error processing offer", [
                            'offer_id' => $offerId ?? 'unknown',
                            'error' => $e->getMessage()
                        ]);
                        continue;
                    }
                }

            foreach ($countryMap as $countryCode => $countryId) {
                if (empty($offersByCountry[$countryCode])) {
                    continue;
                }

                $countryOffers = $offersByCountry[$countryCode];
                echo "Country {$countryCode}: processing " . count($countryOffers) . " offers\n";

                foreach ($countryOffers as $entry) {
                    $offerMeta = $entry['offer'];
                    $marketPriceData = $entry['market_price'];

                    $listing = Listing_model::firstOrNew([
                        'country' => $countryId,
                        'variation_id' => $offerMeta['variation_id'],
                        'marketplace_id' => $marketplaceId
                    ]);

                    $marketPriceAmount = $marketPriceData['price'] ?? $offerMeta['price_amount'];
                    $marketMinPrice = $marketPriceData['min_price'] ?? $offerMeta['min_price'];

                    $listing->price = $offerMeta['reference_price'] ?? $marketPriceAmount;
                    $listing->currency_id = $offerMeta['currency_id'];
                    $listing->reference_uuid = $offerMeta['offer_id'];

                    if ($listing->name == null && $offerMeta['title']) {
                        $listing->name = $offerMeta['title'];
                    }

                    $effectiveMinPrice = $offerMeta['reference_min_price'] ?? $marketMinPrice;
                    if ($effectiveMinPrice !== null) {
                        $listing->min_price = $effectiveMinPrice;
                    }

                    if (isset($marketPriceData['max_price']) || $offerMeta['max_price'] !== null) {
                        $listing->max_price = $marketPriceData['max_price'] ?? $offerMeta['max_price'];
                    }

                    if (isset($marketPriceData['price_limit']) || $offerMeta['price_limit'] !== null) {
                        $listing->price_limit = $marketPriceData['price_limit'] ?? $offerMeta['price_limit'];
                    }
                    if (isset($marketPriceData['min_price_limit']) || $offerMeta['min_price_limit'] !== null) {
                        $listing->min_price_limit = $marketPriceData['min_price_limit'] ?? $offerMeta['min_price_limit'];
                    }

                    echo $listing->id . " ";
                    $listing->save();
                    $processedListings++;
                }
            }

            $countriesProcessed = array_intersect(array_keys($offersByCountry), array_keys($countryMap));
            $countryCount = count(array_unique($countriesProcessed));

            echo "Processed listings: {$processedListings} across {$totalOffers} offers in {$countryCount} countries\n";

            Log::info("Refurbed: Completed listing sync", [
                'total_listings' => $processedListings,
                'total_offers' => $totalOffers,
                'countries_processed' => $countryCount,
            ]);
            echo "Refurbed sync complete: {$processedListings} listings processed ({$totalOffers} offers) across {$countryCount} countries\n";

        } catch (\Exception $e) {
            Log::error("Refurbed: Fatal error in get_refurbed_listings", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            echo "\nRefurbed sync failed: " . $e->getMessage() . "\n";
        }
    }
}
