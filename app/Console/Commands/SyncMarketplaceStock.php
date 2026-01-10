<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Marketplace_model;
use App\Models\MarketplaceStockModel;
use App\Models\Variation_model;
use App\Http\Controllers\BackMarketAPIController;
use App\Http\Controllers\RefurbedAPIController;
use Illuminate\Support\Facades\Log;

class SyncMarketplaceStock extends Command
{
    protected $signature = 'marketplace:sync-stock 
                            {--marketplace= : Specific marketplace ID to sync}
                            {--force : Force sync even if last sync was less than 6 hours ago}';
    
    protected $description = 'Sync stock from marketplace APIs (6-hour interval per marketplace)';
    
    // Sync interval in hours (configurable per marketplace)
    private $syncIntervals = [
        1 => 6, // Back Market: 6 hours
        2 => 6, // Marketplace 2: 6 hours
        3 => 6, // Marketplace 3: 6 hours
        4 => 6, // Refurbed: 6 hours
    ];
    
    // Store sync summary for return
    private $syncSummary = [];
    
    public function handle()
    {
        $marketplaceId = $this->option('marketplace');
        $force = $this->option('force');
        
        Log::info("SyncMarketplaceStock: Command started", [
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
        Log::info("SyncMarketplaceStock: Starting sync", [
            'marketplace_id' => $marketplaceId,
            'marketplace_name' => $marketplace->name,
            'force' => $force
        ]);
        
        // Get sync interval for this marketplace (use config if available, otherwise default)
        $syncInterval = $marketplace->sync_interval_hours ?? ($this->syncIntervals[$marketplaceId] ?? 6);
        
        // Get all marketplace stocks that need syncing
        $marketplaceStocks = MarketplaceStockModel::where('marketplace_id', $marketplaceId)
            ->whereHas('variation', function($q) {
                $q->where(function($query) {
                    $query->whereNotNull('reference_id')
                          ->orWhereNotNull('sku');
                });
            })
            ->get();
        
        $totalRecords = $marketplaceStocks->count();
        $syncedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $errors = [];
        
        $this->info("Found {$totalRecords} marketplace stock records to check");
        Log::info("SyncMarketplaceStock: Found records", [
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
            
            // Sync based on marketplace type
            try {
                switch ($marketplaceId) {
                    case 1: // Back Market
                        $this->syncBackMarket($marketplaceStock);
                        break;
                    case 4: // Refurbed
                        $this->syncRefurbed($marketplaceStock);
                        break;
                    default:
                        $this->warn("No sync handler for marketplace ID {$marketplaceId}");
                        continue 2;
                }
                
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
                
                Log::error("Error syncing marketplace stock", [
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
        
        Log::info("SyncMarketplaceStock: Sync completed", [
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
    
    private function syncBackMarket($marketplaceStock)
    {
        $variation = $marketplaceStock->variation;
        
        if (!$variation || !$variation->reference_id) {
            Log::warning("SyncMarketplaceStock: Variation missing reference_id", [
                'variation_id' => $variation->id ?? null,
                'marketplace_stock_id' => $marketplaceStock->id
            ]);
            return;
        }
        
        $bm = new BackMarketAPIController();
        $apiListing = $bm->getOneListing($variation->reference_id);
        
        if (!$apiListing || !isset($apiListing->quantity)) {
            Log::warning("Back Market API returned invalid response", [
                'variation_id' => $variation->id,
                'reference_id' => $variation->reference_id,
                'api_response' => $apiListing
            ]);
            throw new \Exception("Invalid API response for reference_id: {$variation->reference_id}");
        }
        
        $apiQuantity = (int)$apiListing->quantity;
        
        // Update marketplace stock (reconciliation)
        $oldListedStock = $marketplaceStock->listed_stock;
        $marketplaceStock->listed_stock = $apiQuantity;
        $marketplaceStock->available_stock = max(0, $marketplaceStock->listed_stock - $marketplaceStock->locked_stock);
        $marketplaceStock->last_synced_at = now();
        $marketplaceStock->last_api_quantity = $apiQuantity;
        $marketplaceStock->save();
        
        Log::info("SyncMarketplaceStock: Back Market stock updated", [
            'variation_id' => $variation->id,
            'reference_id' => $variation->reference_id,
            'old_stock' => $oldListedStock,
            'new_stock' => $apiQuantity,
            'difference' => $apiQuantity - $oldListedStock
        ]);
        
        // Log to history if there's a discrepancy
        if ($oldListedStock != $apiQuantity) {
            \App\Models\MarketplaceStockHistory::create([
                'marketplace_stock_id' => $marketplaceStock->id,
                'variation_id' => $variation->id,
                'marketplace_id' => $marketplaceStock->marketplace_id,
                'listed_stock_before' => $oldListedStock,
                'listed_stock_after' => $apiQuantity,
                'locked_stock_before' => $marketplaceStock->locked_stock,
                'locked_stock_after' => $marketplaceStock->locked_stock,
                'available_stock_before' => max(0, $oldListedStock - $marketplaceStock->locked_stock),
                'available_stock_after' => $marketplaceStock->available_stock,
                'quantity_change' => $apiQuantity - $oldListedStock,
                'change_type' => 'reconciliation',
                'notes' => "Reconciliation sync: Local={$oldListedStock}, API={$apiQuantity}"
            ]);
        }
        
        // Update variation.listed_stock for backward compatibility (only if this is the primary marketplace)
        if ($marketplaceStock->marketplace_id == 1) {
            $variation->listed_stock = $apiQuantity;
            $variation->save();
        }
    }
    
    private function syncRefurbed($marketplaceStock)
    {
        $variation = $marketplaceStock->variation;
        
        if (!$variation || !$variation->sku) {
            return;
        }
        
        $refurbed = new RefurbedAPIController();
        
        try {
            // Get offer by SKU
            $offers = $refurbed->getAllOffers(['sku' => $variation->sku], [], 1);
            
            if (empty($offers['offers'])) {
                Log::warning("Refurbed offer not found for SKU", [
                    'variation_id' => $variation->id,
                    'sku' => $variation->sku
                ]);
                return;
            }
            
            $offer = $offers['offers'][0];
            $apiQuantity = (int)($offer['stock'] ?? $offer['quantity'] ?? 0);
            
            // Update marketplace stock (reconciliation)
            $oldListedStock = $marketplaceStock->listed_stock;
            $marketplaceStock->listed_stock = $apiQuantity;
            $marketplaceStock->available_stock = max(0, $marketplaceStock->listed_stock - $marketplaceStock->locked_stock);
            $marketplaceStock->last_synced_at = now();
            $marketplaceStock->last_api_quantity = $apiQuantity;
            $marketplaceStock->save();
            
            // Log to history if there's a discrepancy
            if ($oldListedStock != $apiQuantity) {
                \App\Models\MarketplaceStockHistory::create([
                    'marketplace_stock_id' => $marketplaceStock->id,
                    'variation_id' => $variation->id,
                    'marketplace_id' => $marketplaceStock->marketplace_id,
                    'listed_stock_before' => $oldListedStock,
                    'listed_stock_after' => $apiQuantity,
                    'locked_stock_before' => $marketplaceStock->locked_stock,
                    'locked_stock_after' => $marketplaceStock->locked_stock,
                    'available_stock_before' => max(0, $oldListedStock - $marketplaceStock->locked_stock),
                    'available_stock_after' => $marketplaceStock->available_stock,
                    'quantity_change' => $apiQuantity - $oldListedStock,
                    'change_type' => 'reconciliation',
                    'notes' => "Reconciliation sync: Local={$oldListedStock}, API={$apiQuantity}"
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error syncing Refurbed stock", [
                'variation_id' => $variation->id,
                'sku' => $variation->sku,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
