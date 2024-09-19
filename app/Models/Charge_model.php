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
    public function charge_values()
    {
        return $this->hasMany(Charge_value_model::class, 'charge_id', 'id');
    }
    public function current_value()
    {
        return $this->hasOne(Charge_value_model::class, 'charge_id', 'id')->where('ended_at', null)->orWhere('ended_at', '>=', date('Y-m-d H:i:s'));
    }
    public function latest_value()
    {
        return $this->hasOne(Charge_value_model::class, 'charge_id', 'id')->orderBy('id', 'desc');
    }
}
