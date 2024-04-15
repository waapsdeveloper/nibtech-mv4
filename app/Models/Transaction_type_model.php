<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction_type_model extends Model
{
    use HasFactory;
    protected $table = 'transaction_type';
    protected $primaryKey = 'id';


}
