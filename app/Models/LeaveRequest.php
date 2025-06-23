<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;
    protected $fillable = ['admin_id', 'start_date', 'end_date', 'leave_type', 'reason', 'status'];

    public function admin()
    {
        return $this->belongsTo(Admin_model::class, 'admin_id', 'id');
    }
}
