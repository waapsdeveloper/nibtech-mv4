<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Grade_model extends Model
{
    use HasFactory;
    protected $table = 'grade';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
    ];
    public function variations()
    {
        return $this->hasMany(Variation_model::class, 'grade', 'id');
    }

}
