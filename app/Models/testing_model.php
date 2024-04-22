<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class testing_model extends Model
{
    use HasFactory;
    protected $table = 'testing';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        'reference_id',
        'variation_id',
        'stock_id',
        'name',
        'imei',
        'serial_number',
        'color',
        'storage',
        'battery_health',
        'vendor_grade',
        'grade',
        'fault',
        'tester',
        'lot',
        'status',
    ];

}
