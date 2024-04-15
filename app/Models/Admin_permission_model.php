<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Admin_permission_model extends Model
{
    use HasFactory;
    protected $table = 'admin_permission';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin_model::class, 'admin_id', 'id');
    }

    public function permission()
    {
        return $this->hasOne(Permission_model::class, 'id', 'permission_id');
    }
}
