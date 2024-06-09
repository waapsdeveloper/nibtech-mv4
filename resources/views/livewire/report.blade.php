@extends('layouts.app')

    @section('styles')

		<!-- INTERNAL Select2 css -->
		<link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet" />

		<!-- INTERNAL Data table css -->
		<link href="{{asset('assets/plugins/datatable/css/dataTables.bootstrap5.css')}}" rel="stylesheet" />
		<link href="{{asset('assets/plugins/datatable/css/buttons.bootstrap5.min.css')}}"  rel="stylesheet">
		<link href="{{asset('assets/plugins/datatable/responsive.bootstrap5.css')}}" rel="stylesheet" />

    @endsection

    @section('content')
					<!-- breadcrumb -->
					<div class="breadcrumb-header justify-content-between">
						<div class="left-content">
						<span class="main-content-title mg-b-0 mg-b-lg-1">{{ __('locale.Dashboards') }}</span>
						</div>
						<div class="justify-content-center mt-2">
							<ol class="breadcrumb">
								<li class="breadcrumb-item active" aria-current="page">{{ __('locale.Dashboards') }}</li>
                                <li class="breadcrumb-item active" aria-current="page">Reports</li>
							</ol>
						</div>
					</div>
					<!-- /breadcrumb -->

            <div class="row">

                <div class="col-md">
                    {{-- <div class="card-header">
                        <h4 class="card-title mb-1">Model</h4>
                    </div> --}}
                    <div class="form-floating">
                        <input type="text" name="product" value="{{ Request::get('product') }}" class="form-control" data-bs-placeholder="Select Model" list="product-menu" form="index">
                        <label for="product">Product</label>
                    </div>
                    <datalist id="product-menu">
                        <option value="">Products</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @if(isset($_GET['product']) && $product->id == $_GET['product']) {{'selected'}}@endif>{{ $product->model }}</option>
                        @endforeach
                    </datalist>
                </div>
                <div class="col-md">
                    {{-- <div class="card-header">
                        <h4 class="card-title mb-1">Storage</h4>
                    </div> --}}
                    <select name="storage" class="form-control form-select" form="index">
                        <option value="">Storage</option>
                        @foreach ($storages as $id=>$name)
                            <option value="{{ $id }}" @if(isset($_GET['storage']) && $id == $_GET['storage']) {{'selected'}}@endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md">
                    {{-- <div class="card-header">
                        <h4 class="card-title mb-1">Storage</h4>
                    </div> --}}
                    <select name="color" class="form-control form-select" form="index">
                        <option value="">Color</option>
                        @foreach ($colors as $id=>$name)
                            <option value="{{ $id }}" @if(isset($_GET['color']) && $id == $_GET['color']) {{'selected'}}@endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md">
                    {{-- <div class="card-header">
                        <h4 class="card-title mb-1">Grade</h4>
                    </div> --}}
                    <select name="grade" class="form-control form-select" form="index">
                        <option value="">Grade</option>
                        @foreach ($grades as $id=>$name)
                            <option value="{{ $id }}" @if(isset($_GET['grade']) && $id == $_GET['grade']) {{'selected'}}@endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- <div class="col-md">
                    <div class="form-floating">
                        <input class="form-control" id="datetimepicker" type="date" id="start" name="start_date" value="{{$start_date}}">
                        <label for="start">{{ __('locale.Start Date') }}</label>
                    </div>
                </div>
                <div class="col-md">
                    <div class="form-floating">
                        <input class="form-control" id="datetimepicker" type="date" id="end" name="end_date" value="{{$end_date}}">
                        <label for="end">{{ __('locale.End Date') }}</label>
                    </div>
                </div>
                <div class="col-md">
                    <button type="submit" class="btn btn-icon  btn-success me-1"><i class="fe fe-search"></i></button>
                </div> --}}
            </div>
            <br>

            <div class="card">
                <div class="card-header mb-0">
                    <h4 class="card-title mb-0">Sales & Returns</h4>
                </div>

                <div class="card-body mt-0">
                    <table class="table table-bordered table-hover text-md-nowrap">
                        <thead>
                            <tr>
                                <th><small><b>No</b></small></th>
                                <th><small><b>Categories</b></small></th>
                                <th><small><b>Qty</b></small></th>
                                @if (session('user')->hasPermission('view_price'))
                                <th title=""><small><b>EUR Price</b></small></th>
                                <th title=""><small><b>GBP Price</b></small></th>
                                @endif
                                @if (session('user')->hasPermission('view_cost'))
                                    <th title=""><small><b>Cost</b></small></th>
                                    <th title=""><small><b>Repair</b></small></th>
                                    <th title=""><small><b>Fee</b></small></th>
                                    <th title=""><small><b>Profit</b></small></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $total_sale_orders = 0;
                                $total_approved_sale_orders = 0;
                                $total_sale_eur_items = 0;
                                $total_approved_sale_eur_items = 0;
                                $total_sale_gbp_items = 0;
                                $total_approved_sale_gbp_items = 0;
                                $total_sale_cost = 0;
                                $total_repair_cost = 0;
                                $total_eur_profit = 0;
                            @endphp
                            <tr>
                                <td colspan="9" align="center"><b>Sales</b></td>
                            </tr>
                            @foreach ($aggregated_sales as $s => $sales)
                                @php
                                    $total_sale_orders += $sales->orders_qty;
                                    $total_approved_sale_orders += $sales->approved_orders_qty;
                                    $total_sale_eur_items += $sales->eur_items_sum;
                                    $total_approved_sale_eur_items += $sales->eur_approved_items_sum;
                                    $total_sale_gbp_items += $sales->gbp_items_sum;
                                    $total_approved_sale_gbp_items += $sales->gbp_approved_items_sum;
                                    $total_sale_cost += $aggregated_sales_cost[$sales->category_id];
                                    $total_repair_cost += $sales->items_repair_sum;
                                    $total_eur_profit += $sales->eur_items_sum - $aggregated_sales_cost[$sales->category_id] - $sales->items_repair_sum;
                                @endphp
                                <tr>
                                    <td>{{ $s+1 }}</td>
                                    <td>{{ $categories[$sales->category_id] }}</td>
                                    <td>{{ $sales->orders_qty." (".$sales->approved_orders_qty.")" }}</td>
                                    @if (session('user')->hasPermission('view_price'))
                                    <td>€{{ number_format($sales->eur_items_sum,2)." (€".number_format($sales->eur_approved_items_sum,2).")" }}</td>
                                    <td>£{{ number_format($sales->gbp_items_sum,2)." (£".number_format($sales->gbp_approved_items_sum,2).")" }}</td>
                                    @endif
                                    @if (session('user')->hasPermission('view_cost'))
                                    <td title="{{count(explode(',',$sales->stock_ids))}}">€{{ number_format($aggregated_sales_cost[$sales->category_id],2) }}</td>
                                    <td>€{{ number_format($sales->items_repair_sum,2) }}</td>
                                    <td>{{ number_format(0,2) }}</td>
                                    <td>€{{ number_format($sales->eur_items_sum - $aggregated_sales_cost[$sales->category_id] - $sales->items_repair_sum,2) }} + £{{ number_format($sales->gbp_items_sum,2) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="2"><strong>Profif</strong></td>
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
                            </tr>
                        {{-- </tbody>
                    </table> --}}
                {{-- </div>
                <div class="card-header mb-0">
                    <h4 class="card-title mb-0">Returns Report</h4>
                </div>

                <div class="card-body mt-0"> --}}
                    {{-- <table class="table table-bordered table-hover text-md-nowrap">
                        <thead>
                            <tr>
                                <th><small><b>Returns</b></small></th>
                                <th><small><b>Categories</b></small></th>
                                <th><small><b>Qty</b></small></th>
                                @if (session('user')->hasPermission('view_price'))
                                <th title=""><small><b>EUR Sales</b></small></th>
                                <th title=""><small><b>GBP Sales</b></small></th>
                                @endif
                                @if (session('user')->hasPermission('view_cost'))
                                    <th title=""><small><b>Cost</b></small></th>
                                    <th title=""><small><b>Repair</b></small></th>
                                    <th title=""><small><b>Fee</b></small></th>
                                    <th title=""><small><b>Loss</b></small></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody> --}}
                            <tr>
                                <td colspan="9" align="center"><b>Returns</b></td>
                            </tr>
                            @php
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
                            @foreach ($aggregated_returns as $s => $returns)
                                @php
                                    $total_return_orders += $returns->orders_qty;
                                    $total_approved_return_orders += $returns->approved_orders_qty;
                                    $total_return_eur_items += $returns->eur_items_sum;
                                    $total_approved_return_eur_items += $returns->eur_approved_items_sum;
                                    $total_return_gbp_items += $returns->gbp_items_sum;
                                    $total_approved_return_gbp_items += $returns->gbp_approved_items_sum;
                                    $total_return_cost += $aggregated_return_cost[$returns->category_id];
                                    $total_repair_return_cost += $returns->items_repair_sum;
                                    $total_eur_loss += $returns->eur_items_sum - $aggregated_return_cost[$returns->category_id] - $returns->items_repair_sum;
                                @endphp
                                <tr>
                                    <td>{{ $s+1 }}</td>
                                    <td>{{ $categories[$returns->category_id] }}</td>
                                    <td>{{ $returns->orders_qty." (".$returns->approved_orders_qty.")" }}</td>
                                    @if (session('user')->hasPermission('view_price'))
                                    <td>€{{ number_format($returns->eur_items_sum,2) }}</td>
                                    <td>£{{ number_format($returns->gbp_items_sum,2) }}</td>
                                    @endif
                                    @if (session('user')->hasPermission('view_cost'))
                                    <td title="{{count(explode(',',$returns->stock_ids))}}">€{{ number_format($aggregated_return_cost[$returns->category_id],2) }}</td>
                                    <td>€{{ number_format($returns->items_repair_sum,2) }}</td>
                                    <td>{{ number_format(0,2) }}</td>
                                    <td>€{{ number_format(-$returns->eur_items_sum + $aggregated_return_cost[$returns->category_id] + $returns->items_repair_sum,2) }} + £{{ number_format($returns->gbp_items_sum,2) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="2"><strong>Loss</strong></td>
                                <td><strong>{{ $total_return_orders." (".$total_approved_return_orders.")" }}</strong></td>
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
                            </tr>
                            <tr>
                                <td colspan="2"><strong>Total</strong></td>
                                <td><strong>{{ $total_sale_orders-$total_return_orders." (".$total_approved_sale_orders-$total_approved_return_orders.")" }}</strong></td>
                                @if (session('user')->hasPermission('view_price'))
                                <td><strong>€{{ number_format($total_sale_eur_items-$total_return_eur_items,2)." (€".number_format($total_approved_sale_eur_items-$total_approved_return_eur_items,2).")" }}</strong></td>
                                <td><strong>£{{ number_format($total_sale_gbp_items-$total_return_gbp_items,2)." (£".number_format($total_approved_sale_gbp_items-$total_approved_return_gbp_items,2).")" }}</strong></td>
                                @endif
                                @if (session('user')->hasPermission('view_cost'))
                                <td title=""><strong>€{{ number_format($total_sale_cost-$total_return_cost,2) }}</strong></td>
                                <td><strong>€{{ number_format($total_repair_cost-$total_repair_return_cost,2) }}</strong></td>
                                <td><strong>{{ number_format(0,2) }}</strong></td>
                                <td><strong>€{{ number_format($total_eur_profit-$total_eur_loss) }} + £{{ number_format($total_sale_gbp_items-$total_return_gbp_items,2) }}</strong></td>
                                @endif
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>


					<!-- row -->
					<div class="row">
						<div class="col-xl-5 col-lg-12 col-md-12 col-sm-12">
							<div class="row">
								<div class="col-xl-12 col-lg-12 col-md-12 col-xs-12">
									<div class="card">
										<div class="card-header">
                                            <div class="d-flex justify-content-between">
											<h4 class="card-title mb-1">Top Selling Products</h4>

                                                {{-- @dd($products) --}}
                                            {{-- <select class="select2" multiple>
                                                @foreach ($products as $pro)
                                                    @php
                                                        // print_r($pro);
                                                    @endphp
                                                    <option value="{{ $pro['id'] }}">{{ $pro['series'] . " " .$pro['model'] }}</option>
                                                @endforeach
                                                <option>1</option>
                                                <option>2</option>
                                                <option>3</option>
                                                <option>4</option>
                                                <option>5</option>
                                            </select> --}}
                                            <form method="get" action="" class="row form-inline">
                                                <label for="perPage" class="card-title inline">Show:</label>
                                                <select name="per_page" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                                    <option value="10" {{ Request::get('per_page') == 10 ? 'selected' : '' }}>10</option>
                                                    <option value="20" {{ Request::get('per_page') == 20 ? 'selected' : '' }}>20</option>
                                                    <option value="50" {{ Request::get('per_page') == 50 ? 'selected' : '' }}>50</option>
                                                    <option value="100" {{ Request::get('per_page') == 100 ? 'selected' : '' }}>100</option>
                                                </select>
                                                {{-- <button type="submit">Apply</button> --}}
                                                <input type="hidden" name="start_date" value="{{ $start_date }}">
                                                <input type="hidden" name="end_date" value="{{ $end_date }}">
                                                <input type="hidden" name="product" value="{{ Request::get('product') }}">
                                                <input type="hidden" name="storage" value="{{ Request::get('storage') }}">>
                                                <input type="hidden" name="color" value="{{ Request::get('color') }}">
                                                <input type="hidden" name="grade" value="{{ Request::get('grade') }}">
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
                                                        @endphp
                                                        <tr>
                                                            <td>{{ $top+1 }}</td>
                                                            <td>{{ $product->product_name . " - " . $product->storage . " - " . $product->color . " - " . $product->grade }}</td>
                                                            <td>{{ $product->total_quantity_sold }}</td>
                                                            @if (session('user')->hasPermission('view_price'))
                                                            <td>€{{ number_format($product->average_price,2) }}</td>
                                                            @endif

                                                            <td>
                                                                <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical  tx-18"></i></a>
                                                                <div class="dropdown-menu">
                                                                    {{-- <a class="dropdown-item" href="{{url(session('url').'order')}}/refresh/{{ $order->reference_id }}"><i class="fe fe-arrows-rotate me-2 "></i>Refresh</a> --}}
                                                                    {{-- <a class="dropdown-item" href="{{ $order->delivery_note_url }}" target="_blank"><i class="fe fe-arrows-rotate me-2 "></i>Delivery Note</a> --}}
                                                                    <a class="dropdown-item" href="https://backmarket.fr/bo_merchant/listings/active?sku={{ $product->sku }}" target="_blank"><i class="fe fe-caret me-2"></i>View Listing in BackMarket</a>
                                                                    <a class="dropdown-item" href="{{url(session('url').'order')}}?sku={{ $product->sku }}&start_date={{ $start_date }}&end_date={{ $end_date }}" target="_blank"><i class="fe fe-caret me-2"></i>View Orders</a>
                                                                    <a class="dropdown-item" href="https://backmarket.fr/bo_merchant/orders/all?sku={{ $product->sku }}&startDate={{ $start_date }}&endDate={{ $end_date }}" target="_blank"><i class="fe fe-caret me-2"></i>View Orders in BackMarket</a>
                                                                    {{-- <a class="dropdown-item" href="javascript:void(0);"><i class="fe fe-trash-2 me-2"></i>Delete</a> --}}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="5" align="right">Weighted Average: €{{ number_format($weighted_average,2) }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
								</div>
							</div>
						</div>
						<div class="col-xl-7 col-lg-12 col-md-12 col-sm-12">
                            <div class="row">
                                <div class="col-xl-3 col-lg-3 col-md-4 col-xs-6">

                                    <div class="card">
                                        <div class="card-header">
                                            <h4 class="card-title mb-1">Orders</h4>
                                        </div>
                                        <div class="card-body py-2">
                                            <table class="w-100">
                                                <tr>
                                                    <td>Total:</td>
                                                    <td class="tx-right"><a href="{{url(session('url').'order')}}?start_date={{ $start_date }}&end_date={{ $end_date }}" title="Go to orders page">{{ $total_orders }}</a></td>
                                                </tr>
                                                <tr>
                                                    <td>Pending:</td>
                                                    <td class="tx-right"><a href="{{url(session('url').'order')}}?status=2&start_date={{ $start_date }}&end_date={{ $end_date }}" title="Go to orders page">{{ $pending_orders }}</a></td>
                                                </tr>
                                                <tr>
                                                    <td>Conversation:</td>
                                                    <td class="tx-right"><a href="{{url(session('url').'order')}}?care=1&start_date={{ $start_date }}&end_date={{ $end_date }}" title="Go to orders page">{{ $total_conversations }}</a></td>
                                                </tr>
                                                <tr>
                                                    <td>Invoiced:</td>
                                                    <td class="tx-right"><a href="{{url(session('url').'order')}}?status=3&start_date={{ $start_date }}&end_date={{ $end_date }}" title="Go to orders page">{{ $invoiced_orders }}</a></td>
                                                </tr>
                                            </table>

                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-lg-3 col-md-4 col-xs-6">

                                    <div class="card">
                                        <div class="card-header">
                                            <h4 class="card-title mb-1">Testing Count</h4>
                                        </div>
                                        <div class="card-body py-2">
                                            <table class="w-100">
                                                @foreach ($testing_count as $testing)
                                                    @if ($testing->stock_operations_count > 0)

                                                    <tr>
                                                        <td>{{ $testing->first_name}}</td>
                                                        <td class="tx-right"><a href="{{url(session('url').'move_inventory')}}?start_date={{ $start_date }}&end_date={{ $end_date }}&adm={{ $testing->id }}" title="Go to Move Inventory page">{{ $testing->stock_operations_count }}</a></td>
                                                    </tr>
                                                    @endif
                                                @endforeach
                                            </table>

                                        </div>
                                    </div>
                                </div>
                                {{-- Date search section --}}
                                <div class="col-xl-6 col-lg-6 col-md-8 col-xs-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h4 class="card-title mb-1">{{ __('locale.Search Records By Dates') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <form action="" method="GET" id="index">
                                                <div class="row">
                                                    <div class="col-xl-5 col-lg-5 col-md-5 col-xs-5">
                                                        <label for="">{{ __('locale.Start Date') }}</label>
                                                        <input class="form-control" id="datetimepicker" type="date" id="strat" name="start_date" value="{{$start_date}}">
                                                    </div>
                                                    <div class="col-xl-5 col-lg-5 col-md-5 col-xs-5">
                                                        <label for="">{{ __('locale.End Date') }}</label>
                                                        <input class="form-control" id="datetimepicker" type="date" id="end" name="end_date" value="{{$end_date}}">
                                                    </div>
                                                    <div class="col-xl-2 col-lg-2 col-md-2 col-xs-2">
                                                        <label for="">&nbsp;</label>
                                                        <button type="submit" class="btn btn-icon  btn-success me-1"><i class="fe fe-search"></i></button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            </div>
								{{-- Welcome Box end --}}
								{{-- Date search section --}}
                            @if (session('user')->hasPermission('view_inventory'))
                            <div class="card custom-card">
                                <div class="row">
                                    <div class="col-lg-9 overflow-hidden">
                                        <div class="card-header border-bottom-0">
                                                <h3 class="card-title mb-2 ">Available Inventory by Grade</h3> <span class="d-block tx-12 mb-0 text-muted"></span>
                                        </div>
                                        <div class="card-body row">
                                            @foreach ($graded_inventory as $inv)
                                                <div class="col-lg-3 col-md-4"><h6><a href="{{url(session('url').'inventory')}}?grade[]={{ $inv->grade_id }}&status={{ $inv->status_id }}" title="Go to orders page">{{ $inv->grade.": ".$inv->quantity." ".$purchase_status[$inv->status_id] }}</a></h6></div>
                                            @endforeach
                                        </div>
                                    </div>
                                {{-- </div>
                                <div class="col-lg-3"> --}}
                                    <div class="col-lg-3 overflow-hidden">
                                        <div class="card-header border-bottom-0">
                                                <h3 class="card-title mb-2 ">Aftersale Inventory</h3> <span class="d-block tx-12 mb-0 text-muted"></span>
                                        </div>
                                        <div class="card-body">
                                            @foreach ($aftersale_inventory as $inv)
                                                <div class=""><h6><a href="{{url(session('url').'inventory')}}?grade[]={{ $inv->grade_id }}&status={{ $inv->status_id }}&stock_status={{ $inv->stock_status }}" title="Go to orders page">{{ $inv->grade.": ".$inv->quantity." ".$purchase_status[$inv->status_id] }}</a></h6></div>
                                            @endforeach
                                            <br>
                                            <h6 class=""><a href="{{url(session('url').'inventory')}}?stock_status=1&replacement=1" title="Go to orders page">Awaiting <br> Replacements : {{$awaiting_replacement}}</a></h6>
                                        </div>

                                    </div>
                                </div>
                            </div>
                            @endif

							<div class="card custom-card overflow-hidden">
								<div class="card-header border-bottom-0">
									<div class="d-flex justify-content-between">
										<h3 class="card-title mb-2 ">Sales for past 6 months</h3>
									</div>
								</div>
								<div class="card-body">
									<div id="statistics1"></div>
								</div>
							</div>
						</div>
						<!-- </div> -->
					</div>
					<!-- row closed -->
    @endsection

    @section('scripts')
		<!-- Internal Chart.Bundle js-->
        <script>
            $(document).ready(function(){
                $('.select2').select2();
            })
            $('.select2').select2({
            placeholder: 'Select an option'
            });
        </script>
		<script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

		<!-- Moment js -->
		<script src="{{asset('assets/plugins/raphael/raphael.min.js')}}"></script>

		<!-- INTERNAL Apexchart js -->
		<script src="{{asset('assets/js/apexcharts.js')}}"></script>
		<script src="{{asset('assets/js/apexcharts.js')}}"></script>

		<!--Internal Sparkline js -->
		<script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

		<!--Internal  index js -->
		<script src="{{asset('assets/js/index.js')}}"></script>

        <!-- Chart-circle js -->
		<script src="{{asset('assets/js/chart-circle.js')}}"></script>

		<!-- Internal Data tables -->
		<script src="{{asset('assets/plugins/datatable/js/jquery.dataTables.min.js')}}"></script>
		<script src="{{asset('assets/plugins/datatable/js/dataTables.bootstrap5.js')}}"></script>
		<script src="{{asset('assets/plugins/datatable/dataTables.responsive.min.js')}}"></script>
		<script src="{{asset('assets/plugins/datatable/responsive.bootstrap5.min.js')}}"></script>

		<!-- INTERNAL Select2 js -->
		<script src="{{asset('assets/plugins/select2/js/select2.full.min.js')}}"></script>
		<script src="{{asset('assets/js/select2.js')}}"></script>
    @endsection
