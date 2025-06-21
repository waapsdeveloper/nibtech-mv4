<?php

namespace App\Http\Livewire;

use App\Events\GroupMessageSent;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\GroupMessage;
use App\Models\Admin_model;
use Livewire\WithPagination;
use App\Models\ChatGroup;

class GroupChat extends Component
{
    use WithFileUploads;


    public $groups;
    public $groupId;
    public $message;
    public $image;
    public $messages;
    public $typingUsers = [];
    protected $rules = [
        'message' => 'nullable|string|max:1000',
        'image' => 'nullable|image|max:2048',
    ];

    public function mount()
    {
        $this->groups = ChatGroup::all();
        $this->groupId = $this->groupId ?? $this->groups->first()?->id;
        $this->loadMessages();
    }

    public function updatedGroupId()
    {
        $this->loadMessages();
    }
    public function sendMessage()
    {

        // dd($this->groupId, $this->message, $this->image);
        $this->validate($this->rules, [
            'message.max' => 'Message cannot exceed 1000 characters.',
            'image.image' => 'The file must be an image.',
            'image.max' => 'Image cannot exceed 2MB.',
        ], [
            'message' => 'Message',
            'image' => 'Image',
        ]);

        $data = [
            'group_id' => $this->groupId,
            'sender_id' => session('user_id'),
            'message' => $this->message,
        ];

        if ($this->image) {
            $data['image'] = $this->image->store('chat_images', 'public');
        }

        $message = GroupMessage::create($data);

        broadcast(new GroupMessageSent($message))->toOthers();

        $this->reset(['message', 'image']);
        $this->loadMessages();
    }

    public function loadMessages()
    {
        $this->messages = GroupMessage::where('group_id', $this->groupId)
            ->with('sender')
            ->latest()
            ->take(50)
            ->get()
            ->reverse();
    }

    public function updatedMessage()
    {
        $userId = session('user_id');
        cache()->put("typing_{$this->groupId}_{$userId}", now()->addSeconds(5));
    }

    public function getTypingUsers()
    {
        $allAdmins = Admin_model::pluck('first_name', 'id');
        $typing = [];

        foreach ($allAdmins as $id => $name) {
            if (cache()->has("typing_{$this->groupId}_{$id}") && $id != session('user_id')) {
                $typing[] = $name;
            }
        }

        $this->typingUsers = $typing;
    }
    protected $listeners = ['echo:group-chat,GroupMessageSent' => 'loadMessages', 'echo:group-chat,TypingEvent' => 'getTypingUsers', 'groupCreated' => 'openNewGroupChat'];

    public function render()
    {
        $this->getTypingUsers();
        return view('livewire.group-chat');
    }
}
