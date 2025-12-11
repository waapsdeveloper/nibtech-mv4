<?php

namespace App\Listeners;

use App\Events\VariationStockUpdated;
use App\Services\Marketplace\StockDistributionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class DistributeStockToMarketplaces implements ShouldQueue
{
    protected $stockDistributionService;

    /**
     * Create the event listener.
     *
     * @param StockDistributionService $stockDistributionService
     */
    public function __construct(StockDistributionService $stockDistributionService)
    {
        $this->stockDistributionService = $stockDistributionService;
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\VariationStockUpdated  $event
     * @return void
     */
    public function handle(VariationStockUpdated $event)
    {
        try {
            Log::info("Distributing stock for variation {$event->variationId}", [
                'variation_id' => $event->variationId,
                'old_stock' => $event->oldStock,
                'new_stock' => $event->newStock,
                'stock_change' => $event->stockChange,
            ]);

            // Only distribute if stock change is positive (stock added)
            // For negative changes (stock removed), you might want different logic
            if ($event->stockChange > 0) {
                $result = $this->stockDistributionService->distributeStock(
                    $event->variationId,
                    $event->stockChange
                );

                if ($result['success']) {
                    Log::info("Stock distribution completed successfully", [
                        'variation_id' => $event->variationId,
                        'total_distributed' => $result['total_distributed'],
                        'remaining_stock' => $result['remaining_stock'],
                    ]);
                } else {
                    Log::warning("Stock distribution failed", [
                        'variation_id' => $event->variationId,
                        'message' => $result['message'] ?? 'Unknown error',
                    ]);
                }
            } else {
                Log::info("Stock change is zero or negative, skipping distribution", [
                    'variation_id' => $event->variationId,
                    'stock_change' => $event->stockChange,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error distributing stock to marketplaces", [
                'variation_id' => $event->variationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
