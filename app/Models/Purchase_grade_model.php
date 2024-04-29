<?php

namespace App\Models;

use BaconQrCode\Renderer\Color\Gray;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase_grade_model extends Model
{
    use HasFactory;
    protected $table = 'purchase_grades';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'order_item_id',
        'grade',
    ];
    public function order_items()
    {
        return $this->belongsTo(Order_item_model::class, 'order_item_id', 'id');
    }
    public function grade_id()
    {
        return $this->hasOne(Grade_model::class, 'id', 'grade');
    }
}
