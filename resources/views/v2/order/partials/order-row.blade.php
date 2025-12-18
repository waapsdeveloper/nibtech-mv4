@php
    $variation = $item?->variation;
    $stock = $item?->stock;
@endphp

<tr>
    @if ($itemIndex === 0)
        <td rowspan="{{ $items_count }}">
            <input type="checkbox" name="ids[]" value="{{ $order->id }}" form="pdf">
        </td>
        <td rowspan="{{ $items_count }}">
            {{ $rowNumber }}
        </td>
        <td rowspan="{{ $items_count }}">
            {{ $order->reference_id }}<br>
            {{ $customer?->company ?? '' }}<br>
            {{ trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? '')) }}
        </td>
    @endif

    <td>
        @if ($variation)
            <strong>{{ $variation->sku }}</strong>
            @if ($variation->product ?? false)
                - {{ $variation->product->model }}
            @endif
        @else
            <span class="text-muted small">No product</span>
        @endif
    </td>

    <td>
        {{ (int) ($item?->quantity ?? 0) }}
    </td>

    @if (session('user')->hasPermission('view_profit') && $itemIndex === 0)
        <td rowspan="{{ $items_count }}">
            @if ($order->charges !== null)
                @php
                    $sign = $currencies[$order->currency] ?? '';
                @endphp
                @if (in_array((int) $order->status, [3,6], true))
                    {{ $sign }}{{ amount_formatter($order->price, 2) }} - {{ $sign }}{{ amount_formatter($order->charges, 2) }}
                @elseif ((int) $order->status === 5)
                    - {{ $sign }}{{ amount_formatter($order->charges, 2) }}
                @endif
            @else
                <strong class="text-info">Awaiting Charge</strong>
            @endif
        </td>
    @endif

    <td class="text-uppercase">
        @isset($stock?->imei) {{ $stock->imei }} @endisset
        @isset($stock?->serial_number) {{ $stock->serial_number }} @endisset
    </td>

    @if ($itemIndex === 0)
        <td rowspan="{{ $items_count }}">
            <div class="small">{{ $order->created_at }}</div>
            @if (!empty($order->tracking_number))
                <div class="small">
                    <a href="https://www.dhl.com/us-en/home/tracking/tracking-express.html?submit=1&tracking-id={{ $order->tracking_number }}" target="_blank" rel="noopener">
                        {{ $order->tracking_number }}
                    </a>
                </div>
            @endif
            <div class="small text-muted">
                {{ $order_statuses[$order->status] ?? '' }}
            </div>
        </td>
    @endif
</tr>


