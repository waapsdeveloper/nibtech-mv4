<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account_type_model extends Model
{
    use HasFactory;
    protected $table = 'account_types';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'name',

    ];

    public function accounts(){
        return $this->hasMany(Account_model::class, 'account_type_id', 'id');
    }



}
