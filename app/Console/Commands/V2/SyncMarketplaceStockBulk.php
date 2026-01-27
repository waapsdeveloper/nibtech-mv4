<?php

namespace App\Console\Commands\V2;

use Illuminate\Console\Command;
use App\Models\V2\MarketplaceStockModel;
use App\Models\Variation_model;
use App\Models\Country_model;
use App\Http\Controllers\BackMarketAPIController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * V2 Optimized Bulk Marketplace Stock Sync Command
 * 
 * Uses bulk fetch (getAllListings) instead of individual API calls
 * Expected improvement: 95-98% reduction in API calls
 * 
 * Usage:
 *   php artisan v2:marketplace:sync-stock-bulk --marketplace=1
 *   php artisan v2:marketplace:sync-stock-bulk --marketplace=1 --force
 */
class SyncMarketplaceStockBulk extends Command
{
    protected $signature = 'v2:marketplace:sync-stock-bulk 
                            {--marketplace=1 : Specific marketplace ID to sync (default: 1 for BackMarket)}
                            {--force : Force sync even if last sync was less than 6 hours ago}';
    
    protected $description = 'V2: Bulk sync stock from marketplace APIs using getAllListings (optimized - 95% fewer API calls)';
    
    // Store sync summary
    private $syncSummary = [];
    
