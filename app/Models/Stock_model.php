<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Stock_model extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'stock';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
        'imei',
        'serial_number',
        'status'
    ];
    public function variation()
    {
        return $this->belongsTo(Variation_model::class, 'variation_id', 'id');
    }
    public function all_orders()
    {
        return $this->hasManyThrough(Order_model::class, Order_item_model::class, 'stock_id', 'id', 'id', 'order_id');
    }

    public function latest_return()
    {
        return $this->hasOne(Order_item_model::class, 'stock_id', 'id')->whereHas('order', function ($q) {
            $q->where('order_type_id',4);
        })->orderBy('id','desc')->first();
    }
    public function order_item()
    {
        return $this->hasMany(Order_item_model::class, 'stock_id', 'id');
    }
    public function order_items()
    {
        return $this->hasMany(Order_item_model::class, 'stock_id', 'id');
    }
    public function process_stocks()
    {
        return $this->hasMany(Process_stock_model::class, 'stock_id', 'id');
    }
    public function process_stock($process_id)
    {
        // Define a custom method to retrieve only one order item
        return $this->hasOne(Process_stock_model::class, 'stock_id', 'id')->where('process_id', $process_id)->orderBy('id','desc')->first();
    }
    public function stock_operations()
    {
        return $this->hasMany(Stock_operations_model::class, 'stock_id', 'id');
    }
    public function latest_operation()
    {
        return $this->hasOne(Stock_operations_model::class, 'stock_id', 'id')->where('new_variation_id', $this->variation_id)->orderBy('id','desc');
    }
    public function stock_operation($process_id)
    {
        // Define a custom method to retrieve only one order item
        return $this->hasOne(Stock_operations_model::class, 'stock_id', 'id')->where('process_id', $process_id)->orderBy('id','desc')->first();
    }
    public function order()
    {
        return $this->hasOne(Order_model::class, 'id', 'order_id');
    }
    public function purchase_item()
    {
        // Define a custom method to retrieve only one order item
        return $this->hasOne(Order_item_model::class, 'stock_id', 'id')->where('order_id', $this->order_id);
    }

    public function last_item(){

        $last_item = $this->purchase_item;
        if($last_item != null){

            while(Order_item_model::where(['linked_id'=>$last_item->id, 'stock_id'=>$this->id])->first()){
                $last_item = Order_item_model::where(['linked_id'=>$last_item->id, 'stock_id'=>$this->id])->first();
                // print_r($last_item);
            }
        }
        return $last_item;
    }
    public function sale_item($order_id)
    {
        // Define a custom method to retrieve only one order item
        return $this->hasOne(Order_item_model::class, 'stock_id', 'id')->where('order_id', $order_id)->orderBy('id','desc')->first();
    }


}
