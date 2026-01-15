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
        'default_stock_formula',
        'default_min_threshold',
        'default_max_threshold',
        'default_min_stock_required',
    ];

    protected $casts = [
        'default_stock_formula' => 'array',
        'default_min_threshold' => 'integer',
        'default_max_threshold' => 'integer',
        'default_min_stock_required' => 'integer',
    ];

    // protected static function booted()
    // {
    //     static::addGlobalScope(new Status_not_3_scope);
    // }





    public function duplicates(){
        return $this->hasMany(Variation_model::class, 'product_id', 'product_id')
                    ->where(function ($query) {
                        if ($this->storage > 0) {
                            $query->where('storage', $this->storage);
                        } else {
                            $query->whereIn('storage', [null, 0]);
                        }
                    })
                    ->where('color', $this->color)
                    ->where('grade', $this->grade)
                    ->when($this->grade > 5, function ($query) {
                        return $query->where('sub_grade', $this->sub_grade);
                    })
                    // ->where('sub_grade', $this->sub_grade)
                    // ->whereNotNull('sku')
                    ->where('id', '!=', $this->id);
    }
    public function duplicate_skus(){
        return $this->hasMany(Variation_model::class, 'product_id', 'product_id')
                    ->where(function ($query) {
                        if ($this->storage > 0) {
                            $query->where('storage', $this->storage);
                        } else {
                            $query->whereIn('storage', [null, 0]);
                        }
                    })
                    ->where('color', $this->color)
                    ->where('grade', $this->grade)
                    // ->where('sub_grade', $this->sub_grade)
                    ->whereNotNull('sku')
                    ->where('state', '!=', 4) // Exclude archived variations
                    ->where('id', '!=', $this->id);
    }
    public function hasDuplicate()
    {
        return $this->duplicates()->exists();
    }

    public function scopeHasDuplicate($query)
    {
        $query->whereHas('duplicates');
    }
    public function same_products()
    {
        return $this->hasMany(Variation_model::class, 'product_id', 'product_id');
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
        return $this->hasMany(Listing_model::class, 'variation_id', 'id')->where('marketplace_id',1)->orderBy('country', 'asc')->orderBy('marketplace_id', 'asc');
    }
    public function all_listings()
    {
        return $this->hasMany(Listing_model::class, 'variation_id', 'id')->orderBy('country', 'asc')->orderBy('marketplace_id', 'asc');
    }
    public function grade_id()
    {
        return $this->belongsTo(Grade_model::class, 'grade', 'id');
    }
    public function sub_grade_id()
    {
        return $this->hasOne(Grade_model::class, 'id', 'sub_grade');
    }

    public function listed_stock_verifications()
    {
        return $this->hasMany(Listed_stock_verification_model::class, 'variation_id', 'id');
    }
    public function process_listed_stock_verifications($process_id)
    {
        return $this->hasMany(Listed_stock_verification_model::class, 'variation_id', 'id')->where('process_id', $process_id);
    }

    public function today_orders()
    {
        return $this->hasMany(Order_item_model::class, 'variation_id', 'id')->whereHas('order', function($q){
            $q->whereDate('created_at', date('Y-m-d'))->where('order_type_id',3);
        });
    }
    public function today_orders_count()
    {
        return $this->today_orders->count();
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
        return $this->hasMany(Stock_model::class, 'variation_id', 'id')->where('status',1)->whereHas('active_order')->whereHas('latest_listing_or_topup');
    }
    public function order_items()
    {
        return $this->hasMany(Order_item_model::class, 'variation_id', 'id');
    }
    public function pending_orders()
    {
        return $this->hasMany(Order_item_model::class, 'variation_id', 'id')->whereHas('order', function($q){
            $q->where('order_type_id',3)->where('status',2);
        });
    }

    public function pending_orders_count()
    {
        return $this->pending_orders->count();
    }

    public function pending_orders_sum()
    {
        return $this->pending_orders->sum('quantity');
    }
    public function pending_bm_orders()
    {
        return $this->hasMany(Order_item_model::class, 'variation_id', 'id')->whereHas('order', function($q){
            $q->where('order_type_id',3)->where('status',2)->where('marketplace_id',1);
        });
    }
    public function process_stocks()
    {
        return $this->hasMany(Process_stock_model::class, 'variation_id', 'id');
    }
    public function update_qty($bm)
    {
        $var = $bm->getOneListing($this->reference_id);
        
        // Check if response is valid and has expected properties, use current values as fallback
        if ($var && is_object($var)) {
            $quantity = isset($var->quantity) ? $var->quantity : $this->listed_stock;
            $sku = isset($var->sku) ? $var->sku : $this->sku;
            $state = isset($var->publication_state) ? $var->publication_state : $this->state;
        } else {
            // If API call failed, use current database values
            $quantity = $this->listed_stock;
            $sku = $this->sku;
            $state = $this->state;
        }
        
        Variation_model::where('id', $this->id)->update([
            'listed_stock' => $quantity,
            'sku' => $sku,
            'state' => $state
        ]);
        return $quantity;
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
    public function merge($id){
        $duplicate = $this;
        $new = Variation_model::find($id);
        if($duplicate != null && $new != null){

            // Update related records to point to the original variation
            Listing_model::where('variation_id', $duplicate->id)->update(['variation_id' => $new->id]);
            Listed_stock_verification_model::where('variation_id', $duplicate->id)->update(['variation_id' => $new->id]);
            Order_item_model::where('variation_id', $duplicate->id)->update(['variation_id' => $new->id]);
            Process_model::where('old_variation_id', $duplicate->id)->update(['old_variation_id' => $new->id]);
            Process_model::where('new_variation_id', $duplicate->id)->update(['new_variation_id' => $new->id]);
            Process_stock_model::where('variation_id', $duplicate->id)->update(['variation_id' => $new->id]);
            Stock_model::where('variation_id', $duplicate->id)->update(['variation_id' => $new->id]);
            Stock_operations_model::where('old_variation_id', $duplicate->id)->update(['old_variation_id' => $new->id]);
            Stock_operations_model::where('new_variation_id', $duplicate->id)->update(['new_variation_id' => $new->id]);

            // Soft delete the duplicate
            $duplicate->delete();
            session()->put('success', 'Variation Merged');
        }else{
            session()->put('error', 'Variation Not Found');

        }
    }
}
