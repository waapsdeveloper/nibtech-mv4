<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account_journal_model extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'account_journals';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'transaction_id',
        'account_id',
        'debit',
        'credit',

    ];

    public function transaction(){
        return $this->hasOne(Account_transaction_model::class, 'id', 'transaction_id');
    }
    public function account(){
        return $this->hasOne(Account_model::class, 'id', 'account_id');
    }
    public function creator(){
        return $this->hasOne(Admin_model::class, 'id', 'created_by');
    }



}
