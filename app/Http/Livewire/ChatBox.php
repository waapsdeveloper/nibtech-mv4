<?php

namespace App\Http\Livewire;

use App\Events\GroupMessageSent;
use App\Events\PrivateMessageSent;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use App\Models\GroupMessage;
use App\Models\PrivateMessage;
use App\Models\Admin_model;
use App\Models\ChatGroup;

class ChatBox extends Component
{
    use WithFileUploads;

    public $index = 0; // Used for right-side stacking
    public $open = true;
    public $message;
    public $image;

    public $messages = [];

    public $isGroup = false;
    public $groupId = null;
    public $recipientId = null;

    public $groupName;
    public $recipientName;

    protected $rules = [
        'message' => 'nullable|string|max:1000',
        'image' => 'nullable|image|max:2048',
    ];

    public function mount($groupId = null, $recipientId = null, $index = 0)
    {
        $this->groupId = $groupId;
        $this->recipientId = $recipientId;
        $this->index = $index;

        if ($groupId) {
            $this->isGroup = true;
            $this->groupName = ChatGroup::find($groupId)?->name ?? 'Group';
        } else {
            $this->isGroup = false;
            $admin = Admin_model::find($recipientId);
            $this->recipientName = $admin?->first_name ?? 'User';
        }

        $this->loadMessages();
    }

    public function loadMessages()
    {
        if ($this->isGroup) {
            $this->messages = GroupMessage::where('group_id', $this->groupId)
                ->with('sender')
                ->latest()
                ->take(50)
                ->get()
                ->reverse();
        } else {
            $userId = session('user_id');
            $this->messages = PrivateMessage::where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->where('receiver_id', $this->recipientId);
            })->orWhere(function ($q) use ($userId) {
                $q->where('sender_id', $this->recipientId)->where('receiver_id', $userId);
            })->with('sender')
            ->latest()
            ->take(50)
            ->get()
            ->reverse();
        }

    }

    public function removeImage()
    {
        if ($this->image) {
            Storage::disk('public')->delete($this->image);
            $this->image = null;
        }
    }
    public function sendMessage()
    {
        $this->validate([
            'message' => 'nullable|string|max:1000',
            'image' => 'nullable|image|max:2048',
        ], [
            'message.max' => 'Message cannot exceed 1000 characters.',
            'image.image' => 'Only image files are allowed.',
            'image.max' => 'Image cannot exceed 2MB.',
        ]);


        $data = [
            'sender_id' => session('user_id'),
            'message' => $this->message,
        ];

        if ($this->image) {
            $data['image'] = $this->image->store('chat_images', 'public');
        }

        if ($this->isGroup) {
            $data['group_id'] = $this->groupId;
            $message = GroupMessage::create($data);
            broadcast(new GroupMessageSent($message));
        } else {
            $data['receiver_id'] = $this->recipientId;
            $message = PrivateMessage::create($data);
            broadcast(new PrivateMessageSent($message));
        }

        $this->reset(['message', 'image']);
        $this->loadMessages();
        $this->dispatchBrowserEvent('messageSent');

    }

    protected $listeners = [
        'openGroupChat' => 'openGroup',
        'openPrivateChat' => 'openPrivate',
        'hideChat' => 'hideChat',
        'echo:group-chat,GroupMessageSent' => 'loadMessages',
        'echo:private-chat,PrivateMessageSent' => 'loadMessages',
    ];

    public function openGroup($groupId)
    {
        $this->groupId = $groupId;
        $this->recipientId = null;
        $this->isGroup = true;
        $this->groupName = ChatGroup::find($groupId)?->name ?? 'Group';
        // $this->hide = false;
        $this->loadMessages();
    }

    public function openPrivate($recipientId)
    {
        $this->recipientId = $recipientId;
        $this->groupId = null;
        $this->isGroup = false;
        $this->recipientName = Admin_model::find($recipientId)?->first_name ?? 'User';
        // $this->hide = false;
        $this->loadMessages();
    }


    public function hideChat()
    {
        // $this->hide = true;
    }
    public function close()
    {
        $this->open = false;
        $this->emit('chatClosed', $this->index);
    }

    public function render()
    {
        return view('livewire.chat-box');
    }
}
