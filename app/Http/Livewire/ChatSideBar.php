<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Admin_model;
use App\Models\ChatGroup;
use App\Models\ChatNotification;
use App\Models\GroupMessage;
use App\Models\PrivateMessage;
use Illuminate\Support\Collection;

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

        $lastPrivateMessages = $this->getPrivateLastMessages($adminId);

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
            ->get()
            ->map(function ($group) use ($unreadGroups) {
                $group->unread_count = $unreadGroups[$group->id] ?? 0;
                return $group;
            })
            ->sortByDesc(function ($group) {
                return $group->latestMessage?->created_at?->timestamp ?? 0;
            })
            ->values();

        $this->privateChats = Admin_model::query()
            ->where('id', '!=', $adminId)
            ->get()
            ->map(function ($user) use ($unreadPrivates, $lastPrivateMessages) {
                $lastMessage = $lastPrivateMessages->get($user->id);
                $user->latestMessage = $lastMessage;
                $user->last_message_at = $lastMessage?->created_at;
                $user->unread_count = $unreadPrivates[$user->id] ?? 0;
                $user->hasNewMessages = ($user->unread_count ?? 0) > 0;
                return $user;
            })
            ->sortByDesc(function ($user) {
                return $user->last_message_at?->timestamp ?? 0;
            })
            ->values();
    }

    public function openGroupChat($groupId)
    {
        $this->groupId = $groupId;
        $this->emit('openGroupChat', $groupId);

        if ($this->groups instanceof Collection) {
            $selected = $this->groups->firstWhere('id', $groupId);

            if ($selected) {
                $this->groups = $this->groups
                    ->reject(fn ($group) => $group->id === $groupId)
                    ->push($selected)
                    ->values();
            }
        }
    }

    public function openPrivateChat($adminId)
    {
        $this->emit('openPrivateChat', $adminId);

        if ($this->privateChats instanceof Collection) {
            $selected = $this->privateChats->firstWhere('id', $adminId);

            if ($selected) {
                $this->privateChats = $this->privateChats
                    ->reject(fn ($user) => $user->id === $adminId)
                    ->push($selected)
                    ->values();
            }
        }
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

    protected function getPrivateLastMessages(int $adminId): Collection
    {
        $conversationLastIds = PrivateMessage::query()
            ->selectRaw('MAX(id) as last_id, CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as partner_id', [$adminId])
            ->where(function ($query) use ($adminId) {
                $query->where('sender_id', $adminId)
                      ->orWhere('receiver_id', $adminId);
            })
            ->groupBy('partner_id')
            ->pluck('last_id', 'partner_id');

        if ($conversationLastIds->isEmpty()) {
            return collect();
        }

        return PrivateMessage::query()
            ->whereIn('id', $conversationLastIds->values())
            ->with('sender')
            ->get()
            ->keyBy(function ($message) use ($adminId) {
                return $message->sender_id === $adminId
                    ? $message->receiver_id
                    : $message->sender_id;
            });
    }
}
