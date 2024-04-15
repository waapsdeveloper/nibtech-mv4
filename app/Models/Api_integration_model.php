<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Api_integration_model extends Model
{
    use HasFactory;
    protected $table = 'api_integration';
    protected $primaryKey = 'id';

    const CREATED_AT = 'date';
    const UPDATED_AT = 'date';
}
