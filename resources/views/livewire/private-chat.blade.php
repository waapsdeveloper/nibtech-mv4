@php
    $emojiPalette = ['😀','😂','😍','😎','👍','🙏','🎉','🚀','🤝','😅','🤔','👏','💯','🔥','😢'];
@endphp

<div>
    <div wire:poll.3s>
        <div class="p-4 h-[400px] overflow-y-scroll border rounded-md bg-gray-50" id="private-chat-messages">
            @foreach($threadMessages as $msg)
                <div class="mb-3">
                    <strong>{{ $msg->sender->name ?? 'Unknown' }}:</strong>
                    @if ($msg->message)
                        <div class="whitespace-pre-line">{{ $msg->message }}</div>
                    @endif
                    @if ($msg->image)
                        <div>
                            <img src="{{ asset('storage/' . $msg->image) }}" class="h-32 mt-2 rounded shadow-sm" alt="Chat upload"/>
                        </div>
                    @endif
                    @if ($msg->gif_url)
                        <div>
                            <img src="{{ $msg->gif_url }}" class="h-32 mt-2 rounded shadow-sm" alt="Chat gif" loading="lazy"/>
                        </div>
                    @endif
                    <small class="text-gray-400 text-xs">{{ $msg->created_at->diffForHumans() }}</small>
                </div>
            @endforeach
        </div>
    </div>

    <form wire:submit.prevent="sendMessage" class="mt-4 space-y-3">
        <input type="hidden" id="selectedGifUrl" wire:model="gifUrl">

        <div class="flex items-center gap-2">
            <button type="button" id="emojiToggle" class="px-3 py-1 text-sm rounded border border-gray-300 bg-white hover:bg-gray-100">
                😀 Emoji
            </button>
            <button type="button" id="gifToggle" class="px-3 py-1 text-sm rounded border border-gray-300 bg-white hover:bg-gray-100">
                GIFs &amp; Stickers
            </button>
            <div class="text-xs text-gray-500">Add flair to your message</div>
        </div>

        <div id="emojiPanel" class="hidden border rounded p-2 bg-white shadow-sm">
            <div class="text-xs font-semibold text-gray-500 mb-1">Pick an emoji</div>
            <div class="flex flex-wrap gap-1">
                @foreach ($emojiPalette as $emoji)
                    <button type="button" class="emoji-btn px-2 py-1 text-lg" data-emoji="{{ $emoji }}" aria-label="Insert emoji {{ $emoji }}">
                        {{ $emoji }}
                    </button>
                @endforeach
            </div>
        </div>

        <div id="gifPanel" class="hidden border rounded p-3 bg-white shadow-sm space-y-2">
            <div class="flex flex-col md:flex-row gap-2">
                <input type="text" id="gifSearchInput" class="flex-1 border rounded px-2 py-1 text-sm" placeholder="Search GIFs (e.g. celebrate, thanks)">
                <button type="button" id="gifSearchButton" class="px-3 py-1 bg-purple-600 text-white text-sm rounded">Search</button>
            </div>
            <div id="gifStatus" class="text-xs text-gray-500"></div>
            <div id="gifResults" class="grid grid-cols-2 md:grid-cols-3 gap-2"></div>
        </div>

        @if ($gifUrl)
            <div class="flex items-center gap-3 bg-yellow-50 border border-yellow-200 rounded p-2">
                <div class="text-xs font-semibold text-yellow-700">GIF attached</div>
                <img src="{{ $gifUrl }}" alt="Selected GIF" class="h-16 rounded shadow-sm" loading="lazy">
                <button type="button" wire:click.prevent="removeGif" class="text-xs text-red-500 hover:underline">Remove</button>
            </div>
        @endif

        <textarea id="chatMessage" wire:model.defer="message" placeholder="Type a message" class="w-full p-2 border rounded"></textarea>
        <input type="file" wire:model="image" class="block text-sm" accept="image/*" />
        @error('message') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        @error('image') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        @error('gifUrl') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

        <div class="flex gap-2">
            <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Send</button>
            <button type="button" wire:click.prevent="removeGif" class="px-4 py-2 border rounded text-sm">Clear GIF</button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('livewire:load', function () {
        const userId = @json(session('user_id'));
        const receiverId = @json($receiverId);
        const tenorKey = @json(config('services.tenor.api_key'));
        const tenorLimit = @json(config('services.tenor.limit', 12));

        const scrollMessagesToBottom = () => {
            const container = document.getElementById('private-chat-messages');
            if (! container) {
                return;
            }
            requestAnimationFrame(() => {
                container.scrollTop = container.scrollHeight;
            });
        };

        if (! window.Echo) {
            console.warn('Laravel Echo is not loaded; real-time chat updates are disabled.');
        }

        // Listen on both directions when Echo is available
        if (window.Echo) {
            window.Echo.private(`private-chat.${userId}.${receiverId}`)
                .listen('PrivateMessageSent', () => {
                    Livewire.emit('loadMessages');
                    scrollMessagesToBottom();
                });

            window.Echo.private(`private-chat.${receiverId}.${userId}`)
                .listen('PrivateMessageSent', () => {
                    Livewire.emit('loadMessages');
                    scrollMessagesToBottom();
                });
        }

        async function searchGifs(query, gifStatusEl, gifResultsEl) {
            if (! tenorKey) {
                gifStatusEl.textContent = 'Set TENOR_API_KEY in .env to enable GIF search.';
                return;
            }

            if (! query) {
                gifStatusEl.textContent = 'Type a keyword to find GIFs.';
                gifResultsEl.innerHTML = '';
                return;
            }

            gifStatusEl.textContent = 'Searching Tenor...';
            gifResultsEl.innerHTML = '';

            try {
                const response = await fetch(`https://tenor.googleapis.com/v2/search?q=${encodeURIComponent(query)}&key=${tenorKey}&limit=${tenorLimit}&media_filter=gif,tinygif&client_key=nibritaintech`);
                if (! response.ok) {
                    throw new Error('Unable to load GIFs');
                }
                const payload = await response.json();
                const results = payload.results || [];

                if (! results.length) {
                    gifStatusEl.textContent = 'No GIFs found. Try another word!';
                    return;
                }

                gifStatusEl.textContent = `Showing ${results.length} result(s)`;
                gifResultsEl.innerHTML = '';

                results.forEach((item) => {
                    const gifUrl = item.media_formats?.gif?.url || item.media_formats?.tinygif?.url;
                    if (! gifUrl) {
                        return;
                    }
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'rounded overflow-hidden border hover:ring-2 hover:ring-purple-400 transition';
                    button.innerHTML = `<img src="${gifUrl}" alt="GIF" class="w-full h-32 object-cover" loading="lazy">`;
                    button.addEventListener('click', () => {
                        Livewire.emit('gifSelected', gifUrl);
                        gifStatusEl.textContent = 'GIF attached. You can send now!';
                    });
                    gifResultsEl.appendChild(button);
                });
            } catch (error) {
                gifStatusEl.textContent = 'GIF search failed. Please try again later.';
            }
        }

        const bindComposer = () => {
            const emojiPanel = document.getElementById('emojiPanel');
            const emojiToggle = document.getElementById('emojiToggle');
            const gifPanel = document.getElementById('gifPanel');
            const gifToggle = document.getElementById('gifToggle');
            const gifSearchInput = document.getElementById('gifSearchInput');
            const gifSearchButton = document.getElementById('gifSearchButton');
            const gifResults = document.getElementById('gifResults');
            const gifStatus = document.getElementById('gifStatus');
            const messageInput = document.getElementById('chatMessage');

            if (emojiToggle && ! emojiToggle.dataset.bound) {
                emojiToggle.addEventListener('click', () => {
                    emojiPanel.classList.toggle('hidden');
                });
                emojiToggle.dataset.bound = 'true';
            }

            if (gifToggle && ! gifToggle.dataset.bound) {
                gifToggle.addEventListener('click', () => {
                    gifPanel.classList.toggle('hidden');
                });
                gifToggle.dataset.bound = 'true';
            }

            document.querySelectorAll('.emoji-btn').forEach((btn) => {
                if (btn.dataset.bound) {
                    return;
                }
                btn.addEventListener('click', () => {
                    if (! messageInput) {
                        return;
                    }
                    const emoji = btn.dataset.emoji;
                    const cursorStart = messageInput.selectionStart || messageInput.value.length;
                    const cursorEnd = messageInput.selectionEnd || messageInput.value.length;
                    const text = messageInput.value;
                    messageInput.value = text.substring(0, cursorStart) + emoji + text.substring(cursorEnd);
                    messageInput.dispatchEvent(new Event('input'));
                    messageInput.focus();
                });
                btn.dataset.bound = 'true';
            });

            if (gifSearchButton && ! gifSearchButton.dataset.bound) {
                gifSearchButton.addEventListener('click', () => {
                    searchGifs(gifSearchInput.value.trim(), gifStatus, gifResults);
                });
                gifSearchButton.dataset.bound = 'true';
            }

            if (gifSearchInput && ! gifSearchInput.dataset.bound) {
                gifSearchInput.addEventListener('keyup', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        searchGifs(gifSearchInput.value.trim(), gifStatus, gifResults);
                    }
                });
                gifSearchInput.dataset.bound = 'true';
            }
        };

        bindComposer();
        scrollMessagesToBottom();

        Livewire.hook('message.processed', () => {
            bindComposer();
            scrollMessagesToBottom();
        });
    });
</script>
