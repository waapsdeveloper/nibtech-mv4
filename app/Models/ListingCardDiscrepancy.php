<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per variation where Stock (listed), Available (card), and Stocks table count are not all equal.
 */
class ListingCardDiscrepancy extends Model
{
    protected $table = 'listing_card_discrepancies';

    protected $fillable = [
        'variation_id',
        'listed_stock',
        'available_count',
        'stocks_table_count',
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
