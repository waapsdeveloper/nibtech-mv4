<?php

namespace App\Http\Livewire;

use Livewire\Component;

class ChatManager extends Component
{
    public $chatBoxes = []; // array of ['id' => X, 'isGroup' => true/false]

    protected $listeners = [
        'openGroupChat' => 'openGroupChat',
        'openPrivateChat' => 'openPrivateChat',
        'hideChatBox' => 'hideChatBox',
        'chatClosed' => 'closeChat',
    ];
    public function mount()
    {
        $this->chatBoxes = session()->get('open_chat_boxes', []);
    }

    public function updatedChatBoxes()
    {
        session(['open_chat_boxes' => $this->chatBoxes]);
    }

    public function openGroupChat($groupId)
    {
        $this->chatBoxes[] = ['group_id' => $groupId];
        $this->chatBoxes = array_unique($this->chatBoxes, SORT_REGULAR); // prevent duplicates
        session(['open_chat_boxes' => $this->chatBoxes]);
    }

    public function openPrivateChat($adminId)
    {
        $this->chatBoxes[] = ['recipient_id' => $adminId];
        $this->chatBoxes = array_unique($this->chatBoxes, SORT_REGULAR);
        session(['open_chat_boxes' => $this->chatBoxes]);
    }

    public function addChatBox($id, $isGroup)
    {
        foreach ($this->chatBoxes as $box) {
            if ($box['id'] === $id && $box['isGroup'] === $isGroup) return;
        }

        $this->chatBoxes[] = ['id' => $id, 'isGroup' => $isGroup];
    }

    public function hideChatBox($index)
    {
        unset($this->chatBoxes[$index]);
        $this->chatBoxes = array_values($this->chatBoxes); // reindex
    }

    public function closeChat($index)
    {
        unset($this->chatBoxes[$index]);
        $this->chatBoxes = array_values($this->chatBoxes); // reindex
        session(['open_chat_boxes' => $this->chatBoxes]);
    }
    public function render()
    {
        return view('livewire.chat-manager');
    }
}
