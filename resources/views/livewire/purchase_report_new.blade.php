@extends('layouts.new')

@section('styles')
<style type="text/css" media="print">
    @page { size: landscape; }
  </style>


@endsection

    @section('content')

            <div class="card m-0">
                <div class="card-header m-0">
                    <h4 class="card-title mb-0">Purchase Report</h4>
                </div>
                <div class="card-body m-0 p-2">
                    @php
                        // Output as HTML table using Blade syntax
                        $i = 0;
                    @endphp
                    <table class="table table-bordered table-hover text-md-nowrap">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Product Name</th>
                                <th>Item Count</th>
                                <th>Item Average</th>
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
                                    @foreach ($vendors as $vendor_id => $vendor_name)
                                        @if (!isset($row['vendors'][$vendor_id]))
                                            <td></td>
                                        @else
                                            @php
                                                $vendor_data = $row['vendors'][$vendor_id] ?? ['item_count' => null, 'item_sum' => null, 'item_average' => null, 'sellable_percentage' => null];
                                            @endphp
                                            <td>
                                                {{ $vendor_data['item_count'] }} |
                                                €{{ $vendor_data['item_average'] }} |
                                                {{ $vendor_data['sellable_percentage'] }}%
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

    @endsection
