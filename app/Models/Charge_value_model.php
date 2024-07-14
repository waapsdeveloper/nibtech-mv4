<?php

namespace App\Models;

use App\Http\Livewire\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Charge_value_model extends Model
{
    use HasFactory;
    protected $table = 'charges';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'charge_id',
        'value',
        'started_at',
        'ended_at',
    ];
    public function charge(){
        return $this->hasOne(Charge_model::class, 'id', 'charge_id');
    }
    public function orders()
    {
        return $this->hasManyThrough(Order_model::class, Order_charge_model::class, 'charge_value_id', 'id', 'id', 'order_id');
    }
}
