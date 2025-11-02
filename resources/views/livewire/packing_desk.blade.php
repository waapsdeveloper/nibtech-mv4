@section('content')
<div class="p-6 space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Packing Desk</h1>
            <p class="text-sm text-gray-600">Stage: <span class="font-medium text-gray-900">{{ strtoupper($stage) }}</span></p>
        </div>
        <div class="flex flex-col gap-2 max-w-xl w-full">
            @if($statusMessage)
                <div class="px-3 py-2 text-sm text-green-800 bg-green-100 rounded-md border border-green-200">{{ $statusMessage }}</div>
            @endif
            @if($errorMessage)
                <div class="px-3 py-2 text-sm text-red-800 bg-red-100 rounded-md border border-red-200">{{ $errorMessage }}</div>
            @endif
            <input
                id="packing-scan-input"
                type="text"
                wire:model.defer="scanInput"
                wire:keydown.enter.prevent="processScan"
                autocomplete="off"
                class="w-full px-4 py-3 text-lg font-semibold tracking-widest uppercase border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Scan here..."
            >
            <p class="text-xs text-gray-500">Press ESC or scan "RESET" to start over.</p>
        </div>
    </div>

    <div class="border border-gray-200 rounded-md p-4 bg-gray-50">
        <form wire:submit.prevent="applyFilters" class="grid gap-3 md:grid-cols-5">
            <div class="md:col-span-2">
                <label for="filter-reference" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide">Order Reference</label>
                <input
                    id="filter-reference"
                    type="text"
                    wire:model.defer="filterReference"
                    class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="e.g. 12345"
                    autocomplete="off"
                >
            </div>
            <div class="md:col-span-2">
                <label for="filter-customer" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide">Customer</label>
                <input
                    id="filter-customer"
                    type="text"
                    wire:model.defer="filterCustomer"
                    class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Name or company"
                    autocomplete="off"
                >
            </div>
            <div>
                <label for="filter-tracking" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide">Tracking</label>
                <input
                    id="filter-tracking"
                    type="text"
                    wire:model.defer="filterTracking"
                    class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Tracking fragment"
                    autocomplete="off"
                >
            </div>
            <div>
                <label for="filter-limit" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide">Queue Limit</label>
                <input
                    id="filter-limit"
                    type="number"
                    min="1"
                    max="100"
                    wire:model.defer="filterLimit"
                    class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>
            <div class="md:col-span-5 flex justify-end gap-2">
                <button type="button" wire:click="clearFilters" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-200 rounded-md hover:bg-gray-300">Clear</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">Apply</button>
            </div>
        </form>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="space-y-3">
            <h2 class="text-lg font-semibold">Active Orders</h2>
            <div class="overflow-hidden border border-gray-200 rounded-md">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Reference</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Customer</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-600">Required</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-600">Scanned</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-600">Remaining</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Tracking</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-600">Basket</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            @php $inBasket = isset($dispatchBasket[$order['id']]); @endphp
                            <tr class="{{ $activeOrderId === $order['id'] ? 'bg-blue-50' : ($inBasket ? 'bg-yellow-50' : 'bg-white') }}">
                                <td class="px-3 py-2 font-semibold text-gray-900">{{ $order['reference'] }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $order['customer'] }}</td>
                                <td class="px-3 py-2 text-center text-gray-700">{{ $order['required'] }}</td>
                                <td class="px-3 py-2 text-center text-gray-700">{{ $order['scanned'] ?? 0 }}</td>
                                <td class="px-3 py-2 text-center font-semibold {{ ($order['remaining'] ?? 0) === 0 ? 'text-green-700' : 'text-gray-900' }}">
                                    {{ $order['remaining'] ?? 0 }}
                                </td>
                                <td class="px-3 py-2 text-gray-700">{{ $order['tracking_number'] ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    @if($inBasket)
                                        <div class="flex items-center justify-center gap-2">
                                            <button type="button" wire:click="activateBasketOrder({{ $order['id'] }})" class="px-2 py-1 text-xs font-semibold text-blue-700 bg-blue-100 rounded hover:bg-blue-200">Activate</button>
                                            <button type="button" wire:click="toggleDispatchBasket({{ $order['id'] }})" class="px-2 py-1 text-xs font-semibold text-red-700 bg-red-100 rounded hover:bg-red-200">Remove</button>
                                        </div>
                                    @else
                                        <div class="flex justify-center">
                                            <button type="button" wire:click="toggleDispatchBasket({{ $order['id'] }})" class="px-3 py-1 text-xs font-semibold text-gray-700 border border-gray-300 rounded hover:bg-gray-100">Add</button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-gray-500">No orders queued. Scan a SKU to load work.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="space-y-3">
            <h2 class="text-lg font-semibold">Order Progress</h2>
            <div class="border border-gray-200 rounded-md p-4 space-y-4">
                <div>
                    <p class="text-sm font-medium text-gray-600">Current SKU</p>
                    <p class="text-lg font-semibold text-gray-900">{{ $currentSku ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Active Order</p>
                    @if($activeOrderId)
                        @php
                            $activeOrder = null;
                            foreach ($orders as $queueOrder) {
                                if ($queueOrder['id'] === $activeOrderId) {
                                    $activeOrder = $queueOrder;
                                    break;
                                }
                            }
                        @endphp
                        <p class="text-lg font-semibold text-gray-900">{{ $activeOrder['reference'] ?? '—' }}</p>
                        <p class="text-sm text-gray-600">Tracking: {{ $activeOrder['tracking_number'] ?? '—' }}</p>
                    @else
                        <p class="text-lg font-semibold text-gray-400">None</p>
                    @endif
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Scanned IMEIs</p>
                    @php
                        $currentImeis = [];
                        $activeKey = is_numeric($activeOrderId) ? (int) $activeOrderId : null;
                        if ($activeKey !== null && array_key_exists($activeKey, $scannedImeis)) {
                            $currentImeis = $scannedImeis[$activeKey];
                        }
                    @endphp
                    @if(!empty($currentImeis))
                        <ul class="space-y-1">
                            @foreach($currentImeis as $imei)
                                <li class="px-2 py-1 text-sm font-mono bg-gray-100 rounded">{{ $imei }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Scan devices to populate IMEIs.</p>
                    @endif
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Dispatch Basket</h3>
                    @if(!empty($dispatchBasket))
                        <span class="text-xs font-medium text-gray-500">{{ count($dispatchBasket) }} in queue</span>
                    @endif
                </div>
                <div class="border border-gray-200 rounded-md p-3 space-y-2 max-h-48 overflow-y-auto">
                    @forelse($dispatchBasket as $basketId => $basketOrder)
                        <div class="flex items-start justify-between text-sm text-gray-700">
                            <div>
                                <p class="font-semibold">{{ $basketOrder['reference'] }}</p>
                                <p class="text-xs text-gray-500">Remaining {{ $basketOrder['remaining'] }} &middot; Tracking {{ $basketOrder['tracking_number'] ?? '—' }}</p>
                            </div>
                            <div class="flex flex-col gap-2">
                                <button type="button" wire:click="activateBasketOrder({{ $basketId }})" class="px-2 py-1 text-xs font-semibold text-blue-700 bg-blue-100 rounded hover:bg-blue-200">Activate</button>
                                <button type="button" wire:click="removeFromDispatchBasket({{ $basketId }})" class="px-2 py-1 text-xs font-semibold text-red-700 bg-red-100 rounded hover:bg-red-200">Remove</button>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Use the table controls to prepare a dispatch basket.</p>
                    @endforelse
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Picklist</h3>
                    <div class="flex gap-2">
                        <button type="button" wire:click="clearPicklist" class="px-2 py-1 text-xs font-semibold text-gray-600 bg-gray-200 rounded hover:bg-gray-300">Clear</button>
                        <button type="button" wire:click="buildPicklist" class="px-2 py-1 text-xs font-semibold text-white bg-blue-600 rounded hover:bg-blue-700">Build</button>
                    </div>
                </div>
                <div class="border border-gray-200 rounded-md p-3 space-y-2 max-h-48 overflow-y-auto">
                    @forelse($picklist as $item)
                        <div class="text-sm text-gray-700">
                            <p class="font-semibold">{{ $item['reference'] }} &middot; {{ $item['customer'] }}</p>
                            <p class="text-xs text-gray-500">Required {{ $item['units_required'] }} &middot; Remaining {{ $item['units_remaining'] }} &middot; Tracking {{ $item['tracking'] }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Generate a picklist to group devices for picking.</p>
                    @endforelse
                </div>
            </div>

            <div class="space-y-2">
                <h3 class="text-sm font-semibold text-gray-700">Completed Orders</h3>
                <div class="border border-gray-200 rounded-md p-3 space-y-2 max-h-56 overflow-y-auto">
                    @forelse(array_reverse($completedOrders) as $log)
                        <div class="text-sm text-gray-700">
                            <p class="font-semibold">{{ $log['reference'] }} &middot; {{ $log['sku'] }}</p>
                            <p class="text-xs text-gray-500">{{ $log['imei_count'] }} IMEI(s) &middot; Tracking {{ $log['tracking'] }} &middot; {{ $log['timestamp'] }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Completed orders will appear here once dispatched.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
    @once
        <script>
    const focusPackingInput = () => {
        const input = document.getElementById('packing-scan-input');
        if (input) {
            input.focus();
            input.select();
        }
    };

    window.addEventListener('packing-focus-input', focusPackingInput);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            window.Livewire.emit('packingReset');
        }
    });

    const dispatchBase = @json(url('order/dispatch'));

    window.addEventListener('packing-dispatch', (event) => {
        const detail = event.detail || {};
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${dispatchBase}/${detail.orderId}`;
        form.target = '_blank';

        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            input.value = token.getAttribute('content');
            form.appendChild(input);
        }

        ['sku', 'imei', 'tester'].forEach((key) => {
            (detail[key] || []).forEach((value) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `${key}[]`;
                input.value = value;
                form.appendChild(input);
            });
        });

        if (detail.tracking) {
            const trackingInput = document.createElement('input');
            trackingInput.type = 'hidden';
            trackingInput.name = 'tracking';
            trackingInput.value = detail.tracking;
            form.appendChild(trackingInput);
        }

        document.body.appendChild(form);
        form.submit();

        setTimeout(() => {
            form.remove();
        }, 1000);
    });

    document.addEventListener('livewire:load', focusPackingInput);
        </script>
    @endonce
@endpush
