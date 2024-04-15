<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Balance_log_model extends Model
{
    use HasFactory;
    protected $table = 'balance_log';
    protected $primaryKey = 'id';
    const CREATED_AT = 'updated_at';

}
