<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RepairPart extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'repair_parts';

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'compatible_device',
        'on_hand',
        'reorder_level',
        'unit_cost',
        'active',
    ];

    protected $casts = [
        'active' => 'bool',
    ];

    public function product()
    {
        return $this->belongsTo(Products_model::class, 'product_id');
    }

    public function usages()
    {
        return $this->hasMany(RepairPartUsage::class);
    }

    public function batches()
    {
        return $this->hasMany(PartBatch::class, 'repair_part_id');
    }

    public function partsPurchases()
    {
        return $this->hasMany(PartsPurchase::class, 'repair_part_id');
    }

    public function brokenRecords()
    {
        return $this->hasMany(PartBrokenRecord::class, 'repair_part_id');
    }

    /** Total on hand across all batches */
    public function getOnHandFromBatchesAttribute(): int
    {
        return (int) $this->batches()->sum('quantity_remaining');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Generate a suggested system SKU for new parts (e.g. PART-20260219-0001).
     * Used on batch-receive page when no barcode is scanned.
     */
    public static function generateSuggestedSku(): string
    {
        $prefix = 'PART-' . date('Ymd') . '-';
        $last = static::withTrashed()
            ->where('sku', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($last && preg_match('/' . preg_quote($prefix, '/') . '(\d+)$/', $last->sku, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
