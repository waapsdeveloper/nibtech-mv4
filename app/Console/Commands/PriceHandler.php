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
        
        // Get total count for progress tracking
        $totalListings = Listing_model::whereIn('handler_status', [1,3])
            ->where('marketplace_id', 1)
            ->where('buybox', '!=', 1)
            ->where('min_price_limit', '>', 0)
            ->whereColumn('min_price_limit', '<=', 'buybox_price')
            ->whereColumn('min_price_limit', '<=', 'min_price')
            ->count();
        
        $this->info("Processing {$totalListings} listings in chunks...");
        
        // Pre-load countries to avoid N+1 queries
        $countries = Country_model::all()->keyBy('code');
        
        // Process in chunks of 50 to reduce memory usage and allow progress tracking
        $chunkSize = 50;
        $processed = 0;
        
        Listing_model::whereIn('handler_status', [1,3])
            ->where('marketplace_id', 1)
            ->where('buybox', '!=', 1)
            ->where('min_price_limit', '>', 0)
            ->whereColumn('min_price_limit', '<=', 'buybox_price')
            ->whereColumn('min_price_limit', '<=', 'min_price')
            ->with(['variation', 'country_id']) // Eager load relationships
            ->chunk($chunkSize, function ($listings) use ($bm, &$error, &$processed, $countries) {
                $variation_ids = $listings->pluck('variation_id')->filter();
                $variations = Variation_model::whereIn('id', $variation_ids)
                    ->where('listed_stock', '>', 0)
                    ->get()
                    ->keyBy('id');
                
                $references = $listings->pluck('reference_uuid')->filter()->unique()->toArray();
                $referenceVariationMap = $listings
                    ->filter(fn ($listing) => !empty($listing->reference_uuid) && !empty($listing->variation_id))
                    ->pluck('variation_id', 'reference_uuid')
                    ->toArray();
                
                $resolvedVariationCache = [];
                
                foreach ($references as $reference) {
                    $responses = $bm->getListingCompetitors($reference);
                    usleep(500000); // 0.5 second delay instead of 1 second (reduces wait time by 50%)
                    
                    if ($responses == null) {
                        $error .= "No response for variation: " . $reference . "\n";
                        continue;
                    }
                    if (is_object($responses) && $responses->type == 'unknown-competitor') {
                        continue;
                    }
                    if (is_array($responses) && isset($responses['type']) && $responses['type'] == 'unknown-competitor') {
                        continue;
                    }
                    echo "SKU: " . $reference . "\n";
                    echo "Response: \n";
                    
                    foreach($responses as $list){
                // print_r($list);
                if(is_string($list) || is_int($list)){
                    print_r($responses);
                    echo "\n\n";
                    $error .= $list;
                    continue;
                }
                if(is_array($list)){
                    if (is_array($list) || is_object($list)) {
                        $code = is_array($list) ? ($list['code'] ?? null) : ($list->code ?? null);
                        if ($code === 'unknown-competitor') {
                            continue;
                        }
                        $error .= json_encode($list) . "\n";
                        $error .= "Error in response for variation: {$reference}\n";
                        continue;
                    }
                }
                // Use pre-loaded country cache instead of querying database
                $country = $countries->get($list->market);
                if($country == null){
                    $error .= "No country found for market: " . $list->market . " for variation: " . $reference . "\n";
                    continue;
                }
                $listing = Listing_model::firstOrNew(['reference_uuid' => $reference, 'country' => $country->id, 'marketplace_id' => 1]);
                if (! $listing->variation_id) {
                    $variationId = $referenceVariationMap[$reference]
                        ?? $resolvedVariationCache[$reference]
                        ?? $this->resolveVariationIdForReference($reference);

                    if (! $variationId) {
                        Log::warning('Price handler unable to resolve variation for reference', [
                            'reference_uuid' => $reference,
                        ]);
                        continue;
                    }

                    $listing->variation_id = $variationId;
                    $resolvedVariationCache[$reference] = $variationId;
                }
                // if(!isset($list->id)){
                //     $error .= "No listing ID found for market: " . $list->market . " for variation: " . json_encode($list) . "\n";
                //     continue;
                // }
                // $listing->reference_uuid = $list->id;
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

                // print_r($listing);
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
                    // print_r($response);
                    $listing->price = $new_price;
                    $listing->min_price = $new_min_price;
                }elseif($listing->handler_status == 1 && $listing->bybox !== 1 && ($listing->buybox_price < $listing->min_price_limit || $listing->buybox_price > $listing->price_limit)){
                    $listing->handler_status = 2;
                }
                $listing->save();
                    }
                }
                
                $processed += count($references);
                $this->info("Processed {$processed} references...");
            });
        
        // foreach ($variations as $variation) {

        //     $responses = $bm->getListingCompetitors($variation->reference_uuid);
        //     sleep(1);
        //     // $responses = $listingController->getCompetitors($variation->id, 1);
        //     if ($responses == null) {
        //         $error .= "No response for variation: " . $variation->sku . "\n";
        //         continue;
        //     }
        //     if (is_object($responses) && $responses->type == 'unknown-competitor') {
        //         continue;
        //     }
        //     if (is_array($responses) && isset($responses['type']) && $responses['type'] == 'unknown-competitor') {
        //         continue;
        //     }
        //     echo "SKU: " . $variation->sku . "\n";
        //     echo "Response: \n";
        //     foreach($responses as $list){
        //         // print_r($list);
        //         if(is_string($list) || is_int($list)){
        //             print_r($responses);
        //             echo "\n\n";
        //             $error .= $list;
        //             continue;
        //         }
        //         if(is_array($list)){
        //             if (is_array($list) || is_object($list)) {
        //                 $code = is_array($list) ? ($list['code'] ?? null) : ($list->code ?? null);
        //                 if ($code === 'unknown-competitor') {
        //                     continue;
        //                 }
        //                 $error .= json_encode($list) . "\n";
        //                 $error .= "Error in response for variation: {$variation->sku}\n";
        //                 continue;
        //             }
        //         }
        //         $country = Country_model::where('code',$list->market)->first();
        //         $listing = Listing_model::firstOrNew(['variation_id'=>$variation->id, 'country'=>$country->id, 'marketplace_id' => 1]);
        //         if($country == null){
        //             $error .= "No country found for market: " . $list->market . " for variation: " . $variation->sku . "\n";
        //             continue;
        //         }
        //         if($list->id == null){
        //             $error .= "No listing ID found for market: " . $list->market . " for variation: " . $variation->sku . "\n";
        //             continue;
        //         }
        //         $listing->reference_uuid = $list->id;
        //         if($list->price != null){
        //             $listing->price = $list->price->amount;
        //         }
        //         if($list->min_price != null){
        //             $listing->min_price = $list->min_price->amount;
        //         }
        //         $listing->buybox = $list->is_winning;
        //         $listing->buybox_price = $list->price_to_win->amount;
        //         $listing->buybox_winner_price = $list->winner_price->amount;
        //         $listing->save();

        //         // print_r($listing);
        //         if($listing->handler_status == 1 && $listing->buybox !== 1 && $listing->buybox_price >= $listing->min_price_limit && ($listing->buybox_price <= $listing->price_limit || $listing->price_limit == 0)){
        //             $new_min_price = $listing->buybox_price - 2;
        //             if($new_min_price < $listing->min_price_limit){
        //                 $new_min_price = $listing->min_price_limit;
        //             }

        //             if($new_min_price > $listing->price || $new_min_price < $listing->price*0.92){
        //                 $new_price = $new_min_price / 0.92;
        //             }else{
        //                 $new_price = $listing->price;
        //             }
        //             $new_min_price = round($new_min_price, 2);
        //             $new_price = round($new_price, 2);
        //             $response = $bm->updateOneListing($listing->variation->reference_id,json_encode(['min_price'=>$new_min_price, 'price'=>$new_price]), $listing->country_id->market_code);
        //             // print_r($response);
        //             $listing->price = $new_price;
        //             $listing->min_price = $new_min_price;
        //         }elseif($listing->handler_status == 1 && $listing->bybox !== 1 && ($listing->buybox_price < $listing->min_price_limit || $listing->buybox_price > $listing->price_limit)){
        //             $listing->handler_status = 2;
        //         }
        //         $listing->save();

        //     }
        // }
        if($error != ''){
            Log::info($error);
        }
        return 0;

    }
    public function recheck_inactive_handlers(){
        // Use chunking to process in smaller batches
        $listingController = app(ListingController::class);
        $processed = 0;
        
        Listing_model::where('handler_status', 2)
            ->where('min_price_limit', '>', 0)
            ->where('min_price_limit', '<=', 'buybox_price')
            ->where('min_price_limit', '<=', 'min_price')
            ->with('variation') // Eager load variation
            ->chunk(50, function ($listings) use ($listingController, &$processed) {
                $variationIds = $listings->pluck('variation_id')->filter();
                
                $variations = Variation_model::whereIn('id', $variationIds)
                    ->where('listed_stock', '>', 0)
                    ->get()
                    ->keyBy('id');
                
                foreach ($variations as $variation) {
                    $json_data = $listingController->get_variation_available_stocks($variation->id);
                    if (json_decode($json_data) == null) {
                        $json_data = json_encode(['breakeven_price' => 0]);
                        Log::info("Handler: No data for variation: " . $variation->sku . " - " . $json_data);
                        continue;
                    }
                    
                    $breakeven_price = json_decode($json_data)->breakeven_price;
                    
                    // Use bulk update instead of individual updates
                    $listings->where('variation_id', $variation->id)
                        ->where('min_price_limit', '<=', $breakeven_price)
                        ->where('price_limit', '>=', $breakeven_price)
                        ->update(['handler_status' => 3]);
                    
                    $processed++;
                }
            });
        
        if ($processed > 0) {
            $this->info("Rechecked {$processed} inactive handlers");
        }
    }

    protected function resolveVariationIdForReference(string $reference): ?int
    {
        $reference = trim($reference);

        if ($reference === '') {
            return null;
        }

        return Variation_model::where('reference_uuid', $reference)->value('id')
            ?? Variation_model::where('reference_id', $reference)->value('id');
    }
}
