<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Stock_model extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'stock';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
        'imei',
        'serial_number'
    ];
    public function variation()
    {
        return $this->belongsTo(Variation_model::class, 'variation_id', 'id');
    }

    public function order_item()
    {
        return $this->hasMany(Order_item_model::class, 'stock_id', 'id');
    }
    public function order()
    {
        return $this->hasOne(Order_model::class, 'id', 'order_id');
    }
    public function purchase_item()
    {
        // Define a custom method to retrieve only one order item
        return $this->hasOne(Order_item_model::class, 'stock_id', 'id')->where('order_id', $this->order_id);
    }

}
