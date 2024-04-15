<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country_model extends Model
{
    use HasFactory;
    protected $table = 'country';
    protected $primaryKey = 'id';

    
}
