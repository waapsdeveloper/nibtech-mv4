<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Settlements_model extends Model
{
    use HasFactory;
    protected $table = 'settlements';
    protected $primaryKey =  'id';
    const CREATED_AT = 'datetime';
    const UPDATED_AT = NULL;

    public function merchant()
    {
        return $this->hasOne(User::class, 'id', 'give_to_id');
    }

    public function tr_status()
    {
        return $this->hasOne(Transaction_status_model::class, 'id', 'status');
    }

    public function currency_id()
    {
        return $this->hasOne(Currency_model::class, 'id', 'currency');
    }

    public function bank()
    {
        return $this->hasOne(Bank_model::class, 'id', 'user_bank');
    }
}
