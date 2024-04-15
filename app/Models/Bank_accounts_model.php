<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Bank_accounts_model extends Model
{
    use HasFactory;
    protected $table = 'bank_accounts';
    protected $primaryKey = 'id';
    const CREATED_AT = 'datetime';
    const UPDATED_AT = 'datetime';
    // protected $dates = ['deleted_at'];



    public function bank_accounts()
    {
        return $this->belongsTo(Bank_accounts_model::class, 'id', 'id');
    }

    public function bank_name()
    {
        return $this->hasOne(Bank_model::class, 'id', 'bank_id');
    }

    public function merchant_name()
    {
        return $this->hasOne(Merchant_model::class, 'id', 'merchant_id');
    }
    public function vendor_service()
    {
        return $this->hasOne(Vendor_service_limit_model::class, 'id', 'vendor_service_limit_id');
    }

}

