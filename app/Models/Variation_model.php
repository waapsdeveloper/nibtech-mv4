<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


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
        'reference_uuid',
        'sku',
        'color',
        'storage',
        'grade',
        'sub_grade',
        'state',
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
                    ->where('sub_grade', $this->sub_grade)
                    ->whereNotNull('sku')
                    ->where('id', '!=', $this->id);
    }
    public function hasDuplicate()
    {
        return $this->duplicates()->exists();
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
    public function color_id()
    {
        return $this->hasOne(Color_model::class, 'id', 'color');
    }
    public function listings()
    {
        return $this->hasMany(Listing_model::class, 'variation_id', 'id')->orderBy('country', 'asc');
    }
    public function grade_id()
    {
        return $this->belongsTo(Grade_model::class, 'grade', 'id');
    }
    public function sub_grade_id()
    {
        return $this->hasOne(Grade_model::class, 'id', 'sub_grade');
    }

    public function images()
    {
        return $this->hasMany(Variation_image_model::class, 'variation_id', 'id')->orderBy('sort', 'asc');
    }

    public function stocks()
    {
        return $this->hasMany(Stock_model::class, 'variation_id', 'id');
    }
    public function sold_stocks()
    {
        return $this->hasMany(Stock_model::class, 'variation_id', 'id')->where('status',2);
    }
    public function all_available_stocks()
    {
        return $this->hasMany(Stock_model::class, 'variation_id', 'id')->where('status',1);
    }
    public function available_stocks()
    {
        return $this->hasMany(Stock_model::class, 'variation_id', 'id')->where('status',1)->whereHas('active_order');
    }
    public function order_items()
    {
        return $this->hasMany(Order_item_model::class, 'variation_id', 'id');
    }
    public function pending_orders()
    {
        return $this->hasMany(Order_item_model::class, 'variation_id', 'id')->where('status',2)->whereHas('order', function($q){
            $q->where('order_type_id','!=',1);
        });
    }
    public function update_qty($bm)
    {
        $var = $bm->getOneListing($this->reference_id);
        Variation_model::where('id', $this->id)->update([
            'listed_stock' => $var->quantity,
        ]);
        return $var->quantity;
    }
    public function update_product($product_id)
    {
        $variation = $this;
        $pss = Product_storage_sort_model::firstOrNew(['product_id'=>$product_id,'storage'=>$variation->storage]);
        if($pss->id == null){
            $pss->save();
        }
        $variation->product_storage_sort_id = $pss->id;
        $variation->product_id = $product_id;
        $variation->save();
    }
    public function update_storage($storage_id)
    {
        $variation = $this;
        $pss = Product_storage_sort_model::firstOrNew(['product_id'=>$variation->product_id,'storage'=>$storage_id]);
        if($pss->id == null){
            $pss->save();
        }
        $variation->product_storage_sort_id = $pss->id;
        $variation->storage = $storage_id;
        $variation->save();
    }
}
