<?php
namespace App\Console\Commands\V2;

use Illuminate\Console\Command;
use App\Models\Marketplace_model;
use App\Models\V2\MarketplaceStockModel;
use App\Services\V2\MarketplaceAPIService;
use App\Services\V2\MarketplaceSyncFailureService;
use Illuminate\Support\Facades\Log;

/**
 * V2 Version of SyncMarketplaceStock Command
 * Uses generic MarketplaceAPIService instead of marketplace-specific controllers
 */
class SyncMarketplaceStock extends Command
{
    protected $signature = 'v2:marketplace:sync-stock
                            {--marketplace= : Specific marketplace ID to sync}
                            {--force : Force sync even if last sync was less than 6 hours ago}';

    protected $description = 'V2: Sync stock from marketplace APIs (6-hour interval per marketplace)';

    protected MarketplaceAPIService $apiService;

    // Store sync summary for return
    private $syncSummary = [];

    public function __construct(MarketplaceAPIService $apiService)
    {
        parent::__construct();
        $this->apiService = $apiService;
    }

    public function handle()
    {
        $marketplaceId = $this->option('marketplace');
        $force = $this->option('force');

        Log::info("V2 SyncMarketplaceStock: Command started", [
            'marketplace_id' => $marketplaceId,
            'force' => $force
        ]);

        if ($marketplaceId) {
            // Sync specific marketplace
            $this->syncMarketplace((int)$marketplaceId, $force);

            // Return exit code based on results
            if (!empty($this->syncSummary) && $this->syncSummary['errors'] > 0) {
                return 1; // Exit with error if there were errors
            }
        } else {
            // Sync all marketplaces that need syncing
            $this->syncAllMarketplaces($force);
        }

        return 0; // Success
    }

    private function syncAllMarketplaces($force = false)
    {
        $marketplaces = Marketplace_model::where('status', 1)->get();

        foreach ($marketplaces as $marketplace) {
            $this->info("Checking marketplace: {$marketplace->name} (ID: {$marketplace->id})");
            $this->syncMarketplace($marketplace->id, $force);
        }
    }

