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

    protected array $bmBenchmarkCache = [];

    protected ?array $bmBenchmarkCountryIds = null;

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
            $pendingPriceUpdates = [];

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
                            'buybox_entries' => $this->extractRefurbedBuyboxEntries($offer),
                        ];

                        // Collect market-specific price entries so each country gets its own listing
                        $marketEntries = [];

                        $collectMarketEntry = function (?array $marketPrice, ?string $marketCode = null) use (&$marketEntries, $priceCurrency) {
                            if (empty($marketPrice) || !is_array($marketPrice)) {
                                return;
                            }

                            $code = $marketCode ?? $marketPrice['market_code'] ?? null;
                            if (!$code) {
                                return;
                            }

                            $marketEntries[$code] = [
                                'market_code' => $code,
                                'price' => $marketPrice['price'] ?? null,
                                'min_price' => $marketPrice['min_price'] ?? null,
                                'max_price' => $marketPrice['max_price'] ?? null,
                                'price_limit' => $marketPrice['price_limit'] ?? null,
                                'min_price_limit' => $marketPrice['min_price_limit'] ?? null,
                                'currency' => $marketPrice['currency'] ?? ($marketPrice['price']['currency'] ?? $priceCurrency),
                            ];
                        };

                        $collectMarketEntry($offer['market_price'] ?? null);

                        if (!empty($offer['set_market_prices']) && is_array($offer['set_market_prices'])) {
                            foreach ($offer['set_market_prices'] as $marketPrice) {
                                $collectMarketEntry($marketPrice);
                            }
                        }

                        if (!empty($offer['calculated_market_prices']) && is_array($offer['calculated_market_prices'])) {
                            foreach ($offer['calculated_market_prices'] as $marketPrice) {
                                if (!is_array($marketPrice)) {
                                    continue;
                                }

                                $code = $marketPrice['market_code'] ?? null;
                                if ($code && isset($marketEntries[$code])) {
                                    continue;
                                }

                                $collectMarketEntry($marketPrice);
                            }
                        }

                        if (empty($marketEntries)) {
                            $marketEntries[$countryCode] = [
                                'market_code' => $countryCode,
                                'price' => $priceAmount,
                                'min_price' => $offer['min_price'] ?? null,
                                'max_price' => $offer['max_price'] ?? null,
                                'price_limit' => $offer['price_limit'] ?? null,
                                'min_price_limit' => $offer['min_price_limit'] ?? null,
                                'currency' => $priceCurrency,
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
                    $marketCode = $marketPriceData['market_code'] ?? $countryCode;
                    $sku = $offerMeta['sku'] ?? null;

                    $listing = Listing_model::firstOrNew([
                        'country' => $countryId,
                        'variation_id' => $offerMeta['variation_id'],
                        'marketplace_id' => $marketplaceId
                    ]);

                    $currencyId = $offerMeta['currency_id'];
                    if (!empty($marketPriceData['currency'])) {
                        $currencyId = $this->resolveCurrencyIdByCode($marketPriceData['currency']) ?? $currencyId;
                    }
                    if ($currencyId) {
                        $listing->currency_id = $currencyId;
                    }

                    $listing->reference_uuid = $offerMeta['offer_id'];

                    if ($listing->name == null && $offerMeta['title']) {
                        $listing->name = $offerMeta['title'];
                    }

                    $marketPriceAmount = $this->extractRefurbedPriceAmount($marketPriceData['price'] ?? null);
                    if ($marketPriceAmount === null) {
                        $marketPriceAmount = $offerMeta['price_amount'];
                    }

                    $listing->price = $offerMeta['reference_price'] ?? $marketPriceAmount;

                    $marketMinPrice = $this->extractRefurbedPriceAmount($marketPriceData['min_price'] ?? null);
                    if ($marketMinPrice === null) {
                        $marketMinPrice = $offerMeta['min_price'];
                    }

                    $effectiveMinPrice = $offerMeta['reference_min_price'] ?? $marketMinPrice;
                    if ($effectiveMinPrice !== null) {
                        $listing->min_price = $effectiveMinPrice;
                    }

                    $marketMaxPrice = $this->extractRefurbedPriceAmount($marketPriceData['max_price'] ?? null);
                    if ($marketMaxPrice !== null || $offerMeta['max_price'] !== null) {
                        $listing->max_price = $marketMaxPrice ?? $offerMeta['max_price'];
                    }

                    if (array_key_exists('price_limit', $marketPriceData) || $offerMeta['price_limit'] !== null) {
                        $listing->price_limit = $marketPriceData['price_limit'] ?? $offerMeta['price_limit'];
                    }

                    if (array_key_exists('min_price_limit', $marketPriceData) || $offerMeta['min_price_limit'] !== null) {
                        $listing->min_price_limit = $marketPriceData['min_price_limit'] ?? $offerMeta['min_price_limit'];
                    }

                    $buyboxEntries = $offerMeta['buybox_entries'] ?? [];
                    $entryBuybox = $this->normalizeRefurbedBuyboxEntry($marketPriceData);
                    $resolvedBuybox = $entryBuybox ?: $this->resolveRefurbedBuyboxForMarket($buyboxEntries, $marketCode);

                    if ($resolvedBuybox) {
                        if (array_key_exists('has_buybox', $resolvedBuybox) && $resolvedBuybox['has_buybox'] !== null) {
                            $listing->buybox = $resolvedBuybox['has_buybox'];
                        }
                        if (array_key_exists('price_to_win', $resolvedBuybox) && $resolvedBuybox['price_to_win'] !== null) {
                            $listing->buybox_price = $resolvedBuybox['price_to_win'];
                        }
                        if (array_key_exists('winner_price', $resolvedBuybox) && $resolvedBuybox['winner_price'] !== null) {
                            $listing->buybox_winner_price = $resolvedBuybox['winner_price'];
                        }

                        if (empty($currencyId) && !empty($resolvedBuybox['currency'])) {
                            $currencyId = $this->resolveCurrencyIdByCode($resolvedBuybox['currency']);
                            if ($currencyId) {
                                $listing->currency_id = $currencyId;
                            }
                        }
                    }

                    $bmBenchmark = $this->getBackMarketBenchmarkForVariation($offerMeta['variation_id']);
                    if ($bmBenchmark['max_price'] !== null) {
                        $listing->price = $listing->price !== null ? max($listing->price, $bmBenchmark['max_price']) : $bmBenchmark['max_price'];
                    }
                    if ($bmBenchmark['max_min_price'] !== null) {
                        $listing->min_price = $listing->min_price !== null ? max($listing->min_price, $bmBenchmark['max_min_price']) : $bmBenchmark['max_min_price'];
                    }

                    echo $listing->id . " ";
                    $listing->save();
                    $processedListings++;

                    if ($sku && $marketCode) {
                        $currencyCode = $marketPriceData['currency'] ?? ($marketPriceData['price']['currency'] ?? $this->resolveCurrencyCodeById($listing->currency_id));
                        if ($currencyCode && $this->shouldPushRefurbedMarketPrice($marketPriceData, $listing, $offerMeta)) {
                            $payload = $this->buildRefurbedMarketPricePayload($marketCode, $currencyCode, $listing);
                            if ($payload) {
                                $pendingPriceUpdates[$sku][$payload['market_code']] = $payload;
                            }
                        }
                    }
                }
            }

            $countriesProcessed = array_intersect(array_keys($offersByCountry), array_keys($countryMap));
            $countryCount = count(array_unique($countriesProcessed));

            foreach ($pendingPriceUpdates as $sku => $setMarketPrices) {
                $this->pushRefurbedPriceUpdates($refurbed, $sku, $setMarketPrices);
            }

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

    protected function getBackMarketBenchmarkForVariation(int $variationId): array
    {
        if (isset($this->bmBenchmarkCache[$variationId])) {
            return $this->bmBenchmarkCache[$variationId];
        }

        $countryIds = $this->getBenchmarkCountryIds();
        if (empty($countryIds)) {
            return $this->bmBenchmarkCache[$variationId] = [
                'max_price' => null,
                'max_min_price' => null,
            ];
        }

        $listings = Listing_model::where('variation_id', $variationId)
            ->where('marketplace_id', 1)
            ->whereIn('country', $countryIds)
            ->get(['price', 'min_price']);

        $maxPrice = null;
        $maxMinPrice = null;

        foreach ($listings as $listing) {
            if ($listing->price !== null) {
                $maxPrice = $maxPrice === null ? $listing->price : max($maxPrice, $listing->price);
            }

            $minValue = $listing->min_price ?? $listing->price;
            if ($minValue !== null) {
                $maxMinPrice = $maxMinPrice === null ? $minValue : max($maxMinPrice, $minValue);
            }
        }

        return $this->bmBenchmarkCache[$variationId] = [
            'max_price' => $maxPrice,
            'max_min_price' => $maxMinPrice ?? $maxPrice,
        ];
    }

    protected function getBenchmarkCountryIds(): array
    {
        if ($this->bmBenchmarkCountryIds === null) {
            $this->bmBenchmarkCountryIds = Country_model::whereIn('code', ['FR', 'ES'])->pluck('id')->all();
        }

        return $this->bmBenchmarkCountryIds;
    }

    private function extractRefurbedBuyboxEntries(array $offer): array
    {
        $entries = [];

        $collect = function (array $candidate, ?string $marketCode = null) use (&$entries) {
            $normalized = $this->normalizeRefurbedBuyboxEntry($candidate);
            if ($normalized === null) {
                return;
            }

            $code = $marketCode ?? $candidate['market_code'] ?? $candidate['market'] ?? null;
            if ($code) {
                $entries[strtoupper($code)] = $normalized;
            } else {
                $entries['*'] = $normalized;
            }
        };

        $walker = function ($payload, ?string $marketCode = null) use (&$walker, $collect) {
            if (!is_array($payload)) {
                return;
            }

            $currentMarket = $marketCode ?? $payload['market_code'] ?? $payload['market'] ?? null;

            if ($this->looksLikeRefurbedBuyboxEntry($payload)) {
                $collect($payload, $currentMarket);
            }

            foreach ($payload as $key => $value) {
                if (!is_array($value)) {
                    continue;
                }

                $childMarket = $value['market_code'] ?? $value['market'] ?? (is_string($key) && strlen($key) <= 3 ? strtoupper($key) : $currentMarket);
                $walker($value, $childMarket);
            }
        };

        $walker($offer);

        return $entries;
    }

    private function normalizeRefurbedBuyboxEntry(?array $payload): ?array
    {
        if (empty($payload) || !is_array($payload)) {
            return null;
        }

        $priceToWin = $this->extractRefurbedPriceAmount($payload['price_to_win'] ?? $payload['price_for_buybox'] ?? $payload['buybox_price'] ?? null);
        $winnerPrice = $this->extractRefurbedPriceAmount($payload['winner_price'] ?? $payload['buybox_winner_price'] ?? null);
        $hasBuybox = $this->normalizeRefurbedBoolean($payload['same_merchant_winner'] ?? $payload['is_winner'] ?? $payload['wins_buybox'] ?? $payload['has_buybox'] ?? $payload['buybox'] ?? null);
        $currency = $payload['price_to_win']['currency'] ?? $payload['price_for_buybox']['currency'] ?? $payload['winner_price']['currency'] ?? $payload['currency'] ?? null;

        if ($priceToWin === null && $winnerPrice === null && $hasBuybox === null && $currency === null) {
            return null;
        }

        return [
            'has_buybox' => $hasBuybox,
            'price_to_win' => $priceToWin,
            'winner_price' => $winnerPrice,
            'currency' => $currency,
        ];
    }

    private function resolveRefurbedBuyboxForMarket(array $entries, ?string $marketCode): ?array
    {
        if ($marketCode) {
            $code = strtoupper($marketCode);
            if (isset($entries[$code])) {
                return $entries[$code];
            }
        }

        return $entries['*'] ?? null;
    }

    private function looksLikeRefurbedBuyboxEntry(array $payload): bool
    {
        $hintKeys = [
            'price_to_win',
            'price_for_buybox',
            'winner_price',
            'buybox_price',
            'buybox_winner_price',
            'same_merchant_winner',
            'is_winner',
            'wins_buybox',
            'has_buybox',
            'buybox',
        ];

        foreach ($hintKeys as $key) {
            if (array_key_exists($key, $payload)) {
                return true;
            }
        }

        return false;
    }

    private function extractRefurbedPriceAmount($value): ?float
    {
        if (is_array($value)) {
            if (array_key_exists('amount', $value)) {
                $value = $value['amount'];
            } elseif (array_key_exists('value', $value)) {
                $value = $value['value'];
            }
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function normalizeRefurbedBoolean($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            return ((int) $value) ? 1 : 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'winner'], true)) {
                return 1;
            }
            if (in_array($normalized, ['0', 'false', 'no'], true)) {
                return 0;
            }
        }

        return null;
    }

    private function resolveCurrencyCodeById(?int $currencyId): ?string
    {
        static $cache = [];

        if (!$currencyId) {
            return null;
        }

        if (!array_key_exists($currencyId, $cache)) {
            $cache[$currencyId] = Currency_model::where('id', $currencyId)->value('code');
        }

        return $cache[$currencyId];
    }

    private function resolveCountryCodeById(?int $countryId): ?string
    {
        static $cache = [];

        if (!$countryId) {
            return null;
        }

        if (!array_key_exists($countryId, $cache)) {
            $cache[$countryId] = Country_model::where('id', $countryId)->value('code');
        }

        return $cache[$countryId];
    }

    private function shouldPushRefurbedMarketPrice(array $marketPriceData, Listing_model $listing, array $offerMeta): bool
    {
        $baselinePrice = $offerMeta['reference_price']
            ?? $this->extractRefurbedPriceAmount($marketPriceData['price'] ?? null)
            ?? $offerMeta['price_amount'] ?? null;

        if ($this->valueChanged($baselinePrice, $listing->price)) {
            return true;
        }

        $baselineMinPrice = $offerMeta['reference_min_price']
            ?? $marketPriceData['min_price'] ?? $offerMeta['min_price'] ?? null;
        if ($this->valueChanged($baselineMinPrice, $listing->min_price)) {
            return true;
        }

        $baselinePriceLimit = $marketPriceData['price_limit'] ?? $offerMeta['price_limit'] ?? null;
        if ($this->valueChanged($baselinePriceLimit, $listing->price_limit)) {
            return true;
        }

        $baselineMinPriceLimit = $marketPriceData['min_price_limit'] ?? $offerMeta['min_price_limit'] ?? null;
        if ($this->valueChanged($baselineMinPriceLimit, $listing->min_price_limit)) {
            return true;
        }

        return false;
    }

    private function buildRefurbedMarketPricePayload(string $marketCode, string $currencyCode, Listing_model $listing): ?array
    {
        $payload = [
            'market_code' => strtoupper($marketCode),
        ];

        if (($pricePayload = $this->buildRefurbedMoneyPayload($listing->price, $currencyCode)) !== null) {
            $payload['price'] = $pricePayload;
        }

        if (($minPrice = $this->roundPriceValue($listing->min_price)) !== null) {
            $payload['min_price'] = $minPrice;
        }

        if (($maxPrice = $this->roundPriceValue($listing->max_price)) !== null) {
            $payload['max_price'] = $maxPrice;
        }

        if (($priceLimit = $this->roundPriceValue($listing->price_limit)) !== null) {
            $payload['price_limit'] = $priceLimit;
        }

        if (($minPriceLimit = $this->roundPriceValue($listing->min_price_limit)) !== null) {
            $payload['min_price_limit'] = $minPriceLimit;
        }

        $hasValue = array_diff_key($payload, ['market_code' => true]);

        return empty($hasValue) ? null : $payload;
    }

    private function buildRefurbedMoneyPayload(?float $amount, string $currencyCode): ?array
    {
        if ($amount === null) {
            return null;
        }

        return [
            'amount' => $this->roundPriceValue($amount),
            'currency' => $currencyCode,
        ];
    }

    private function roundPriceValue(?float $value): ?float
    {
        return $value === null ? null : round($value, 2);
    }

    private function valueChanged(?float $original, ?float $current): bool
    {
        if ($original === null && $current === null) {
            return false;
        }

        if ($original === null || $current === null) {
            return true;
        }

        return abs($original - $current) > 0.0001;
    }

    private function pushRefurbedPriceUpdates(RefurbedAPIController $refurbed, ?string $sku, array $setMarketPrices): void
    {
        if (empty($sku) || empty($setMarketPrices)) {
            return;
        }

        try {
            $refurbed->updateOffer([
                'sku' => $sku,
            ], [
                'set_market_prices' => array_values($setMarketPrices),
            ]);
        } catch (\Throwable $e) {
            Log::error('Refurbed: Failed to push price update', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveCurrencyIdByCode(?string $currencyCode): ?int
    {
        if (!$currencyCode) {
            return null;
        }

        return Currency_model::where('code', $currencyCode)->value('id');
    }
}
