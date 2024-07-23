<?php

namespace App\Models;

use App\Http\Livewire\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Process_model extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'process';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'reference_id',
        'order_id',
        // 'old_variation_id',
        // 'new_variation_id',
        // 'given_by_id',
        'exchange_rate',
        'process_type_id',
        // 'grade',
        'description',
        'status',
    ];



    public function currency_id()
    {
        return $this->hasOne(Currency_model::class, 'id', 'currency');
    }

    public function customer(){
        return $this->hasOne(Customer_model::class, 'id', 'customer_id');
    }
    public function order(){
        return $this->belongsTo(Order_model::class, 'order_id', 'id');
    }
    public function old_variation(){
        return $this->hasOne(Variation_model::class, 'id', 'old_variation_id');
    }
    public function new_variation(){
        return $this->hasOne(Variation_model::class, 'id', 'new_variation_id');
    }
    public function given_by(){
        return $this->hasOne(Admin_model::class, 'id', 'given_by_id');
    }
    public function taken_by(){
        return $this->hasOne(Admin_model::class, 'id', 'taken_by_id');
    }
    public function process_type_id()
    {
        return $this->hasOne(Multi_type_model::class, 'id', 'process_type_id');
    }
    public function linked()
    {
        return $this->belongsTo(Process_model::class, 'linked_id');
    }
    public function childs()
    {
        return $this->hasMany(Process_model::class, 'linked_id');
    }
    public function process_stocks()
    {
        return $this->hasMany(Process_stock_model::class, 'process_id', 'id');
    }
}
