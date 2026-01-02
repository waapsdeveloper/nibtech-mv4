<?php

namespace App\Console\Commands\V2;

use Illuminate\Console\Command;
use App\Models\V2\MarketplaceStockModel;
use App\Models\Variation_model;
use App\Models\StockSyncLog;
use App\Http\Controllers\BackMarketAPIController;
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
            // Get all marketplace stock records for this marketplace
            // Only get variations that have reference_id (required for BackMarket API)
            $marketplaceStocks = MarketplaceStockModel::where('marketplace_id', $marketplaceId)
                ->whereHas('variation', function($query) {
                    $query->whereNotNull('reference_id');
                })
                ->with('variation')
                ->get();
            
            $totalRecords = $marketplaceStocks->count();
            
            if ($totalRecords === 0) {
                $this->warn("No marketplace stock records found for marketplace ID {$marketplaceId} with reference_id.");
                return 0;
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
                    
                    // Fetch stock from Backmarket API
                    $apiListing = $bm->getOneListing($variation->reference_id);
                    
                    if (!$apiListing || !isset($apiListing->quantity)) {
                        $skippedCount++;
                        
                        // Determine the reason for invalid response
                        $reason = 'Unknown';
                        $apiResponseType = gettype($apiListing);
                        
                        if ($apiListing === null) {
                            $reason = 'API returned null (listing may not exist or API error)';
                        } elseif (is_object($apiListing)) {
                            // Check if it's an error response
                            if (isset($apiListing->error) || isset($apiListing->message)) {
                                // Convert error/message to string safely
                                $errorMsg = null;
                                if (isset($apiListing->error)) {
                                    $errorMsg = $apiListing->error;
                                } elseif (isset($apiListing->message)) {
                                    $errorMsg = $apiListing->message;
                                }
                                
                                // Convert to string safely
                                if (is_object($errorMsg) || is_array($errorMsg)) {
                                    $errorMsg = json_encode($errorMsg);
                                } elseif ($errorMsg === null) {
                                    $errorMsg = 'Unknown error';
                                } else {
                                    $errorMsg = (string)$errorMsg;
                                }
                                
                                $reason = 'API error: ' . $errorMsg;
                            } elseif (!isset($apiListing->quantity)) {
                                $reason = 'Response missing quantity field (listing may be deleted or inactive)';
                            }
                        } elseif (is_array($apiListing)) {
                            $reason = 'API returned array instead of object';
                        }
                        
                        // Log as info instead of warning if it's expected (listing doesn't exist)
                        // Only log as warning if it's an actual error
                        if (strpos($reason, 'may not exist') !== false || strpos($reason, 'deleted or inactive') !== false) {
                            Log::info("SyncAllMarketplaceStockFromAPI: Skipping variation (expected)", [
                                'variation_id' => $variation->id,
                                'reference_id' => $variation->reference_id,
                                'marketplace_id' => $marketplaceId,
                                'reason' => $reason
                            ]);
                        } else {
                            Log::warning("SyncAllMarketplaceStockFromAPI: Invalid API response", [
                                'variation_id' => $variation->id,
                                'reference_id' => $variation->reference_id,
                                'marketplace_id' => $marketplaceId,
                                'reason' => $reason,
                                'response_type' => $apiResponseType,
                                'response_preview' => is_object($apiListing) ? json_encode($apiListing) : (is_array($apiListing) ? json_encode($apiListing) : (string)$apiListing)
                            ]);
                        }
                        
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
                }
                
                $bar->advance();
            }
            
            $bar->finish();
            $this->newLine(2);
            
            // Display summary
            $this->info('========================================');
            $this->info('SYNC SUMMARY');
            $this->info('========================================');
            $this->info("Total records: {$totalRecords}");
            $this->info("Successfully synced: {$syncedCount}");
            $this->info("Skipped: {$skippedCount}");
            $this->info("Errors: {$errorCount}");
            $this->info('========================================');
            
            if ($errorCount > 0) {
                $this->warn("Errors occurred during sync. Check logs for details.");
                if (count($errors) <= 10) {
                    foreach ($errors as $error) {
                        $this->error("  Variation ID {$error['variation_id']}: {$error['error']}");
                    }
                }
            }
            
            $endTime = microtime(true);
            $duration = (int) ($endTime - $startTime);
            
            // Update log entry with results
            $summary = "Total: {$totalRecords}, Synced: {$syncedCount}, Skipped: {$skippedCount}, Errors: {$errorCount}";
            
            // Capture command output for logging
            $commandOutput = "SYNC ALL MARKETPLACE STOCK FROM API\n";
            $commandOutput .= "Marketplace ID: {$marketplaceId}\n";
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
                'error_details' => $errorCount > 0 ? array_slice($errors, 0, 50) : null, // Limit to 50 errors
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
                'marketplace_id' => $marketplaceId,
                'total_records' => $totalRecords,
                'synced_count' => $syncedCount,
                'skipped_count' => $skippedCount,
                'error_count' => $errorCount,
                'duration_seconds' => $duration
            ]);
            
            return 0;
            
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
            
            return 1;
        }
    }
}

