<div>
    <!-- Group Selector -->
    <select wire:model="groupId" class="mb-3 p-2 border rounded">
        @foreach($groups as $group)
            <option value="{{ $group->id }}">{{ $group->name }}</option>
        @endforeach
    </select>

    <div wire:poll.3s>
        <div class="p-4 h-[400px] overflow-y-scroll border rounded-md bg-gray-50">
            @foreach($messages as $msg)
                <div class="mb-3">
                    <strong>{{ $msg->sender->first_name ?? 'Unknown' }}:</strong>
                    @if ($msg->message)
                        <div>{{ $msg->message }}</div>
                    @endif
                    @if ($msg->image)
                        <div>
                            <img src="{{ asset('storage/' . $msg->image) }}" class="h-32 mt-2 rounded"/>
                        </div>
                    @endif
                    <small class="text-gray-400">{{ $msg->created_at->diffForHumans() }}</small>
                </div>
            @endforeach
        </div>
    </div>
    @if (!empty($typingUsers))
        <div class="text-sm text-gray-500">
            {{ implode(', ', $typingUsers) }} {{ count($typingUsers) > 1 ? 'are' : 'is' }} typing...
        </div>
    @endif
    <form wire:submit.prevent="sendMessage" class="mt-4">
        <textarea wire:model.defer="message" placeholder="Type a message" class="w-full p-2 border rounded mb-2"></textarea>
        <input type="file" wire:model="image" class="mb-2" />
        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Send</button>
    </form>
</div>
