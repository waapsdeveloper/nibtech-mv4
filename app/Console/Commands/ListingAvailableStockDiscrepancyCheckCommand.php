<?php

namespace App\Console\Commands;

use App\Models\ListingAvailableStockDiscrepancy;
use App\Models\Order_item_model;
use App\Models\Process_stock_model;
use App\Models\Stock_model;
use App\Models\Variation_model;
use Illuminate\Console\Command;

/**
 * Compare per-variation "Listed" (variation.listed_stock) vs "Should Be" using the same
 * logic as the dashboard widget: for each variation with grade < 6, should_be = (stock count
 * grade < 6, status 1, excluding aftersale) - (process_stock in process type 22, status < 3)
 * - (pending marketplace order items quantity). Records discrepancies in listing_available_stock_discrepancies.
 */
class ListingAvailableStockDiscrepancyCheckCommand extends Command
{
    protected $signature = 'listing:available-stock-discrepancy-check
                            {--chunk=500 : Number of variations per chunk}';

    protected $description = 'Dashboard Listed vs Should Be: find variations where listed_stock != should_be (grade<6) and store in listing_available_stock_discrepancies';

    public function handle(): int
    {
        $this->info('Running dashboard Listed vs Should Be discrepancy check...');

        $chunkSize = (int) $this->option('chunk');
        $now = now();

        $aftersaleStockIds = Order_item_model::query()
            ->whereHas('order', function ($query) {
                $query->where('order_type_id', 4)->where('status', '<', 3);
            })
            ->pluck('stock_id')
            ->toArray();

        $checked = 0;
        $discrepanciesFound = 0;

        Variation_model::query()
            ->where('grade', '<', 6)
            ->select(['id', 'sku', 'grade', 'listed_stock'])
            ->chunk($chunkSize, function ($variations) use ($aftersaleStockIds, $now, &$checked, &$discrepanciesFound) {
                foreach ($variations as $variation) {
                    $listedStock = (int) ($variation->listed_stock ?? 0);
                    $shouldBe = $this->computeShouldBeForVariation($variation->id, $aftersaleStockIds);
                    $difference = $listedStock - $shouldBe;

                    if ($difference !== 0) {
                        ListingAvailableStockDiscrepancy::updateOrCreate(
                            ['variation_id' => $variation->id],
                            [
                                'listed_stock' => $listedStock,
                                'should_be' => $shouldBe,
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

        // Remove discrepancy rows for variations that no longer exist or are grade >= 6
        $deleted = ListingAvailableStockDiscrepancy::whereHas('variation', function ($q) {
            $q->where('grade', '>=', 6);
        })->delete();

        if ($deleted > 0) {
            $this->info("Removed {$deleted} records for variations with grade >= 6.");
        }

        $this->info("Checked {$checked} variations (grade < 6). Discrepancies recorded: {$discrepanciesFound}.");

        return self::SUCCESS;
    }

    /**
     * Per-variation "should be" = (stock count in grade < 6, status 1, not aftersale)
     * - (process_stock in process type 22, status < 3) - (pending order items qty for this variation).
     */
    private function computeShouldBeForVariation(int $variationId, array $aftersaleStockIds): int
    {
        $stockCount = Stock_model::query()
            ->where('variation_id', $variationId)
            ->where('status', 1)
            ->when(! empty($aftersaleStockIds), function ($query) use ($aftersaleStockIds) {
                $query->whereNotIn('id', $aftersaleStockIds);
            })
            ->count();

        $inProcessType22 = Process_stock_model::query()
            ->whereHas('stock', function ($q) use ($variationId) {
                $q->where('variation_id', $variationId);
            })
            ->whereHas('process', function ($query) {
                $query->where('process_type_id', 22)->where('status', '<', 3);
            })
            ->count();

        $pendingOrderItemsQty = Order_item_model::query()
            ->where('variation_id', $variationId)
            ->whereHas('order', function ($query) {
                $query->where('status', 2)->where('order_type_id', 3);
            })
            ->sum('quantity');

        return max(0, $stockCount - $inProcessType22 - (int) $pendingOrderItemsQty);
    }
}
