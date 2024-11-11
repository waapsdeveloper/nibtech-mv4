<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Variation_image_model extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'variation_images';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'variation_id',
        'image_url',
    ];

    public function variation(){
        return $this->hasOne(Variation_model::class, 'id', 'variation_id');
    }

}
