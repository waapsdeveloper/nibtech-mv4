<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency_model extends Model
{
    use HasFactory;
    protected $table = 'currency';
    protected $primaryKey = 'id';


    public function process(){
        return $this->hasMany(Process_model::class, 'currency', 'id');
    }
    public function account_transaction(){
        return $this->hasMany(Account_transaction_model::class, 'currency', 'id');
    }
    public function account_journal(){
        return $this->hasMany(Account_journal_model::class, 'currency', 'id');
    }
    public function account(){
        return $this->hasMany(Account_model::class, 'currency', 'id');
    }
    public function orders(){
        return $this->hasMany(Order_model::class, 'currency', 'id');
    }
    public function order_items(){
        return $this->hasMany(Order_item_model::class, 'currency', 'id');
    }
}
