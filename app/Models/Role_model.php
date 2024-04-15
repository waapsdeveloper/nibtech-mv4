<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role_model extends Model
{
    use HasFactory;
    protected $table = 'role';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
    ];

    /**
     * Define relationships
     */
    public function permissions()
    {
        return $this->hasManyThrough(Permission_model::class, Role_permission_model::class, 'role_id', 'id', 'id', 'permission_id');
    }

}
