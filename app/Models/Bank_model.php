<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank_model extends Model
{
    use HasFactory;
    protected $table = 'banks';
    protected $primaryKey = 'id';

    const CREATED_AT = 'added_date';
    const UPDATED_AT = NULL;
}
