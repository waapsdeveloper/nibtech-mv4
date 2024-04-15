<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Settlement_model extends Model
{
    use HasFactory;
    protected $table = 'settlements';
    protected $primaryKey = 'id';

    protected $fillable = [
        'take_from',
        'take_from_id',
        'give_to',
        'give_to_id',
        'currency',
        'settlement_type',
        'network',
        'network_charges',
        'wallet_address',
        'amount',
        'status',
        'usdt',
        'usdt_rate'
    ];

    public function tr_status()
    {
        return $this->hasOne(Transaction_status_model::class, 'id', 'status');
    }
    public function currency_id()
    {
        return $this->hasOne(Currency_model::class, 'id', 'currency');
    }
}
