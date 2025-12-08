<?php

namespace App\Http\Livewire;

use App\Models\ChatNotification;
use Livewire\Component;

class ChatNotificationIcon extends Component
{
    public int $count = 0;
    public int $lastCount = 0;

    protected $listeners = ['chatNotificationsUpdated' => 'refreshCount'];

    public function mount(): void
    {
        $initial = $this->resolveUnreadCount();
        $this->count = $initial;
        $this->lastCount = $initial;
    }

    public function refreshCount(): void
    {
        $newCount = $this->resolveUnreadCount();

        if ($newCount > $this->lastCount) {
            $this->dispatchBrowserEvent('chat-notification-tone', [
                'count' => $newCount,
                'delta' => $newCount - $this->lastCount,
            ]);
        }

        $this->count = $newCount;
        $this->lastCount = $newCount;
    }

    protected function resolveUnreadCount(): int
    {
        $adminId = session('user_id');

        if (! $adminId) {
            return 0;
        }

        return ChatNotification::where('admin_id', $adminId)
            ->whereNull('read_at')
            ->count();
    }

    public function render()
    {
        return view('livewire.chat-notification-icon');
    }
}
