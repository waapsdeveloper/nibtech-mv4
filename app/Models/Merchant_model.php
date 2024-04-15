<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Country_model;

class Merchant_model extends Model
{
    use HasFactory;
    protected $table = 'merchant';
    protected $primaryKey = 'id';

    protected $fillable = [
        'merchant_id',
        'fname',
        'lname',
        'email',
        'password',
        'secret_code',
        'payout_tier_start',
        'payout_tier_end',
        'payout_settlement_start',
        'payout_settlement_end'
    ];

    public function country_id(){
        return $this->hasOne(Country_model::class,'id','country');
    }
}
