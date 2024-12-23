<?php

namespace App\Models;

use App\Http\Livewire\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment_model extends Model
{
    use HasFactory;
    protected $table = 'payments';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'order_id',
        'payment_account_id',
        'payment_date',
        'amount',
        'currency_id',
        'exchange_rate',
        'note',
    ];
    public function order(){
        return $this->hasOne(Order_model::class, 'id', 'order_id');
    }
    public function payment_account(){
        return $this->hasOne(Payment_account_model::class, 'id', 'payment_account_id');
    }
    public function currency(){
        return $this->hasOne(Currency_model::class, 'id', 'currency_id');
    }
    public function admin(){
        return $this->hasOne(Admin_model::class, 'id', 'admin_id');
    }
}
