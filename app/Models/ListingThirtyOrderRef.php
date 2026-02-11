<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingThirtyOrderRef extends Model
{
    protected $table = 'listing_thirty_order_refs';

    protected $fillable = [
        'listing_thirty_order_id',
        'order_id',
        'order_item_id',
        'variation_id',
        'bm_order_id',
        'source_command',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function listingThirtyOrder(): BelongsTo
    {
        return $this->belongsTo(ListingThirtyOrder::class, 'listing_thirty_order_id', 'id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order_model::class, 'order_id', 'id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(Order_item_model::class, 'order_item_id', 'id');
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation_model::class, 'variation_id', 'id');
    }
}
