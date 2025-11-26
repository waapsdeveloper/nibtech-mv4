<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address_model extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'address';
    protected $primaryKey = 'id';

    protected $fillable = [
        'customer_id',
        'order_id',
        'street',
        'street2',
        'postal_code',
        'country',
        'city',
        'phone',
        'email',
        'type',
        'status',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'order_id' => 'integer',
        'country' => 'integer',
        'type' => 'integer',
        'status' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order_model::class, 'order_id', 'id');
    }
    public function customer()
    {
        return $this->belongsTo(Customer_model::class, 'customer_id', 'id');
    }
    public function country_id()
    {
        return $this->belongsTo(Country_model::class, 'country', 'id');
    }
    public function type_id()
    {
        return $this->belongsTo(Multi_type_model::class, 'type', 'id');
    }
    public function types()
    {
        return $this->hasMany(Multi_type_model::class, 'type', 'id')->where('table_name', 'address');
    }
}
