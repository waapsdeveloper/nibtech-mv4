<?php

namespace App\Models;

use App\Http\Livewire\Variation;
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

    public function stocks()
    {
        return $this->hasManyThrough(Stock_model::class, Variation_model::class, 'grade', 'variation_id', 'id', 'id');
    }
    public function stocksCount()
    {
        return $this->hasManyThrough(Stock_model::class, Variation_model::class, 'grade', 'variation_id', 'id', 'id')
                    ->selectRaw('count(id) as count')
                    ->groupBy('grade');
    }


}
