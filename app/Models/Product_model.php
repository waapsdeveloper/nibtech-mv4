<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Product_model extends Model
{
    use HasFactory;
    protected $table = 'product';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'reference_id',
    ];

    public function grade_id()
    {
        return $this->hasOne(Grade_model::class, 'id', 'grade');
    }
}