    private function syncMarketplace($marketplaceId, $force = false)
    {
        $marketplace = Marketplace_model::find($marketplaceId);

        if (!$marketplace) {
            $this->error("Marketplace ID {$marketplaceId} not found");
            return;
        }

        $this->info("Syncing marketplace: {$marketplace->name} (ID: {$marketplaceId})");
        Log::info("V2 SyncMarketplaceStock: Starting sync", [
            'marketplace_id' => $marketplaceId,
            'marketplace_name' => $marketplace->name,
            'force' => $force
        ]);

        // Get sync interval for this marketplace (use config if available, otherwise default to 6 hours)
        $syncInterval = $marketplace->sync_interval_hours ?? 6;

        // Get all marketplace stocks that need syncing
        $marketplaceStocks = MarketplaceStockModel::where('marketplace_id', $marketplaceId)
            ->whereHas('variation', function($q) {
                $q->where(function($query) {
                    $query->whereNotNull('reference_id')
                          ->orWhereNotNull('sku');
                });
            })
            ->with('variation')
            ->get();

        $totalRecords = $marketplaceStocks->count();
        $syncedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $errors = [];

        $this->info("Found {$totalRecords} marketplace stock records to check");
        Log::info("V2 SyncMarketplaceStock: Found records", [
            'marketplace_id' => $marketplaceId,
            'total_records' => $totalRecords
        ]);

        foreach ($marketplaceStocks as $marketplaceStock) {
            // Check if sync is needed
            $needsSync = $force ||
                        !$marketplaceStock->last_synced_at ||
                        $marketplaceStock->last_synced_at->diffInHours(now()) >= $syncInterval;

            if (!$needsSync) {
                $skippedCount++;
                continue;
            }

            // Sync using generic API service
            try {
                $result = $this->syncStockRecord($marketplaceStock, $marketplaceId);
                
                if ($result === false) {
                    // Marketplace is unsupported - skip gracefully
                    $skippedCount++;
                    continue;
                }
                
                // Successfully synced
                $syncedCount++;

                if ($syncedCount % 10 == 0) {
                    $this->info("Progress: {$syncedCount}/{$totalRecords} synced...");
                }
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = [
                    'variation_id' => $marketplaceStock->variation_id,
                    'error' => $e->getMessage()
                ];

                Log::error("V2 SyncMarketplaceStock: Error syncing stock", [
                    'marketplace_id' => $marketplaceId,
                    'marketplace_stock_id' => $marketplaceStock->id,
                    'variation_id' => $marketplaceStock->variation_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->error("Error syncing variation {$marketplaceStock->variation_id}: {$e->getMessage()}");
            }
        }

        $summary = "Sync complete for {$marketplace->name}: {$syncedCount} synced, {$skippedCount} skipped";
        if ($errorCount > 0) {
            $summary .= ", {$errorCount} errors";
        }

        $this->info($summary);

        Log::info("V2 SyncMarketplaceStock: Sync completed", [
            'marketplace_id' => $marketplaceId,
            'marketplace_name' => $marketplace->name,
            'total_records' => $totalRecords,
            'synced' => $syncedCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount,
            'error_details' => $errors
        ]);

        // Store summary for return
        $this->syncSummary = [
            'marketplace_id' => $marketplaceId,
            'marketplace_name' => $marketplace->name,
            'total_records' => $totalRecords,
            'synced' => $syncedCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount,
            'error_details' => $errors
        ];
    }

    /**
     * Sync a single marketplace stock record
     * Uses the generic API service to fetch current stock from marketplace
     * 
     * @return bool Returns true if synced successfully, false if skipped (unsupported marketplace)
     * @throws \Exception If there's an actual error (not just unsupported marketplace)
     */
    private function syncStockRecord($marketplaceStock, $marketplaceId)
    {
        $variation = $marketplaceStock->variation;

        if (!$variation) {
            throw new \Exception("Variation not found for marketplace stock ID: {$marketplaceStock->id}");
        }

        // Get current stock from marketplace API
        $apiQuantity = $this->getStockFromMarketplace($variation, $marketplaceId);

        // If null, check if it's because marketplace is unsupported (skip) or actual API error (throw)
        if ($apiQuantity === null) {
            // Check if this marketplace is unsupported
            $isUnsupported = !in_array($marketplaceId, [1, 4]); // Only 1 (Back Market) and 4 (Refurbed) are supported
            
            if ($isUnsupported) {
                // Skip unsupported marketplaces gracefully
                Log::info("V2 SyncMarketplaceStock: Skipping unsupported marketplace", [
                    'marketplace_id' => $marketplaceId,
                    'marketplace_stock_id' => $marketplaceStock->id,
                    'variation_id' => $variation->id,
                ]);
                return false; // Indicates skipped, not an error
            }
            
            // If marketplace is supported but API returned null, it's an actual error
            throw new \Exception("Could not fetch stock from marketplace API");
        }

        // IMPORTANT: Only update listed_stock from API (never touch manual_adjustment)
        // listed_stock = API-synced stock
        // manual_adjustment = manual pushes (separate, never synced)
        // Total = listed_stock + manual_adjustment
        $oldListedStock = $marketplaceStock->listed_stock;
        $marketplaceStock->listed_stock = $apiQuantity;
        // Stock lock system removed - available stock = listed stock
        $marketplaceStock->available_stock = max(0, $marketplaceStock->listed_stock);
        $marketplaceStock->last_synced_at = now();
        $marketplaceStock->last_api_quantity = $apiQuantity;
        // NOTE: manual_adjustment is NOT touched - it's a separate offset that persists through syncs
        $marketplaceStock->save();

        Log::info("V2 SyncMarketplaceStock: Stock updated", [
            'variation_id' => $variation->id,
            'marketplace_id' => $marketplaceId,
            'old_stock' => $oldListedStock,
            'new_stock' => $apiQuantity,
            'difference' => $apiQuantity - $oldListedStock
        ]);

        // Log to history if there's a discrepancy
        if ($oldListedStock != $apiQuantity) {
                \App\Models\V2\MarketplaceStockHistory::create([
                'marketplace_stock_id' => $marketplaceStock->id,
                'variation_id' => $variation->id,
                'marketplace_id' => $marketplaceId,
                'listed_stock_before' => $oldListedStock,
                'listed_stock_after' => $apiQuantity,
                'locked_stock_before' => 0, // Stock lock system removed
                'locked_stock_after' => 0,
                'available_stock_before' => max(0, $oldListedStock),
                'available_stock_after' => $marketplaceStock->available_stock,
                'quantity_change' => $apiQuantity - $oldListedStock,
                'change_type' => 'reconciliation',
                'notes' => "Reconciliation sync: Local={$oldListedStock}, API={$apiQuantity}"
            ]);
        }

        // Update variation.listed_stock for backward compatibility (only if this is the primary marketplace)
        if ($marketplaceId == 1) {
            $variation->listed_stock = $apiQuantity;
            $variation->save();
        }
        
        return true; // Successfully synced
    }

    /**
     * Get current stock quantity from marketplace API
     *
     * @param \App\Models\Variation_model $variation
     * @param int $marketplaceId
     * @return int|null
     */
    private function getStockFromMarketplace($variation, $marketplaceId)
    {
        switch ($marketplaceId) {
            case 1: // Back Market
                return $this->getBackMarketStock($variation);

            case 4: // Refurbed
                return $this->getRefurbedStock($variation);

            default:
                Log::warning("V2 SyncMarketplaceStock: Unsupported marketplace for stock fetch", [
                    'marketplace_id' => $marketplaceId,
                    'variation_id' => $variation->id
                ]);
                return null;
        }
    }

    /**
     * Get stock from Back Market API
     */
    private function getBackMarketStock($variation)
    {
        if (!$variation->reference_id) {
            return null;
        }

        $bm = new \App\Http\Controllers\BackMarketAPIController();
        $apiListing = $bm->getOneListing($variation->reference_id);

        if (!$apiListing || !isset($apiListing->quantity)) {
            return null;
        }

        return (int)$apiListing->quantity;
    }

    /**
     * Get stock from Refurbed API
     */
    private function getRefurbedStock($variation)
    {
        if (!$variation->sku) {
            return null;
        }

        $marketplaceId = 4; // Refurbed marketplace ID
        $failureService = app(MarketplaceSyncFailureService::class);

        // Quick fix: Skip SKUs with invalid characters that Refurbed API doesn't accept
        // Refurbed's OfferSKUFilter rejects SKUs containing: . ( ) and other special chars
        if (preg_match('/[().]/', $variation->sku)) {
            $errorReason = 'SKU contains invalid characters (dots, parentheses) that Refurbed API filter does not accept';
            $errorMessage = 'SKU contains characters that Refurbed API filter does not accept';
            
            Log::warning("V2 SyncMarketplaceStock: Skipping Refurbed sync - SKU contains invalid characters", [
                'variation_id' => $variation->id,
                'sku' => $variation->sku,
                'reason' => $errorReason
            ]);
            
            // Log failure via service (only if feature is enabled)
            $failureService->logFailure(
                $variation->id,
                $variation->sku,
                $marketplaceId,
                $errorReason,
                $errorMessage
            );
            
            return null;
        }

        $refurbed = new \App\Http\Controllers\RefurbedAPIController();

        try {
            $offers = $refurbed->getAllOffers(['sku' => $variation->sku], [], 1);

            if (empty($offers['offers'])) {
                return null;
            }

            $offer = $offers['offers'][0];
            $stock = (int)($offer['stock'] ?? $offer['quantity'] ?? 0);
            
            // If sync succeeds, clear any previous failures
            if ($stock !== null) {
                $failureService->clearFailure($variation->sku, $marketplaceId);
            }
            
            return $stock;
        } catch (\Exception $e) {
            $errorReason = 'API request failed';
            $errorMessage = $e->getMessage();
            
            Log::error("V2 SyncMarketplaceStock: Error fetching Refurbed stock", [
                'variation_id' => $variation->id,
                'sku' => $variation->sku,
                'error' => $errorMessage
            ]);
            
            // Log failure via service (only if feature is enabled)
            $failureService->logFailure(
                $variation->id,
                $variation->sku,
                $marketplaceId,
                $errorReason,
                $errorMessage
            );
            
            return null;
        }
    }
}

