<?php

namespace App\Console\Commands;

use App\Http\Controllers\BackMarketAPIController;
use App\Http\Controllers\RefurbedAPIController;
use App\Models\Country_model;
use App\Models\Currency_model;
use App\Models\Listing_model;
use App\Models\Variation_model;
use App\Models\Order_item_model;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\V2\SlackLogService;
use Carbon\Carbon;

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
        $startTime = microtime(true);
        
        // Check if local sync mode is enabled
        $syncDataInLocal = env('SYNC_DATA_IN_LOCAL', false);
        
        // Log command start (Slack + file)
        Log::channel('functions_thirty')->info('Functions:thirty started', [
            'command' => 'functions:thirty',
            'started_at' => now()->toDateTimeString(),
            'local_mode' => $syncDataInLocal,
        ]);
        SlackLogService::post(
            'listing_sync',
            'info',
            "­ƒöä Functions:thirty command started (BackMarket listings sync)",
            [
                'command' => 'functions:thirty',
                'started_at' => now()->toDateTimeString(),
                'local_mode' => $syncDataInLocal
            ]
        );
        
        if ($syncDataInLocal) {
            // $this->info("ÔÜá´©Å  Local Mode: Will only fetch data, no POST/PUT to BackMarket or Refurbed APIs");
            // SlackLogService::post(
            //     'listing_sync',
            //     'info',
            //     "ÔÜá´©Å  Functions:thirty running in LOCAL MODE - Only fetching data, no API modifications",
            //     [
            //         'command' => 'functions:thirty',
            //         'local_mode' => true
            //     ]
            // );
        }

        // refresh:new skipped (enable when needed to sync orders before stock sync)
        Log::channel('functions_thirty')->info('Functions:thirty: refresh:new skipped', [
            'command' => 'functions:thirty',
            'skipped_at' => now()->toDateTimeString(),
        ]);

        ini_set('max_execution_time', 1200);
        
        // Statistics tracking
        $overallStats = [
            'get_listings' => [
                'countries_processed' => 0,
                'listings_fetched' => 0,
                'variations_created' => 0,
                'variations_updated' => 0,
                'listings_created' => 0,
                'listings_updated' => 0,
                'archived_skipped' => 0,
            ],
            'get_listingsBi' => [
                'countries_processed' => 0,
                'listings_fetched' => 0,
                'variations_updated' => 0,
                'listings_created' => 0,
                'listings_updated' => 0,
                'variations_not_found' => 0,
            ]
        ];
        
        // Run get_listings
        $this->get_listings($overallStats['get_listings']);
        
        // Run get_listingsBi
        $this->get_listingsBi($overallStats['get_listingsBi']);
        
        // Calculate duration
        $duration = round(microtime(true) - $startTime, 2);
        
        // Prepare summary message
        $summaryParts = [];
        
        // get_listings summary
        $glStats = $overallStats['get_listings'];
        if ($glStats['listings_fetched'] > 0 || $glStats['variations_updated'] > 0 || $glStats['listings_updated'] > 0) {
            $glParts = [];
            if ($glStats['listings_fetched'] > 0) {
                $glParts[] = "Listings: {$glStats['listings_fetched']}";
            }
            if ($glStats['variations_updated'] > 0) {
                $glParts[] = "Variations: {$glStats['variations_updated']}";
            }
            if ($glStats['listings_updated'] > 0) {
                $glParts[] = "Synced: {$glStats['listings_updated']}";
            }
            if ($glStats['archived_skipped'] > 0) {
                $glParts[] = "Archived: {$glStats['archived_skipped']}";
            }
            $summaryParts[] = "get_listings(" . implode(", ", $glParts) . ")";
        }
        
        // get_listingsBi summary
        $glBiStats = $overallStats['get_listingsBi'];
        if ($glBiStats['listings_fetched'] > 0 || $glBiStats['variations_updated'] > 0 || $glBiStats['listings_updated'] > 0) {
            $glBiParts = [];
            if ($glBiStats['listings_fetched'] > 0) {
                $glBiParts[] = "Listings: {$glBiStats['listings_fetched']}";
            }
            if ($glBiStats['variations_updated'] > 0) {
                $glBiParts[] = "Variations: {$glBiStats['variations_updated']}";
            }
            if ($glBiStats['listings_updated'] > 0) {
                $glBiParts[] = "Synced: {$glBiStats['listings_updated']}";
            }
            $summaryParts[] = "get_listingsBi(" . implode(", ", $glBiParts) . ")";
        }
        
        $summaryText = !empty($summaryParts) 
            ? " | " . implode(" | ", $summaryParts)
            : " | No listings processed";
        
        // Log command completion with statistics (Slack + file)
        Log::channel('functions_thirty')->info('Functions:thirty completed', [
            'command' => 'functions:thirty',
            'completed_at' => now()->toDateTimeString(),
            'duration_seconds' => $duration,
            'local_mode' => env('SYNC_DATA_IN_LOCAL', false),
            'statistics' => $overallStats,
        ]);
        SlackLogService::post(
            'listing_sync',
            'info',
            "Ô£à Functions:thirty command completed{$summaryText} | Duration: {$duration}s",
            [
                'command' => 'functions:thirty',
                'completed_at' => now()->toDateTimeString(),
                'duration_seconds' => $duration,
                'local_mode' => env('SYNC_DATA_IN_LOCAL', false),
                'statistics' => $overallStats,
                'total_duration' => $duration
            ]
        );

        return 0;
    }

    public function get_listings(&$stats = null){
        $bm = new BackMarketAPIController();

        // Initialize stats if not provided
        if ($stats === null) {
            $stats = [
                'countries_processed' => 0,
                'listings_fetched' => 0,
                'variations_created' => 0,
                'variations_updated' => 0,
                'listings_created' => 0,
                'listings_updated' => 0,
                'archived_skipped' => 0,
            ];
        }

        // print_r($bm->getAllListingsBi(['min_quantity'=>0]));
        $listings = $bm->getAllListings();

        // Preload: collect unique (reference_id, sku) pairs to batch-load variations (avoids N+1)
        $variationKeys = [];
        foreach ($listings as $country => $lists) {
            foreach ($lists as $list) {
                $variationKeys[trim($list->listing_id) . '|' . trim($list->sku)] = [trim($list->listing_id), trim($list->sku)];
            }
        }
        $variationKeys = array_values($variationKeys);

        // One query: all currencies (avoid any per-listing currency lookups)
        $currencyIdsByCode = Currency_model::pluck('id', 'code')->toArray();

        // Batch load variations by (reference_id, sku) in chunks to avoid N queries
        $variationCache = $this->bulkLoadVariationsByReferenceAndSku($variationKeys);

        // Preload existing listings for (country, variation_id, marketplace_id=1) in one query
        $countryKeys = array_keys($listings);
        $variationIdsForListings = array_unique(array_filter(array_map(function ($v) {
            return $v ? $v->id : null;
        }, $variationCache)));
        $listingCache = [];
        if (!empty($variationIdsForListings)) {
            $existingListings = Listing_model::where('marketplace_id', 1)
                ->whereIn('variation_id', $variationIdsForListings)
                ->whereIn('country', $countryKeys)
                ->get();
            foreach ($existingListings as $l) {
                $listingCache[$l->country . '|' . $l->variation_id] = $l;
            }
        }

        // Accumulate publication_state per variation across all countries (so we can resolve "Online if any")
        $variationPublicationStates = [];

        DB::transaction(function () use ($listings, &$stats, &$variationCache, &$variationPublicationStates, $currencyIdsByCode, $countryKeys, &$listingCache) {
            foreach ($listings as $country => $lists) {
                $stats['countries_processed']++;
                foreach ($lists as $list) {
                    $stats['listings_fetched']++;
                    $key = trim($list->listing_id) . '|' . trim($list->sku);
                    $variation = $variationCache[$key] ?? null;

                    if ($list->publication_state == 4) {
                        $stats['archived_skipped']++;
                        continue;
                    }

                    if (!isset($variationPublicationStates[$key])) {
                        $variationPublicationStates[$key] = [];
                    }
                    $variationPublicationStates[$key][] = (int) $list->publication_state;

                    $isNewVariation = false;
                    if ($variation === null) {
                        $variation = Variation_model::firstOrNew(['reference_id' => trim($list->listing_id)]);
                        $variation->sku = trim($list->sku);
                        $variation->grade = (int) $list->state + 1;
                        if ((int) $list->state == 9) {
                            $variation->grade = 1;
                        }
                        $variation->status = 1;
                        $isNewVariation = !$variation->exists;
                        $variationCache[$key] = $variation;
                        echo $list->listing_id . " ";
                    }

                    if ($variation->name == null) {
                        $variation->name = $list->title;
                    }
                    if ($variation->reference_uuid == null) {
                        $variation->reference_uuid = $list->id;
                        echo $list->id . " ";
                    }

                    $variationIsDirty = $variation->isDirty();
                    if ($isNewVariation || $variationIsDirty) {
                        $variation->save();
                        if ($isNewVariation) {
                            $stats['variations_created']++;
                        } elseif ($variationIsDirty) {
                            $stats['variations_updated']++;
                        }
                    }

                    $curr = $list->price->currency ?? $list->currency;
                    $currencyId = $currencyIdsByCode[$curr] ?? null;

                    if ($variation !== null) {
                        $listingKey = $country . '|' . $variation->id;
                        $listing = $listingCache[$listingKey] ?? null;
                        if ($listing === null) {
                            $listing = Listing_model::firstOrNew(['country' => $country, 'variation_id' => $variation->id, 'marketplace_id' => 1]);
                            $listingCache[$listingKey] = $listing;
                        }
                        $isNewListing = !$listing->exists;

                        $listing->max_price = $list->max_price;
                        $listing->min_price = $list->min_price;
                        $variation->listed_stock = $list->quantity;
                        $listing->price = $list->price;
                        $listing->currency_id = $currencyId;
                        if ($listing->name == null) {
                            $listing->name = $list->title;
                        }
                        $listing->reference_uuid = $list->id;
                        $listing->save();

                        if ($isNewListing) {
                            $stats['listings_created']++;
                        } elseif ($listing->wasChanged()) {
                            $stats['listings_updated']++;
                        }

                        if ($variation->reference_uuid == null) {
                            $variation->reference_uuid = $list->id;
                            $variation->save();
                        }
                    }
                }
            }
        });

        // Resolve variation state across all countries: "Online" if any listing is Online (2)
        foreach ($variationPublicationStates as $key => $states) {
            $variation = $variationCache[$key] ?? null;
            if ($variation === null || empty($states)) {
                continue;
            }
            $resolvedState = $this->resolvePublicationState($states);
            if ($variation->state != $resolvedState) {
                $variation->state = $resolvedState;
                $variation->save();
                $stats['variations_updated']++;
                echo $resolvedState . " ";
            }
        }
    }

    /**
     * Load variations by (reference_id, sku) pairs in batch to avoid N+1 queries.
     *
     * @param array<int, array{0: string, 1: string}> $pairs List of [reference_id, sku]
     * @return array<string, Variation_model|null> Map of "reference_id|sku" => model or null
     */
    private function bulkLoadVariationsByReferenceAndSku(array $pairs): array
    {
        $cache = [];
        $chunkSize = 500;
        foreach (array_chunk($pairs, $chunkSize) as $chunk) {
            $query = Variation_model::query();
            foreach ($chunk as $p) {
                $query->orWhere(function ($q) use ($p) {
                    $q->where('reference_id', $p[0])->where('sku', $p[1]);
                });
            }
            $variations = $query->get();
            foreach ($variations as $v) {
                $cache[trim($v->reference_id) . '|' . trim($v->sku)] = $v;
            }
            foreach ($chunk as $p) {
                $key = $p[0] . '|' . $p[1];
                if (!array_key_exists($key, $cache)) {
                    $cache[$key] = null;
                }
            }
        }
        return $cache;
    }

    /**
     * Load variations by SKU in batch.
     *
     * @param string[] $skus
     * @return array<string, Variation_model|null> Map of sku => model or null
     */
    private function bulkLoadVariationsBySku(array $skus): array
    {
        $cache = [];
        $skus = array_unique(array_map('trim', $skus));
        foreach (array_chunk($skus, 500) as $chunk) {
            $variations = Variation_model::whereIn('sku', $chunk)->get();
            foreach ($variations as $v) {
                $cache[$v->sku] = $v;
            }
            foreach ($chunk as $sku) {
                if (!array_key_exists($sku, $cache)) {
                    $cache[$sku] = null;
                }
            }
        }
        return $cache;
    }

    /**
     * Resolve a single publication_state from multiple Backmarket listings (e.g. one per country).
     * Prefer "Online" (2) if any listing is Online, so the app shows correct status when ad runs in any market.
     *
     * @param int[] $states List of publication_state values (0=missing, 1=pending, 2=online, 3=offline)
     * @return int
     */
    private function resolvePublicationState(array $states): int
    {
        $states = array_map('intval', $states);
        if (in_array(2, $states, true)) {
            return 2; // Online ÔÇö ad is running in at least one country
        }
        if (in_array(3, $states, true)) {
            return 3; // Offline
        }
        if (in_array(1, $states, true)) {
            return 1; // Pending validation
        }
        if (in_array(0, $states, true)) {
            return 0; // Missing price or comment
        }
        return (int) ($states[0] ?? 0);
    }

    public function get_listingsBi(&$stats = null){
        $bm = new BackMarketAPIController();

        // Initialize stats if not provided
        if ($stats === null) {
            $stats = [
                'countries_processed' => 0,
                'listings_fetched' => 0,
                'variations_updated' => 0,
                'listings_created' => 0,
                'listings_updated' => 0,
                'variations_not_found' => 0,
            ];
        }

        // print_r($bm->getAllListingsBi(['min_quantity'=>0]));
        $listings = $bm->getAllListingsBi();

        // Preload: collect unique SKUs to batch-load variations (avoids N+1)
        $skus = [];
        foreach ($listings as $lists) {
            foreach ($lists as $list) {
                $skus[] = trim($list->sku);
            }
        }
        $skus = array_unique(array_filter($skus));

        $currencyIdsByCode = Currency_model::pluck('id', 'code')->toArray();
        $variationCacheBySku = $this->bulkLoadVariationsBySku($skus);

        // Preload existing listings for (country, variation_id, marketplace_id=1)
        $countryKeys = array_keys($listings);
        $variationIdsForListings = array_unique(array_filter(array_map(function ($v) {
            return $v ? $v->id : null;
        }, $variationCacheBySku)));
        $listingCache = [];
        if (!empty($variationIdsForListings)) {
            $existingListings = Listing_model::where('marketplace_id', 1)
                ->whereIn('variation_id', $variationIdsForListings)
                ->whereIn('country', $countryKeys)
                ->get();
            foreach ($existingListings as $l) {
                $listingCache[$l->country . '|' . $l->variation_id] = $l;
            }
        }

        foreach ($listings as $country => $lists) {
            $stats['countries_processed']++;
            foreach ($lists as $list) {
                $stats['listings_fetched']++;
                $variation = $variationCacheBySku[$list->sku] ?? null;
                $currencyId = $currencyIdsByCode[$list->currency] ?? null;

                if ($variation === null) {
                    $stats['variations_not_found']++;
                    echo $list->sku . " ";
                } else {
                    $listingKey = $country . '|' . $variation->id;
                    $listing = $listingCache[$listingKey] ?? null;
                    if ($listing === null) {
                        $listing = Listing_model::firstOrNew(['country' => $country, 'variation_id' => $variation->id, 'marketplace_id' => 1]);
                        $listingCache[$listingKey] = $listing;
                    }
                    $isNewListing = !$listing->exists;

                    $variation->listed_stock = $list->quantity;
                    $variationIsDirty = $variation->isDirty();

                    $listing->price = $list->price;
                    $listing->buybox = $list->same_merchant_winner;
                    $listing->buybox_price = $list->price_for_buybox;
                    $listing->currency_id = $currencyId;
                    $listing->save();

                    if ($isNewListing) {
                        $stats['listings_created']++;
                    } elseif ($listing->wasChanged()) {
                        $stats['listings_updated']++;
                    }

                    if ($variationIsDirty) {
                        $variation->save();
                        $stats['variations_updated']++;
                    }
                }
            }
        }
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

            // Log::info("Refurbed: Fetched all offers", ['total' => $totalOffers]);
            echo "Total offers fetched: {".count($offers)."}\n";

            if (empty($offers)) {
                // Log::info("Refurbed: No offers found");
                echo "No offers found\n";
                return;
            }
            echo "Processing offers...\n";

            $countryMap = Country_model::pluck('id', 'code')->toArray();
            if (empty($countryMap)) {
                echo "No countries configured for Refurbed listings\n";
                return;
            }

            // Preload: all SKUs from offers to avoid N+1 variation lookups
            $refurbedSkus = [];
            foreach ($offers as $offer) {
                $sku = $offer['sku'] ?? $offer['merchant_sku'] ?? null;
                if (!empty($sku)) {
                    if (ctype_digit($sku) && strlen($sku) < 4) {
                        $sku = "00" . $sku;
                    }
                    $refurbedSkus[] = trim($sku);
                }
            }
            $refurbedSkus = array_unique(array_filter($refurbedSkus));

            $variationBySku = $this->bulkLoadVariationsBySku($refurbedSkus);
            $currencyIdsByCode = Currency_model::pluck('id', 'code')->toArray();
            $currencyCodeById = array_flip($currencyIdsByCode);

            $offersByCountry = [];
            $processedListings = 0;
            $pendingPriceUpdates = [];

            foreach ($offers as $offer) {
                    try {
                        $offerId = $offer['id'] ?? $offer['offer_id'] ?? null;
                        $sku = $offer['sku'] ?? $offer['merchant_sku'] ?? null;
                        $productId = $offer['product_id'] ?? null;
                        $state = $offer['state'] ?? 'ACTIVE';

                        $priceAmount = $offer['price']['amount'] ?? $offer['price'] ?? null;
                        $priceCurrency = $offer['price']['currency'] ?? $offer['currency'] ?? 'EUR';

                        $quantity = $offer['quantity'] ?? $offer['stock'] ?? 0;
                        $title = $offer['title'] ?? $offer['product_title'] ?? null;

                        $countryCode = $offer['country'] ?? $offer['region'] ?? 'DE';

                        if (empty($sku)) {
                            continue;
                        }
                        if (ctype_digit($sku) && strlen($sku) < 4) {
                            $sku = "00" . $sku;
                        }
                        $variation = $variationBySku[trim($sku)] ?? null;

                        if ($variation == null) {
                            continue;
                        }

                        $currencyId = $currencyIdsByCode[$priceCurrency] ?? null;
                        if (!$currencyId) {
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
                            'currency_id' => $currencyId,
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
                        Log::channel('functions_thirty')->error("Refurbed: Error processing offer", [
                            'offer_id' => $offerId ?? 'unknown',
                            'error' => $e->getMessage()
                        ]);
                        continue;
                    }
                }

            // Preload BM benchmarks for all variation_ids we will use (one query instead of N)
            $refurbedVariationIds = [];
            foreach ($offersByCountry as $entries) {
                foreach ($entries as $entry) {
                    $refurbedVariationIds[$entry['offer']['variation_id']] = true;
                }
            }
            $refurbedVariationIds = array_keys($refurbedVariationIds);
            $this->bmBenchmarkCache = array_merge($this->bmBenchmarkCache, $this->bulkLoadBackMarketBenchmarks($refurbedVariationIds));

            // Preload existing Refurbed listings (marketplace_id=4) to avoid N firstOrNew SELECTs
            $refurbedListingCache = [];
            if (!empty($refurbedVariationIds)) {
                $existingRefurbed = Listing_model::where('marketplace_id', $marketplaceId)
                    ->whereIn('variation_id', $refurbedVariationIds)
                    ->whereIn('country', array_values($countryMap))
                    ->get();
                foreach ($existingRefurbed as $l) {
                    $refurbedListingCache[$l->country . '|' . $l->variation_id] = $l;
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

                    $listingKey = $countryId . '|' . $offerMeta['variation_id'];
                    $listing = $refurbedListingCache[$listingKey] ?? null;
                    if ($listing === null) {
                        $listing = Listing_model::firstOrNew([
                            'country' => $countryId,
                            'variation_id' => $offerMeta['variation_id'],
                            'marketplace_id' => $marketplaceId
                        ]);
                        $refurbedListingCache[$listingKey] = $listing;
                    }

                    $currencyId = $offerMeta['currency_id'];
                    if (!empty($marketPriceData['currency'])) {
                        $currencyId = $currencyIdsByCode[$marketPriceData['currency']] ?? $currencyId;
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
                            $currencyId = $currencyIdsByCode[$resolvedBuybox['currency']] ?? null;
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
                        $currencyCode = $marketPriceData['currency'] ?? ($marketPriceData['price']['currency'] ?? ($currencyCodeById[$listing->currency_id] ?? null));
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

            Log::channel('functions_thirty')->info("Refurbed: Completed listing sync", [
                'total_listings' => $processedListings,
                'total_offers' => $totalOffers,
                'countries_processed' => $countryCount,
            ]);
            echo "Refurbed sync complete: {$processedListings} listings processed ({$totalOffers} offers) across {$countryCount} countries\n";

        } catch (\Exception $e) {
            Log::channel('functions_thirty')->error("Refurbed: Fatal error in get_refurbed_listings", [
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

    /**
     * Load BackMarket benchmark (max price, max min_price) for many variations in one query.
     *
     * @param int[] $variationIds
     * @return array<int, array{max_price: ?float, max_min_price: ?float}>
     */
    private function bulkLoadBackMarketBenchmarks(array $variationIds): array
    {
        $result = [];
        foreach ($variationIds as $vid) {
            $result[$vid] = ['max_price' => null, 'max_min_price' => null];
        }

        $countryIds = $this->getBenchmarkCountryIds();
        if (empty($countryIds) || empty($variationIds)) {
            return $result;
        }

        $listings = Listing_model::where('marketplace_id', 1)
            ->whereIn('variation_id', $variationIds)
            ->whereIn('country', $countryIds)
            ->get(['variation_id', 'price', 'min_price']);

        foreach ($listings as $listing) {
            $vid = $listing->variation_id;
            if (!isset($result[$vid])) {
                continue;
            }
            if ($listing->price !== null) {
                $result[$vid]['max_price'] = $result[$vid]['max_price'] === null
                    ? $listing->price
                    : max($result[$vid]['max_price'], $listing->price);
            }
            $minValue = $listing->min_price ?? $listing->price;
            if ($minValue !== null) {
                $result[$vid]['max_min_price'] = $result[$vid]['max_min_price'] === null
                    ? $minValue
                    : max($result[$vid]['max_min_price'], $minValue);
            }
        }

        foreach ($result as $vid => $data) {
            if ($result[$vid]['max_min_price'] === null && $result[$vid]['max_price'] !== null) {
                $result[$vid]['max_min_price'] = $result[$vid]['max_price'];
            }
        }

        return $result;
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

        // Check if local sync mode is enabled - prevent live data updates to Refurbed
        $syncDataInLocal = env('SYNC_DATA_IN_LOCAL', false);
        
        if ($syncDataInLocal) {
            // Skip live API update when in local testing mode
            // Log through SlackLogService to named log file
            // SlackLogService::post(
            //     'listing_sync', 
            //     'info', 
            //     "FunctionsThirty: Skipping Refurbed price update API call (SYNC_DATA_IN_LOCAL=true) - SKU: {$sku}",
            //     [
            //         'sku' => $sku,
            //         'market_prices' => array_keys($setMarketPrices),
            //         'would_update' => true,
            //         'command' => 'functions:thirty',
            //         'local_mode' => true
            //     ]
            // );
            // $this->info("ÔÜá´©Å  Local Mode: Skipping Refurbed price update for SKU {$sku}");
            return;
        }

        try {
            $refurbed->updateOffer([
                'sku' => $sku,
            ], [
                'set_market_prices' => array_values($setMarketPrices),
            ]);
        } catch (\Throwable $e) {
            Log::channel('functions_thirty')->error('Refurbed: Failed to push price update', [
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
