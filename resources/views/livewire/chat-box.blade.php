<div>
    <style>
        .chat-box {
            position: fixed;
            bottom: 0;
            width: 320px;
            max-height: 500px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 6px 6px 0 0;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            z-index: 9999;
            display: flex;
            flex-direction: column;
        }

        .chat-body {
            overflow-y: auto;
            padding: 10px;
            flex-grow: 1;
            display: none;
        }

        .chat-body.open {
            display: block;
        }

        .chat-footer {
            border-top: 1px solid #ddd;
            padding: 10px;
        }

        .chat-message {
            margin-bottom: 10px;
        }

        .chat-message img {
            max-width: 100%;
            border-radius: 6px;
        }

        .chat-message p {
            margin-bottom: 4px;
        }
    </style>

    <div class="chat-box" style="right: {{ 20 + ($index * 340) }}px;">
        <!-- Chat Header -->
        <div class="chat-header bg-primary text-white px-3 py-2 fw-bold rounded-top d-flex justify-content-between align-items-center">
            <div class="flex-grow-1" wire:click="$toggle('open')" style="cursor: pointer;">
                {{ $isGroup ? $groupName : $recipientName }}
                <i class="fa fa-chevron-up ms-2" style="transform: rotate({{ $open ? 180 : 0 }}deg); transition: 0.3s;"></i>
            </div>
            <button type="button" class="btn btn-close white ms-2" aria-label="Close" wire:click="close">
                <i class="fa fa-times"></i>
            </button>
        </div>

    @if($open)
        <!-- Chat Body -->
        <div class="chat-body open" wire:poll.3s id="chatBody">
            @foreach($messages as $i => $msg)
                <div class="chat-message" wire:key="message-{{ $msg->id }}">
                    <strong>{{ $msg->sender->first_name ?? 'Unknown' }}:</strong>
                    @if($msg->message)
                        <p>{!! nl2br(e($msg->message)) !!}</p>
                    @endif
                    @if($msg->image)
                        <img src="{{ asset('storage/' . $msg->image) }}" alt="image">
                    @endif
                    <small class="text-muted d-block">{{ $msg->created_at->diffForHumans() }}</small>
                </div>
            @endforeach
        </div>

        <!-- Chat Footer -->
        <div class="chat-footer">
            <form wire:submit.prevent="sendMessage" enctype="multipart/form-data" autocomplete="off">
                <div class="d-flex align-items-end gap-2">
                    <!-- File Upload -->
                    <label class="btn btn-light mb-1 p-2" style="line-height: 1;">
                        <i class="fa fa-paperclip"></i>
                        <input type="file" wire:model="image" name="image" accept="image/*" style="display: none;" />
                    </label>

                    <!-- Message Input -->
                    <div class="flex-grow-1 position-relative">
                        @if ($image)
                            <div class="mb-2">
                                <img src="{{ $image->temporaryUrl() }}" alt="Selected image" class="img-thumbnail w-100">
                                <button type="button" wire:click="removeImage" class="btn btn-sm btn-danger position-absolute" style="top: 8px; right: 8px; padding: 2px 6px; z-index: 2;">
                                    &times;
                                </button>
                            </div>
                        @endif
                        <textarea wire:model.defer="message" rows="1" class="form-control mb-1"
                            placeholder="Type a message..." style="resize: none;"
                            onkeydown="if (event.ctrlKey && event.key === 'Enter') { event.preventDefault(); this.form.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true})); }"></textarea>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn btn-primary mb-1">
                        <i class="fa fa-paper-plane"></i>
                    </button>
                </div>

                <!-- Loading Indicator -->
                <div wire:loading wire:target="image" class="mt-2">
                    <div class="spinner-border text-primary" role="status" style="width: 2rem; height: 2rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span>Uploading image...</span>
                </div>
            </form>
    @endif
        </div>
    </div>

    <script>
        document.addEventListener('livewire:load', function () {
            Livewire.on('messageSent', () => {
                const chatBody = document.getElementById('chatBody');
                if (chatBody) {
                    chatBody.scrollTop = chatBody.scrollHeight;
                }
            });
        });
    </script>
</div>
