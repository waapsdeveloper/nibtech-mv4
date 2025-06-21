<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Admin_model;
use App\Models\ChatGroup;
use App\Models\GroupMessage;
use App\Models\PrivateMessage;
use Illuminate\Support\Facades\DB;

class ChatSidebar extends Component
{
    public $groups;
    public $privateChats;
    public $showCreateModal = false;
    public $groupName;
    public $selectedAdminIds = [];
    public $groupId = null;


    public function mount()
    {
        $this->loadChats();
    }

    public function loadChats()
    {
        $adminId = session('user_id');

        $this->groups = ChatGroup::with(['latestMessage' => function ($query) {
                $query->latest();
            }])
            ->withCount(['messages as hasNewMessages' => function ($query) use ($adminId) {
                $query->where('sender_id', '!=', $adminId)
                      ->where('created_at', '>=', now()->subMinutes(5));
            }])
            ->whereHas('members', function ($query) use ($adminId) {
                $query->where('admin_id', $adminId);
            })
            ->orderByDesc(GroupMessage::select('created_at')
                ->whereColumn('group_id', 'chat_groups.id')
                ->latest()
                ->take(1)
            )->get();

        $this->privateChats = Admin_model::where('id', '!=', $adminId)
            ->with(['privateMessages'])
            ->get()
            ->map(function ($user) use ($adminId) {
                $user->hasNewMessages = $user->latestMessage && $user->latestMessage->sender_id != $adminId && $user->latestMessage->created_at >= now()->subMinutes(5);
                return $user;
            });
    }

    public function openGroupChat($groupId)
    {
        $this->groupId = $groupId;
        $this->emit('openGroupChat', $groupId);
    }

    public function openPrivateChat($adminId)
    {
        $this->emit('openPrivateChat', $adminId);
    }

    public function storeNewGroup()
    {
        $this->validate([
            'groupName' => 'required|string|max:255',
            'selectedAdminIds' => 'required|array|min:1',
        ]);

        $group = ChatGroup::create([
            'name' => $this->groupName,
            'created_by' => session('user_id')
        ]);

        $group->members()->sync($this->selectedAdminIds);
        $group->members()->attach(session('user_id')); // Add the creator to the group

        $this->showCreateModal = false;
        $this->emit('groupCreated', $group->id);
    }
    public function createNewChat()
    {
        // $this->emit('createNewChat');
        $this->reset(['groupName', 'selectedAdminIds']);
        $this->showCreateModal = true;
    }

    public function render()
    {
        return view('livewire.chat-sidebar');
    }
}
