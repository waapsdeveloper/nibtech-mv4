<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Order_charge_model extends Model
{
    use HasFactory;
    protected $table = 'order_charges';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'order_id',
        'charge_value_id',
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
