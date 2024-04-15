<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Job_model extends Model
{
    use HasFactory;
    protected $table = 'jobs';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
    ];

}
