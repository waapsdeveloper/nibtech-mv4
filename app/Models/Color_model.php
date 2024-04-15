<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Color_model extends Model
{
    use HasFactory;
    protected $table = 'color';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'name',
    ];

}
