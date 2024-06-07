<?php

namespace App\Models;

use App\Http\Livewire\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Category_model extends Model
{
    use HasFactory;
    protected $table = 'category';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
    ];
    public function products()
    {
        return $this->hasMany(Products_model::class, 'category', 'id');
    }

}
