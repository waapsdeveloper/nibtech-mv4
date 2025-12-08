<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Admin_model;
use App\Models\ChatGroup;
use App\Models\ChatNotification;
use App\Models\GroupMessage;
use App\Models\PrivateMessage;

class ChatSidebar extends Component
{
    public $groups;
    public $privateChats;
    public $showCreateModal = false;
    public $groupName;
    public $selectedAdminIds = [];
    public $groupId = null;

    protected $listeners = ['chatNotificationsUpdated' => 'loadChats'];


    public function mount()
    {
        $this->loadChats();
    }

    public function loadChats()
    {
        $adminId = session('user_id');

        if (! $adminId) {
            $this->groups = collect();
            $this->privateChats = collect();
            return;
        }

        $unreadGroups = ChatNotification::where('admin_id', $adminId)
            ->where('context_type', 'group')
            ->whereNull('read_at')
            ->selectRaw('context_id, COUNT(*) as total')
            ->groupBy('context_id')
            ->pluck('total', 'context_id');

        $unreadPrivates = ChatNotification::where('admin_id', $adminId)
            ->where('context_type', 'private')
            ->whereNull('read_at')
            ->selectRaw('context_id, COUNT(*) as total')
            ->groupBy('context_id')
            ->pluck('total', 'context_id');

        $this->groups = ChatGroup::with(['latestMessage' => function ($query) {
                $query->latest();
            }])
            ->whereHas('members', function ($query) use ($adminId) {
                $query->where('admin_id', $adminId);
            })
            ->orderByDesc(GroupMessage::select('created_at')
                ->whereColumn('group_id', 'chat_groups.id')
                ->latest()
                ->take(1)
            )->get()
            ->map(function ($group) use ($unreadGroups) {
                $group->unread_count = $unreadGroups[$group->id] ?? 0;
                return $group;
            });

        $this->privateChats = Admin_model::where('id', '!=', $adminId)
            ->with(['privateMessages'])
            ->get()
            ->map(function ($user) use ($adminId, $unreadPrivates) {
                $user->unread_count = $unreadPrivates[$user->id] ?? 0;
                $user->hasNewMessages = ($user->unread_count ?? 0) > 0;
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
