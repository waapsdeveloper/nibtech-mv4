<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction_charges_model extends Model
{
    use HasFactory;
    protected $table = 'transaction_charges';
    protected $primaryKey = 'id';
}
