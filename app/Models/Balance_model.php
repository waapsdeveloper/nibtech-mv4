<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Balance_model extends Model
{
    use HasFactory;
    protected $table = 'balance';
    protected $primaryKey = 'id';
    const CREATED_AT = 'updated_at';


    public function currency()
    {
        return $this->hasOne(Currency_model::class, 'id', 'currency_id');
    }
}
