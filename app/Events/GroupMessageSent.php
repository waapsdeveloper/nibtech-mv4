<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(GroupMessage $message)
    {
        $this->message = $message->load('sender');
    }

    public function broadcastOn(): Channel
    {
        return new Channel('group-chat.' . $this->message->group_id);
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->message->id,
            'message' => $this->message->message,
            'image' => $this->message->image,
            'created_at' => $this->message->created_at->toDateTimeString(),
            'sender' => [
                'admin_id' => $this->message->sender->id,
                'name' => $this->message->sender->first_name,
            ]
        ];
    }
}
