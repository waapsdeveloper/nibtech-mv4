<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock_operations_model extends Model
{
    use HasFactory;
    protected $table = 'stock_operations';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'stock_id',
        'order_id',
        'process_id',
        'old_variation_id',
        'new_variation_id',
        'description',
        'admin_id',
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
    public function order()
    {
        return $this->hasOne(Order_model::class, 'id', 'order_id');
    }
    public function process()
    {
        return $this->hasOne(Process_model::class, 'id', 'process_id');
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
}
