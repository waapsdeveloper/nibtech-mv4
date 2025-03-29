<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Admin_customer_model extends Model
{
    use HasFactory;
    protected $table = 'admin_customer';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
        'admin_id',
        'customer_id',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin_model::class, 'admin_id', 'id');
    }

    public function customer()
    {
        return $this->hasOne(Customer_model::class, 'id', 'customer_id');
    }
}
