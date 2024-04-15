<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Product_variation_model extends Model
{
    use HasFactory;
    protected $table = 'product';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'reference_id',
        'products_id',
        'grade',
        'color',
        'storage',
    ];
    public function product(){
        return $this->hasOne(Products_model::class, 'id', 'products_id');
    }
    public function storage_id()
    {
        return $this->hasOne(Storage_model::class, 'id', 'storage');
    }
    public function color_id()
    {
        return $this->hasOne(Color_model::class, 'id', 'color');
    }
    public function grade_id()
    {
        return $this->hasOne(Grade_model::class, 'id', 'grade');
    }
    public function stocks()
    {
        return $this->hasMany(Stock_model::class, 'product_variation_id', 'id');
    }
}
