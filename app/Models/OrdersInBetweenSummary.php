<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row summary of listed-stock:orders-in-between job runs.
 * One record is kept and updated on each run.
 */
class OrdersInBetweenSummary extends Model
{
    protected $table = 'orders_in_between_summary';

    protected $fillable = [
        'last_run_at',
        'total_updated',
        'days_back',
        'backfill_only',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
        'backfill_only' => 'boolean',
    ];

    /**
     * Get the single summary row (create with defaults if missing).
     */
    public static function getSummary(): self
    {
        $row = self::first();
        if ($row) {
            return $row;
        }
        return self::create([
            'last_run_at' => null,
            'total_updated' => 0,
            'days_back' => 30,
            'backfill_only' => true,
        ]);
    }
}
