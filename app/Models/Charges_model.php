<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Charges_model extends Model
{
    use HasFactory;
    protected $table = 'charges';
    protected $primaryKey = 'id';
}
