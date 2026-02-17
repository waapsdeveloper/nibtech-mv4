<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartBrokenRecord extends Model
{
    protected $table = 'part_broken_records';

    protected $fillable = [
        'repair_part_id',
        'part_batch_id',
        'quantity',
        'notes',
        'responsible_person',
        'admin_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function repairPart(): BelongsTo
    {
        return $this->belongsTo(RepairPart::class, 'repair_part_id');
    }

    public function partBatch(): BelongsTo
    {
        return $this->belongsTo(PartBatch::class, 'part_batch_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin_model::class, 'admin_id');
    }
}
