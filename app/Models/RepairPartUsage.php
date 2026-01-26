<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RepairPartUsage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'repair_part_usages';

    protected $fillable = [
        'process_id',
        'process_stock_id',
        'stock_id',
        'repair_part_id',
        'technician_id',
        'qty',
        'unit_cost',
        'total_cost',
        'notes',
    ];

    public function part()
    {
        return $this->belongsTo(RepairPart::class, 'repair_part_id');
    }

    public function process()
    {
        return $this->belongsTo(Process_model::class, 'process_id');
    }

    public function processStock()
    {
        return $this->belongsTo(Process_stock_model::class, 'process_stock_id');
    }

    public function stock()
    {
        return $this->belongsTo(Stock_model::class, 'stock_id');
    }

    public function technician()
    {
        return $this->belongsTo(Admin_model::class, 'technician_id');
    }
}
