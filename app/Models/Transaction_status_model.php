<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction_status_model extends Model
{
    use HasFactory;
    protected $table = 'transaction_status';
    protected $primaryKey = 'id';


}
