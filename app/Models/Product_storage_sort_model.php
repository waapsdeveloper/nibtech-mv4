<?php

namespace App\Models;

use App\Http\Livewire\Listing;
use App\Http\Livewire\Variation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\Status_not_3_scope;


class Product_storage_sort_model extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'product_storage_sort';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'product_id',
        'storage',
        'sort',
    ];
    // protected static function booted()
    // {
    //     static::addGlobalScope(new Status_not_3_scope);
    // }





    public function duplicates(){
        return $this->hasMany(Variation_model::class, 'product_id', 'product_id')
                    ->where('storage', $this->storage)
                    ->where('color', $this->color)
                    ->where('grade', $this->grade)
                    ->whereNotNull('sku')
                    ->where('id', '!=', $this->id);
    }
    public function hasDuplicate()
    {
        return $this->duplicate()->exists();
    }

    public function scopeHasDuplicate($query)
    {
        $query->whereHas('duplicate');
    }

    public function product(){
        return $this->hasOne(Products_model::class, 'id', 'product_id');
    }
    public function storage_id()
    {
        return $this->hasOne(Storage_model::class, 'id', 'storage');
    }
    public function variations()
    {
        return $this->hasMany(Variation_model::class, 'product_storage_sort_id', 'id');
    }
    public function stocks()
    {
        return $this->hasManyThrough(Stock_model::class, Variation_model::class, 'product_storage_sort_id', 'variation_id', 'id', 'id');
    }


}
