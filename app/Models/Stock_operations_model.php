<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stock_operations_model extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'stock_operations';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'stock_id',
        'order_item_id',
        'process_id',
        'api_request_id',
        'old_variation_id',
        'new_variation_id',
        'description',
        'admin_id',
        'created_at'
    ];
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->attributes['admin_id'] = session('user_id');
    }
    public function stock()
    {
        return $this->hasOne(Stock_model::class, 'id', 'stock_id');
    }
    public function order_item()
    {
        return $this->hasOne(Order_model::class, 'id', 'order_item_id');
    }
    public function process()
    {
        return $this->hasOne(Process_model::class, 'id', 'process_id');
    }
    public function api_request()
    {
        return $this->hasOne(Api_request_model::class, 'id', 'api_request_id');
    }
    public function old_variation()
    {
        return $this->hasOne(Variation_model::class, 'id', 'old_variation_id');
    }
    public function new_variation()
    {
        return $this->hasOne(Variation_model::class, 'id', 'new_variation_id');
    }
    public function admin()
    {
        return $this->hasOne(Admin_model::class, 'id', 'admin_id');
    }
    public function new_operation($stock_id, $order_item_id = NULL, $process_id = NULL, $api_request_id = NULL, $old_variation_id = NULL, $new_variation_id = NULL, $description = NULL, $admin_id = NULL, $created_at = NULL)
    {
        $find = $this->where('stock_id', $stock_id)->orderByDesc('id')->first();

        if ($find && $find->order_item_id == $order_item_id && $find->process_id == $process_id && $find->api_request_id == $api_request_id && $find->new_variation_id == $new_variation_id && $find->description == $description && $find->admin_id == $admin_id) {
            return;
        }

        $this->stock_id = $stock_id;
        $this->order_item_id = $order_item_id;
        $this->process_id = $process_id;
        $this->api_request_id = $api_request_id;
        $this->old_variation_id = $old_variation_id;
        $this->new_variation_id = $new_variation_id;
        $this->description = $description;
        if($admin_id != NULL){
            $this->admin_id = $admin_id;
        }else{
            $this->admin_id = session('user_id');
        }
        if ($created_at != NULL) {
            $this->created_at = $created_at;
        }
        $this->save();
    }
}
