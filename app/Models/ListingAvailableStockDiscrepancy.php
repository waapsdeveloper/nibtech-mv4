<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per variation where "Available" (card) count != stocks table count.
 */
class ListingAvailableStockDiscrepancy extends Model
{
    protected $table = 'listing_available_stock_discrepancies';

    protected $fillable = [
        'variation_id',
        'available_count',
        'stocks_table_count',
        'difference',
        'variation_sku',
        'detected_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
    ];

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation_model::class, 'variation_id', 'id');
    }
}
