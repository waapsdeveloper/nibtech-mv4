<table class="table table-bordered table-hover mb-0 text-md-nowrap">
    <thead>
        <tr>
            <th></th>
            <th><small><b>No</b></small></th>
            <th><small><b>Order ID</b></small></th>
            <th><small><b>Product</b></small></th>
            <th><small><b>Qty</b></small></th>
            @if (session('user')->hasPermission('view_profit'))
                <th><small><b>Charge</b></small></th>
            @endif
            <th><small><b>IMEI</b></small></th>
            <th><small><b>Creation Date | TN</b></small></th>
        </tr>
    </thead>
    <tbody>
        @php $i = $orders->firstItem() ? ($orders->firstItem() - 1) : 0; @endphp

        @forelse ($orders as $order)
            @php
                $items = $order->order_items ?? collect();
                $items_count = max(1, (int) $items->count());
                $customer = $order->customer;
            @endphp

            @if ($items->count() === 0)
                @include('v2.order.partials.order-row', [
                    'order' => $order,
                    'customer' => $customer,
                    'item' => null,
                    'itemIndex' => 0,
                    'items_count' => $items_count,
                    'rowNumber' => ++$i,
                    'currencies' => $currencies ?? [],
                    'order_statuses' => $order_statuses ?? [],
                ])
            @else
                @foreach ($items as $itemIndex => $item)
                    @include('v2.order.partials.order-row', [
                        'order' => $order,
                        'customer' => $customer,
                        'item' => $item,
                        'itemIndex' => $itemIndex,
                        'items_count' => $items_count,
                        'rowNumber' => ($itemIndex === 0 ? ++$i : null),
                        'currencies' => $currencies ?? [],
                        'order_statuses' => $order_statuses ?? [],
                    ])
                @endforeach
            @endif
        @empty
            <tr>
                <td colspan="8" class="text-center text-muted p-4">
                    No orders found.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>


