<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartsPurchase extends Model
{
    protected $table = 'parts_purchases';

    protected $fillable = [
        'stock_id',
        'repair_part_id',
        'quantity',
        'unit_price',
        'is_lease',
        'price_set_at',
        'notes',
        'admin_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'is_lease' => 'boolean',
        'price_set_at' => 'datetime',
    ];

    public function stock()
    {
        return $this->belongsTo(Stock_model::class, 'stock_id');
    }

    public function repairPart()
    {
        return $this->belongsTo(RepairPart::class, 'repair_part_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin_model::class, 'admin_id');
    }

    /** Total cost (unit_price * quantity), or null if price not set */
    public function getTotalPriceAttribute(): ?float
    {
        if ($this->unit_price === null) {
            return null;
        }
        return (float) $this->unit_price * (int) $this->quantity;
    }
}
