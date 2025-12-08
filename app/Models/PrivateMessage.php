<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\Chat\ChatNotificationService;

class PrivateMessage extends Model
{
    use HasFactory;
    protected $fillable = ['sender_id', 'receiver_id', 'message', 'image'];

    protected static function booted(): void
    {
        static::created(function (PrivateMessage $message) {
            app(ChatNotificationService::class)->notifyPrivateMessage($message);
        });
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Admin_model::class, 'sender_id', 'id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Admin_model::class, 'receiver_id', 'id');
    }
}
