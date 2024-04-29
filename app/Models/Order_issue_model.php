<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order_issue_model extends Model
{
    use HasFactory;
    protected $table = 'order_issues';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'order_id',
        'data',
        'message',
    ];


    public function order()
    {
        return $this->belongsTo(Order_model::class, 'order_id', 'id');
    }
}
