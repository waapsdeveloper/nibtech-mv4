<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Return_model extends Model
{
    use HasFactory;
    protected $table = 'returns';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        'order_id',
        'stock_id',
        'processed_by',
        'tested_by',
        'processed_at',
        'returned_at',
    ];
    public function admin()
    {
        return $this->hasOne(Admin_model::class, 'id', 'processed_by');
    }
    public function order()
    {
        return $this->hasOne(Order_model::class, 'id', 'order_id');
    }
    public function stock()
    {
        return $this->hasOne(Stock_model::class, 'id', 'stock_id');
    }

}
