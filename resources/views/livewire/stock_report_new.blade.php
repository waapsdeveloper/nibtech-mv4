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
                                @endphp
                                <tr>
                                    <td>{{ $s+1 }}</td>
                                    <td>{{ $products[$variation->product_id] ?? ''}} {{ $storages[$variation->storage] ?? '' }} {{ $colors[$variation->color] ?? '' }} {{ $grades[$variation->grade] ?? '' }}</td>
                                    <td><a href="#" onclick="window.open('{{url('report')}}/vendor_report/{{$purchase_order->customer_id}}?start_date={{request('start_date')}}&end_date={{request('end_date')}}','_blank','print_popup','width=1800,height=800');">{{ $vendors[$purchase_order->customer_id] }} </a></td>
                                    <td><a title="{{$stock->id}} | Search Serial" href="{{url('imei')."?imei=".$stock->imei.$stock->serial_number}}" target="_blank"> {{$stock->imei.$stock->serial_number }} </a></td>
                                    @if (session('user')->hasPermission('view_cost'))
                                    <td>€{{ number_format($purchase_item->price,2) }}</td>
                                    <td title="Count: {{$stock->stock_repairs->count()}}">€{{ number_format($stock->stock_repairs->sum('price'),2) }}</td>
                                    <td>{{ number_format(0,2) }}</td>
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
                                                if (request('start_date') <= $i_order->created_at <= request('end_date') || request('start_date') <= $i_order->processed_at <= request('end_date')) {
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
                                    {{-- @if (session('user')->hasPermission('view_price'))
                                    <td>€{{ number_format($sales->eur_items_sum,2) }} </td>
                                    <td>£{{ number_format($gbp_items_sum,2) }} @if ($returns != null && isset($returns->gbp_items_sum)) (£{{ number_format($returns->gbp_items_sum,2) }}) @endif</td>
                                    @endif
                                    <td>€{{ number_format($total_eur,2)  }} + £{{ number_format($total_gbp,2) }}</td> --}}
                                 </tr>
                            @endforeach
                            {{-- @foreach ($aggregated_returns as $s => $returns)
                                @php
                                    $skip = false;
                                    if (request('bp') == 1){
                                        foreach ($sales_products as $key => $product) {
                                            $pro = explode(',',$product);
                                            if ($returns->product_id == $pro[0] && $returns->storage == $pro[1]) {
                                                $skip = true;
                                                break;
                                            }
                                        }
                                        $return_cost = $aggregated_return_cost[$returns->product_id][$returns->storage];
                                    }
                                    if (request('bc') == 1){
                                        if (in_array($returns->customer_id, $sales_customers)) {
                                            $skip = true;
                                            break;
                                        }
                                        $return_cost = $aggregated_return_cost[$returns->customer_id];
                                    }
                                    if (request('bv') == 1){
                                        if (in_array($returns->customer_id, $sales_vendors)) {
                                            $skip = true;
                                            break;
                                        }
                                        $return_cost = $aggregated_return_cost[$returns->customer_id];
                                    }

                                    if($skip == true){
                                        continue;
                                    }
                                    $total_return_orders += $returns->orders_qty;
                                    // $total_approved_return_orders += $returns->approved_orders_qty;
                                    $total_return_eur_items += $returns->eur_items_sum;
                                    // $total_approved_return_eur_items += $returns->eur_approved_items_sum;
                                    $total_return_gbp_items += $returns->gbp_items_sum;
                                    // $total_approved_return_gbp_items += $returns->gbp_approved_items_sum;
                                    $total_return_cost += $return_cost;
                                    $total_repair_return_cost += $returns->items_repair_sum;
                                    $total_eur_loss += $returns->eur_items_sum - $return_cost - $returns->items_repair_sum;
                                @endphp
                                <tr>
                                    <td>{{ $s+1 }}</td>
                                    @if (request('bp') == 1)
                                    <td>{{ $products[$returns->product_id] ." ". $storages[$returns->storage] }}</td>
                                    @endif
                                    @if (request('bc') == 1)
                                    <td>{{ $customers[$returns->customer_id] }}</td>
                                    @endif
                                    @if (request('bv') == 1)
                                    <td>{{ $vendors[$returns->customer_id] }}</td>
                                    @endif
                                    <td>({{ $returns->orders_qty }})</td>
                                    @if (session('user')->hasPermission('view_cost'))
                                    <td title="{{count(explode(',',$returns->stock_ids))}}">(€{{ number_format($return_cost,2) }})</td>
                                    <td>(€{{ number_format($returns->items_repair_sum,2) }})</td>
                                    <td>({{ number_format(0,2) }})</td>
                                    @endif
                                    @if (session('user')->hasPermission('view_price'))
                                    <td>(€{{ number_format($returns->eur_items_sum,2) }})</td>
                                    <td>(£{{ number_format($returns->gbp_items_sum,2) }})</td>
                                    @endif
                                    <td>(€{{ number_format(-$returns->eur_items_sum + $return_cost + $returns->items_repair_sum,2) }} + £{{ number_format($returns->gbp_items_sum,2) }})</td>
                                </tr>
                            @endforeach --}}
                            {{-- <tr>
                                <td colspan="2"><strong>Profit</strong></td>
                                <td><strong>{{ $total_sale_orders." (".$total_approved_sale_orders.")" }}</strong></td>
                                @if (session('user')->hasPermission('view_price'))
                                <td><strong>€{{ number_format($total_sale_eur_items,2)." (€".number_format($total_approved_sale_eur_items,2).")" }}</strong></td>
                                <td><strong>£{{ number_format($total_sale_gbp_items,2)." (£".number_format($total_approved_sale_gbp_items,2).")" }}</strong></td>
                                @endif
                                @if (session('user')->hasPermission('view_cost'))
                                <td title=""><strong>€{{ number_format($total_sale_cost,2) }}</strong></td>
                                <td><strong>€{{ number_format($total_repair_cost,2) }}</strong></td>
                                <td><strong>{{ number_format(0,2) }}</strong></td>
                                <td><strong>€{{ number_format($total_eur_profit) }} + £{{ number_format($total_sale_gbp_items,2) }}</strong></td>
                                @endif
                            </tr> --}}

                            {{-- <tr>
                                <td colspan="9" align="center"><b>Returns</b></td>
                            </tr>
                            @foreach ($aggregated_returns as $s => $returns)
                                @php
                                    $total_return_orders += $returns->orders_qty;
                                    $total_approved_return_orders += $returns->approved_orders_qty;
                                    $total_return_eur_items += $returns->eur_items_sum;
                                    $total_approved_return_eur_items += $returns->eur_approved_items_sum;
                                    $total_return_gbp_items += $returns->gbp_items_sum;
                                    $total_approved_return_gbp_items += $returns->gbp_approved_items_sum;
                                    $total_return_cost += $aggregated_return_cost[$returns->product_id];
                                    $total_repair_return_cost += $returns->items_repair_sum;
                                    $total_eur_loss += $returns->eur_items_sum - $aggregated_return_cost[$returns->product_id] - $returns->items_repair_sum;
                                @endphp
                                <tr>
                                    <td>{{ $s+1 }}</td>
                                    <td>{{ $products[$returns->product_id] }}</td>
                                    <td>{{ $returns->orders_qty }}</td>
                                    @if (session('user')->hasPermission('view_price'))
                                    <td>€{{ number_format($returns->eur_items_sum,2) }}</td>
                                    <td>£{{ number_format($returns->gbp_items_sum,2) }}</td>
                                    @endif
                                    @if (session('user')->hasPermission('view_cost'))
                                    <td title="{{count(explode(',',$returns->stock_ids))}}">€{{ number_format($aggregated_return_cost[$returns->product_id],2) }}</td>
                                    <td>€{{ number_format($returns->items_repair_sum,2) }}</td>
                                    <td>{{ number_format(0,2) }}</td>
                                    <td>€{{ number_format(-$returns->eur_items_sum + $aggregated_return_cost[$returns->product_id] + $returns->items_repair_sum,2) }} + £{{ number_format($returns->gbp_items_sum,2) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="2"><strong>Loss</strong></td>
                                <td><strong>{{ $total_return_orders }}</strong></td>
                                @if (session('user')->hasPermission('view_price'))
                                <td><strong>€{{ number_format($total_return_eur_items,2) }}</strong></td>
                                <td><strong>£{{ number_format($total_return_gbp_items,2) }}</strong></td>
                                @endif
                                @if (session('user')->hasPermission('view_cost'))
                                <td title=""><strong>€{{ number_format($total_return_cost,2) }}</strong></td>
                                <td><strong>€{{ number_format($total_repair_return_cost,2) }}</strong></td>
                                <td><strong>{{ number_format(0,2) }}</strong></td>
                                <td><strong>€{{ number_format($total_eur_loss) }} + £{{ number_format($total_return_gbp_items,2) }}</strong></td>
                                @endif
                            </tr> --}}
                            {{-- <tr>
                                <td colspan="2"><strong>Net</strong></td>
                                <td><strong>{{ $total_sale_orders-$total_return_orders }}</strong></td>
                                @if (session('user')->hasPermission('view_cost'))
                                <td title=""><strong>€{{ number_format($total_sale_cost-$total_return_cost,2) }}</strong></td>
                                <td><strong>€{{ number_format($total_repair_cost-$total_repair_return_cost,2) }}</strong></td>
                                <td><strong>{{ number_format(0,2) }}</strong></td>
                                @endif
                                @if (session('user')->hasPermission('view_price'))
                                <td><strong>€{{ number_format($total_sale_eur_items-$total_return_eur_items,2) }}</strong></td>
                                <td><strong>£{{ number_format($total_sale_gbp_items-$total_return_gbp_items,2) }}</strong></td>
                                @endif
                                <td><strong>€{{ number_format($total_eur_profit-$total_eur_loss) }} + £{{ number_format($total_sale_gbp_items-$total_return_gbp_items,2) }}</strong></td>
                            </tr> --}}
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
