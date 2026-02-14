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
        'assigned_at',
        'repaired_at',
        'notes',
        'admin_id',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'repaired_at' => 'datetime',
    ];

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock_model::class, 'stock_id');
    }

    public function repairPart(): BelongsTo
    {
        return $this->belongsTo(RepairPart::class, 'repair_part_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin_model::class, 'admin_id');
    }

    public function isRepaired(): bool
    {
        return $this->repaired_at !== null;
    }
}
