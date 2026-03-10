<?php

namespace App\Console\Commands;

use App\Models\ListingCardDiscrepancy;
use App\Models\Stock_model;
use App\Models\Variation_model;
use Illuminate\Console\Command;

/**
 * Find variations where Stock (listed), Available (card), and Stocks table count don't all match.
 * Records them in listing_card_discrepancies for the draft board.
 */
class ListingCardDiscrepancyCheckCommand extends Command
{
    protected $signature = 'listing:listing-card-discrepancy-check
                            {--chunk=500 : Chunk size}';

    protected $description = 'Find variations where Listed (Stock), Available (card), and Stocks table count differ; store in listing_card_discrepancies';

    public function handle(): int
    {
        $this->info('Running listing card mismatch check (Stock vs Available vs Stocks table)...');

        $chunkSize = (int) $this->option('chunk');
        $now = now();
        $checked = 0;
        $found = 0;

        Variation_model::query()
            ->select(['id', 'sku', 'listed_stock'])
            ->chunk($chunkSize, function ($variations) use ($now, &$checked, &$found) {
                foreach ($variations as $variation) {
                    $listed = (int) ($variation->listed_stock ?? 0);
                    $available = $this->getAvailableCount($variation->id);
                    $stocksTable = Stock_model::countForListingStocksTable($variation->id);

                    $allEqual = ($listed === $available && $available === $stocksTable);
                    if (! $allEqual) {
                        ListingCardDiscrepancy::updateOrCreate(
                            ['variation_id' => $variation->id],
                            [
                                'listed_stock' => $listed,
                                'available_count' => $available,
                                'stocks_table_count' => $stocksTable,
                                'variation_sku' => $variation->sku,
                                'detected_at' => $now,
                            ]
                        );
                        $found++;
                    } else {
                        ListingCardDiscrepancy::where('variation_id', $variation->id)->delete();
                    }
                    $checked++;
                }
            });

        $this->info("Checked {$checked} variations. Listing card mismatches: {$found}.");

        return self::SUCCESS;
    }

    /**
     * Same as listing card "Available": status=1, active_order, latest_listing_or_topup.
     */
    private function getAvailableCount(int $variationId): int
    {
        return (int) Stock_model::query()
            ->where('variation_id', $variationId)
            ->where('status', 1)
            ->whereHas('active_order')
            ->whereHas('latest_listing_or_topup')
            ->count();
    }
}
