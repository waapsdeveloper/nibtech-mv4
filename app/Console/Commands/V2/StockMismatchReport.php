<?php

namespace App\Console\Commands\V2;

use App\Models\Listed_stock_verification_model;
use App\Models\Process_model;
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
        $logger->info('Variations where qty_to (after 9014) != qty_from (before next record): ' . $mismatchVariations->count());
        $logger->info('');
        foreach ($mismatchVariations as $row) {
            $logger->info("variation_id: {$row->variation_id} | sku: " . ($row->sku ?? 'N/A') . " | TopupRef 9014 id: {$row->topup_id} qty_to: {$row->topup_qty_to} | Next verification id: {$row->next_id} qty_from: {$row->next_qty_from} | MISMATCH");
        }
        $logger->info('');
        $logger->info('========== END REPORT ==========');

        $this->info('Report written to storage/logs/stock_mismatch_report.log. Variations with 9014/next qty mismatch: ' . $mismatchVariations->count());
        $this->line('Full path: ' . $logPath);
        return 0;
    }

    /**
     * Variations that have a topup ref 9014 record and immediately after a verification record,
     * where topup.qty_to != next_record.qty_from.
     *
     * @return \Illuminate\Support\Collection<object{variation_id: int, sku: string|null, topup_id: int, topup_qty_to: int, next_id: int, next_qty_from: int}>
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
            ->with('product')
            ->get()
            ->keyBy('id');

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
                    $mismatches->push((object) [
                        'variation_id' => $variationId,
                        'sku' => $variation->sku ?? null,
                        'topup_id' => $current->id,
                        'topup_qty_to' => (int) $current->qty_to,
                        'next_id' => $next->id,
                        'next_qty_from' => (int) $next->qty_from,
                    ]);
                }
                break; // one 9014 record per variation in this flow; skip rest
            }
        }

        return $mismatches;
    }

}
