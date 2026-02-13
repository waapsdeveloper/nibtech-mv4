<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PermissionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'permission',
        'status',
        'request_type',
        'expires_at',
        'approved_by',
        'note',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin_model::class, 'admin_id');
    }

    public function approver()
    {
        return $this->belongsTo(Admin_model::class, 'approved_by');
    }
}
