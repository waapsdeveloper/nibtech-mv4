<?php

namespace App\Events;

use App\Models\PrivateMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrivateMessageSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public $message;

    public function __construct(PrivateMessage $message)
    {
        //
        $this->message = $message->load('sender', 'receiver');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $sender = $this->message->sender_id;
        $receiver = $this->message->receiver_id;
        // Channel for the private conversation between two users
        $channels = [
            new PrivateChannel('private-chat.' . $sender . '.' . $receiver),
            new PrivateChannel('private-chat.' . $receiver . '.' . $sender),
        ];
        return $channels;
    }
    public function broadcastWith()
    {
        return [
            'message' => $this->message->toArray()
        ];
    }
}
