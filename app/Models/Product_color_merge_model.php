<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Product_color_merge_model extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'product_color_merge';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'product_id',
        'color_from',
        'color_to',
    ];

    public function product(){
        return $this->hasOne(Products_model::class, 'id', 'product_id');
    }
    public function color_from_id()
    {
        return $this->hasOne(Color_model::class, 'id', 'color_from');
    }
    public function color_to_id()
    {
        return $this->hasOne(Color_model::class, 'id', 'color_to');
    }


}
