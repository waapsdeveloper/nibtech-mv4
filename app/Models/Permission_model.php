<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission_model extends Model
{
    use HasFactory;
    protected $table = 'permission';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
        'name'
    ];
    /**
     * Define relationships
     */
    public function roles()
    {
        return $this->belongsToMany(Role_model::class);
    }
    public function admin_permissions()
    {
        return $this->hasMany(Admin_permission_model::class, 'permission_id', 'id');
    }
    public function admins()
    {
        return $this->hasManyThrough(Admin_model::class, Admin_permission_model::class, 'permission_id', 'id', 'id', 'admin_id');
    }

}
