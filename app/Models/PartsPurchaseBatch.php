<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartsPurchaseBatch extends Model
{
    protected $table = 'parts_purchase_batches';

    protected $fillable = [
        'system_barcode',
        'manufacturer_barcode',
        'notes',
    ];

    public function purchases()
    {
        return $this->hasMany(PartsPurchase::class, 'batch_id');
    }

    /**
     * Generate a unique system barcode for a new batch.
     * Format: PPB-YYYYMMDD-NNNN (e.g. PPB-20260218-0001)
     */
    public static function generateSystemBarcode(): string
    {
        $prefix = 'PPB-' . date('Ymd') . '-';
        $last = static::where('system_barcode', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $seq = $last ? (int) substr($last->system_barcode, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
