<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merchant_team_model extends Model
{
    use HasFactory;
    protected $table = 'merchant_team';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
