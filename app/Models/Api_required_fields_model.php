<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Api_required_fields_model extends Model
{
    use HasFactory;
    protected $table = 'api_required_fields';
    protected $primaryKey = 'id';
 }
