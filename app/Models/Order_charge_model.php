<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Order_charge_model extends Model
{
    use HasFactory;
    protected $table = 'order_charges';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'order_id',
        'charge_value_id',
        'amount',
    ];

    public function order()
    {
        return $this->belongsTo(Order_model::class, 'order_id', 'id');
    }

    public function charge_value()
    {
        return $this->hasOne(Charge_value_model::class, 'id', 'charge_value_id');
    }
}
