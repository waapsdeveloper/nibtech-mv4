<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Chats</h5>
        <button wire:click="createNewChat" class="btn btn-sm btn-light text-primary">+ New Chat</button>
    </div>

    <div class="card-body p-2" style="max-height: 90vh; overflow-y: auto;">
        <h6 class="text-muted">Group Chats</h6>
        <ul class="list-group mb-3">
            @forelse($groups as $group)
                <li class="list-group-item d-flex justify-content-between align-items-center"
                    wire:click="openGroupChat({{ $group->id }})" style="cursor: pointer;">
                    <div>
                        <strong>{{ $group->name }}</strong><br>
                        <small class="text-muted">
                            {{ $group->latestMessage?->message ? Str::limit($group->latestMessage->message, 30) : 'No messages' }}
                        </small>
                    </div>
                    @if($group->hasNewMessages)
                        <span class="badge bg-danger rounded-pill">New</span>
                    @endif
                </li>
            @empty
                <li class="list-group-item text-muted">No groups available</li>
            @endforelse
        </ul>

        <h6 class="text-muted">Private Chats</h6>
        <ul class="list-group">
            @forelse($privateChats as $user)
                <li class="list-group-item d-flex justify-content-between align-items-center"
                    wire:click="openPrivateChat({{ $user->id }})" style="cursor: pointer;">
                    <div>
                        <strong>{{ $user->first_name }}</strong><br>
                        <small class="text-muted">
                            {{ $user->latestMessage?->message ? Str::limit($user->latestMessage->message, 30) : 'No messages' }}
                        </small>
                    </div>
                    @if($user->hasNewMessages)
                        <span class="badge bg-danger rounded-pill">New</span>
                    @endif
                </li>
            @empty
                <li class="list-group-item text-muted">No private chats</li>
            @endforelse
        </ul>
    </div>
    @if ($groupId)
        <livewire:chat-box :groupId="$groupId" />
    @endif

<!-- Create Group Modal -->
@if($showCreateModal)
    <div class="modal fade show d-block" tabindex="-1" role="dialog" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Group</h5>
                    <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Group Name</label>
                        <input type="text" class="form-control" wire:model.defer="groupName">
                        @error('groupName') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="mb-3">
                        <label>Select Members</label>
                        <select wire:model.defer="selectedAdminIds" class="form-control" multiple>
                            @foreach(App\Models\Admin_model::all() as $admin)
                                @if($admin->id != session('user_id'))
                                    <option value="{{ $admin->id }}">{{ $admin->first_name }}</option>
                                @endif
                            @endforeach
                        </select>
                        {{-- @error('selectedAdminIds') <span class="text-danger">{{ $message }}</span> @enderror --}}
                    </div>
                </div>
                <div class="modal-footer">
                    <button wire:click="storeNewGroup" class="btn btn-primary">Create Group</button>
                    <button class="btn btn-secondary" wire:click="$set('showCreateModal', false)">Cancel</button>
                </div>
            </div>
        </div>
    </div>
@endif
</div>

