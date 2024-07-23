<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock_movement_model extends Model
{
    use HasFactory;
    protected $table = 'stock_movement';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'stock_id',
        'admin_id',
        'description',
        'exit_at',
        'received_by',
        'received_at'
    ];
    public function stock()
    {
        return $this->hasOne(Stock_model::class, 'id', 'stock_id');
    }
    public function admin()
    {
        return $this->hasOne(Admin_model::class, 'id', 'admin_id');
    }
}
