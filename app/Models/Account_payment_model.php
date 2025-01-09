<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account_payment_model extends Model
{
    use HasFactory;
    protected $table = 'account_payments';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'reference_id',
        'customer_id',
        'transaction_id',

    ];
    public function order(){
        return $this->hasOne(Order_model::class, 'id', 'order_id');
    }
    public function process(){
        return $this->hasOne(Process_model::class, 'id', 'process_id');
    }
    public function customer(){
        return $this->hasOne(Customer_model::class, 'id', 'customer_id');
    }
    public function transaction(){
        return $this->hasOne(Account_transaction_model::class, 'id', 'transaction_id');
    }
    public function payment_method(){
        return $this->hasOne(Account_payment_method_model::class, 'id', 'payment_method_id');
    }
    public function creator(){
        return $this->hasOne(Admin_model::class, 'id', 'created_by');
    }



}
