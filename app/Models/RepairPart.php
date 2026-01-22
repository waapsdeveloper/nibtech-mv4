<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RepairPart extends Model
{
    use HasFactory, SoftDeletes;

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
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function usages()
    {
        return $this->hasMany(RepairPartUsage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
