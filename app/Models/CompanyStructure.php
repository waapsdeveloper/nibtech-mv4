<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyStructure extends Model
{
    use HasFactory;
    protected $fillable = ['type', 'name', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Scope methods for easy access

    public function scopeShifts($query)
    {
        return $query->where('type', 'shift');
    }

    public function scopeLeavePolicies($query)
    {
        return $query->where('type', 'leave_policy');
    }

    public function scopePayTypes($query)
    {
        return $query->where('type', 'pay_type');
    }
}
