<div wire:init="loadOrders" class="orders-table-wrapper">
    <div wire:loading.delay.longer class="py-4 text-center text-muted">
        <span class="spinner-border spinner-border-sm align-middle" role="status"></span>
        <span class="ms-2 align-middle">Loading orders&hellip;</span>
    </div>

    <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    @php
                        $from = $readyToLoad ? $orders->firstItem() : null;
                        $to = $readyToLoad ? $orders->lastItem() : null;
                        $total = $readyToLoad ? $orders->total() : null;
                    @endphp
                        <div class="card-header pb-0 d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">
                                <label>
                                    <input type="checkbox" id="checkAll" onclick="checkAll()"> Check All
                                </label>
                                @if(request('missing') == 'scan')
                                    <input type="hidden" name="missing" value="scan" form="pdf">
                                @endif
                                <input class="btn btn-sm btn-secondary" type="submit" value="Print Labels" form="pdf" onclick=" if($('.table-hover :checkbox:checked').length == 0){event.preventDefault();alert('No Order Selected');}">
                            </h4>
                            <h5 class="card-title mg-b-0">{{ __('locale.From') }} {{ $from ?? '--' }} {{ __('locale.To') }} {{ $to ?? '--' }} {{ __('locale.Out Of') }} {{ $total ?? '--' }} </h5>

                            <div class="row">
                                {{-- <div class="form-group"> --}}
                                    <label for="perPage" class="card-title inline">Sort:</label>
                                    <select name="sort" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()" form="search">
                                        <option value="1" {{ Request::get('sort') == 1 ? 'selected' : '' }}>Order DESC</option>
                                        <option value="2" {{ Request::get('sort') == 2 ? 'selected' : '' }}>Order ASC</option>
                                        <option value="3" {{ Request::get('sort') == 3 ? 'selected' : '' }}>Name DESC</option>
                                        <option value="4" {{ Request::get('sort') == 4 ? 'selected' : '' }}>Name ASC</option>
                                    </select>
                                    {{-- <button type="submit">Apply</button> --}}
                                {{-- </div>
                                <div class="form-group"> --}}
                                    <label for="perPage" class="card-title inline">per page:</label>
                                    <select name="per_page" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()" form="search">
                                        <option value="10" {{ Request::get('per_page') == 10 ? 'selected' : '' }}>10</option>
                                        <option value="20" {{ Request::get('per_page') == 20 ? 'selected' : '' }}>20</option>
                                        <option value="50" {{ Request::get('per_page') == 50 ? 'selected' : '' }}>50</option>
                                        <option value="100" {{ Request::get('per_page') == 100 ? 'selected' : '' }}>100</option>
                                    </select>
                                    {{-- <button type="submit">Apply</button> --}}
                                {{-- </div> --}}
                            </div>

                    </div>

                    <datalist id="tester_list">
                        @foreach ($testers as $tester)
                            <option value="{{ $tester }}">
                        @endforeach
                    </datalist>
                        @if (! $readyToLoad)
                        <div class="card-body py-5 text-center text-muted">
                            <span class="spinner-border spinner-border-sm align-middle" role="status"></span>
                            <span class="ms-2 align-middle">Preparing orders&hellip;</span>
                        </div>
                        @else
                        <div class="card-body">
                            <div class="table-responsive">
                                <form id="pdf" method="POST" target="_blank" action="{{ url('export_label') }}">
                                    @csrf
                                    <input type="hidden" name="sort" value="{{ Request::get('sort') }}">
                                </form>
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
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $rowNumber = $orders->firstItem() - 1;
                                            $renderedOrderIds = [];
                                        @endphp
                                        @foreach ($orders as $order)
                                            @if (in_array($order->id, $renderedOrderIds, true))
                                                @continue
                                            @endif
                                            @php
                                                $renderedOrderIds[] = $order->id;
                                                $rowNumber++;
                                                $rowCounter = $rowCounters[$order->id] ?? [
                                                    'tester_start' => null,
                                                    'tester_count' => 0,
                                                    'imei_start' => null,
                                                    'imei_count' => 0,
                                                ];
                                            @endphp

                                            @include('livewire.order.partials.order-row', [
                                                'order' => $order,
                                                'rowNumber' => $rowNumber,
                                                'rowCounter' => $rowCounter,
                                                'storages' => $storages,
                                                'colors' => $colors,
                                                'grades' => $grades,
                                                'admins' => $admins,
                                                'currencies' => $currencies,
                                                'order_statuses' => $order_statuses,
                                            ])
                                        @endforeach
                                        @php
                                            if (session()->has('refresh')) {
                                                session()->forget('refresh');
                                            }
                                        @endphp
                                    </tbody>
                                    @php
                                        $paginationColspan = session('user')->hasPermission('view_profit') ? 5 : 4;
                                        $totalsColspan = 3;
                                    @endphp
                                    <tfoot>
                                        <tr>
                                            <td colspan="{{ $paginationColspan }}">
                                                {{ $orders->onEachSide(3)->links() }} {{ __('locale.From') }} {{ $orders->firstItem() }} {{ __('locale.To') }} {{ $orders->lastItem() }} {{ __('locale.Out Of') }} {{ $orders->total() }}
                                            </td>
                                            @if (request('missing_refund') || request('missing_reimburse'))
                                                <td>
                                                    <a
                                                        class="dropdown-item"
                                                        id="open_all_imei"
                                                        href="#"
                                                        data-imei-list='@json($pageImeiList)'
                                                        data-imei-base="{{ url('imei') }}"
                                                    >Open All IMEI Details</a>
                                                </td>
                                            @else
                                                <td></td>
                                            @endif
                                            <td colspan="{{ $totalsColspan }}" class="text-end">
                                                Total Items in this page: {{ $total_items }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                                <br>
                            </div>
                        </div>

                        @endif
                        {{-- </div> --}}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

