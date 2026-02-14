<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartBatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'part_batches';

    protected $fillable = [
        'repair_part_id',
        'batch_number',
        'quantity_received',
        'quantity_remaining',
        'quantity_purchased',
        'unit_cost',
        'total_cost',
        'received_at',
        'purchase_date',
        'supplier',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'date',
        'purchase_date' => 'date',
        'quantity_received' => 'integer',
        'quantity_remaining' => 'integer',
        'quantity_purchased' => 'integer',
    ];

    public function repairPart()
    {
        return $this->belongsTo(RepairPart::class, 'repair_part_id');
    }

    public function usages()
    {
        return $this->hasMany(RepairPartUsage::class, 'batch_id');
    }

    /** Parts with stock (quantity_remaining > 0) */
    public function scopeInStock($query)
    {
        return $query->where('quantity_remaining', '>', 0);
    }
}
