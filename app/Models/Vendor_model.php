<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Balance_model;


class Vendor_model extends Model
{
    use HasFactory;
    protected $table = 'vendor';
    protected $primaryKey = 'id';
    const CREATED_AT = 'business_start_date';
    const UPDATED_AT = NULL;

    public function balance_id()
    {
        return $this->hasOne(Balance_model::class, 'keeper_id', 'id');
    }

    public function vendorServiceLimits()
    {
        return $this->hasMany(Vendor_service_limit_model::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(Bank_accounts_model::class);
    }

    public function country_id()
    {
        return $this->hasOne(Country_model::class, 'id', 'country');
    }
    public function st()
    {
        return $this->hasOne(Status_model::class, 'id', 'status');
    }

}
