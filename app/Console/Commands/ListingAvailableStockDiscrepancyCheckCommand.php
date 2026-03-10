<?php

namespace App\Console\Commands;

use App\Models\ListingAvailableStockDiscrepancy;
use App\Models\Stock_model;
use App\Models\Variation_model;
use Illuminate\Console\Command;

class ListingAvailableStockDiscrepancyCheckCommand extends Command
{
    protected $signature = 'listing:available-stock-discrepancy-check
                            {--chunk=500 : Number of variations per chunk}';

    protected $description = 'Compare Available (card) vs stocks table count per variation and store discrepancies in listing_available_stock_discrepancies';

    public function handle(): int
    {
        $this->info('Running listing available vs stocks table count check...');

        $chunkSize = (int) $this->option('chunk');
        $now = now();
        $checked = 0;
        $discrepanciesFound = 0;

        Variation_model::query()
            ->whereNotNull('sku')
            ->select(['id', 'sku'])
            ->chunk($chunkSize, function ($variations) use ($now, &$checked, &$discrepanciesFound) {
                foreach ($variations as $variation) {
                    $availableCount = $this->getAvailableCount($variation->id);
                    $stocksTableCount = $this->getStocksTableCount($variation->id);
                    $difference = $stocksTableCount - $availableCount;

                    if ($difference !== 0) {
                        ListingAvailableStockDiscrepancy::updateOrCreate(
                            ['variation_id' => $variation->id],
                            [
                                'available_count' => $availableCount,
                                'stocks_table_count' => $stocksTableCount,
                                'difference' => $difference,
                                'variation_sku' => $variation->sku,
                                'detected_at' => $now,
                            ]
                        );
                        $discrepanciesFound++;
                    } else {
                        ListingAvailableStockDiscrepancy::where('variation_id', $variation->id)->delete();
                    }
                    $checked++;
                }
            });

        $this->info("Checked {$checked} variations. Discrepancies recorded: {$discrepanciesFound}.");

        return self::SUCCESS;
    }

    /**
     * Count matching Variation's available_stocks (same as card).
     */
    private function getAvailableCount(int $variationId): int
    {
        return Stock_model::query()
            ->where('variation_id', $variationId)
            ->where('status', 1)
            ->whereHas('active_order')
            ->whereHas('latest_listing_or_topup')
            ->count();
    }

    /**
     * Count matching get_variation_available_stocks (stocks table in listing card details).
     * Must use same scope as ListingController::get_variation_available_stocks so pagination.total matches.
     */
    private function getStocksTableCount(int $variationId): int
    {
        return Stock_model::query()
            ->where('variation_id', $variationId)
            ->where('status', 1)
            ->whereHas('latest_closed_listing_or_topup')
            ->count();
    }
}
