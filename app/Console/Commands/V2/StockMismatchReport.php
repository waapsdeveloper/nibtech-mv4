<?php

namespace App\Console\Commands\V2;

use App\Models\Listed_stock_verification_model;
use App\Models\Process_model;
use App\Models\V2\MarketplaceStockModel;
use App\Models\Variation_model;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class StockMismatchReport extends Command
{
    protected $signature = 'listing:stock-mismatch-report';

    protected $description = 'Report variations where topup ref 9014 qty_to does not match the next verification record qty_from. Writes to storage/logs/stock_mismatch_report.log (cleared each run).';

    public function handle(): int
    {
        $logPath = storage_path('logs/stock_mismatch_report.log');
        $logDir = dirname($logPath);
        if (!File::isDirectory($logDir)) {
            File::makeDirectory($logDir, 0755, true);
        }
        // Clear log file on each run
        if (File::exists($logPath)) {
            File::put($logPath, '');
        }
        $this->line('Log file: ' . $logPath);

        // --- Focus: topup ref 9014 vs next verification record (qty_after 9014 vs qty_before of next) ---
        $mismatchVariations = $this->getTopup9014VerificationMismatches();
        $logger = Log::channel('stock_mismatch_report');
        $logger->info('========== STOCK MISMATCH REPORT (Topup 9014 vs next verification) ' . now()->toDateTimeString() . ' ==========');
        $toLog = $mismatchVariations->filter(function ($row) {
            if (! empty($row->edited_by_admin)) {
                return false;
            }
            if (isset($row->difference, $row->total_stock) && $row->difference <= $row->total_stock) {
                return false;
            }
            return true;
        });
        $logger->info('Variations where qty_to (after 9014) != qty_from (before next record) and (available - pending) > total_stock: ' . $toLog->count() . ' (omitted: edited by admin, or (available - pending) <= total stock)');
        $logger->info('');
        foreach ($toLog as $row) {
            $diffStr = ($row->mismatch_amount >= 0 ? '+' : '') . $row->mismatch_amount;
            $logger->info("sku: " . ($row->sku ?? 'N/A') . " | available: {$row->available_count} | pending: {$row->pending} | (available - pending): {$row->difference} | total_stock: {$row->total_stock} | TopupRef 9014 id: {$row->topup_id} qty_to: {$row->topup_qty_to} | Next id: {$row->next_id} qty_from: {$row->next_qty_from} | MISMATCH (diff: {$diffStr})");
        }
        $logger->info('');
        $logger->info('========== END REPORT ==========');

        $this->info('Report written to storage/logs/stock_mismatch_report.log. Variations logged: ' . $toLog->count());
        $this->line('Full path: ' . $logPath);
        return 0;
    }

    /**
     * Variations that have a topup ref 9014 record and immediately after a verification record,
     * where topup.qty_to != next_record.qty_from.
     *
     * @return \Illuminate\Support\Collection<object{variation_id: int, sku: string|null, topup_id: int, topup_qty_to: int, next_id: int, next_qty_from: int, mismatch_amount: int, edited_by_admin: bool, available_count: int, pending: int, difference: int, total_stock: int}>
     */
    private function getTopup9014VerificationMismatches(): \Illuminate\Support\Collection
    {
        $processIds9014 = Process_model::where('reference_id', '9014')->pluck('id');
        if ($processIds9014->isEmpty()) {
            return collect();
        }

        $topupRecords = Listed_stock_verification_model::query()
            ->whereIn('process_id', $processIds9014)
            ->orderBy('variation_id')
            ->orderBy('id')
            ->get();

        $variationIds = $topupRecords->pluck('variation_id')->unique()->values();
        if ($variationIds->isEmpty()) {
            return collect();
        }

        $allForVariations = Listed_stock_verification_model::query()
            ->whereIn('variation_id', $variationIds)
            ->orderBy('variation_id')
            ->orderBy('id')
            ->get();

        $variations = Variation_model::query()
            ->whereIn('id', $variationIds)
            ->with(['product', 'pending_orders'])
            ->withCount('available_stocks')
            ->get()
            ->keyBy('id');

        $totalStockByVariation = MarketplaceStockModel::query()
            ->whereIn('variation_id', $variationIds)
            ->get()
            ->groupBy('variation_id')
            ->map(function ($rows) {
                return $rows->sum(fn ($r) => (int) ($r->listed_stock ?? 0) + (int) ($r->manual_adjustment ?? 0));
            });

        $mismatches = collect();
        foreach ($allForVariations->groupBy('variation_id') as $variationId => $records) {
            $sorted = $records->sortBy('id')->values();
            for ($i = 0; $i < $sorted->count() - 1; $i++) {
                $current = $sorted[$i];
                $next = $sorted[$i + 1];
                if (! $processIds9014->contains($current->process_id)) {
                    continue;
                }
                if ((int) $current->qty_to !== (int) $next->qty_from) {
                    $variation = $variations->get($variationId);
                    $topupQty = (int) $current->qty_to;
                    $nextQty = (int) $next->qty_from;
                    $editedByAdmin = $sorted->where('id', '>', $current->id)->contains('admin_id', 1);
                    $availableCount = (int) ($variation->available_stocks_count ?? 0);
                    $pending = (int) $variation->pending_orders->sum('quantity');
                    $difference = $availableCount - $pending;
                    $totalStock = (int) ($totalStockByVariation->get($variationId) ?? $variation->listed_stock ?? 0);
                    $mismatches->push((object) [
                        'variation_id' => $variationId,
                        'sku' => $variation->sku ?? null,
                        'topup_id' => $current->id,
                        'topup_qty_to' => $topupQty,
                        'next_id' => $next->id,
                        'next_qty_from' => $nextQty,
                        'mismatch_amount' => $topupQty - $nextQty,
                        'edited_by_admin' => $editedByAdmin,
                        'available_count' => $availableCount,
                        'pending' => $pending,
                        'difference' => $difference,
                        'total_stock' => $totalStock,
                    ]);
                }
                break; // one 9014 record per variation in this flow; skip rest
            }
        }

        return $mismatches;
    }

}
