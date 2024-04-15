<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Currency_model;
use App\Models\Payment_mothod_model;

class Allowed_methods_model extends Model
{
    use HasFactory;
    protected $table = 'merchant_allowed_method';
    protected $primaryKey = 'id';

    public function currency(){
        return $this->hasOne(Currency_model::class,'id','currency_id');
    }
    public function payment_method(){
        return $this->hasOne(Payment_method_model::class,'id','payment_method_id');
    }
}
