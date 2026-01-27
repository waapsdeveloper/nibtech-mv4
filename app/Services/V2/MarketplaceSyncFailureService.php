<?php

namespace App\Services\V2;

use App\Models\MarketplaceSyncFailure;
use App\Models\Listing_model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Service for tracking marketplace sync failures
 * Controlled by MARKETPLACE_SYNC_FAILURE_TRACKING_ENABLED env variable
 */
class MarketplaceSyncFailureService
{
    /**
     * Check if failure tracking is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return filter_var(env('MARKETPLACE_SYNC_FAILURE_TRACKING_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Log a sync failure for a SKU
     *
     * @param int $variationId
     * @param string $sku
     * @param int $marketplaceId
     * @param string $errorReason Human-readable error reason
     * @param string|null $errorMessage Full error message from API
     * @return MarketplaceSyncFailure|null Returns the failure record if tracking is enabled, null otherwise
     */
    public function logFailure(
        int $variationId,
        string $sku,
        int $marketplaceId,
        string $errorReason,
        ?string $errorMessage = null
    ): ?MarketplaceSyncFailure {
        // Check if feature is enabled
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            // Auto-truncate if records exceed 1000
            $this->autoTruncateIfNeeded();

            // Check if SKU is posted on marketplace (has listing)
            $isPosted = Listing_model::where('variation_id', $variationId)
                ->where('marketplace_id', $marketplaceId)
                ->exists();

            // Find existing record or create new one
            $failure = MarketplaceSyncFailure::firstOrNew(
                [
                    'sku' => $sku,
                    'marketplace_id' => $marketplaceId,
                ]
            );

            if (!$failure->exists) {
                // New record - set initial values
                $failure->variation_id = $variationId;
                $failure->error_reason = $errorReason;
                $failure->error_message = $errorMessage;
                $failure->is_posted_on_marketplace = $isPosted;
                $failure->failure_count = 1;
                $failure->first_failed_at = now();
                $failure->last_attempted_at = now();
            } else {
                // Existing record - update values and increment count
                $failure->variation_id = $variationId;
                $failure->error_reason = $errorReason;
                $failure->error_message = $errorMessage;
                $failure->is_posted_on_marketplace = $isPosted;
                $failure->failure_count++;
                $failure->last_attempted_at = now();
            }

            $failure->save();

            return $failure;
        } catch (\Exception $e) {
            // Log error but don't break the sync process
            Log::error("MarketplaceSyncFailureService: Error logging failure", [
                'variation_id' => $variationId,
                'sku' => $sku,
                'marketplace_id' => $marketplaceId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all failures for a marketplace
     *
     * @param int $marketplaceId
     * @param bool $onlyPosted Filter to only SKUs posted on marketplace
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFailures(int $marketplaceId, bool $onlyPosted = false)
    {
        if (!$this->isEnabled()) {
            return collect([]);
        }

        $query = MarketplaceSyncFailure::where('marketplace_id', $marketplaceId);

        if ($onlyPosted) {
            $query->where('is_posted_on_marketplace', true);
        }

        return $query->orderBy('last_attempted_at', 'desc')->get();
    }

    /**
     * Clear failures for a SKU (when sync succeeds)
     *
     * @param string $sku
     * @param int $marketplaceId
     * @return bool
     */
    public function clearFailure(string $sku, int $marketplaceId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            return MarketplaceSyncFailure::where('sku', $sku)
                ->where('marketplace_id', $marketplaceId)
                ->delete() > 0;
        } catch (\Exception $e) {
            Log::error("MarketplaceSyncFailureService: Error clearing failure", [
                'sku' => $sku,
                'marketplace_id' => $marketplaceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Auto-truncate table if records exceed 1000
     * This prevents the table from growing too large
     *
     * @return void
     */
    private function autoTruncateIfNeeded(): void
    {
        try {
            $recordCount = MarketplaceSyncFailure::count();
            
            if ($recordCount >= 1000) {
                Log::warning("MarketplaceSyncFailureService: Auto-truncating table - records exceeded 1000", [
                    'record_count' => $recordCount,
                    'threshold' => 1000
                ]);
                
                DB::table('marketplace_sync_failures')->truncate();
                
                Log::info("MarketplaceSyncFailureService: Table truncated successfully. New records will be saved.");
            }
        } catch (\Exception $e) {
            // Log error but don't break the sync process
            Log::error("MarketplaceSyncFailureService: Error during auto-truncate", [
                'error' => $e->getMessage()
            ]);
        }
    }
}
