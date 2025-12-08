<?php

namespace App\Http\Livewire;

use App\Models\ChatNotification;
use Livewire\Component;

class ChatNotificationIcon extends Component
{
    public int $count = 0;

    protected $listeners = ['chatNotificationsUpdated' => 'refreshCount'];

    public function mount(): void
    {
        $this->refreshCount();
    }

    public function refreshCount(): void
    {
        $adminId = session('user_id');
        $this->count = $adminId
            ? ChatNotification::where('admin_id', $adminId)->whereNull('read_at')->count()
            : 0;
    }

    public function render()
    {
        return view('livewire.chat-notification-icon');
    }
}
