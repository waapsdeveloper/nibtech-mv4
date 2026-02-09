<?php

namespace App\Console\Commands\V2;

use App\Models\Admin_model;
use App\Models\Listed_stock_verification_model;
use App\Models\Process_model;
use App\Models\V2\MarketplaceStockModel;
use App\Models\Variation_model;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StockMismatchReport extends Command
{
    protected $signature = 'listing:stock-mismatch-report
                            {--limit=0 : Max number of variations to process (0 = all)}
                            {--page=1 : Page when using default sort}';

    protected $description = 'Report variations where listed stock ≠ available stock (same as stock_mismatch=1). Writes to storage/logs/stock_mismatch_report.log';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $page = (int) $this->option('page');

        $query = Variation_model::query()
            ->with(['product', 'available_stocks', 'pending_orders'])
            ->withCount('available_stocks')
            ->whereIn('state', [2, 3])
            ->whereNotNull('sku')
            ->havingRaw('variation.listed_stock != available_stocks_count OR (SELECT COALESCE(ms.listed_stock, 0) FROM marketplace_stock ms WHERE ms.variation_id = variation.id AND ms.marketplace_id = 1 AND ms.deleted_at IS NULL LIMIT 1) != available_stocks_count');

        $total = (clone $query)->count();
        if ($limit > 0) {
            $query->limit($limit);
        }
        $query->orderBy('variation.id');
        if ($limit <= 0 && $page > 1) {
            $query->offset(($page - 1) * 10);
            $query->limit(10);
        } elseif ($limit <= 0) {
            $query->limit(500);
        }

        $variations = $query->get();
        $logger = Log::channel('stock_mismatch_report');

        $logger->info('========== STOCK MISMATCH REPORT ' . now()->toDateTimeString() . ' ==========');
        $logger->info('Total variations matching stock_mismatch=1: ' . $total);
        $logger->info('This run: ' . $variations->count() . ' variation(s).');
        $logger->info('');

        $index = 0;
        foreach ($variations as $variation) {
            $index++;
            $this->logVariationOutcome($logger, $index, $variation);
        }

        $logger->info('');
        $logger->info('========== END REPORT ==========');

        $this->info("Report written to storage/logs/stock_mismatch_report.log ({$variations->count()} items, total matching: {$total}).");

        return 0;
    }

    private function logVariationOutcome($logger, int $index, Variation_model $variation): void
    {
        $variation->loadMissing(['product', 'pending_orders']);
        $availableCount = $variation->available_stocks_count ?? $variation->available_stocks->count();
        $pendingQty = $variation->pending_orders->sum('quantity');
        $difference = $availableCount - $pendingQty;

        $marketplaceStocks = MarketplaceStockModel::where('variation_id', $variation->id)->get();
        $totalListed = 0;
        $totalManual = 0;
        $bmListed = null;
        foreach ($marketplaceStocks as $ms) {
            $listed = (int) ($ms->listed_stock ?? 0);
            $manual = (int) ($ms->manual_adjustment ?? 0);
            $totalListed += $listed;
            $totalManual += $manual;
            if ((int) $ms->marketplace_id === 1) {
                $bmListed = $listed;
            }
        }
        $totalStockDisplayed = $totalListed + $totalManual;
        if ($totalStockDisplayed === 0 && $totalManual === 0) {
            $totalStockDisplayed = (int) ($variation->listed_stock ?? 0);
        }

        $history = Listed_stock_verification_model::where('variation_id', $variation->id)
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        $logger->info("---------- #{$index} ----------");
        $logger->info("variation_id: {$variation->id}");
        $logger->info("sku: " . ($variation->sku ?? 'N/A'));
        $logger->info("product: " . ($variation->product->model ?? 'N/A'));
        $logger->info("variation.listed_stock: " . ($variation->listed_stock ?? 0));
        $logger->info("marketplace_stock BM (mp=1) listed_stock: " . ($bmListed !== null ? (string) $bmListed : 'no row'));
        $logger->info("total_stock_displayed (card): {$totalStockDisplayed}");
        $logger->info("manual_adjustment_sum: {$totalManual}");
        $logger->info("available_stocks_count: {$availableCount}");
        $logger->info("pending_orders_sum: {$pendingQty}");
        $logger->info("AV (available): {$availableCount} | PO (pending): {$pendingQty} | DF (difference): {$difference}");
        $logger->info("outcome: listed/displayed={$totalStockDisplayed} vs physical_available={$availableCount} " . ($totalStockDisplayed != $availableCount ? ' MISMATCH' : ' ok'));

        if ($history->isNotEmpty()) {
            $logger->info("history (last " . $history->count() . "):");
            foreach ($history as $h) {
                $processRef = $h->process_id ? (Process_model::find($h->process_id)->reference_id ?? (string) $h->process_id) : '';
                $adminName = $h->admin_id ? (Admin_model::find($h->admin_id)->first_name ?? 'n/a') : 'n/a';
                $logger->info("  TopupRef: {$processRef} | PendingOrders: {$h->pending_orders} | QtyBefore: {$h->qty_from} | QtyAdded: {$h->qty_change} | QtyAfter: {$h->qty_to} | Admin: {$adminName} | Date: {$h->created_at}");
            }
        } else {
            $logger->info("history: (none)");
        }
        $logger->info('');
    }
}
