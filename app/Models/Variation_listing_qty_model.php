<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Variation_listing_qty_model extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'variation_listing_qty';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'variation_id',
        'quantity',
    ];





    public function variation(){
        return $this->hasOne(Variation_model::class, 'id', 'variation_id');
    }

}
