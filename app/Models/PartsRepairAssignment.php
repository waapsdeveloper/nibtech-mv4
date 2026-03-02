<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartsRepairAssignment extends Model
{
    protected $table = 'parts_repair_assignments';

    protected $fillable = [
        'stock_id',
        'repair_part_id',
        'part_batch_id',
        'unit_cost',
        'reference_id',
        'customer_id',
        'assigned_at',
        'repaired_at',
        'notes',
        'admin_id',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'repaired_at' => 'datetime',
        'unit_cost' => 'decimal:2',
    ];

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock_model::class, 'stock_id');
    }

    public function repairPart(): BelongsTo
    {
        return $this->belongsTo(RepairPart::class, 'repair_part_id');
    }

    public function partBatch(): BelongsTo
    {
        return $this->belongsTo(PartBatch::class, 'part_batch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer_model::class, 'customer_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin_model::class, 'admin_id');
    }

    public function isRepaired(): bool
    {
        return $this->repaired_at !== null;
    }

    /**
     * Unique repair barcode: RPR-{id}-{imei_or_ref} so scanning/filtering finds this record.
     * Alphanumeric only for Code 128. Used for print repair barcode and filter lookup.
     */
    public function getRepairBarcodeAttribute(): string
    {
        $suffix = '';
        if ($this->relationLoaded('stock') && $this->stock) {
            $s = $this->stock;
            $suffix = preg_replace('/[^A-Za-z0-9]/', '', ($s->imei ?? '') . ($s->serial_number ?? ''));
        }
        if ($suffix === '' && $this->reference_id) {
            $suffix = preg_replace('/[^A-Za-z0-9]/', '', substr($this->reference_id, 0, 20));
        }
        if ($suffix === '') {
            $suffix = 'R' . $this->id;
        }
        return 'RPR-' . $this->id . '-' . substr($suffix, 0, 24);
    }
}
