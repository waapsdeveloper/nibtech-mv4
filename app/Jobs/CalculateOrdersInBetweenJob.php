<?php

namespace App\Jobs;

use App\Models\Listed_stock_verification_model;
use App\Models\OrdersInBetweenSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Calculates orders_in_between for listed_stock_verification records.
 * For each verification, counts marketplace orders (order_type_id = 3) that contain
 * the same variation and were created between the previous verification and this one.
 */
class CalculateOrdersInBetweenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Process at most this many verification records per run */
    public const CHUNK_SIZE = 500;

    /** Only consider verifications created in the last N days (null = no limit) */
    public ?int $daysBack;

    /** Only update records where orders_in_between is 0 (backfill); true by default */
    public bool $backfillOnly = true;

    public $tries = 2;
    public $timeout = 1800; // 30 min

    public function __construct(?int $daysBack = 30, bool $backfillOnly = true)
    {
        $this->daysBack = $daysBack;
        $this->backfillOnly = $backfillOnly;
    }

    public function handle(): void
    {
        $query = Listed_stock_verification_model::query()
            ->orderBy('id');

        if ($this->daysBack !== null) {
            $query->where('created_at', '>=', now()->subDays($this->daysBack));
        }

        if ($this->backfillOnly) {
            $query->where(function ($q) {
                $q->whereNull('orders_in_between')
                    ->orWhere('orders_in_between', 0);
            });
        }

        $updated = 0;
        $query->chunk(self::CHUNK_SIZE, function ($verifications) use (&$updated) {
            foreach ($verifications as $verification) {
                $count = $this->countOrdersInBetween($verification);
                if ($count !== null) {
                    $verification->orders_in_between = $count;
                    $verification->saveQuietly();
                    $updated++;
                }
            }
        });

        Log::info('CalculateOrdersInBetweenJob: completed', [
            'updated' => $updated,
            'days_back' => $this->daysBack,
            'backfill_only' => $this->backfillOnly,
        ]);

        $summary = OrdersInBetweenSummary::getSummary();
        $summary->update([
            'last_run_at' => now(),
            'total_updated' => $updated,
            'days_back' => $this->daysBack,
            'backfill_only' => $this->backfillOnly,
        ]);
    }

    /**
     * Count distinct marketplace orders for this variation between previous verification and this one.
     *
     * @return int|null Count, or null on error
     */
    protected function countOrdersInBetween(Listed_stock_verification_model $verification): ?int
    {
        $variationId = $verification->variation_id;
        $thisCreatedAt = $verification->created_at;

        $previous = Listed_stock_verification_model::where('variation_id', $variationId)
            ->where('created_at', '<', $thisCreatedAt)
            ->orderByDesc('created_at')
            ->first();

        $from = $previous ? $previous->created_at : null;

        try {
            $q = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.variation_id', $variationId)
                ->where('orders.order_type_id', 3)
                ->where('orders.created_at', '<=', $thisCreatedAt);

            if ($from !== null) {
                $q->where('orders.created_at', '>', $from);
            }

            return (int) $q->distinct()->count('orders.id');
        } catch (\Throwable $e) {
            Log::warning('CalculateOrdersInBetweenJob: count failed', [
                'verification_id' => $verification->id,
                'variation_id' => $variationId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
