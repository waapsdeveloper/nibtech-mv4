<?php

namespace App\Models;

use App\Http\Livewire\Listing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\Status_not_3_scope;


class Variation_model extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'variation';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'product_id',
        'reference_id',
        'sku',
        'color',
        'storage',
        'grade',
    ];
    protected static function booted()
    {
        static::addGlobalScope(new Status_not_3_scope);
    }





    public function product(){
        return $this->hasOne(Products_model::class, 'id', 'product_id');
    }
    public function storage_id()
    {
        return $this->hasOne(Storage_model::class, 'id', 'storage');
    }
    public function color_id()
    {
        return $this->hasOne(Color_model::class, 'id', 'color');
    }
    public function variation_listing_qty()
    {
        return $this->hasOne(Variation_listing_qty_model::class, 'variation_id', 'id');
    }
    public function listings()
    {
        return $this->hasMany(Listing_model::class, 'variation_id', 'id');
    }
    public function grade_id()
    {
        return $this->belongsTo(Grade_model::class, 'grade', 'id');
    }
    public function stocks()
    {
        return $this->hasMany(Stock_model::class, 'variation_id', 'id');
    }
    public function order_items()
    {
        return $this->hasMany(Order_item_model::class, 'variation_id', 'id');
    }
}
