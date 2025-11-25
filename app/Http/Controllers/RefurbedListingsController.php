<?php

namespace App\Http\Controllers;

use App\Console\Commands\FunctionsThirty;
use App\Models\Listing_model;
use App\Models\Variation_model;
use App\Models\Country_model;
use App\Models\Currency_model;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RefurbedListingsController extends Controller
{
    protected RefurbedAPIController $refurbed;

    public function __construct(RefurbedAPIController $refurbed)
    {
        $this->refurbed = $refurbed;
    }

    public function test(Request $request): JsonResponse
    {
        $perPage = $this->clampPageSize((int) $request->input('per_page', 50));

        // Get all listings without filtering by state for testing
        $filter = [];

        // Allow optional state filtering if provided
        if ($request->has('state')) {
            $states = $this->normalizeList($request->input('state'));
            if (!empty($states)) {
                $filter['state'] = ['any_of' => $states];
            }
        }

        $pagination = $this->buildPagination($perPage, $request->input('page_token'));
        $sort = $this->buildSort(
            $request->input('sort_by'),
            $request->input('sort_direction', 'ASC')
        );

        $payload = $this->refurbed->listOffers($filter, $pagination, $sort);

        return response()->json([
            'request' => [
                'filter' => $filter,
                'pagination' => $pagination,
                'sort' => $sort,
            ],
            'response' => $payload,
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $perPage = $this->clampPageSize((int) $request->input('per_page', 50));
        // Note: Refurbed uses different state enum values (OFFER_STATE_ACTIVE, etc.)
        // For now, fetch all offers without state filter
        $states = $this->normalizeList($request->input('state'), []);

        $filter = [];
        if (!empty($states)) {
            $filter['state'] = ['any_of' => $states];
        }

        $pagination = $this->buildPagination($perPage, $request->input('page_token'));
        $sort = $this->buildSort(
            $request->input('sort_by'),
            $request->input('sort_direction', 'ASC')
        );

        $payload = $this->refurbed->listOffers($filter, $pagination, $sort);

        return response()->json([
            'filters' => [
                'states' => $states,
                'page_size' => $pagination['page_size'] ?? $perPage,
            ],
            'data' => $payload,
        ]);
    }

    private function normalizeList(mixed $value, array $default = []): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        if (! is_array($value)) {
            $value = [];
        }

        $value = array_values(array_filter($value));

        return empty($value) ? $default : $value;
    }

    private function clampPageSize(int $perPage): int
    {
        return max(1, min($perPage, 200));
    }

    private function buildPagination(int $perPage, ?string $pageToken): array
    {
        return array_filter([
            'page_size' => $perPage,
            'page_token' => $pageToken,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function buildSort(?string $field, string $direction): array
    {
        if (! $field) {
            return [];
        }

        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        return [
            'order_by' => $field,
            'direction' => $direction,
        ];
    }

    /**
     * Test endpoint to zero out all Refurbed listed stock
     */
    public function zeroStock()
    {
        try {
            set_time_limit(300); // 5 minutes

            $updated = 0;
            $failed = 0;
            $errors = [];

            // Get ALL offers from Refurbed API (with automatic pagination), sorted by stock descending, stock > 0
            $filter = [
                'stock' => ['gt' => 0],
            ];

            $targetSkus = null;
            $skuFilter = request('sku');
            if ($skuFilter) {
                $parsedSkus = array_values(array_filter(array_map('trim', explode(',', $skuFilter))));
                if (! empty($parsedSkus)) {
                    $targetSkus = array_fill_keys($parsedSkus, false);
                    $filter['sku'] = ['any_of' => array_keys($targetSkus)];
                }
            }

            $sort = [
                'order' => 'DESC',
                'by' => 'STOCK',
            ];

            $pageToken = null;
            $pageCount = 0;
            $maxPages = 250;
            $hasMore = true;
                            } catch (\Throwable $e) {

            while ($hasMore && $pageCount < $maxPages) {
                $pagination = array_filter([
                    'page_size' => 100,
                    'page_token' => $pageToken,
                ]);

                $response = $this->refurbed->listOffers($filter, $pagination, $sort);
                $offers = $response['offers'] ?? [];

                if (empty($offers)) {
                    break;
                }

                foreach ($offers as $offer) {
                    $sku = $offer['sku'] ?? null;
                    $currentStock = (int) ($offer['stock'] ?? 0);

                    if (! $sku) {
                        continue;
                    }

                    // Safety guard in case the API still returns items with zero stock
                    if ($currentStock <= 0) {
                        continue;
                    }

                    if ($targetSkus !== null) {
                        if (! array_key_exists($sku, $targetSkus)) {
                            continue;
                        }

                        if ($targetSkus[$sku] === true) {
                            continue;
                        }
                    }

                    $identifier = ['sku' => $sku];
                    $updates = ['stock' => 0];
                    $attempt = 1;
                    $maxAttempts = 5;
                    $updateSucceeded = false;
                    $lastException = null;

                    while ($attempt <= $maxAttempts && ! $updateSucceeded) {
                        try {
                            $this->refurbed->updateOffer($identifier, $updates);
                            $updateSucceeded = true;
                            $updated++;

                            if (! $bulkModeActive && ($updated + $failed) > 10) {
                                $bulkModeActive = true;
                            }

                            if ($targetSkus !== null) {
                                $targetSkus[$sku] = true;

                                // Stop as soon as we processed every requested SKU
                                if (! in_array(false, $targetSkus, true)) {
                                    $hasMore = false;
                                }
                            }

                            $this->sleepAfterSuccess($bulkModeActive);

                        } catch (\Throwable $e) {
                            $lastException = $e;

                            if ($this->isRateLimitException($e) && $attempt < $maxAttempts) {
                                $this->sleepAfterRateLimit($attempt);
                                $attempt++;
                                continue;
                            }

                            break;
                        }
                    }

                    if (! $updateSucceeded) {
                        $failed++;
                        $errors[] = [
                            'sku' => $sku,
                            'error' => $lastException?->getMessage() ?? 'Unknown error',
                        ];

                        if (! $bulkModeActive && ($updated + $failed) > 10) {
                            $bulkModeActive = true;
                        }

                        if ($bulkModeActive) {
                            usleep(2000000); // 2 second delay after error
                        }
                    }

                    if ($targetSkus !== null && ! $hasMore) {
                        break;
                    }
                }

                $lastOffer = end($offers);
                $pageToken = $lastOffer['id'] ?? null;
                    if ($hasMore) {
                        $hasMore = $response['has_more'] ?? false;
                    }
                $pageCount++;
            }

            if ($updated === 0 && $failed === 0) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No Refurbed offers found',
                    'updated' => 0,
                ]);
            }

            $totalProcessed = $updated + $failed;

            if (! empty($errors)) {
                Log::error('Refurbed: Zero stock operation had failures', [
                    'total_failed' => $failed,
                    'errors' => $errors,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Stock zeroed for {$updated} listings",
                'updated' => $updated,
                'failed' => $failed,
                'total' => $totalProcessed,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            Log::error('Refurbed: Zero stock operation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to zero stock: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manually trigger Refurbed listing sync
     */
    public function syncListings(): JsonResponse
    {
        try {
            set_time_limit(300); // 5 minutes

            $command = new FunctionsThirty();

            // Capture output
            ob_start();
            $command->get_refurbed_listings();
            $output = ob_get_clean();

            return response()->json([
                'status' => 'success',
                'message' => 'Refurbed listing sync completed',
                'output' => $output,
            ]);

        } catch (\Exception $e) {
            Log::error('Refurbed: Manual sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update all Refurbed stock quantities based on system variation->listed_stock
     */
    public function updateStockFromSystem(): JsonResponse
    {
        try {
            set_time_limit(300); // 5 minutes

            $updated = 0;
            $failed = 0;
            $skipped = 0;
            $errors = [];
            $marketplaceId = 4;
            $syncedListings = [];

            $variationQuery = Variation_model::query()
                ->whereNotNull('sku')
                ->where('listed_stock','>',0)
                ->where('sku','15Pro256White-1');
                // ->whereHas('listings', function ($query) use ($marketplaceId) {
                //     $query->where('marketplace_id', $marketplaceId);
                // });

            $totalVariations = (clone $variationQuery)->count();

            if ($totalVariations === 0) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No Refurbed variations found',
                    'updated' => 0,
                ]);
            }

            // Only apply aggressive rate limiting for bulk operations (more than 10 items)
            $isBulkOperation = $totalVariations > 10;

            $variationQuery->chunkById(100, function ($variations) use (&$updated, &$failed, &$skipped, &$errors, &$syncedListings, $isBulkOperation, $marketplaceId) {
                foreach ($variations as $variation) {
                    $sku = trim($variation->sku ?? '');

                    if ($sku === '') {
                        $skipped++;
                        continue;
                    }

                    try {
                        $this->ensureRefurbedListingExists($variation, $marketplaceId);
                        $updated++;
                        $snapshot = $this->snapshotRefurbedListings($variation, $marketplaceId);
                        if (! empty($snapshot)) {
                            $syncedListings[] = $snapshot;
                        }

                        if ($isBulkOperation) {
                            usleep(100000); // 0.1 second delay for bulk updates
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        $errors[] = [
                            'sku' => $variation->sku ?? 'unknown',
                            'error' => $e->getMessage(),
                        ];

                        if ($isBulkOperation) {
                            usleep(200000); // 0.2 second delay after error
                        }
                    }
                }
            });

            // Log consolidated results
            Log::info('Refurbed: Price sync from system completed', [
                'updated' => $updated,
                'failed' => $failed,
                'skipped' => $skipped,
                'total' => $totalVariations,
            ]);

            // Log errors separately if any
            if (!empty($errors)) {
                Log::error('Refurbed: Price sync had failures', [
                    'total_failed' => $failed,
                    'errors' => $errors,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Prices synced for {$updated} listings based on system data",
                'updated' => $updated,
                'failed' => $failed,
                'skipped' => $skipped,
                'total' => $totalVariations,
                'errors' => $errors,
                'listings' => $syncedListings,
            ]);

        } catch (\Exception $e) {
            Log::error('Refurbed: Price sync from system failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to sync prices: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Force Refurbed offer prices to match the highest Back Market listing price.
     */
    public function updatePricesFromBackMarket(Request $request): JsonResponse
    {
        try {
            set_time_limit(300);

            $bmMarketplaceId = 1;
            $refurbedMarketplaceId = 4;

            $updated = 0;
            $failed = 0;
            $skipped = 0;
            $errors = [];
            $syncedListings = [];

            $skuFilter = $this->normalizeList($request->input('sku'));

            $variationQuery = Variation_model::query()
                ->whereNotNull('sku')
                ->whereHas('listings', function ($query) use ($bmMarketplaceId) {
                    $query->where('marketplace_id', $bmMarketplaceId)
                        ->whereNotNull('price');
                })
                ->whereHas('listings', function ($query) use ($refurbedMarketplaceId) {
                    $query->where('marketplace_id', $refurbedMarketplaceId);
                });

            if (! empty($skuFilter)) {
                $variationQuery->whereIn('sku', $skuFilter);
            }

            $totalVariations = (clone $variationQuery)->count();

            if ($totalVariations === 0) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No overlapping Back Market and Refurbed listings found',
                    'updated' => 0,
                ]);
            }

            $isBulkOperation = $totalVariations > 10;

            $variationQuery->chunkById(100, function ($variations) use (&$updated, &$failed, &$skipped, &$errors, &$syncedListings, $refurbedMarketplaceId, $isBulkOperation) {
                foreach ($variations as $variation) {
                    $benchmark = $this->getBackMarketBenchmarkPrices($variation);
                    $bmPrice = $benchmark['max_price'];
                    $bmMinPrice = $benchmark['max_min_price'];

                    if ($bmPrice === null) {
                        $skipped++;
                        continue;
                    }

                    $bmPrice = (float) $bmPrice;
                    $bmMinPrice = $bmMinPrice !== null ? (float) $bmMinPrice : null;

                    try {
                        $refurbedListings = Listing_model::where('variation_id', $variation->id)
                            ->where('marketplace_id', $refurbedMarketplaceId)
                            ->get();

                        if ($refurbedListings->isEmpty()) {
                            $this->ensureRefurbedListingExists($variation, $refurbedMarketplaceId);
                            $refurbedListings = Listing_model::where('variation_id', $variation->id)
                                ->where('marketplace_id', $refurbedMarketplaceId)
                                ->get();
                        }

                        if ($refurbedListings->isEmpty()) {
                            $skipped++;
                            continue;
                        }

                        $marketPayloads = [];

                        foreach ($refurbedListings as $listing) {
                            $listing->price = $bmPrice;
                            if ($bmMinPrice !== null) {
                                $listing->min_price = $bmMinPrice;
                            }
                            $listing->save();

                            $marketCode = $this->resolveCountryCodeById($listing->country);
                            $currencyCode = $this->resolveCurrencyCodeById($listing->currency_id);

                            if ($marketCode && $currencyCode) {
                                $payload = $this->buildMarketPricePayload($marketCode, $currencyCode, $listing);
                                if ($payload) {
                                    $marketPayloads[$marketCode] = $payload;
                                }
                            }
                        }

                        if (empty($marketPayloads)) {
                            $skipped++;
                            continue;
                        }

                        $stockQuantity = $this->resolveStockQuantity($variation);

                        $apiResult = $this->pushRefurbedPriceUpdates(
                            $variation->sku,
                            array_values($marketPayloads),
                            $bmPrice,
                            $bmMinPrice,
                            $stockQuantity
                        );

                        $syncedListings[] = [
                            'sku' => $variation->sku,
                            'price' => $this->roundPriceValue($bmPrice),
                            'min_price' => $bmMinPrice !== null ? $this->roundPriceValue($bmMinPrice) : null,
                            'stock' => $stockQuantity,
                            'markets' => array_keys($marketPayloads),
                            'api_result' => $apiResult,
                        ];

                        $updated++;

                        if ($isBulkOperation) {
                            usleep(100000); // 0.1 second delay for bulk updates
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        $errors[] = [
                            'sku' => $variation->sku ?? 'unknown',
                            'error' => $e->getMessage(),
                        ];

                        if ($isBulkOperation) {
                            usleep(200000); // 0.2 second delay after failure
                        }
                    }
                }
            });

            Log::info('Refurbed: BM price sync completed', [
                'updated' => $updated,
                'failed' => $failed,
                'skipped' => $skipped,
                'total' => $totalVariations,
            ]);

            if (! empty($errors)) {
                Log::error('Refurbed: BM price sync had failures', [
                    'total_failed' => $failed,
                    'errors' => $errors,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Refurbed prices aligned to Back Market max price for {$updated} variations",
                'updated' => $updated,
                'failed' => $failed,
                'skipped' => $skipped,
                'total' => $totalVariations,
                'errors' => $errors,
                'listings' => $syncedListings,
            ]);

        } catch (\Exception $e) {
            Log::error('Refurbed: BM price sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update Refurbed prices from Back Market benchmark: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function ensureRefurbedListingExists(Variation_model $variation, int $marketplaceId): void
    {
        $referenceListing = $variation->listings()->first();
        $offerSnapshot = $this->fetchOfferSnapshot($variation->sku);
        $currencyId = $referenceListing->currency_id ?? null;
        $countryId = $referenceListing->country ?? null;
        $referenceUuid = $variation->reference_uuid;
        $name = $variation->name;
        $marketEntries = [];
        $referencePrice = null;
        $referenceMinPrice = null;
        $offerMaxPrice = null;
        $fallbackCode = null;
        $fallbackPrice = null;
        $fallbackCurrency = null;
        $fallbackPriceLimit = null;
        $fallbackMinPriceLimit = null;

        if ($offerSnapshot) {
            $currencyId = $this->resolveCurrencyIdFromOffer($offerSnapshot, $currencyId);
            $referenceUuid = $offerSnapshot['id'] ?? $referenceUuid;
            $name = $name ?? ($offerSnapshot['title'] ?? null);
            $marketEntries = $this->extractMarketEntries($offerSnapshot);
            $referencePrice = $offerSnapshot['reference_price'] ?? null;
            $referenceMinPrice = $offerSnapshot['reference_min_price'] ?? null;
            $offerMaxPrice = $offerSnapshot['max_price'] ?? null;
            $fallbackCode = $offerSnapshot['country'] ?? $offerSnapshot['region'] ?? null;
            $fallbackPrice = $offerSnapshot['price']['amount'] ?? $offerSnapshot['price'] ?? null;
            $fallbackCurrency = $offerSnapshot['price']['currency'] ?? $offerSnapshot['currency'] ?? null;
            $fallbackPriceLimit = $offerSnapshot['price_limit'] ?? null;
            $fallbackMinPriceLimit = $offerSnapshot['min_price_limit'] ?? null;
        }

        if (empty($marketEntries)) {
            $marketEntries = [[
                'market_code' => $fallbackCode,
                'price' => $fallbackPrice,
                'currency' => $fallbackCurrency,
                'min_price' => null,
                'max_price' => null,
                'price_limit' => $fallbackPriceLimit,
                'min_price_limit' => $fallbackMinPriceLimit,
            ]];
        }

        $buyboxEntries = $offerSnapshot ? $this->extractBuyboxEntries($offerSnapshot) : [];
        $pendingMarketPriceUpdates = [];
        $bmBenchmark = $this->getBackMarketBenchmarkPrices($variation);
        $bmMaxPrice = $bmBenchmark['max_price'];
        $bmMaxMinPrice = $bmBenchmark['max_min_price'];

        foreach ($marketEntries as $entry) {
            $entryCountryId = $this->resolveCountryId($entry['market_code'] ?? null, $countryId);
            if (! $entryCountryId) {
                $entryCountryId = Country_model::query()->orderBy('id')->value('id');
            }

            if (! $entryCountryId) {
                Log::warning('Refurbed: Unable to create listing without country', [
                    'variation_id' => $variation->id,
                ]);
                continue;
            }

            $entryCurrencyId = $currencyId;
            if (empty($entryCurrencyId) && !empty($entry['currency'])) {
                $entryCurrencyId = $this->resolveCurrencyIdByCode($entry['currency']);
            }

            $listing = Listing_model::firstOrNew([
                'country' => $entryCountryId,
                'marketplace_id' => $marketplaceId,
                'variation_id' => $variation->id,
            ]);

            if ($entryCurrencyId) {
                $listing->currency_id = $entryCurrencyId;
            }

            if ($name) {
                $listing->name = $name;
            }

            if ($referenceUuid) {
                $listing->reference_uuid = $referenceUuid;
            }

            $listing->price = $referencePrice ?? $entry['price'] ?? $listing->price;
            $listing->min_price = $referenceMinPrice ?? $entry['min_price'] ?? $listing->min_price;
            if (!empty($entry['max_price']) || $offerMaxPrice !== null) {
                $listing->max_price = $entry['max_price'] ?? $offerMaxPrice;
            }
            if (!empty($entry['price_limit'])) {
                $listing->price_limit = $entry['price_limit'];
            }
            if (!empty($entry['min_price_limit'])) {
                $listing->min_price_limit = $entry['min_price_limit'];
            }

            $entryBuybox = $this->normalizeBuyboxEntryData($entry);
            $resolvedBuybox = $entryBuybox ?: $this->resolveBuyboxForMarket($buyboxEntries, $entry['market_code'] ?? null);
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

                if (empty($entryCurrencyId) && !empty($resolvedBuybox['currency'])) {
                    $entryCurrencyId = $this->resolveCurrencyIdByCode($resolvedBuybox['currency']);
                    if ($entryCurrencyId) {
                        $listing->currency_id = $entryCurrencyId;
                    }
                }
            }

            if ($bmMaxPrice !== null) {
                $listing->price = $listing->price !== null ? max($listing->price, $bmMaxPrice) : $bmMaxPrice;
            }

            if ($bmMaxMinPrice !== null) {
                $listing->min_price = $listing->min_price !== null ? max($listing->min_price, $bmMaxMinPrice) : $bmMaxMinPrice;
            }

            if (! $listing->exists) {
                $listing->status = 1;
            }

            $listing->save();

            $marketCode = $entry['market_code'] ?? $this->resolveCountryCodeById($entryCountryId);
            $currencyCode = $entry['currency'] ?? $this->resolveCurrencyCodeById($listing->currency_id);
            if ($marketCode && $currencyCode && $this->shouldPushMarketPrice($entry, $listing, $referencePrice, $referenceMinPrice)) {
                $payload = $this->buildMarketPricePayload($marketCode, $currencyCode, $listing);
                if ($payload) {
                    $pendingMarketPriceUpdates[$payload['market_code']] = $payload;
                }
            }
        }

        if (!empty($pendingMarketPriceUpdates) && !empty($variation->sku)) {
            $this->pushRefurbedPriceUpdates(
                $variation->sku,
                array_values($pendingMarketPriceUpdates),
                $referencePrice,
                $referenceMinPrice,
                $this->resolveStockQuantity($variation)
            );
        }
    }

    private function snapshotRefurbedListings(Variation_model $variation, int $marketplaceId): array
    {
        $listings = Listing_model::where('variation_id', $variation->id)
            ->where('marketplace_id', $marketplaceId)
            ->get(['country', 'price', 'min_price', 'max_price', 'price_limit', 'min_price_limit']);

        if ($listings->isEmpty()) {
            return [];
        }

        return [
            'sku' => $variation->sku,
            'variation_id' => $variation->id,
            'listings' => $listings->map(function ($listing) {
                return [
                    'country_id' => $listing->country,
                    'country' => $this->resolveCountryCodeById($listing->country),
                    'price' => $this->roundPriceValue($listing->price),
                    'min_price' => $this->roundPriceValue($listing->min_price),
                    'max_price' => $this->roundPriceValue($listing->max_price),
                    'price_limit' => $this->roundPriceValue($listing->price_limit),
                    'min_price_limit' => $this->roundPriceValue($listing->min_price_limit),
                ];
            })->all(),
        ];
    }

    private function getBackMarketBenchmarkPrices(Variation_model $variation): array
    {
        $targetCodes = ['FR', 'ES'];
        static $countryIds = null;
        if ($countryIds === null) {
            $countryIds = Country_model::whereIn('code', $targetCodes)->pluck('id')->all();
        }

        if (empty($countryIds)) {
            return [
                'max_price' => null,
                'max_min_price' => null,
            ];
        }

        $listings = Listing_model::where('variation_id', $variation->id)
            ->where('marketplace_id', 1)
            ->whereIn('country', $countryIds)
            ->get(['country', 'price', 'min_price']);

        $maxPrice = null;
        $maxMinPrice = null;

        foreach ($listings as $listing) {
            if ($listing->price !== null) {
                $maxPrice = $maxPrice === null ? $listing->price : max($maxPrice, $listing->price);
            }

            $minPriceValue = $listing->min_price ?? $listing->price;
            if ($minPriceValue !== null) {
                $maxMinPrice = $maxMinPrice === null ? $minPriceValue : max($maxMinPrice, $minPriceValue);
            }
        }

        return [
            'max_price' => $maxPrice,
            'max_min_price' => $maxMinPrice ?? $maxPrice,
        ];
    }

    private function fetchOfferSnapshot(?string $sku): ?array
    {
        if (empty($sku)) {
            return null;
        }

        try {
            $response = $this->refurbed->getOffer(['sku' => $sku]);
            return $response['offer'] ?? $response ?? null;
                    } catch (\Throwable $e) {
            // Log::warning('Refurbed: Failed to fetch offer snapshot', [
            //     'sku' => $sku,
            //     'error' => $e->getMessage(),
            // ]);

            return null;
        }
    }

    private function resolveCurrencyIdFromOffer(array $offer, ?int $fallback = null): ?int
    {
        $currencyCode = $offer['price']['currency'] ?? $offer['currency'] ?? null;
        $currencyId = $this->resolveCurrencyIdByCode($currencyCode);

        return $currencyId ?? $fallback;
    }

    private function resolveCurrencyIdByCode(?string $currencyCode): ?int
    {
        if ($currencyCode) {
            $currency = Currency_model::where('code', $currencyCode)->first();
            if ($currency) {
                return $currency->id;
            }
        }

        return null;
    }

    private function resolveCountryId(?string $marketCode, ?int $fallback = null): ?int
    {
        if ($marketCode) {
            $country = Country_model::where('code', $marketCode)->first();
            if ($country) {
                return $country->id;
            }
        }

        return $fallback;
    }

    private function extractMarketEntries(array $offer): array
    {
        $entries = [];

        $collectEntry = function (?array $marketPrice) use (&$entries) {
            if (empty($marketPrice) || !is_array($marketPrice)) {
                return;
            }

            $code = $marketPrice['market_code'] ?? null;
            if (! $code) {
                return;
            }

            $entries[$code] = [
                'market_code' => $code,
                'price' => $marketPrice['price']['amount'] ?? $marketPrice['price'] ?? null,
                'currency' => $marketPrice['price']['currency'] ?? $marketPrice['currency'] ?? null,
                'min_price' => $marketPrice['min_price'] ?? null,
                'max_price' => $marketPrice['max_price'] ?? null,
                'price_limit' => $marketPrice['price_limit'] ?? null,
                'min_price_limit' => $marketPrice['min_price_limit'] ?? null,
            ];
        };

        if (! empty($offer['market_price'])) {
            $collectEntry($offer['market_price']);
        }

        foreach (['set_market_prices', 'calculated_market_prices'] as $key) {
            if (empty($offer[$key]) || !is_array($offer[$key])) {
                continue;
            }

            foreach ($offer[$key] as $marketPrice) {
                $collectEntry($marketPrice);
            }
        }

        if (empty($entries)) {
            $countryCode = $offer['country'] ?? $offer['region'] ?? null;
            if ($countryCode) {
                $entries[$countryCode] = [
                    'market_code' => $countryCode,
                    'price' => $offer['price']['amount'] ?? $offer['price'] ?? null,
                    'currency' => $offer['price']['currency'] ?? $offer['currency'] ?? null,
                    'min_price' => $offer['min_price'] ?? null,
                    'max_price' => $offer['max_price'] ?? null,
                    'price_limit' => $offer['price_limit'] ?? null,
                    'min_price_limit' => $offer['min_price_limit'] ?? null,
                ];
            }
        }

        return $entries;
    }

    private function extractBuyboxEntries(array $offer): array
    {
        $entries = [];

        $collectEntry = function (array $candidate, ?string $marketCode = null) use (&$entries) {
            $normalized = $this->normalizeBuyboxEntryData($candidate);
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

        $walker = function ($payload, ?string $marketCode = null) use (&$walker, $collectEntry) {
            if (!is_array($payload)) {
                return;
            }

            $currentMarket = $marketCode ?? $payload['market_code'] ?? $payload['market'] ?? null;

            if ($this->arrayLooksLikeBuyboxEntry($payload)) {
                $collectEntry($payload, $currentMarket);
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

    private function resolveBuyboxForMarket(array $entries, ?string $marketCode): ?array
    {
        if ($marketCode) {
            $code = strtoupper($marketCode);
            if (isset($entries[$code])) {
                return $entries[$code];
            }
        }

        return $entries['*'] ?? null;
    }

    private function normalizeBuyboxEntryData(?array $payload): ?array
    {
        if (empty($payload) || !is_array($payload)) {
            return null;
        }

        $priceToWin = $this->extractPriceAmount($payload['price_to_win'] ?? $payload['price_for_buybox'] ?? $payload['buybox_price'] ?? null);
        $winnerPrice = $this->extractPriceAmount($payload['winner_price'] ?? $payload['buybox_winner_price'] ?? null);
        $hasBuybox = $this->normalizeBooleanValue($payload['same_merchant_winner'] ?? $payload['is_winner'] ?? $payload['wins_buybox'] ?? $payload['has_buybox'] ?? $payload['buybox'] ?? null);
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

    private function arrayLooksLikeBuyboxEntry(array $payload): bool
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

    private function extractPriceAmount($value): ?float
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

    private function normalizeBooleanValue($value): ?int
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

        if (! $currencyId) {
            return null;
        }

        if (! array_key_exists($currencyId, $cache)) {
            $cache[$currencyId] = Currency_model::where('id', $currencyId)->value('code');
        }

        return $cache[$currencyId];
    }

    private function resolveCountryCodeById(?int $countryId): ?string
    {
        static $cache = [];

        if (! $countryId) {
            return null;
        }

        if (! array_key_exists($countryId, $cache)) {
            $cache[$countryId] = Country_model::where('id', $countryId)->value('code');
        }

        return $cache[$countryId];
    }

    private function shouldPushMarketPrice(array $entry, Listing_model $listing, ?float $referencePrice, ?float $referenceMinPrice): bool
    {
        $baselinePrice = $referencePrice ?? $entry['price'] ?? null;
        if ($this->valueChanged($baselinePrice, $listing->price)) {
            return true;
        }

        $baselineMinPrice = $referenceMinPrice ?? $entry['min_price'] ?? null;
        if ($this->valueChanged($baselineMinPrice, $listing->min_price)) {
            return true;
        }

        if ($this->valueChanged($entry['price_limit'] ?? null, $listing->price_limit)) {
            return true;
        }

        if ($this->valueChanged($entry['min_price_limit'] ?? null, $listing->min_price_limit)) {
            return true;
        }

        return false;
    }

    private function buildMarketPricePayload(string $marketCode, string $currencyCode, Listing_model $listing): ?array
    {
        $normalizedMarket = strtoupper(trim($marketCode));
        $normalizedCurrency = strtoupper(trim($currencyCode));

        if ($normalizedMarket === '' || $normalizedCurrency === '') {
            return null;
        }

        $payload = [
            'market_code' => $normalizedMarket,
        ];

        if (($pricePayload = $this->buildMoneyPayload($listing->price, $normalizedCurrency)) !== null) {
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

    private function roundPriceValue(?float $value): ?float
    {
        return $value === null ? null : round($value, 2);
    }

    private function buildMoneyPayload(?float $value, string $currencyCode): ?array
    {
        if ($currencyCode === '') {
            return null;
        }

        $amount = $this->roundPriceValue($value);

        if ($amount === null) {
            return null;
        }

        return [
            'amount' => $amount,
            'currency' => $currencyCode,
        ];
    }

    private function resolveStockQuantity(?Variation_model $variation): ?int
    {
        if (! $variation || $variation->listed_stock === null) {
            return null;
        }

        $quantity = (int) $variation->listed_stock;

        return $quantity < 0 ? 0 : $quantity;
    }

    private function formatPriceString(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return number_format($this->roundPriceValue($value), 2, '.', '');
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

    private function pushRefurbedPriceUpdates(
        string $sku,
        array $setMarketPrices,
        ?float $referencePrice = null,
        ?float $referenceMinPrice = null,
        ?int $stockQuantity = null
    ): array
    {
        if (empty($setMarketPrices) && $stockQuantity === null) {
            return [
                'success' => false,
                'error' => 'No market prices or stock values to push',
            ];
        }

        $identifier = ['sku' => $sku];
        $payload = [];

        if (! empty($setMarketPrices)) {
            $payload['set_market_prices'] = array_values($setMarketPrices);
        }

        if (($formattedReference = $this->formatPriceString($referencePrice)) !== null) {
            $payload['reference_price'] = $formattedReference;
        }

        if (($formattedReferenceMin = $this->formatPriceString($referenceMinPrice)) !== null) {
            $payload['reference_min_price'] = $formattedReferenceMin;
        }

        if ($stockQuantity !== null) {
            $payload['stock'] = max(0, $stockQuantity);
        }

        try {
            $response = $this->refurbed->updateOffer($identifier, $payload);

            // Log::info('Refurbed: Pushed market prices', [
            //     'sku' => $sku,
            //     'markets' => array_map(fn ($entry) => $entry['market_code'] ?? 'UNKNOWN', $payload['set_market_prices'] ?? []),
            //     'payload' => $payload['set_market_prices'] ?? [],
            //     'reference_price' => $payload['reference_price'] ?? null,
            //     'reference_min_price' => $payload['reference_min_price'] ?? null,
            //     'stock' => $payload['stock'] ?? null,
            //     'response' => $response,
            // ]);

            return [
                'success' => true,
                'response' => $response,
            ];
        } catch (\Throwable $e) {
            Log::error('Refurbed: Failed to push market prices', [
                'sku' => $sku,
                'markets' => array_map(fn ($entry) => $entry['market_code'] ?? 'UNKNOWN', $payload['set_market_prices'] ?? []),
                'payload' => $payload['set_market_prices'] ?? [],
                'reference_price' => $payload['reference_price'] ?? null,
                'reference_min_price' => $payload['reference_min_price'] ?? null,
                'stock' => $payload['stock'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function sleepAfterSuccess(bool $bulkModeActive): void
    {
        $delayMicroseconds = $bulkModeActive ? 100000 : 250000; // 0.1s vs 250ms
        usleep($delayMicroseconds);
    }

    private function sleepAfterRateLimit(int $attempt): void
    {
        $seconds = min(5, 2 ** ($attempt - 1)); // 1,2,4,5
        usleep((int) ($seconds * 100000));
    }

    private function isRateLimitException(\Throwable $exception): bool
    {
        if ($exception instanceof RequestException && $exception->response) {
            return $exception->response->status() === 429;
        }

        return str_contains(strtolower($exception->getMessage()), 'rate limit');
    }
}
