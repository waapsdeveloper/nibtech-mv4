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

    public function order_item()
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

        $last_item = $this->purchase_item();
        while(Order_item_model::where('linked_id',$last_item->id)->first()){
            $last_item = Order_item_model::where('linked_id',$last_item->id)->first();
        }
        return $last_item;
    }
    public function sale_item($order_id)
    {
        // Define a custom method to retrieve only one order item
        return $this->hasOne(Order_item_model::class, 'stock_id', 'id')->where('order_id', $order_id)->orderBy('id','desc')->first();
    }
    public function check_status()
    {

        $sale_status = Order_item_model::where(['stock_id'=>$this->id,'linked_id'=>$this->purchase_item->id])->first();
        if($this->status == 1){
            if($sale_status != null){
                $this->status = 2;
                $this->save();
                session()->put('success', 'IMEI Sold');
            }else{
                session()->put('success', 'IMEI Available');
            }
        }
        if($this->status == 2){
            if($sale_status == null){
                $this->status = 1;
                $this->save();
                session()->put('success', 'IMEI Available');
            }else{
                session()->put('success', 'IMEI Sold');
            }
        }
    }
}
