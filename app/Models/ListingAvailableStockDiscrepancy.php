<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per variation where "Listed" (dashboard Total Listed) differs from "Should Be"
 * (same formula as dashboard widget: graded stock grade<6 - process type 22 - pending order items).
 * Draft board: fix sets variation.listed_stock to should_be and pushes to Back Market.
 */
class ListingAvailableStockDiscrepancy extends Model
{
    protected $table = 'listing_available_stock_discrepancies';

    protected $fillable = [
        'variation_id',
        'listed_stock',
        'should_be',
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
