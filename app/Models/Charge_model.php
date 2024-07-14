<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Charge_model extends Model
{
    use HasFactory;
    protected $table = 'charges';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'charge_frequency_id',
        'order_type_id',
        'payment_method_id',
        'name',
        'description',
        'amount_type',
        'status',
    ];
    public function charge_frequency(){
        return $this->hasOne(Charge_frequency_model::class, 'id', 'charge_frequency_id');
    }
    public function order_type(){
        return $this->hasOne(Multi_type_model::class, 'id', 'order_type_id');
    }
    public function payment_method(){
        return $this->hasOne(Payment_method_model::class, 'id', 'payment_method_id');
    }
}
