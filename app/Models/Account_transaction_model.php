<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account_transaction_model extends Model
{
    use HasFactory;
    protected $table = 'account_transactions';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'reference_id',
        'order_id',
        'process_id',
        'customer_id',
        'transaction_type_id',

    ];

    public function payments(){
        return $this->hasMany(Account_payment_model::class, 'transaction_id', 'id');
    }
    public function account_journal(){
        return $this->hasMany(Account_journal_model::class, 'transaction_id', 'id');
    }
    public function order(){
        return $this->hasOne(Order_model::class, 'id', 'order_id');
    }
    public function process(){
        return $this->hasOne(Process_model::class, 'id', 'process_id');
    }
    public function customer(){
        return $this->hasOne(Customer_model::class, 'id', 'customer_id');
    }
    public function transaction_type(){
        return $this->hasOne(Account_transaction_type_model::class, 'id', 'transaction_type_id');
    }
    public function currency_id(){
        return $this->hasOne(Currency_model::class, 'id', 'currency');
    }
    public function creator(){
        return $this->hasOne(Admin_model::class, 'id', 'created_by');
    }
    public function authorizer(){
        return $this->hasOne(Admin_model::class, 'id', 'authorized_by');
    }



}
