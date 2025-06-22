<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;
    protected $fillable = ['admin_id', 'date', 'clock_in', 'clock_out'];

    public function admin()
    {
        return $this->belongsTo(Admin_model::class, 'admin_id', 'id');
    }
    public function dailyBreaks()
    {
        return $this->hasMany(DailyBreak::class);
    }
}
