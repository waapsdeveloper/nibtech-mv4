<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Marketplace_model extends Model
{
    use HasFactory;
    protected $table = 'marketplace';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
        'name',
        'api_key',
    ];

    public function listings()
    {
        return $this->hasMany(Listing_model::class, 'marketplace_id', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order_model::class, 'marketplace_id', 'id');
    }

}
