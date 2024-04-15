<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment_method_model extends Model
{
    use HasFactory;
    protected $table = 'payment_method';
    protected $primaryKey = 'id';

    public function payment_type()
    {
        return $this->hasOne(Payment_type_model::class, 'id', 'payment_type_id');
    }
}
