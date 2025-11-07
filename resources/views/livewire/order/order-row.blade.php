<tbody wire:init="loadRow" class="order-row-wrapper">
    @php
        $columnCount = session('user')->hasPermission('view_profit') ? 9 : 8;
    @endphp

    @if (! $ready || $order === null)
        <tr>
            <td colspan="{{ $columnCount }}" class="text-center text-muted py-3">
                <span class="spinner-border spinner-border-sm align-middle" role="status"></span>
                <span class="ms-2 align-middle">Loading order {{ $rowNumber }}&hellip;</span>
            </td>
        </tr>
    @else
        @include('livewire.order.partials.order-row', [
            'order' => $order,
            'rowNumber' => $rowNumber,
            'rowCounter' => $rowCounter,
            'storages' => $storages,
            'colors' => $colors,
            'grades' => $grades,
            'admins' => $admins,
            'currencies' => $currencies,
            'order_statuses' => $orderStatuses,
            'inputAnchor' => $inputAnchor,
        ])

        @php
            if (session()->has('refresh')) {
                session()->forget('refresh');
            }
        @endphp
    @endif
</tbody>
