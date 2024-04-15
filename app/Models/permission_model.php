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
    ];
    /**
     * Define relationships
     */
    public function roles()
    {
        return $this->belongsToMany(Role_model::class);
    }
}
