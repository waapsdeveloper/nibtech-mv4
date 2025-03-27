<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Daily_closing_model extends Model
{
    use HasFactory;
    protected $table = 'daily_closing';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        // 'reference_id',
    ];

    /**
     * Define relationships
     */

}
