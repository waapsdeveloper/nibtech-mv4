@extends('layouts.new')

@section('styles')
<style type="text/css" media="print">
    @page { size: landscape; }
  </style>


@endsection

    @section('content')

            <div class="card m-0">
                <div class="card-header m-0">
                    <h4 class="card-title mb-0">Stock Report</h4>
                </div>
                <div class="card-body m-0 p-2">
                    <table class="table table-bordered table-hover text-md-nowrap">
                        <thead>
                            <tr>
                                <th><small><b>No</b></small></th>
                                <th><small><b>Products</b></small></th>
                                <th><small><b>Vendors</b></small></th>
                                <th><small><b>IMEI</b></small></th>
                                @if (session('user')->hasPermission('view_cost'))
                                    <th><small><b>Cost</b></small></th>
                                    <th><small><b>Repair</b></small></th>
                                    <th><small><b>Fee</b></small></th>
                                @endif
                                @if (session('user')->hasPermission('view_price'))
                                <th><small><b>EUR Price</b></small></th>
                                <th><small><b>GBP Price</b></small></th>
                                @endif
                                <th><small><b>Profit</b></small></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $count = 0;
                                $price = [];
                                $imei_list = [];

                            @endphp
                            @foreach ($stocks as $s => $stock)
                                @php
                                    $variation = $stock->variation;
                                    $purchase_order = $stock->order;
                                    $purchase_item = $stock->purchase_item;
                                    $order_items = $stock->order_items->where('order_id', '!=',$stock->order_id);

                                    if(isset($total_cost)){
                                        $total_cost += $purchase_item->price;
                                    }else{
                                        $total_cost = $purchase_item->price;
                                    }
                                    if (in_array($stock->imei.$stock->serial_number, $imei_list)) {
                                        echo "Duplicate IMEI: ".$stock->imei.$stock->serial_number."<br>";
                                    }else{
                                        $imei_list[] = $stock->imei.$stock->serial_number;
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $s+1 }}</td>
                                    <td>{{ $products[$variation->product_id] ?? ''}} {{ $storages[$variation->storage] ?? '' }} {{ $colors[$variation->color] ?? '' }} {{ $grades[$variation->grade] ?? '' }}</td>
                                    <td><a href="#" onclick="window.open('{{url('report')}}/vendor_report/{{$purchase_order->customer_id}}?start_date={{request('start_date')}}&end_date={{request('end_date')}}','_blank','print_popup','width=1800,height=800');">{{ $vendors[$purchase_order->customer_id] }} </a></td>
                                    <td><a title="{{$stock->id}} | Search Serial" href="{{url('imei')."?imei=".$stock->imei.$stock->serial_number}}" target="_blank"> {{$stock->imei.$stock->serial_number }} </a></td>
                                    @if (session('user')->hasPermission('view_cost'))
                                    <td>€{{ amount_formatter($purchase_item->price,2) }}</td>
                                    <td title="Count: {{$stock->stock_repairs->count()}}">€{{ amount_formatter($stock->stock_repairs->sum('price'),2) }}</td>
                                    <td>{{ amount_formatter($stock->charges,2) }}</td>
                                   @endif
                                    @foreach ($order_items as $ind => $item)
                                        @php
                                            $i_order = $item->order;
                                            if($item->currency == null){
                                                $curr = $i_order->currency;
                                            }else{
                                                $curr = $item->currency;
                                            }
                                            if(!in_array($i_order->order_type_id,[4,3])){
                                                $curr = 4;
                                            }
                                            if(isset($total[$ind][$curr])){
                                                $total[$ind][$curr] += $item->price;
                                            }else{
                                                $total[$ind][$curr] = $item->price;
                                            }
                                            if (in_array($i_order->order_type_id,[2,3,5])) {
                                                if ((request('start_date') <= $item->created_at && $item->created_at <= request('end_date')) || (request('start_date') <= $i_order->processed_at && $i_order->processed_at <= request('end_date'))) {
                                                    $count ++;
                                                    if(isset($price[$curr])){
                                                        $price[$curr] += $item->price;
                                                    }else{
                                                        $price[$curr] = $item->price;
                                                    }
                                                }
                                            }
                                        @endphp
                                        <td title="{{$item->id}}">
                                            {{ $order_types[$i_order->order_type_id] }}<br>
                                            {{ $i_order->reference_id }}<br>
                                            {{ $currencies[$curr] }}{{ $item->price }}<br>
                                            {{ $i_order->created_at }}<br>
                                            {{ $i_order->processed_at }}
                                        </td>
                                    @endforeach
                                 </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2">Total</th>
                                <th>{{$count}}</th>
                                <th>
                                    @foreach ($price as $curr => $price)
                                        {{$currencies[$curr].$price}}<br>
                                    @endforeach
                                </th>
                                <th>{{$total_cost}}</th>
                                <th></th>
                                <th></th>
                                @foreach ($total as $ind)
                                    <th>
                                    @foreach ($ind as $curr => $price)
                                        {{$currencies[$curr].$price}}<br>
                                    @endforeach
                                    </th>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

    @endsection

    @section('scripts')

    @endsection
