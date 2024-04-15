<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency_exchange_model extends Model
{
    use HasFactory;
    protected $table ='currency_exchange';
    protected $primaryKey = 'id';
}
