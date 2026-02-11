<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListingThirtyOrder extends Model
{
    protected $table = 'listing_thirty_orders';

    protected $fillable = [
        'variation_id',
        'country_code',
        'bm_listing_id',
        'bm_listing_uuid',
        'sku',
        'source',
        'quantity',
        'publication_state',
        'state',
        'title',
        'price_amount',
        'price_currency',
        'min_price',
        'max_price',
        'payload_json',
        'synced_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'publication_state' => 'integer',
        'state' => 'integer',
        'price_amount' => 'decimal:2',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'payload_json' => 'array',
        'synced_at' => 'datetime',
    ];

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation_model::class, 'variation_id', 'id');
    }

    public function refs(): HasMany
    {
        return $this->hasMany(ListingThirtyOrderRef::class, 'listing_thirty_order_id', 'id');
    }
}
