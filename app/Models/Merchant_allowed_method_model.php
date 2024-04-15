<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Merchant_model;
use App\Models\Currency_model;
use App\Models\Payment_method_model;
class Merchant_allowed_method_model extends Model
{
    use HasFactory;
    protected $table = "merchant_allowed_method";
    protected $id = 'id';
    const CREATED_AT = 'updated_at';

    public function merchant()
    {
        return $this->hasOne(Merchant_model::class, 'id', 'merchant_id');
    }
    public function currency()
    {
        return $this->hasOne(Currency_model::class, 'id', 'currency_id');
    }
    public function payment_method()
    {
        return $this->hasOne(Payment_method_model::class, 'id', 'payment_method_id');
    }

    public function admin()
    {
        return $this->hasOne(Admin_model::class, 'id', 'updated_by');
    }

}
