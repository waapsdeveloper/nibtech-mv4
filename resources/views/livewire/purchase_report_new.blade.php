@extends('layouts.new')

@section('styles')
<style type="text/css" media="print">
    @page { size: landscape; }
  </style>


@endsection

    @section('content')

            <div class="card m-0">
                <div class="card-header m-0 d-flex justify-content-between">
                    <h4 class="card-title mb-0">Purchase Report</h4>

                    <form action="" method="GET" id="index" class="mb-0 form-inline">
                        <div class="form-floating">
                            <select class="form-select" name="vendor" id="vendor">
                                <option value="">All Vendors</option>
                                @foreach ($vendors as $vendor_id => $vendor_name)
                                    <option value="{{ $vendor_id }}" {{ $vendor_id == request('vendor') ? 'selected' : '' }}>{{ $vendor_name }}</option>
                                @endforeach
                            </select>
                            <label for="vendor_id">Vendor</label>
                        </div>
                        <div class="form-floating">
                            <input class="form-control" id="datetimepicker" type="date" id="start" name="start_date" value="{{$start_date}}">
                            <label for="start">{{ __('locale.Start Date') }}</label>
                        </div>
                        <div class="form-floating">
                            <input class="form-control" id="datetimepicker" type="date" id="end" name="end_date" value="{{$end_date}}">
                            <label for="end">{{ __('locale.End Date') }}</label>
                        </div>
                        <button type="submit" class="btn btn-icon  btn-success me-1"><i class="fe fe-search"></i></button>
                    </form>
                </div>
                <div class="card-body m-0 p-2">
                    @php
                        // Output as HTML table using Blade syntax
                        $i = 0;
                    @endphp
                    <table class="table table-sm table-bordered table-hover text-md-nowrap">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Product Name</th>
                                <th title="Total Purchase">Prchs</th>
                                <th title="Average Cost">Cost</th>
                                <th title="Total Sales">Sales</th>
                                <th title="Average Sales Price">Price</th>
                                <th title="Available Sellable Stock">Sellable</th>
                                @foreach ($vendors as $vendor_id => $vendor_name)
                                    <th>{{ $vendor_name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($list as $pss_id => $row)
                                <tr>
                                    <td>{{ ++$i }}</td>
                                    <td>{{ $row['product_name'] }} {{ $row['storage_name'] }}</td>
                                    <td>{{ $row['item_count'] }}</td>
                                    <td>€{{ $row['item_average'] }}</td>
                                    <td>{{ $row['sold_item_count'] }}</td>
                                    <td>€{{ $row['sold_item_average'] }}</td>
                                    <td>
                                        <a href="{{ url('inventory') }}?product={{ $row['product_id'] }}&storage={{ $row['storage_id'] }}" title="View Inventory" target="_blank">
                                        {{ $row['available_sellable_stock_count'] }}
                                        </a>
                                    </td>

                                    @foreach ($vendors as $vendor_id => $vendor_name)
                                        @if (!isset($row['vendors'][$vendor_id]))
                                            <td></td>
                                        @else
                                            @php
                                                $vendor_data = $row['vendors'][$vendor_id] ?? ['item_count' => null, 'item_sum' => null, 'item_average' => null, 'sellable_percentage' => null];
                                            @endphp
                                            <td
                                                @if ($vendor_data['sellable_percentage'] > 100)
                                                    class="table-danger"
                                                @elseif ($vendor_data['sellable_percentage'] < 70)
                                                    class="table-warning"
                                                @elseif ($vendor_data['sellable_percentage'] < 50)
                                                    class="table-danger"
                                                @endif
                                                title="{{ $vendor_name.': '.$vendor_data['sellable_stock_count'] }}"
                                                >
                                                <a href="javascript:void(0);" onclick="load_imeis({{ json_encode($vendor_data['imeis']) }})"
                                                title="Stocks: {{ implode(',', $vendor_data['imeis']) }}"
                                                >
                                                    {{ $vendor_data['item_count'] }} |
                                                    €{{ $vendor_data['item_average'] }} |
                                                </a>
                                                <a href="javascript:void(0);" onclick="load_imeis({{ json_encode($vendor_data['sellable_imeis']) }})"
                                                title="Sellable Stocks: {{ implode(',', $vendor_data['sellable_imeis']) }}"
                                                >
                                                    {{ $vendor_data['sellable_percentage'] }}%
                                                </a>
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                </div>
            </div>

    @endsection

    @section('scripts')
        <script>
            function load_imeis(imeis) {
                if (imeis.length > 0) {
                    for (let i = 0; i < imeis.length; i++) {
                        let imei = imeis[i];
                        window.open("{{ url('imei') }}?imei=" + imei, '_blank');
                    }
                }
            }
        </script>
    @endsection
