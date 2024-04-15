<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Api_response_model extends Model
{
    use HasFactory;
    protected $table = 'api_response';
    protected $primaryKey = 'id';
    const CREATED_AT = 'datetime';
    const UPDATED_AT = 'datetime';

}
