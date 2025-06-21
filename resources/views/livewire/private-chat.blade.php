<div>
    <div wire:poll.3s>
        <div class="p-4 h-[400px] overflow-y-scroll border rounded-md bg-gray-50" id="private-chat-messages">
            @foreach($messages as $msg)
                <div class="mb-3">
                    <strong>{{ $msg->sender->name ?? 'Unknown' }}:</strong>
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

    <form wire:submit.prevent="sendMessage" class="mt-4">
        <textarea wire:model.defer="message" placeholder="Type a message" class="w-full p-2 border rounded mb-2"></textarea>
        <input type="file" wire:model="image" class="mb-2" />
        @error('message') <span class="text-red-500">{{ $message }}</span> @enderror
        @error('image') <span class="text-red-500">{{ $message }}</span> @enderror
        <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Send</button>
    </form>
</div>

<script>
    document.addEventListener('livewire:load', function () {
        const userId = @json(session('user_id'));
        const receiverId = @json($receiverId);

        // Listen on both directions
        window.Echo.private(`private-chat.${userId}.${receiverId}`)
            .listen('PrivateMessageSent', (e) => {
                Livewire.emit('loadMessages');
                const container = document.getElementById('private-chat-messages');
                container.scrollTop = container.scrollHeight;
            });

        window.Echo.private(`private-chat.${receiverId}.${userId}`)
            .listen('PrivateMessageSent', (e) => {
                Livewire.emit('loadMessages');
                const container = document.getElementById('private-chat-messages');
                container.scrollTop = container.scrollHeight;
            });
    });
</script>
