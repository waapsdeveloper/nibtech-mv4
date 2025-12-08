<?php

namespace App\Services\Chat;

use App\Models\ChatNotification;
use App\Models\GroupMessage;
use App\Models\PrivateMessage;
use Illuminate\Support\Str;

class ChatNotificationService
{
    public function notifyGroupMessage(GroupMessage $message): void
    {
        $message->loadMissing('sender', 'group.members');
        $group = $message->group;

        if (! $group) {
            return;
        }

        $snippet = $this->summarizeMessage($message->message, $message->image);

        foreach ($group->members as $member) {
            if ((int) $member->id === (int) $message->sender_id) {
                continue;
            }

            ChatNotification::create([
                'admin_id' => $member->id,
                'context_type' => 'group',
                'context_id' => $message->group_id,
                'message_id' => $message->id,
                'snippet' => $snippet,
                'payload' => [
                    'sender' => $message->sender?->first_name,
                    'group' => $group->name,
                ],
            ]);
        }
    }

    public function notifyPrivateMessage(PrivateMessage $message): void
    {
        $message->loadMissing('sender');

        if ($message->sender_id === $message->receiver_id) {
            return;
        }

        ChatNotification::create([
            'admin_id' => $message->receiver_id,
            'context_type' => 'private',
            'context_id' => $message->sender_id,
            'message_id' => $message->id,
            'snippet' => $this->summarizeMessage($message->message, $message->image),
            'payload' => [
                'sender' => $message->sender?->first_name,
            ],
        ]);
    }

    protected function summarizeMessage(?string $body, ?string $image): string
    {
        $body = trim((string) $body);

        if ($body !== '') {
            return Str::limit(strip_tags($body), 120);
        }

        if ($image) {
            return 'Sent an attachment';
        }

        return 'New chat message';
    }
}
