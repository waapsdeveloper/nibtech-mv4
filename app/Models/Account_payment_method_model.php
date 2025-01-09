<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account_payment_method_model extends Model
{
    use HasFactory;
    protected $table = 'account_payment_methods';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'name',

    ];
    public function payments(){
        return $this->hasMany(Account_payment_model::class, 'payment_method_id', 'id');
    }



}
