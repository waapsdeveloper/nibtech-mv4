<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\PrivateMessage;
use App\Models\Admin_model;
use Illuminate\Support\Facades\Auth;
use App\Events\PrivateMessageSent;

class PrivateChat extends Component
{
    use WithFileUploads;

    public $receiverId;
    public $selectedAdminId;
    public $message = '';
    public $image;
    public $gifUrl = null;
    public $threadMessages;

    protected $rules = [
        'message' => 'nullable|string|max:1000',
        'image' => 'nullable|image|max:2048',
        'gifUrl' => 'nullable|url|max:2048',
    ];

    public function mount($receiverId)
    {
        $this->receiverId = $receiverId;
        $this->loadMessages();
    }

    public function loadMessages()
    {
        $userId = session('user_id');
        $this->threadMessages = PrivateMessage::where(function($query) use ($userId) {
            $query->where('sender_id', $userId)->where('receiver_id', $this->receiverId);
        })->orWhere(function($query) use ($userId) {
            $query->where('sender_id', $this->receiverId)->where('receiver_id', $userId);
        })
        ->with(['sender', 'receiver'])
        ->latest()
        ->take(50)
        ->get()
        ->reverse();
    }

    public function sendMessage()
    {
        if (! $this->message && ! $this->image && ! $this->gifUrl) {
            $this->addError('message', 'Type a message, attach an image, or pick a GIF.');
            return;
        }

        $this->validate();

        $data = [
            'sender_id' => session('user_id'),
            'receiver_id' => $this->receiverId,
            'message' => $this->message,
        ];

        if ($this->image) {
            $data['image'] = $this->image->store('chat_images', 'public');
        }

        if ($this->gifUrl) {
            $data['gif_url'] = $this->gifUrl;
        }

        $message = PrivateMessage::create($data);

        PrivateMessageSent::dispatch($message);

        $this->reset(['message', 'image', 'gifUrl']);
        $this->loadMessages();
    }

    protected $listeners = [
        'echo:private-chat,PrivateMessageSent' => 'loadMessages',
        'openPrivateChat',
        'gifSelected' => 'setGif',
        'clearGif' => 'removeGif',
    ];

    public function openPrivateChat($adminId)
    {
        $this->selectedAdminId = $adminId;
        $this->loadMessages();
    }

    public function setGif(?string $url): void
    {
        $this->gifUrl = $url;
    }

    public function removeGif(): void
    {
        $this->gifUrl = null;
    }
    public function render()
    {
        return view('livewire.private-chat');
    }
}
