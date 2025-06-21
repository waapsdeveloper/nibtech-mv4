
<div>
    @foreach($chatBoxes as $index => $chat)
        @if(isset($chat['group_id']))
            <livewire:chat-box
                :key="'group-'.$chat['group_id']"
                :group-id="$chat['group_id']"
                :index="$index"
                wire:close="closeChat({{ $index }})"
            />
        @elseif(isset($chat['recipient_id']))
            <livewire:chat-box
                :key="'private-'.$chat['recipient_id']"
                :recipient-id="$chat['recipient_id']"
                :index="$index"
                wire:close="closeChat({{ $index }})"
            />
        @endif
    @endforeach

</div>
