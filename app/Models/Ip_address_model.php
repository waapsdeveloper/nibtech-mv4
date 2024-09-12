<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Ip_address_model extends Model
{
    use HasFactory;
    protected $table = 'ip_addresses';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
        'admin_id',
        'ip',
        'status',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin_model::class, 'admin_id', 'id');
    }
}
