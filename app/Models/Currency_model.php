<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency_model extends Model
{
    use HasFactory;
    protected $table = 'currency';
    protected $primaryKey = 'id';
}
