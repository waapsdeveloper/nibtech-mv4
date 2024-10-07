<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Support_attachment_model extends Model
{
    use HasFactory;

    use SoftDeletes;
    protected $table = 'support_attachments';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'support_message_id',
        'link',
    ];

    public function support_message()
    {
        return $this->belongsTo(Support_message_model::class, 'support_message_id', 'id');
    }

    public function new_attachment()
    {
        $find = $this;
    }
}