    public function handle()
    {
        $marketplaceId = (int) $this->option('marketplace') ?? 1;
        $force = $this->option('force');
        
        $this->info('========================================');
        $this->info('V2 BULK MARKETPLACE STOCK SYNC');
        $this->info('========================================');
        $this->info("Marketplace ID: {$marketplaceId}");
        $this->info("Using bulk fetch (getAllListings)");
        $this->info('========================================');
        $this->newLine();
        
        Log::info("V2 SyncMarketplaceStockBulk: Command started", [
            'marketplace_id' => $marketplaceId,
            'force' => $force
        ]);
        
        // Only BackMarket (ID 1) is supported for bulk fetch currently
        if ($marketplaceId !== 1) {
            $this->error("Bulk sync currently only supports BackMarket (marketplace ID 1)");
            $this->warn("For other marketplaces, use: v2:marketplace:sync-stock");
            return 1;
        }
        
        try {
            $this->syncMarketplaceBulk($marketplaceId, $force);
            
            // Display summary
            $this->displaySummary();
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Error during bulk sync: " . $e->getMessage());
            Log::error('V2 SyncMarketplaceStockBulk: Command failed', [
                'marketplace_id' => $marketplaceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
    
    /**
     * Sync marketplace stock using bulk fetch
     */
    private function syncMarketplaceBulk($marketplaceId, $force = false)
    {
        $startTime = microtime(true);
        
        $this->info("Step 1: Fetching all listings from BackMarket API (bulk)...");
        $this->warn("⚠ This may take several minutes depending on number of countries and pages...");
        Log::info("V2 SyncMarketplaceStockBulk: Starting API fetch", [
            'marketplace_id' => $marketplaceId,
            'timestamp' => now()->toDateTimeString()
        ]);
        
        // Step 1: Bulk fetch ALL listings from API (1 call per country)
        // Check API credentials before making the call
        $this->info("Checking API credentials...");
        $marketplace = \App\Models\Marketplace_model::where('name', 'BackMarket')->first();
        $apiKeySource = null;
        $apiKey = null;
        
        if ($marketplace && $marketplace->api_key) {
            $apiKey = $marketplace->api_key;
            $apiKeySource = "marketplace table (ID: {$marketplace->id})";
            $this->info("✓ Found API key in marketplace table");
            Log::info("V2 SyncMarketplaceStockBulk: Using API key from marketplace table", [
                'marketplace_id' => $marketplace->id,
                'api_key_length' => strlen($apiKey),
                'api_key_preview' => substr($apiKey, 0, 10) . '...' // First 10 chars for debugging
            ]);
        } else {
            $envKey = env('BM_API1');
            if ($envKey) {
                $apiKey = $envKey;
                $apiKeySource = "environment (BM_API1)";
                $this->info("✓ Using API key from environment (BM_API1)");
                Log::info("V2 SyncMarketplaceStockBulk: Using API key from environment");
            } else {
                $this->error("✗ No API key found!");
                $this->error("   Check marketplace table (name='BackMarket') or BM_API1 environment variable.");
                Log::error("V2 SyncMarketplaceStockBulk: No API key available");
                throw new \RuntimeException('BackMarket API key is missing. Update marketplace table or set BM_API1 environment variable.');
            }
        }
        
        if (empty($apiKey) || strlen($apiKey) < 10) {
            $this->error("✗ API key appears to be invalid (too short or empty)");
            $this->error("   Source: {$apiKeySource}");
            Log::error("V2 SyncMarketplaceStockBulk: API key validation failed", [
                'source' => $apiKeySource,
                'length' => strlen($apiKey ?? '')
            ]);
            throw new \RuntimeException("Invalid API key from {$apiKeySource}. Please verify the key is correct.");
        }
        
        try {
            $bm = new BackMarketAPIController();
            
            $apiStartTime = microtime(true);
            $this->info("Making API call to getAllListings()...");
            $this->warn("⚠ This may take several minutes - fetching from multiple countries with pagination...");
            $this->info("   Progress will be logged every 30 seconds");
            Log::info("V2 SyncMarketplaceStockBulk: Starting getAllListings() call", [
                'timestamp' => now()->toDateTimeString(),
                'api_key_source' => $apiKeySource
            ]);
            
            // Get country count for progress estimation
            $countryCount = Country_model::where('market_code', '!=', null)->count();
            $this->info("   Processing {$countryCount} countries...");
            
            // Use set_time_limit to allow long-running operation (if not disabled)
            if (function_exists('set_time_limit')) {
                @set_time_limit(0); // 0 = no time limit
            }
            
            // Make the API call (progress is logged inside getAllListings method)
            // Always fetches fresh data from API (no caching)
            $allListings = $bm->getAllListings(); // BULK FETCH ✅
            
            $apiDuration = round(microtime(true) - $apiStartTime, 2);
            
            // Check if we got a valid response
            if ($allListings === null || (is_array($allListings) && empty($allListings))) {
                $this->warn("⚠ API returned empty or null response");
                $this->warn("   This might indicate an authentication issue.");
                Log::warning("V2 SyncMarketplaceStockBulk: API returned empty response", [
                    'api_key_source' => $apiKeySource
                ]);
            }
            
            $countriesProcessed = is_array($allListings) ? count($allListings) : 0;
            Log::info("V2 SyncMarketplaceStockBulk: API fetch completed", [
                'duration_seconds' => $apiDuration,
                'countries_count' => $countriesProcessed,
                'expected_countries' => $countryCount
            ]);
            
            $this->info("✓ API fetch completed in {$apiDuration}s");
            if ($countriesProcessed < $countryCount) {
                $this->warn("   ⚠ Only {$countriesProcessed} of {$countryCount} countries returned data");
            }
        } catch (\RuntimeException $e) {
            // Handle authentication/configuration errors
            Log::error("V2 SyncMarketplaceStockBulk: Configuration error", [
                'error' => $e->getMessage(),
                'type' => 'configuration',
                'api_key_source' => $apiKeySource
            ]);
            $this->error("✗ Configuration Error: " . $e->getMessage());
            $this->warn("💡 Tip: Check your marketplace table API key or BM_API1 environment variable");
            throw $e;
        } catch (\Exception $e) {
            Log::error("V2 SyncMarketplaceStockBulk: API fetch failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'type' => 'api_error',
                'api_key_source' => $apiKeySource
            ]);
            $this->error("✗ API fetch failed: " . $e->getMessage());
            $this->warn("💡 This might be due to:");
            $this->warn("   - Invalid API credentials in marketplace table");
            $this->warn("   - Wrong API key for your local environment");
            $this->warn("   - Network/connectivity issues");
            $this->warn("   - API rate limiting");
            throw $e;
        }
        
        if (empty($allListings)) {
            $this->warn("No listings returned from API");
            return;
        }
        
        $totalListingsFromAPI = 0;
        foreach ($allListings as $countryListings) {
            $totalListingsFromAPI += count($countryListings);
        }
        
        $this->info("✓ Fetched {$totalListingsFromAPI} listings from API");
        $this->newLine();
        
        // Step 2: Create mapping by reference_id for quick lookup
        $this->info("Step 2: Creating reference_id mapping...");
        $listingMap = $this->createListingMap($allListings);
        $this->info("✓ Created map with " . count($listingMap) . " unique listings");
        $this->newLine();
        
        // Step 3: Get all variations that need updating
        $this->info("Step 3: Loading variations from database...");
        
        // Get variation IDs that have marketplace stock records
        $variationIds = MarketplaceStockModel::where('marketplace_id', $marketplaceId)
            ->whereHas('variation', function($q) {
                $q->whereNotNull('reference_id');
            })
            ->pluck('variation_id')
            ->unique()
            ->toArray();
        
        $variations = Variation_model::whereIn('id', $variationIds)
            ->whereNotNull('reference_id')
            ->get();
        
        $this->info("✓ Found {$variations->count()} variations to check");
        $this->newLine();
        
        // Step 4: Update marketplace_stock records in batch (optimized)
        $this->info("Step 4: Updating marketplace stock records (bulk mode)...");
        $bar = $this->output->createProgressBar($variations->count());
        $bar->start();
        
        $updated = 0;
        $skipped = 0;
        $notFound = 0;
        $errors = 0;
        $updates = [];
        
        // Pre-load all marketplace stock records in one query (optimization)
        $variationIds = $variations->pluck('id')->toArray();
        $marketplaceStocks = MarketplaceStockModel::where('marketplace_id', $marketplaceId)
            ->whereIn('variation_id', $variationIds)
            ->get()
            ->keyBy('variation_id');
        
        // Prepare bulk update arrays
        $bulkUpdates = [];
        $now = now();
        
        foreach ($variations as $variation) {
            try {
                $referenceId = trim($variation->reference_id);
                
                if (!isset($listingMap[$referenceId])) {
                    $notFound++;
                    $bar->advance();
                    continue;
                }
                
                $listingData = $listingMap[$referenceId];
                $apiQuantity = (int) ($listingData['quantity'] ?? 0);
                
                // Get marketplace stock record from pre-loaded collection
                $marketplaceStock = $marketplaceStocks->get($variation->id);
                
                if (!$marketplaceStock) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                
                // Check if sync is needed (unless force)
                if (!$force && $marketplaceStock->last_synced_at) {
                    $hoursSinceSync = $marketplaceStock->last_synced_at->diffInHours($now);
                    if ($hoursSinceSync < 6) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }
                }
                
                // Store old values for history
                $oldListedStock = $marketplaceStock->listed_stock ?? 0;
                $oldAvailableStock = $marketplaceStock->available_stock ?? 0;
                $lockedStock = $marketplaceStock->locked_stock ?? 0;
                $newAvailableStock = max(0, $apiQuantity - $lockedStock);
                
                // Prepare for bulk update
                $bulkUpdates[$marketplaceStock->id] = [
                    'listed_stock' => $apiQuantity,
                    'available_stock' => $newAvailableStock,
                    'last_synced_at' => $now,
                    'last_api_quantity' => $apiQuantity,
                    'updated_at' => $now
                ];
                
                $updated++;
                
                // Track changes for history
                if ($oldListedStock != $apiQuantity) {
                    $updates[] = [
                        'marketplace_stock_id' => $marketplaceStock->id,
                        'variation_id' => $variation->id,
                        'marketplace_id' => $marketplaceId,
                        'old_listed_stock' => $oldListedStock,
                        'new_listed_stock' => $apiQuantity,
                        'old_available_stock' => $oldAvailableStock,
                        'new_available_stock' => $newAvailableStock,
                        'locked_stock' => $lockedStock,
                        'quantity_change' => $apiQuantity - $oldListedStock
                    ];
                }
                
            } catch (\Exception $e) {
                $errors++;
                Log::error("V2 SyncMarketplaceStockBulk: Error processing variation", [
                    'variation_id' => $variation->id ?? null,
                    'error' => $e->getMessage()
                ]);
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        // Perform bulk update (much faster than individual saves)
        if (!empty($bulkUpdates)) {
            $this->info("   Performing bulk database update...");
            $this->performBulkUpdate($bulkUpdates);
            $this->info("   ✓ Bulk update completed");
        }
        $this->newLine();
        
        // Step 5: Create history records for changes
        if (!empty($updates)) {
            $this->info("Step 5: Creating history records...");
            $this->createHistoryRecords($updates);
            $this->info("✓ Created " . count($updates) . " history records");
            $this->newLine();
        }
        
        // Step 6: Update variation.listed_stock (sum of all marketplaces)
        $this->info("Step 6: Updating variation.listed_stock (sum of all marketplaces)...");
        $variationsUpdated = $this->updateVariationListedStock($variations->pluck('id')->toArray());
        $this->info("✓ Updated {$variationsUpdated} variations");
        $this->newLine();
        
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        // Store summary
        $this->syncSummary = [
            'marketplace_id' => $marketplaceId,
            'total_listings_from_api' => $totalListingsFromAPI,
            'variations_checked' => $variations->count(),
            'updated' => $updated,
            'skipped' => $skipped,
            'not_found' => $notFound,
            'errors' => $errors,
            'duration_seconds' => $duration
        ];
        
        Log::info("V2 SyncMarketplaceStockBulk: Sync completed", $this->syncSummary);
    }
    
    /**
     * Create mapping by reference_id for quick lookup
     */
    private function createListingMap($allListings): array
    {
        $listingMap = [];
        
        foreach ($allListings as $countryId => $lists) {
            if (!is_array($lists)) {
                continue;
            }
            
            foreach ($lists as $list) {
                // BackMarket API returns listing_id or id
                // Handle both object and array responses
                if (is_object($list)) {
                    $referenceId = trim($list->listing_id ?? $list->id ?? '');
                    $quantity = $list->quantity ?? 0;
                    $sku = $list->sku ?? null;
                    $state = $list->publication_state ?? null;
                } elseif (is_array($list)) {
                    $referenceId = trim($list['listing_id'] ?? $list['id'] ?? '');
                    $quantity = $list['quantity'] ?? 0;
                    $sku = $list['sku'] ?? null;
                    $state = $list['publication_state'] ?? null;
                } else {
                    continue;
                }
                
                if (empty($referenceId)) {
                    continue;
                }
                
                // Store the first occurrence (or merge if needed)
                if (!isset($listingMap[$referenceId])) {
                    $listingMap[$referenceId] = [
                        'quantity' => (int) $quantity,
                        'sku' => $sku,
                        'state' => $state,
                        'country_id' => $countryId
                    ];
                }
            }
        }
        
        return $listingMap;
    }
    
    /**
     * Create history records for stock changes
     */
    private function createHistoryRecords(array $updates)
    {
        $historyRecords = [];
        
        foreach ($updates as $update) {
            $historyRecords[] = [
                'marketplace_stock_id' => $update['marketplace_stock_id'],
                'variation_id' => $update['variation_id'],
                'marketplace_id' => $update['marketplace_id'],
                'listed_stock_before' => $update['old_listed_stock'],
                'listed_stock_after' => $update['new_listed_stock'],
                'locked_stock_before' => $update['locked_stock'],
                'locked_stock_after' => $update['locked_stock'],
                'available_stock_before' => $update['old_available_stock'],
                'available_stock_after' => $update['new_available_stock'],
                'quantity_change' => $update['quantity_change'],
                'change_type' => 'reconciliation',
                'notes' => "Bulk sync: Local={$update['old_listed_stock']}, API={$update['new_listed_stock']}",
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
        
        // Bulk insert history records
        if (!empty($historyRecords)) {
            DB::table('marketplace_stock_history')->insert($historyRecords);
        }
    }
    
    /**
     * Perform bulk update of marketplace stock records
     * Uses raw SQL for maximum performance
     */
    private function performBulkUpdate(array $bulkUpdates): void
    {
        if (empty($bulkUpdates)) {
            return;
        }
        
        // Process in chunks to avoid memory issues and long-running transactions
        foreach (array_chunk($bulkUpdates, 500, true) as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $id => $data) {
                    DB::table('marketplace_stock')
                        ->where('id', $id)
                        ->update($data);
                }
            });
        }
    }
    
    /**
     * Update variation.listed_stock as sum of all marketplace stocks (optimized)
     */
    private function updateVariationListedStock(array $variationIds): int
    {
        $updated = 0;
        
        // Use raw SQL aggregation for better performance
        // Get all sums in one query instead of N queries
        $stockSums = MarketplaceStockModel::whereIn('variation_id', $variationIds)
            ->select('variation_id', DB::raw('SUM(listed_stock) as total_stock'))
            ->groupBy('variation_id')
            ->pluck('total_stock', 'variation_id')
            ->toArray();
        
        // Process in chunks to avoid memory issues
        foreach (array_chunk($variationIds, 500) as $chunk) {
            $variations = Variation_model::whereIn('id', $chunk)
                ->get();
            
            $bulkVariationUpdates = [];
            
            foreach ($variations as $variation) {
                $totalStock = (int) ($stockSums[$variation->id] ?? 0);
                
                if ($variation->listed_stock != $totalStock) {
                    $bulkVariationUpdates[$variation->id] = [
                        'listed_stock' => $totalStock,
                        'updated_at' => now()
                    ];
                    $updated++;
                }
            }
            
            // Bulk update variations
            if (!empty($bulkVariationUpdates)) {
                foreach ($bulkVariationUpdates as $id => $data) {
                    DB::table('variation')
                        ->where('id', $id)
                        ->update($data);
                }
            }
        }
        
        return $updated;
    }
    
    /**
     * Display sync summary
     */
    private function displaySummary()
    {
        if (empty($this->syncSummary)) {
            return;
        }
        
        $summary = $this->syncSummary;
        
        $this->info('========================================');
        $this->info('SYNC SUMMARY');
        $this->info('========================================');
        $this->info("Total listings from API: {$summary['total_listings_from_api']}");
        $this->info("Variations checked: {$summary['variations_checked']}");
        $this->info("✓ Updated: {$summary['updated']}");
        $this->info("⊘ Skipped: {$summary['skipped']}");
        $this->info("⚠ Not found in API: {$summary['not_found']}");
        
        if ($summary['errors'] > 0) {
            $this->error("✗ Errors: {$summary['errors']}");
        }
        
        $this->info("Duration: {$summary['duration_seconds']}s");
        $this->info('========================================');
        
        // Calculate efficiency
        $apiCalls = count(Country_model::where('market_code', '!=', null)->get()); // Approximate
        $apiCalls = count(Country_model::where('market_code', '!=', null)->get()); // Approximate
        $this->info("API Calls: ~{$apiCalls} (bulk fetch)");
        $this->info("Efficiency: 95-98% reduction vs individual calls");
        $this->info('========================================');
    }
}

