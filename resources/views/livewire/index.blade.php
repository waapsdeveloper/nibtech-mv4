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
							</ol>
						</div>
					</div>
					<!-- /breadcrumb -->
                    @if (count($variations) > 0)

                    <div class="row">
                        <div class="col-xl-12">
                            <div class="card">
                                <div class="card-header pb-0">
                                    <div class="d-flex justify-content-between">
                                        <h4 class="card-title mg-b-0">
                                            New Added Variations
                                        </h4>
                                    </div>
                                </div>
                                <div class="card-body"><div class="table-responsive">
                                        <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                            <thead>
                                                <tr>
                                                    <th><small><b>No</b></small></th>
                                                    <th><small><b>Product</b></small></th>
                                                    <th><small><b>Name</b></small></th>
                                                    <th><small><b>SKU</b></small></th>
                                                    <th><small><b>Color</b></small></th>
                                                    <th><small><b>Storage</b></small></th>
                                                    <th><small><b>Grade</b></small></th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $i = 0;
                                                @endphp
                                                @foreach ($variations as $index => $product)
                                                    <form method="post" action="{{url(session('url').'variation/update_product')}}/{{ $product->id }}" class="row form-inline">
                                                        @csrf
                                                    <tr>
                                                        <td>{{ $i + 1 }}</td>
                                                        <td>
                                                            <input type="text" name="update[product_id]" list="models" class="form-select form-select-sm" required>
                                                            <datalist id="models">
                                                                <option value="">None</option>
                                                                @foreach ($products as $prod)
                                                                    <option value="{{ $prod->id }}" {{ $product->product_id == $prod->id ? 'selected' : '' }}>{{ $prod->series." ".$prod->model }}</option>
                                                                @endforeach
                                                            </datalist>
                                                        </td>
                                                        <td>{{ $product->name }}</td>
                                                        <td>{{ $product->sku }}</td>
                                                        <td>
                                                            <select name="update[color]" class="form-select form-select-sm">
                                                                <option value="">None</option>
                                                                @foreach ($colors as $color)
                                                                    <option value="{{ $color->id }}" {{ $product->color == $color->id ? 'selected' : '' }}>{{ $color->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <select name="update[storage]" class="form-select form-select-sm">
                                                                <option value="">None</option>
                                                                @foreach ($storages as $storage)
                                                                    <option value="{{ $storage->id }}" {{ $product->storage == $storage->id ? 'selected' : '' }}>{{ $storage->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <select name="update[grade]" class="form-select form-select-sm">
                                                                <option value="">None</option>
                                                                @foreach ($grades as $grade)
                                                                    <option value="{{ $grade->id }}" {{ $product->grade == $grade->id ? 'selected' : '' }}>{{ $grade->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input type="submit" value="Update" class="btn btn-success">
                                                        </td>
                                                    </tr>
                                                    </form>

                                                    @php
                                                        $i ++;
                                                    @endphp
                                                @endforeach
                                            </tbody>
                                        </table>
                                    <br>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @endif
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
                                                    @foreach ($top_products as $top => $product)

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
                                            </table>
                                        </div>
                                    </div>
								</div>
							</div>
						</div>
						<div class="col-xl-7 col-lg-12 col-md-12 col-sm-12">
                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-4 col-xs-4">

                                    <div class="card">
                                        <div class="card-header">
                                            <h4 class="card-title mb-1">Orders</h4>
                                        </div>
                                        <div class="card-body">
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
                                            </table>

                                        </div>
                                    </div>
                                </div>
                                {{-- Date search section --}}
                                <div class="col-xl-8 col-lg-8 col-md-8 col-xs-8">
                                    <div class="card">
                                        <div class="card-header">
                                            <h4 class="card-title mb-1">{{ __('locale.Search Records By Dates') }}</h4>
                                        </div>
                                        <div class="card-body">
                                            <form action="" method="GET">
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
							<div class="card custom-card overflow-hidden">
								<div class="card-header border-bottom-0">
									<div>
										<h3 class="card-title mb-2 ">Available Inventory by Grade</h3> <span class="d-block tx-12 mb-0 text-muted"></span>
									</div>
								</div>
								<div class="card-body row">
									@foreach ($graded_inventory as $inv)
                                        <div class="col-md-4"><h6><a href="{{url(session('url').'inventory')}}?grade={{ $inv->grade_id }}" title="Go to orders page">{{ $inv->grade.": ".$inv->quantity }}</a></h6></div>
                                    @endforeach
								</div>
							</div>
                            @endif

							<div class="card custom-card overflow-hidden">
								<div class="card-header border-bottom-0">
									<div>
										<h3 class="card-title mb-2 ">Daily Orders for this month</h3> <span class="d-block tx-12 mb-0 text-muted"></span>
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
