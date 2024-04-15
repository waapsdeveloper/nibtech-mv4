<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Role_permission_model extends Model
{
    use HasFactory;
    protected $table = 'role_permission';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'role_id',
        'permission_id',
    ];

    public function role()
    {
        return $this->belongsTo(Admin_model::class, 'role_id', 'id');
    }

    public function permission()
    {
        return $this->hasOne(Permission_model::class, 'id', 'permission_id');
    }
}
