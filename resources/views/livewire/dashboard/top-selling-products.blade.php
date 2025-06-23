<div class="card">
    <div class="card-header pb-0">
        <div class="d-flex justify-content-between">
            <h4 class="card-title ">Top Selling Products</h4>
            <form wire:submit.prevent>
                <select wire:model="perPage" class="form-select form-select-sm">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </form>
        </div>
    </div>

    <div class="card-body">
        <table class="table table-bordered table-hover text-md-nowrap">
            <thead>
                <tr>
                    <th><small><b>No</b></small></th>
                    <th><small><b>Product</b></small></th>
                    <th><small><b>Qty</b></small></th>
                    @if (session('user')->hasPermission('view_price'))
                        <th title="Only Shows average price for selected ranged EU orders"><small><b>Avg</b></small></th>
                    @endif
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @php
                    $total = $top_products->sum('total_quantity_sold');
                    $weighted_average = 0;
                @endphp
                @foreach ($top_products as $top => $product)
                    @php
                        $weighted_average += $product->total_quantity_sold / $total * $product->average_price;
                        $variation = $product->variation;
                    @endphp
                    <tr>
                        <td>{{ $top+1 }}</td>
                        <td>{{ $products[$variation->product_id] ?? null }} {{ $storages[$variation->storage] ?? null }} {{ $colors[$variation->color] ?? null }} {{ $grades[$variation->grade] ?? null }} - {{ $variation->sku ?? null }}</td>
                        <td>{{ $product->total_quantity_sold }}</td>
                        @if (session('user')->hasPermission('view_price'))
                        <td>€{{ amount_formatter($product->average_price,2) }}</td>
                        @endif

                        <td>
                            <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                            <div class="dropdown-menu">
                                {{-- <a class="dropdown-item" href="{{url('order')}}/refresh/{{ $order->reference_id }}"><i class="fe fe-arrows-rotate me-2 "></i>Refresh</a> --}}
                                {{-- <a class="dropdown-item" href="{{ $order->delivery_note_url }}" target="_blank"><i class="fe fe-arrows-rotate me-2 "></i>Delivery Note</a> --}}
                                <a class="dropdown-item" href="https://backmarket.fr/bo-seller/listings/active?sku={{ $variation->sku }}" target="_blank"><i class="fe fe-caret me-2"></i>View Listing in BackMarket</a>
                                <a class="dropdown-item" href="{{url('order')}}?sku={{ $variation->sku }}&start_date={{ $start_date }}&end_date={{ $end_date }}" target="_blank"><i class="fe fe-caret me-2"></i>View Orders</a>
                                <a class="dropdown-item" href="https://backmarket.fr/bo-seller/orders/all?sku={{ $variation->sku }}&startDate={{ $start_date }}&endDate={{ $end_date }}" target="_blank"><i class="fe fe-caret me-2"></i>View Orders in BackMarket</a>
                                {{-- <a class="dropdown-item" href="javascript:void(0);"><i class="fe fe-trash-2 me-2"></i>Delete</a> --}}
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"><strong>Total:</strong></td>
                    <td title="Total"><strong>{{ $total }}</strong></td>
                    @if (session('user')->hasPermission('view_price'))
                    <td title="Weighted Average"><strong>€{{ amount_formatter($weighted_average,2) }}</strong></td>
                    @endif
                </tr>
            </tfoot>
        </table>
    </div>
</div>
