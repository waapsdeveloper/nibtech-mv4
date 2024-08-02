@extends('layouts.new')

@section('styles')
<style type="text/css" media="print">
    @page { size: landscape; }
  </style>


@endsection

    @section('content')

            <div class="card m-0">
                <div class="card-header m-0">
                    <h4 class="card-title mb-0">P&L by Products</h4>
                </div>
                <div class="card-body m-0 p-2">
                    <table class="table table-bordered table-hover text-md-nowrap">
                        <thead>
                            <tr>
                                <th><small><b>No</b></small></th>
                                <th><small><b>products</b></small></th>
                                <th style="width: 100px;"><small><b>Qty</b></small></th>
                                @if (session('user')->hasPermission('view_cost'))
                                    <th style="width: 230px;"><small><b>Cost</b></small></th>
                                    <th style="width: 150px;"><small><b>Repair</b></small></th>
                                    <th style="width: 150px;"><small><b>Fee</b></small></th>
                                @endif
                                @if (session('user')->hasPermission('view_price'))
                                <th style="width: 250px;"><small><b>EUR Price</b></small></th>
                                <th style="width: 200px;"><small><b>GBP Price</b></small></th>
                                @endif
                                <th style="width: 220px;"><small><b>Profit</b></small></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $sales_products = [];
                                $sales_storages = [];

                                $total_sale_orders = 0;
                                $total_approved_sale_orders = 0;
                                $total_sale_eur_items = 0;
                                $total_approved_sale_eur_items = 0;
                                $total_sale_gbp_items = 0;
                                $total_approved_sale_gbp_items = 0;
                                $total_sale_cost = 0;
                                $total_repair_cost = 0;
                                $total_eur_profit = 0;
                                $total_gbp_profit = 0;

                                $total_return_orders = 0;
                                $total_approved_return_orders = 0;
                                $total_return_eur_items = 0;
                                $total_approved_return_eur_items = 0;
                                $total_return_gbp_items = 0;
                                $total_approved_return_gbp_items = 0;
                                $total_return_cost = 0;
                                $total_repair_return_cost = 0;
                                $total_eur_loss = 0;
                            @endphp
                            @foreach ($aggregated_sales as $s => $sales)
                                @php
                                    $returns = $aggregated_returns->where('product_id', $sales->product_id)->where('storage', $sales->storage)->first();
                                    $sales_products[] = $sales->product_id.','.$sales->storage;
                                    $sales_storages[] = $sales->storage;

                                    $total_sale_orders += $sales->orders_qty;
                                    $total_sale_eur_items += $sales->eur_items_sum;
                                    $total_sale_gbp_items += $sales->gbp_items_sum;
                                    $total_sale_cost += $aggregated_sales_cost[$sales->product_id][$sales->storage];
                                    $total_repair_cost += $sales->items_repair_sum;
                                    $total_eur = $sales->eur_items_sum - $aggregated_sales_cost[$sales->product_id][$sales->storage] - $sales->items_repair_sum;
                                    $total_gbp = $sales->gbp_items_sum;
                                    if($returns != null){
                                        $total_return_orders += $returns->orders_qty;
                                        $total_approved_return_orders += $returns->approved_orders_qty;
                                        $total_return_eur_items += $returns->eur_items_sum;
                                        $total_approved_return_eur_items += $returns->eur_approved_items_sum;
                                        $total_return_gbp_items += $returns->gbp_items_sum;
                                        $total_approved_return_gbp_items += $returns->gbp_approved_items_sum;
                                        $total_return_cost += $aggregated_return_cost[$returns->product_id][$returns->storage];
                                        $total_repair_return_cost += $returns->items_repair_sum;
                                        $eur_loss = $returns->eur_items_sum - $aggregated_return_cost[$returns->product_id][$returns->storage] - $returns->items_repair_sum;
                                        $total_eur = $total_eur - $eur_loss;
                                        $total_gbp = $total_gbp - $returns->gbp_items_sum;
                                    }
                                    $total_eur_profit += $total_eur;
                                    $total_gbp_profit += $total_gbp;
                                @endphp
                                <tr>
                                    <td>{{ $s+1 }}</td>
                                    <td>{{ $products[$sales->product_id] ." ". $storages[$sales->storage] }}</td>
                                    <td>{{ $sales->orders_qty }} {{ isset($returns->orders_qty) ? "(" . $returns->orders_qty.")" : null }}
                                    </td>
                                    @if (session('user')->hasPermission('view_cost'))
                                    <td title="{{count(explode(',',$sales->stock_ids))}}">€{{ number_format($aggregated_sales_cost[$sales->product_id][$sales->storage],2) }} @if ($returns != null) (€{{ number_format($aggregated_return_cost[$returns->product_id][$returns->storage],2) }}) @endif</td>
                                    <td>€{{ number_format($sales->items_repair_sum,2) }} @if ($returns != null) (€{{ number_format($returns->items_repair_sum,2) }}) @endif</td>
                                    <td>{{ number_format(0,2) }} @if ($returns != null) () @endif</td>
                                   @endif
                                    @if (session('user')->hasPermission('view_price'))
                                    <td>€{{ number_format($sales->eur_items_sum,2) }} @if ($returns != null) (€{{ number_format($returns->eur_items_sum,2) }}) @endif</td>
                                    <td>£{{ number_format($sales->gbp_items_sum,2) }} @if ($returns != null) (£{{ number_format($returns->gbp_items_sum,2) }}) @endif</td>
                                    @endif
                                    <td>€{{ number_format($total_eur,2)  }} + £{{ number_format($total_gbp,2) }}</td>
                                 </tr>
                            @endforeach
                            @foreach ($aggregated_returns as $s => $returns)
                                @php
                                    $skip = false;
                                    foreach ($sales_products as $key => $product) {
                                        $pro = explode(',',$product);
                                        if ($returns->product_id == $pro[0] && $returns->storage == $pro[1]) {
                                            $skip = true;
                                            break;
                                        }
                                    }
                                    if($skip == true){
                                        continue;
                                    }
                                    $total_return_orders += $returns->orders_qty;
                                    $total_approved_return_orders += $returns->approved_orders_qty;
                                    $total_return_eur_items += $returns->eur_items_sum;
                                    $total_approved_return_eur_items += $returns->eur_approved_items_sum;
                                    $total_return_gbp_items += $returns->gbp_items_sum;
                                    $total_approved_return_gbp_items += $returns->gbp_approved_items_sum;
                                    $total_return_cost += $aggregated_return_cost[$returns->product_id][$returns->storage];
                                    $total_repair_return_cost += $returns->items_repair_sum;
                                    $total_eur_loss += $returns->eur_items_sum - $aggregated_return_cost[$returns->product_id][$returns->storage] - $returns->items_repair_sum;
                                @endphp
                                <tr>
                                    <td>{{ $s+1 }}</td>
                                    <td>{{ $products[$returns->product_id]." ".$storages[$returns->storage] }}</td>
                                    <td>({{ $returns->orders_qty }})</td>
                                    @if (session('user')->hasPermission('view_cost'))
                                    <td title="{{count(explode(',',$returns->stock_ids))}}">(€{{ number_format($aggregated_return_cost[$returns->product_id][$returns->storage],2) }})</td>
                                    <td>(€{{ number_format($returns->items_repair_sum,2) }})</td>
                                    <td>({{ number_format(0,2) }})</td>
                                    @endif
                                    @if (session('user')->hasPermission('view_price'))
                                    <td>(€{{ number_format($returns->eur_items_sum,2) }})</td>
                                    <td>(£{{ number_format($returns->gbp_items_sum,2) }})</td>
                                    @endif
                                    <td>(€{{ number_format(-$returns->eur_items_sum + $aggregated_return_cost[$returns->product_id][$returns->storage] + $returns->items_repair_sum,2) }} + £{{ number_format($returns->gbp_items_sum,2) }})</td>
                                </tr>
                            @endforeach
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
                            <tr>
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
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

    @endsection

    @section('scripts')

    @endsection
