<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\Chat\ChatNotificationService;

class GroupMessage extends Model
{
    use HasFactory;
    protected $fillable = ['group_id', 'sender_id', 'message', 'image'];

    protected static function booted(): void
    {
        static::created(function (GroupMessage $message) {
            app(ChatNotificationService::class)->notifyGroupMessage($message);
        });
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Admin_model::class, 'sender_id', 'id');
    }

    public function group()
    {
        return $this->belongsTo(ChatGroup::class, 'group_id');
    }
}
