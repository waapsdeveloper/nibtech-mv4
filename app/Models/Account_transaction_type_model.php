<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account_transaction_type_model extends Model
{
    use HasFactory;
    protected $table = 'account_transactions';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'name',
    ];
    public function transactions(){
        return $this->hasMany(Account_transaction_model::class, 'transaction_type_id', 'id');
    }


}
