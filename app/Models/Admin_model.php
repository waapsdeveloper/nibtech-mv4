<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use DB;
use Illuminate\Auth\Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Admin_model extends Model
{

    use HasApiTokens, HasFactory, Authenticatable;

    protected $table = 'admin';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
    ];

    public function role()
    {
        return $this->hasOne(Role_model::class, 'id', 'role_id');
    }

    public function permissions()
    {
        return $this->hasManyThrough(Permission_model::class, Admin_permission_model::class, 'admin_id', 'id', 'id', 'permission_id');
    }

    public function stock_operations(){
        return $this->hasMany(Stock_operations_model::class, 'admin_id', 'id');
    }

    public function hasPermission($permission)
    {
        if (session('user_id') == 1){return true;}
        // Check if user has the permission directly
        if ($this->permissions->contains('name', $permission)) {
            return true;
        }

        // Check if user has the permission through any of their roles
        // foreach ($this->roles as $role) {
        if ($this->role->permissions->contains('name', $permission)) {
            return true;
        }
        // }
        // Check if user has the required role but does not have the permission directly
        // foreach ($this->roles as $role) {
            if ($this->role->permissions->isEmpty() && $this->role->name === 'required_role') {
                return true;
            }
        // }

        return false;
    }


}
