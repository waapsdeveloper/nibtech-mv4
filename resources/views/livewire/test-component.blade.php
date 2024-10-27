<div x-data="{ count: 0 }">
    <p>Alpine.js is working!</p>
    <button @click="count++">Increment</button>
    <span x-text="count"></span>
</div>
<div x-data="{ showMessage: false }" @toggle-message.window="showMessage = !showMessage">
    <button @click="showMessage = !showMessage">Toggle Alpine Message</button>
    <p x-show="showMessage">Alpine.js toggled message!</p>

    <button wire:click="$emit('toggle-message')">Toggle with Livewire</button>
    <button wire:click="toggleMessage">Toggle with Livewire</button>

</div>
<div x-data="{ showMessage: false }">
    <button @click="showMessage = !showMessage">Toggle Alpine Message</button>
    <p x-show="showMessage">Alpine.js toggled message!</p>

    <!-- Livewire button triggers Alpine.js toggle -->
    <button wire:click="toggleMessage">Toggle with Livewire</button>

    <!-- Listen globally for the 'toggle-message' event -->
    <script>
        document.addEventListener('livewire:load', function () {
            Livewire.on('toggle-message', () => {
                document.querySelectorAll('[x-data]').forEach((el) => {
                    const alpineInstance = el.__x;
                    if (alpineInstance) alpineInstance.$data.showMessage = !alpineInstance.$data.showMessage;
                });
            });
        });
    </script>
</div>
