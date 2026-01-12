<?php

namespace App\Console\Commands\V2;

use Illuminate\Console\Command;
use App\Models\V2\MarketplaceStockModel;
use App\Models\Variation_model;
use App\Models\StockSyncLog;
use App\Http\Controllers\BackMarketAPIController;
use App\Services\V2\SlackLogService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Sync all marketplace stock from API
 * 
 * This command fetches stock quantities from Backmarket API for all variations
 * in a specific marketplace and updates the marketplace_stock table.
 * Runs in queue for bulk operations.
 */
class SyncAllMarketplaceStockFromAPI extends Command
{
    protected $signature = 'v2:sync-all-marketplace-stock-from-api 
                            {--marketplace=1 : The marketplace ID to sync (default: 1 for BackMarket)}';
    
    protected $description = 'Fetch stock quantities from Backmarket API for all variations in a marketplace and update marketplace_stock table';
    
    public function handle()
    {
        $marketplaceId = (int) $this->option('marketplace');
        
        // Check if command was run within last 30 minutes
        $lastRun = StockSyncLog::where('marketplace_id', $marketplaceId)
            ->whereIn('status', ['running', 'completed'])
            ->orderBy('started_at', 'desc')
            ->first();
        
        if ($lastRun && $lastRun->started_at) {
            $minutesSinceLastRun = Carbon::now()->diffInMinutes($lastRun->started_at);
            
            if ($minutesSinceLastRun < 30) {
                $remainingMinutes = 30 - $minutesSinceLastRun;
                $errorMessage = "COMMAND CANNOT RUN YET - Cooldown period active. Last sync was run {$minutesSinceLastRun} minute(s) ago. Please wait {$remainingMinutes} more minute(s).";
                
                $this->error("==========================================");
                $this->error("COMMAND CANNOT RUN YET");
                $this->error("==========================================");
                $this->error("Last sync was run {$minutesSinceLastRun} minute(s) ago.");
                $this->error("Please wait {$remainingMinutes} more minute(s) before running again.");
                $this->error("Last run: {$lastRun->started_at->format('Y-m-d H:i:s')}");
                $this->error("Status: {$lastRun->status}");
                $this->error("==========================================");
                
                // Create a log entry for cooldown to track the attempt
                StockSyncLog::create([
                    'marketplace_id' => $marketplaceId,
                    'status' => 'cancelled',
                    'summary' => $errorMessage,
                    'started_at' => now(),
                    'completed_at' => now(),
                    'duration_seconds' => 0,
                    'admin_id' => auth()->id() ?? null
                ]);
                
                Log::warning('SyncAllMarketplaceStockFromAPI: Command blocked by cooldown', [
                    'marketplace_id' => $marketplaceId,
                    'minutes_since_last_run' => $minutesSinceLastRun,
                    'remaining_minutes' => $remainingMinutes,
                    'last_run_id' => $lastRun->id
                ]);
                
                return 1;
            }
        }
        
        // Create log entry
        $logEntry = StockSyncLog::create([
            'marketplace_id' => $marketplaceId,
            'status' => 'running',
            'started_at' => now(),
            'admin_id' => auth()->id() ?? null
        ]);
        
        $startTime = microtime(true);
        
        $this->info('========================================');
        $this->info('SYNC ALL MARKETPLACE STOCK FROM API');
        $this->info('========================================');
        $this->info("Marketplace ID: {$marketplaceId}");
        $this->info("Log ID: {$logEntry->id}");
        $this->info('========================================');
        
        try {
            // Only BackMarket (ID 1) supports bulk fetch currently
            if ($marketplaceId === 1) {
                // Use bulk fetch for BackMarket (much more efficient)
                return $this->syncBulk($marketplaceId, $logEntry);
            } else {
                // Use individual calls for other marketplaces
                return $this->syncIndividual($marketplaceId, $logEntry);
            }
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $duration = (int) ($endTime - $startTime);
            
            // Update log entry with error
            $logEntry->update([
                'status' => 'failed',
                'error_details' => [['error' => $e->getMessage()]],
                'summary' => 'Command failed: ' . $e->getMessage(),
                'completed_at' => now(),
                'duration_seconds' => $duration
            ]);
            
            $this->error('Error during sync: ' . $e->getMessage());
            Log::error('SyncAllMarketplaceStockFromAPI: Command failed', [
                'log_id' => $logEntry->id,
                'marketplace_id' => $marketplaceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Send error to Slack
            SlackLogService::post('stock_sync', 'error', "V2 Stock Sync Command Failed: {$e->getMessage()}", [
                'command' => 'v2:sync-all-marketplace-stock-from-api',
                'log_id' => $logEntry->id,
                'marketplace_id' => $marketplaceId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], true);
            
            return 1;
        }
    }
    
    /**
     * Sync using bulk fetch (optimized for BackMarket)
     */
    private function syncBulk($marketplaceId, $logEntry)
    {
        $startTime = microtime(true);
        
        $this->info("Using BULK FETCH (optimized - 95% fewer API calls)");
        $this->newLine();
        
        // Step 1: Bulk fetch ALL listings from API
        $this->info("Fetching all listings from BackMarket API (bulk)...");
        $bm = new BackMarketAPIController();
        $allListings = $bm->getAllListings(); // BULK FETCH ✅
        
        if (empty($allListings)) {
            $this->warn("No listings returned from API");
            return $this->completeLogEntry($logEntry, $startTime, 0, 0, 0, 0, []);
        }
        
        $totalListingsFromAPI = 0;
        foreach ($allListings as $countryListings) {
            $totalListingsFromAPI += is_array($countryListings) ? count($countryListings) : 0;
        }
        
        $this->info("✓ Fetched {$totalListingsFromAPI} listings from API");
        $this->newLine();
        
        // Step 2: Create mapping by reference_id
        $this->info("Creating reference_id mapping...");
        $listingMap = $this->createListingMap($allListings);
        $this->info("✓ Created map with " . count($listingMap) . " unique listings");
        $this->newLine();
        
        // Step 3: Get all marketplace stock records
        $this->info("Loading marketplace stock records...");
        $marketplaceStocks = MarketplaceStockModel::where('marketplace_id', $marketplaceId)
            ->whereHas('variation', function($query) {
                $query->whereNotNull('reference_id');
            })
            ->with('variation')
            ->get();
        
        $totalRecords = $marketplaceStocks->count();
        
        if ($totalRecords === 0) {
            $this->warn("No marketplace stock records found for marketplace ID {$marketplaceId} with reference_id.");
            return $this->completeLogEntry($logEntry, $startTime, $totalRecords, 0, 0, 0, []);
        }
        
        $this->info("Found {$totalRecords} marketplace stock records to sync");
        $this->newLine();
        
        $bar = $this->output->createProgressBar($totalRecords);
        $bar->start();
        
        $syncedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $errors = [];
        $updates = [];
        
        foreach ($marketplaceStocks as $marketplaceStock) {
            try {
                $variation = $marketplaceStock->variation;
                
                if (!$variation || !$variation->reference_id) {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }
                
                $referenceId = trim($variation->reference_id);
                
                // Look up in bulk fetch map instead of individual API call
                if (!isset($listingMap[$referenceId])) {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }
                
                $listingData = $listingMap[$referenceId];
                $apiQuantity = (int) ($listingData['quantity'] ?? 0);
                
                // Get current locked stock (preserve it)
                $lockedStock = $marketplaceStock->locked_stock ?? 0;
                $oldListedStock = $marketplaceStock->listed_stock ?? 0;
                $oldAvailableStock = $marketplaceStock->available_stock ?? 0;
                
                // Update listed_stock with API quantity
                $marketplaceStock->listed_stock = $apiQuantity;
                
                // Calculate available_stock = listed_stock - locked_stock
                $newAvailableStock = max(0, $apiQuantity - $lockedStock);
                $marketplaceStock->available_stock = $newAvailableStock;
                
                // Update sync metadata
                $marketplaceStock->last_synced_at = now();
                $marketplaceStock->last_api_quantity = $apiQuantity;
                
                // Save the record
                $marketplaceStock->save();
                
                $syncedCount++;
                
                // Log if there was a change
                if ($oldListedStock != $apiQuantity || $oldAvailableStock != $newAvailableStock) {
                    Log::info('SyncAllMarketplaceStockFromAPI: Stock updated', [
                        'variation_id' => $variation->id,
                        'reference_id' => $variation->reference_id,
                        'marketplace_id' => $marketplaceId,
                        'old_listed_stock' => $oldListedStock,
                        'new_listed_stock' => $apiQuantity,
                        'old_available_stock' => $oldAvailableStock,
                        'new_available_stock' => $newAvailableStock,
                        'locked_stock' => $lockedStock
                    ]);
                }
                
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = [
                    'variation_id' => $variation->id ?? null,
                    'error' => $e->getMessage()
                ];
                
                Log::error('SyncAllMarketplaceStockFromAPI: Error syncing variation', [
                    'variation_id' => $variation->id ?? null,
                    'marketplace_id' => $marketplaceId,
                    'error' => $e->getMessage()
                ]);
                
                // Send critical errors to Slack (only for first few to avoid spam)
                if ($errorCount <= 5) {
                    SlackLogService::post('stock_sync', 'error', "Error syncing variation stock: {$e->getMessage()}", [
                        'variation_id' => $variation->id ?? null,
                        'reference_id' => $variation->reference_id ?? null,
                        'marketplace_id' => $marketplaceId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        return $this->completeLogEntry($logEntry, $startTime, $totalRecords, $syncedCount, $skippedCount, $errorCount, $errors);
    }
    
    /**
     * Sync using individual API calls (for non-BackMarket marketplaces)
     */
    private function syncIndividual($marketplaceId, $logEntry)
    {
        $startTime = microtime(true);
        
        $this->info("Using individual API calls (for marketplace ID {$marketplaceId})");
        $this->newLine();
        
        // Get all marketplace stock records for this marketplace
        $marketplaceStocks = MarketplaceStockModel::where('marketplace_id', $marketplaceId)
            ->whereHas('variation', function($query) {
                $query->whereNotNull('reference_id');
            })
            ->with('variation')
            ->get();
        
        $totalRecords = $marketplaceStocks->count();
        
        if ($totalRecords === 0) {
            $this->warn("No marketplace stock records found for marketplace ID {$marketplaceId} with reference_id.");
            return $this->completeLogEntry($logEntry, $startTime, $totalRecords, 0, 0, 0, []);
        }
        
        $this->info("Found {$totalRecords} marketplace stock records to sync");
        
        $bar = $this->output->createProgressBar($totalRecords);
        $bar->start();
        
        $syncedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $errors = [];
        
        $bm = new BackMarketAPIController();
        
        foreach ($marketplaceStocks as $marketplaceStock) {
            try {
                $variation = $marketplaceStock->variation;
                
                if (!$variation || !$variation->reference_id) {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }
                
                // Fetch stock from Backmarket API (individual call)
                $apiListing = $bm->getOneListing($variation->reference_id);
                
                if (!$apiListing || !isset($apiListing->quantity)) {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }
                
                $apiQuantity = (int) $apiListing->quantity;
                
                // Get current locked stock (preserve it)
                $lockedStock = $marketplaceStock->locked_stock ?? 0;
                $oldListedStock = $marketplaceStock->listed_stock ?? 0;
                $oldAvailableStock = $marketplaceStock->available_stock ?? 0;
                
                // Update listed_stock with API quantity
                $marketplaceStock->listed_stock = $apiQuantity;
                
                // Calculate available_stock = listed_stock - locked_stock
                $newAvailableStock = max(0, $apiQuantity - $lockedStock);
                $marketplaceStock->available_stock = $newAvailableStock;
                
                // Update sync metadata
                $marketplaceStock->last_synced_at = now();
                $marketplaceStock->last_api_quantity = $apiQuantity;
                
                // Save the record
                $marketplaceStock->save();
                
                $syncedCount++;
                
                // Log if there was a change
                if ($oldListedStock != $apiQuantity || $oldAvailableStock != $newAvailableStock) {
                    Log::info('SyncAllMarketplaceStockFromAPI: Stock updated', [
                        'variation_id' => $variation->id,
                        'reference_id' => $variation->reference_id,
                        'marketplace_id' => $marketplaceId,
                        'old_listed_stock' => $oldListedStock,
                        'new_listed_stock' => $apiQuantity,
                        'old_available_stock' => $oldAvailableStock,
                        'new_available_stock' => $newAvailableStock,
                        'locked_stock' => $lockedStock
                    ]);
                }
                
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = [
                    'variation_id' => $variation->id ?? null,
                    'error' => $e->getMessage()
                ];
                
                Log::error('SyncAllMarketplaceStockFromAPI: Error syncing variation', [
                    'variation_id' => $variation->id ?? null,
                    'marketplace_id' => $marketplaceId,
                    'error' => $e->getMessage()
                ]);
                
                // Send critical errors to Slack (only for first few to avoid spam)
                if ($errorCount <= 5) {
                    SlackLogService::post('stock_sync', 'error', "Error syncing variation stock: {$e->getMessage()}", [
                        'variation_id' => $variation->id ?? null,
                        'reference_id' => $variation->reference_id ?? null,
                        'marketplace_id' => $marketplaceId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        return $this->completeLogEntry($logEntry, $startTime, $totalRecords, $syncedCount, $skippedCount, $errorCount, $errors);
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
                } elseif (is_array($list)) {
                    $referenceId = trim($list['listing_id'] ?? $list['id'] ?? '');
                    $quantity = $list['quantity'] ?? 0;
                } else {
                    continue;
                }
                
                if (empty($referenceId)) {
                    continue;
                }
                
                // Store the first occurrence
                if (!isset($listingMap[$referenceId])) {
                    $listingMap[$referenceId] = [
                        'quantity' => (int) $quantity
                    ];
                }
            }
        }
        
        return $listingMap;
    }
    
    /**
     * Complete log entry and return exit code
     */
    private function completeLogEntry($logEntry, $startTime, $totalRecords, $syncedCount, $skippedCount, $errorCount, $errors)
    {
        $endTime = microtime(true);
        $duration = (int) ($endTime - $startTime);
        
        // Update log entry with results
        $summary = "Total: {$totalRecords}, Synced: {$syncedCount}, Skipped: {$skippedCount}, Errors: {$errorCount}";
        
        // Capture command output for logging
        $commandOutput = "SYNC ALL MARKETPLACE STOCK FROM API\n";
        $commandOutput .= "Total records: {$totalRecords}\n";
        $commandOutput .= "Successfully synced: {$syncedCount}\n";
        $commandOutput .= "Skipped: {$skippedCount}\n";
        $commandOutput .= "Errors: {$errorCount}\n";
        $commandOutput .= "Duration: {$duration} seconds\n";
        
        if ($errorCount > 0 && count($errors) > 0) {
            $commandOutput .= "\nErrors:\n";
            foreach (array_slice($errors, 0, 10) as $error) {
                $commandOutput .= "  Variation ID {$error['variation_id']}: {$error['error']}\n";
            }
        }
        
        $logEntry->update([
            'status' => 'completed',
            'total_records' => $totalRecords,
            'synced_count' => $syncedCount,
            'skipped_count' => $skippedCount,
            'error_count' => $errorCount,
            'error_details' => $errorCount > 0 ? array_slice($errors, 0, 50) : null,
            'summary' => $summary,
            'completed_at' => now(),
            'duration_seconds' => $duration
        ]);
        
        // Log the output for debugging
        Log::info('SyncAllMarketplaceStockFromAPI: Command output', [
            'log_id' => $logEntry->id,
            'output' => $commandOutput
        ]);
        
        Log::info('SyncAllMarketplaceStockFromAPI: Completed', [
            'log_id' => $logEntry->id,
            'total_records' => $totalRecords,
            'synced_count' => $syncedCount,
            'skipped_count' => $skippedCount,
            'error_count' => $errorCount,
            'duration_seconds' => $duration
        ]);
        
        // Display summary
        $this->info('========================================');
        $this->info('SYNC SUMMARY');
        $this->info('========================================');
        $this->info("Total records: {$totalRecords}");
        $this->info("Successfully synced: {$syncedCount}");
        $this->info("Skipped: {$skippedCount}");
        $this->info("Errors: {$errorCount}");
        $this->info("Duration: {$duration}s");
        $this->info('========================================');
        
        if ($errorCount > 0) {
            $this->warn("Errors occurred during sync. Check logs for details.");
            if (count($errors) <= 10) {
                foreach ($errors as $error) {
                    $this->error("  Variation ID {$error['variation_id']}: {$error['error']}");
                }
            }
            
            // Send error summary to Slack
            SlackLogService::post('stock_sync', 'warning', "V2 Stock Sync: {$errorCount} error(s) occurred", [
                'command' => 'v2:sync-all-marketplace-stock-from-api',
                'log_id' => $logEntry->id,
                'marketplace_id' => $marketplaceId,
                'total_records' => $totalRecords,
                'synced_count' => $syncedCount,
                'error_count' => $errorCount,
                'duration_seconds' => $duration
            ], true);
        }
        
        return $errorCount > 0 ? 1 : 0;
    }
}

