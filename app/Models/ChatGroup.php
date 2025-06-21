<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatGroup extends Model
{
    use HasFactory;
    protected $fillable = ['name'];

    public function messages()
    {
        return $this->hasMany(GroupMessage::class, 'group_id');
    }
    public function latestMessage()
    {
        return $this->hasOne(GroupMessage::class, 'group_id')->latestOfMany();
    }
    public function members()
    {
        return $this->belongsToMany(Admin_model::class, 'group_chat_members', 'group_id', 'admin_id');
    }

}
