<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account_model extends Model
{
    use HasFactory;
    protected $table = 'accounts';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'reference_id',
        'account_type_id',
        'parent_id',
        'name',

    ];

    public function account_journals(){
        return $this->hasMany(Account_journal_model::class, 'account_id', 'id');
    }
    public function account_type(){
        return $this->hasOne(Account_type_model::class, 'id', 'account_type_id');
    }
    public function parent(){
        return $this->hasOne(Account_model::class, 'id', 'parent_id');
    }
    public function childs(){
        return $this->hasMany(Account_model::class, 'parent_id', 'id');
    }
    public function creator(){
        return $this->hasOne(Admin_model::class, 'id', 'created_by');
    }


}
