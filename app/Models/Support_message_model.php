<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Support_message_model extends Model
{
    use HasFactory;

    use SoftDeletes;
    protected $table = 'support_messages';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'reference_id',
        'initiator',
        'admin_id',
        'message',
    ];

    public function support()
    {
        return $this->belongsTo(Support_model::class, 'support_id', 'id');
    }
    public function initiator_name()
    {
        if($this->initiator == 1){
            return "BackMarket";
        }elseif($this->initiator == 2){
            return "Merchant";
        }elseif($this->initiator == 3){
            return "Customer";
        }
    }
    public function admin()
    {
        return $this->hasOne(Admin_model::class, 'id', 'admin_id');
    }

    public function support_attachments()
    {
        return $this->hasMany(Support_attachment_model::class, 'support_message_id', 'id');
    }
}
