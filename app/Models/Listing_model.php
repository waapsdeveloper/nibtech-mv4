<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Listing_model extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'listings';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'country',
        'marketplace_id',
        'variation_id',
        'reference_uuid',
        'name',
        'min_price',
        'max_price',
        'price',
        'buybox',
        'buybox_price',
        'buybox_winner_price',
        'min_price_limit',
        'price_limit',
        'handler_status',
        'currency_id',
        'target_price',
        'target_percentage',
        'admin_id',
        'status',
        'is_enabled'
    ];





    public function variation(){
        return $this->hasOne(Variation_model::class, 'id', 'variation_id');
    }
    public function country_id()
    {
        return $this->hasOne(Country_model::class, 'id', 'country');
    }
    public function currency()
    {
        return $this->hasOne(Currency_model::class, 'id', 'currency_id');
    }
    public function admin()
    {
        return $this->hasOne(Admin_model::class, 'id', 'admin_id');
    }
    public function marketplace()
    {
        return $this->hasOne(Marketplace_model::class, 'id', 'marketplace_id');
    }

}
