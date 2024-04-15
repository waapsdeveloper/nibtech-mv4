<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Transaction_status_model;
use App\Models\Transaction_type_model;
use App\Models\Currency_model;

class Transactions_model extends Model
{
    use HasFactory;
    protected $table = 'transactions';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    public function tr_type()
    {
        return $this->hasOne(Transaction_type_model::class, 'id', 'transaction_type');
    }
    public function tr_status()
    {
        return $this->hasOne(Transaction_status_model::class, 'id', 'status');
    }
    public function currency_id()
    {
        return $this->hasOne(Currency_model::class, 'id', 'currency');
    }

    public function decline($id){

    }
}
