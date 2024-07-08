<?php

namespace App\Models;

use App\Http\Livewire\Listing;
use App\Http\Livewire\Variation;
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
        'state',
    ];
    protected static function booted()
    {
        static::addGlobalScope(new Status_not_3_scope);
    }





    public function duplicate(){
        return Self::where('product_id',$this->product_id)->where('storage',$this->storage)->where('color',$this->color)->where('grade',$this->grade)->get();
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
    public function stocks()
    {
        return $this->hasMany(Stock_model::class, 'variation_id', 'id');
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
}
