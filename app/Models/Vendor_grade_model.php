<?php

namespace App\Models;

use App\Http\Livewire\Variation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Vendor_grade_model extends Model
{
    use HasFactory;
    protected $table = 'vendor_grade';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
        'name'
    ];

}
