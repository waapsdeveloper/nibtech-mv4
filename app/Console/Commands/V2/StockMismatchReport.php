<?php

namespace App\Console\Commands\V2;

use App\Models\Admin_model;
use App\Models\Listed_stock_verification_model;
use App\Models\Process_model;
use App\Models\V2\MarketplaceStockHistory;
use App\Models\V2\MarketplaceStockModel;
use App\Models\Variation_model;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class StockMismatchReport extends Command
{
    protected $signature = 'listing:stock-mismatch-report
                            {--limit=0 : Max number of variations to process (0 = all)}
                            {--page=1 : Page when using default sort}
                            {--apply : Apply reconciliation: update BM listed_stock and insert history so listed = available}';

    protected $description = 'Report variations where listed stock ≠ available stock (same as stock_mismatch=1). Use --apply to write history and fix listed. Writes to storage/logs/stock_mismatch_report.log';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $page = (int) $this->option('page');

        $logPath = storage_path('logs/stock_mismatch_report.log');
        $logDir = dirname($logPath);
        if (!File::isDirectory($logDir)) {
            File::makeDirectory($logDir, 0755, true);
        }
        $this->line('Log file: ' . $logPath);

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
        $apply = $this->option('apply');
        $logger = Log::channel('stock_mismatch_report');

        $logger->info('========== STOCK MISMATCH REPORT ' . now()->toDateTimeString() . ' ==========');
        $logger->info('Total variations matching stock_mismatch=1: ' . $total);
        $logger->info('This run: ' . $variations->count() . ' variation(s).' . ($apply ? ' [APPLY MODE]' : ''));
        $logger->info('');

        $appliedCount = 0;
        $index = 0;
        foreach ($variations as $variation) {
            $index++;
            $data = $this->getVariationMismatchData($variation);
            $this->logVariationOutcome($logger, $index, $variation, $data);
            if ($apply && $data['adjustmentToMatch'] != 0) {
                if ($this->applyReconciliation($variation, $data, $logger)) {
                    $appliedCount++;
                }
            }
        }

        $logger->info('');
        $logger->info('========== END REPORT ==========');
        if ($apply) {
            $logger->info("Applied reconciliation: {$appliedCount} variation(s) updated.");
        }

        $exists = File::exists($logPath);
        $this->info("Report written to storage/logs/stock_mismatch_report.log ({$variations->count()} items, total matching: {$total}).");
        if ($apply) {
            $this->info("Applied reconciliation to {$appliedCount} variation(s).");
        }
        $this->line('Full path: ' . $logPath);
        if ($exists) {
            $this->line('File exists: yes, size ' . File::size($logPath) . ' bytes.');
        } else {
            $this->warn('File was not created. Check write permissions on storage/logs/');
        }

        return 0;
    }

    /**
     * @return array{availableCount: int, totalStockDisplayed: int, totalListed: int, totalManual: int, adjustmentToMatch: int, bmListed: int|null, bmMarketplaceStock: MarketplaceStockModel|null, bmManual: int|null}
     */
    private function getVariationMismatchData(Variation_model $variation): array
    {
        $variation->loadMissing(['product', 'pending_orders', 'available_stocks']);
        $availableCount = $variation->available_stocks_count ?? $variation->available_stocks->count();
        $marketplaceStocks = MarketplaceStockModel::where('variation_id', $variation->id)->get();
        $totalListed = 0;
        $totalManual = 0;
        $bmListed = null;
        $bmMarketplaceStock = null;
        $bmManual = null;
        foreach ($marketplaceStocks as $ms) {
            $listed = (int) ($ms->listed_stock ?? 0);
            $manual = (int) ($ms->manual_adjustment ?? 0);
            $totalListed += $listed;
            $totalManual += $manual;
            if ((int) $ms->marketplace_id === 1) {
                $bmListed = $listed;
                $bmMarketplaceStock = $ms;
                $bmManual = $manual;
            }
        }
        $totalStockDisplayed = $totalListed + $totalManual;
        if ($totalStockDisplayed === 0 && $totalManual === 0) {
            $totalStockDisplayed = (int) ($variation->listed_stock ?? 0);
        }
        $adjustmentToMatch = $availableCount - $totalStockDisplayed;

        return [
            'availableCount' => $availableCount,
            'totalStockDisplayed' => $totalStockDisplayed,
            'totalListed' => $totalListed,
            'totalManual' => $totalManual,
            'adjustmentToMatch' => $adjustmentToMatch,
            'bmListed' => $bmListed,
            'bmMarketplaceStock' => $bmMarketplaceStock,
            'bmManual' => $bmManual,
        ];
    }

    private function logVariationOutcome($logger, int $index, Variation_model $variation, array $data): void
    {
        $availableCount = $data['availableCount'];
        $totalStockDisplayed = $data['totalStockDisplayed'];
        $adjustmentToMatch = $data['adjustmentToMatch'];
        $bmListed = $data['bmListed'];
        $bmMarketplaceStock = $data['bmMarketplaceStock'];
        $bmManual = $data['bmManual'];
        $totalListed = $data['totalListed'];
        $totalManual = $data['totalManual'];
        $pendingQty = $variation->pending_orders->sum('quantity');

        $verificationHistory = Listed_stock_verification_model::where('variation_id', $variation->id)
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        $marketplaceHistory = MarketplaceStockHistory::where('variation_id', $variation->id)
            ->where('marketplace_id', 1)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $logger->info("---------- #{$index} ----------");
        $logger->info("variation_id: {$variation->id} | sku: " . ($variation->sku ?? 'N/A') . " | product: " . ($variation->product->model ?? 'N/A'));
        $logger->info('');
        $logger->info("DIFFERENCE (what this variation has vs what we need):");
        $logger->info("  listed/displayed (card): {$totalStockDisplayed}");
        $logger->info("  available (physical):   {$availableCount}");
        $logger->info("  adjustment needed:      " . ($adjustmentToMatch >= 0 ? '+' : '') . "{$adjustmentToMatch}  (so listed becomes {$availableCount})");
        $logger->info("  → " . ($totalStockDisplayed != $availableCount ? 'MISMATCH' : 'ok'));
        $logger->info('');
        $logger->info("Current breakdown: variation.listed_stock=" . ($variation->listed_stock ?? 0) . " | BM listed_stock=" . ($bmListed !== null ? (string) $bmListed : 'no row') . " | manual_adjustment_sum={$totalManual} | pending_orders_sum={$pendingQty}");
        $logger->info('');

        $logger->info("Current status – listed_stock_verification (last " . $verificationHistory->count() . "):");
        if ($verificationHistory->isNotEmpty()) {
            foreach ($verificationHistory as $h) {
                $processRef = $h->process_id ? (Process_model::find($h->process_id)->reference_id ?? (string) $h->process_id) : '';
                $adminName = $h->admin_id ? (Admin_model::find($h->admin_id)->first_name ?? 'n/a') : 'n/a';
                $logger->info("  TopupRef: {$processRef} | PendingOrders: {$h->pending_orders} | QtyBefore: {$h->qty_from} | QtyAdded: {$h->qty_change} | QtyAfter: {$h->qty_to} | Admin: {$adminName} | Date: {$h->created_at}");
            }
        } else {
            $logger->info("  (none)");
        }
        $logger->info('');

        $logger->info("Current status – marketplace_stock_history BM (last " . $marketplaceHistory->count() . "):");
        if ($marketplaceHistory->isNotEmpty()) {
            foreach ($marketplaceHistory as $h) {
                $logger->info("  id: {$h->id} | listed_before: {$h->listed_stock_before} → listed_after: {$h->listed_stock_after} | quantity_change: {$h->quantity_change} | change_type: {$h->change_type} | {$h->created_at}");
            }
        } else {
            $logger->info("  (none)");
        }
        $logger->info('');

        $logger->info("ADJUSTMENT TO MATCH (add as history / manual so listed = available):");
        if ($totalStockDisplayed != $availableCount) {
            $logger->info("  Option A – Add reconciliation history record (marketplace_stock_history):");
            $logger->info("    variation_id: {$variation->id}");
            $logger->info("    marketplace_id: 1");
            $listedBefore = $bmListed ?? 0;
            $listedAfter = $availableCount;
            $qtyChange = $listedAfter - $listedBefore;
            $logger->info("    listed_stock_before: {$listedBefore} | listed_stock_after: {$listedAfter} | quantity_change: {$qtyChange} | change_type: reconciliation");
            if ($bmMarketplaceStock) {
                $logger->info("    marketplace_stock_id: {$bmMarketplaceStock->id}");
            }
            $logger->info("  Option B – Set manual_adjustment on BM marketplace_stock so total displayed = available:");
            $bmManualInt = (int) $bmManual;
            $manualNew = $bmManualInt + $adjustmentToMatch;
            $logger->info("    current manual_adjustment (BM): {$bmManualInt} → set to: {$manualNew} (add " . ($adjustmentToMatch >= 0 ? '+' : '') . "{$adjustmentToMatch})");
        } else {
            $logger->info("  No adjustment needed (listed = available).");
        }
        $logger->info('');
    }

    /**
     * Update BM marketplace_stock and insert reconciliation history so total displayed = available.
     * Returns true if applied, false on error.
     */
    private function applyReconciliation(Variation_model $variation, array $data, $logger): bool
    {
        $availableCount = $data['availableCount'];
        $adjustmentToMatch = $data['adjustmentToMatch'];
        $bmListed = (int) ($data['bmListed'] ?? 0);
        $bmMarketplaceStock = $data['bmMarketplaceStock'];

        $listedBefore = $bmListed;
        $listedAfter = $bmListed + $adjustmentToMatch;
        $quantityChange = $adjustmentToMatch;

        if ($listedAfter < 0) {
            $logger->warning("  [APPLY SKIP] variation_id {$variation->id}: listed_after would be {$listedAfter}, skipping.");
            return false;
        }

        try {
            DB::beginTransaction();

            if ($bmMarketplaceStock !== null) {
                $bmMarketplaceStock->listed_stock = $listedAfter;
                $bmMarketplaceStock->save();
                $marketplaceStockId = $bmMarketplaceStock->id;
            } else {
                $bmMarketplaceStock = MarketplaceStockModel::create([
                    'variation_id' => $variation->id,
                    'marketplace_id' => 1,
                    'listed_stock' => $listedAfter,
                ]);
                $marketplaceStockId = $bmMarketplaceStock->id;
            }

            $historyRecord = MarketplaceStockHistory::create([
                'marketplace_stock_id' => $marketplaceStockId,
                'variation_id' => $variation->id,
                'marketplace_id' => 1,
                'listed_stock_before' => $listedBefore,
                'listed_stock_after' => $listedAfter,
                'locked_stock_before' => 0,
                'locked_stock_after' => 0,
                'available_stock_before' => max(0, $listedBefore),
                'available_stock_after' => max(0, $listedAfter),
                'quantity_change' => $quantityChange,
                'change_type' => 'reconciliation',
                'notes' => 'Stock mismatch report apply: listed set to match available (listing:stock-mismatch-report --apply)',
            ]);

            Listed_stock_verification_model::create([
                'variation_id' => $variation->id,
                'pending_orders' => 0,
                'qty_from' => $listedBefore,
                'qty_change' => $quantityChange,
                'qty_to' => $listedAfter,
                'process_id' => null,
                'admin_id' => null,
            ]);

            $variation->listed_stock = $listedAfter;
            $variation->save();

            DB::commit();
            $logger->info("  [APPLIED] variation_id {$variation->id}: listed_stock {$listedBefore} → {$listedAfter}. History: marketplace_stock_history id={$historyRecord->id}, listed_stock_verification record added.");
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            $logger->error("  [APPLY FAILED] variation_id {$variation->id}: " . $e->getMessage());
            return false;
        }
    }
}
