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
						<span class="main-content-title mg-b-0 mg-b-lg-1">Reports</span>
                        <a href="javascript:void(0);" class="btn btn-sm btn-success float-right" data-bs-target="#modaldemo"
                        data-bs-toggle="modal"><i class="mdi mdi-plus"></i> Change Password </a>
						</div>
                        <div>
                            <div class="btn-group p-1" role="group">
                                <button type="button" class="btn-sm btn-secondary dropdown-toggle" id="pack_sheet" data-bs-toggle="dropdown" aria-expanded="false">
                                Orders Report
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="pack_sheet">
                                    <li><a class="dropdown-item" href="{{url('report/export')}}?packlist=2&start_date={{$start_date}}&end_date={{$end_date}}">.xlsx</a></li>
                                    {{-- <li><a class="dropdown-item" href="{{url('export_bulksale_invoice')}}?packlist=1" target="_blank">.pdf</a></li> --}}
                                </ul>
                            </div>
                        </div>
						<div class="justify-content-center mt-2">
							<ol class="breadcrumb">
								<li class="breadcrumb-item active" aria-current="page">{{ __('locale.Dashboards') }}</li>
                                <li class="breadcrumb-item active" aria-current="page">Reports</li>
							</ol>
						</div>
					</div>
					<!-- /breadcrumb -->

                    @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
                        <span class="alert-inner--text"><strong>{{session('success')}}</strong></span>
                        <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <br>
                    @php
                    session()->forget('success');
                    @endphp
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <span class="alert-inner--icon"><i class="fe fe-thumbs-down"></i></span>
                            <span class="alert-inner--text"><strong>{{session('error')}}</strong></span>
                            <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
                        </div>
                    <br>
                    @php
                    session()->forget('error');
                    @endphp
                    @endif
                    <div class="row mb-3">

                        <div class="col-md">
                            <select name="vendor" class="form-control form-select">
                                <option value="">Vendor</option>
                                @foreach ($vendors as $id=>$name)
                                    <option value="{{ $id }}" @if(isset($_GET['vendor']) && $id == $_GET['vendor']) {{'selected'}}@endif>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md">
                            <div class="form-floating">
                                <input type="text" name="batch" value="{{ Request::get('batch') }}" class="form-control" data-bs-placeholder="Select Model" form="index">
                                <label for="batch">Batch</label>
                            </div>
                        </div>
                        <div class="col-md">
                            <select name="category" class="form-control form-select" form="index">
                                <option value="">Category</option>
                                @foreach ($categories as $id=>$name)
                                    <option value="{{ $id }}" @if(isset($_GET['category']) && $id == $_GET['category']) {{'selected'}}@endif>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md">
                            <select name="brand" class="form-control form-select" form="index">
                                <option value="">Brand</option>
                                @foreach ($brands as $id=>$name)
                                    <option value="{{ $id }}" @if(isset($_GET['brand']) && $id == $_GET['brand']) {{'selected'}}@endif>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md">
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

                    </div>
            <div class="card">
                <div class="card-header mb-0 d-flex justify-content-between">
                    <div class="mb-0">
                        <h4 class="card-title mb-0">Sales & Returns</h4>
                        <form class="form-inline" method="POST" target="print_popup" action="{{url('report')}}/pnl" onsubmit="window.open('about:blank','print_popup','width=1600,height=800');">
                            @csrf
                            <input type="hidden" name="start_date" value="{{$start_date}}">
                            <input type="hidden" name="end_date" value="{{$end_date}}">
                            <input type="hidden" name="product" value="{{ Request::get('product') }}">
                            <input type="hidden" name="vendor" value="{{ Request::get('vendor') }}">
                            <input type="hidden" name="batch" value="{{ Request::get('batch') }}">
                            <input type="hidden" name="storage" value="{{ Request::get('storage') }}">
                            <input type="hidden" name="color" value="{{ Request::get('color') }}">
                            <input type="hidden" name="grade" value="{{ Request::get('grade') }}">
                            <input type="hidden" name="category" value="{{ Request::get('category') }}">
                            <input type="hidden" name="brand" value="{{ Request::get('brand') }}">
                            <button class="btn btn-link" type="submit" name="bp" value="1">Profit & Loss by Products</button>
                            <button class="btn btn-link" type="submit" name="bc" value="1">Profit & Loss by Customers</button>
                            <button class="btn btn-link" type="submit" name="bv" value="1">Profit & Loss by Vendors</button>
                        </form>
                    </div>
                    <div class="">

                        <form action="" method="GET" id="index" class="mb-0">
                            <div class="row">
                                <div class="col-xl-5 col-lg-5 col-md-5 col-xs-5">
                                    <div class="form-floating">
                                        <input class="form-control" id="datetimepicker" type="date" id="start" name="start_date" value="{{$start_date}}">
                                        <label for="start">{{ __('locale.Start Date') }}</label>
                                    </div>
                                </div>
                                <div class="col-xl-5 col-lg-5 col-md-5 col-xs-5">
                                    <div class="form-floating">
                                        <input class="form-control" id="datetimepicker" type="date" id="end" name="end_date" value="{{$end_date}}">
                                        <label for="end">{{ __('locale.End Date') }}</label>
                                    </div>
                                </div>
                                <div class="col-xl-2 col-lg-2 col-md-2 col-xs-2">
                                    <button type="submit" class="btn btn-icon  btn-success me-1"><i class="fe fe-search"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body mt-0">
                    <form method="POST" id="stock_report" target="print_popup" action="{{ url('report/stock_report')}}" onsubmit="window.open('about:blank','print_popup','width=1600,height=800');">
                        @csrf
                        <input type="hidden" name="start_date" value="{{$start_date}}">
                        <input type="hidden" name="end_date" value="{{$end_date}}">
                    </form>
                    <table class="table table-bordered table-hover text-md-nowrap">
                        <thead>
                            <tr>
                                <th><small><b>No</b></small></th>
                                <th><small><b>Categories</b></small></th>
                                <th><small><b>Qty</b></small></th>
                                @if (session('user')->hasPermission('view_cost'))
                                    <th title=""><small><b>Cost</b></small></th>
                                    <th title=""><small><b>Repair</b></small></th>
                                    <th title=""><small><b>Fee</b></small></th>
                                @endif
                                @if (session('user')->hasPermission('view_price'))
                                <th title=""><small><b>EUR Price</b></small></th>
                                <th title=""><small><b>GBP Price</b></small></th>
                                @endif
                                <th title=""><small><b>Profit</b></small></th>
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
                                    // $total_approved_sale_orders += $sales->approved_orders_qty;
                                    $total_sale_eur_items += $sales->eur_items_sum;
                                    // $total_approved_sale_eur_items += $sales->eur_approved_items_sum;
                                    $total_sale_gbp_items += $sales->gbp_items_sum;
                                    // $total_approved_sale_gbp_items += $sales->gbp_approved_items_sum;
                                    $total_sale_cost += $aggregated_sales_cost[$sales->category_id];
                                    $total_repair_cost += $sales->items_repair_sum;
                                    $total_eur_profit += $sales->eur_items_sum - $aggregated_sales_cost[$sales->category_id] - $sales->items_repair_sum;
                                @endphp
                                <tr>
                                    <td>{{ $s+1 }}</td>
                                    <td>{{ $categories[$sales->category_id] }}</td>
                                    <td><button class="btn btn-link py-0" form="stock_report" type="submit" name="stock_ids" value="{{$sales->stock_ids}}">{{ $sales->orders_qty }}</button></td>
                                    @if (session('user')->hasPermission('view_cost'))
                                        <td title="{{count(explode(',',$sales->stock_ids))}}">€{{ amount_formatter($aggregated_sales_cost[$sales->category_id],2) }}</td>
                                        <td>€{{ amount_formatter($sales->items_repair_sum,2) }}</td>
                                        <td>{{ amount_formatter(0,2) }}</td>
                                    @endif
                                    @if (session('user')->hasPermission('view_price'))
                                        <td>€{{ amount_formatter($sales->eur_items_sum,2) }}</td>
                                        <td>£{{ amount_formatter($sales->gbp_items_sum,2) }}</td>
                                    @endif
                                    <td>€{{ amount_formatter($sales->eur_items_sum - $aggregated_sales_cost[$sales->category_id] - $sales->items_repair_sum,2) }} + £{{ amount_formatter($sales->gbp_items_sum,2) }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="2"><strong>Profit</strong></td>
                                <td><strong>{{ $total_sale_orders." (".$total_approved_sale_orders.")" }}</strong></td>
                                @if (session('user')->hasPermission('view_cost'))
                                    <td title=""><strong>€{{ amount_formatter($total_sale_cost,2) }}</strong></td>
                                    <td><strong>€{{ amount_formatter($total_repair_cost,2) }}</strong></td>
                                    <td><strong>{{ amount_formatter(0,2) }}</strong></td>
                                @endif
                                @if (session('user')->hasPermission('view_price'))
                                    <td><strong>€{{ amount_formatter($total_sale_eur_items,2)." (€".amount_formatter($total_approved_sale_eur_items,2).")" }}</strong></td>
                                    <td><strong>£{{ amount_formatter($total_sale_gbp_items,2)." (£".amount_formatter($total_approved_sale_gbp_items,2).")" }}</strong></td>
                                @endif
                                <td><strong>€{{ amount_formatter($total_eur_profit) }} + £{{ amount_formatter($total_sale_gbp_items,2) }}</strong></td>
                            </tr>

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
                                    // $total_approved_return_eur_items += $returns->eur_approved_items_sum;
                                    $total_return_gbp_items += $returns->gbp_items_sum;
                                    // $total_approved_return_gbp_items += $returns->gbp_approved_items_sum;
                                    $total_return_cost += $aggregated_return_cost[$returns->category_id];
                                    $total_repair_return_cost += $returns->items_repair_sum;
                                    $total_eur_loss += $returns->eur_items_sum - $aggregated_return_cost[$returns->category_id] - $returns->items_repair_sum;
                                @endphp
                                <tr>
                                    <td>{{ $s+1 }}</td>
                                    <td>{{ $categories[$returns->category_id] }}</td>
                                    <td>{{ $returns->orders_qty }}</td>
                                    @if (session('user')->hasPermission('view_cost'))
                                        <td title="{{count(explode(',',$returns->stock_ids))}}">€{{ amount_formatter($aggregated_return_cost[$returns->category_id],2) }}</td>
                                        <td>€{{ amount_formatter($returns->items_repair_sum,2) }}</td>
                                        <td>{{ amount_formatter(0,2) }}</td>
                                    @endif
                                    @if (session('user')->hasPermission('view_price'))
                                        <td>€{{ amount_formatter($returns->eur_items_sum,2) }}</td>
                                        <td>£{{ amount_formatter($returns->gbp_items_sum,2) }}</td>
                                    @endif
                                    <td>€{{ amount_formatter(-$returns->eur_items_sum + $aggregated_return_cost[$returns->category_id] + $returns->items_repair_sum,2) }} + £{{ amount_formatter($returns->gbp_items_sum,2) }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="2"><strong>Loss</strong></td>
                                <td><strong>{{ $total_return_orders }}</strong></td>
                                @if (session('user')->hasPermission('view_cost'))
                                    <td title=""><strong>€{{ amount_formatter($total_return_cost,2) }}</strong></td>
                                    <td><strong>€{{ amount_formatter($total_repair_return_cost,2) }}</strong></td>
                                    <td><strong>{{ amount_formatter(0,2) }}</strong></td>
                                @endif
                                @if (session('user')->hasPermission('view_price'))
                                    <td><strong>€{{ amount_formatter($total_return_eur_items,2) }}</strong></td>
                                    <td><strong>£{{ amount_formatter($total_return_gbp_items,2) }}</strong></td>
                                @endif
                                <td><strong>€{{ amount_formatter($total_eur_loss) }} + £{{ amount_formatter($total_return_gbp_items,2) }}</strong></td>
                            </tr>
                            <tr>
                                <td colspan="2"><strong>Net</strong></td>
                                <td><strong>{{ $total_sale_orders-$total_return_orders }}</strong></td>
                                @if (session('user')->hasPermission('view_cost'))
                                    <td title=""><strong>€{{ amount_formatter($total_sale_cost-$total_return_cost,2) }}</strong></td>
                                    <td><strong>€{{ amount_formatter($total_repair_cost-$total_repair_return_cost,2) }}</strong></td>
                                    <td><strong>{{ amount_formatter(0,2) }}</strong></td>
                                @endif
                                @if (session('user')->hasPermission('view_price'))
                                    <td><strong>€{{ amount_formatter($total_sale_eur_items-$total_return_eur_items,2) }}</strong></td>
                                    <td><strong>£{{ amount_formatter($total_sale_gbp_items-$total_return_gbp_items,2) }}</strong></td>
                                @endif
                                <td><strong>€{{ amount_formatter($total_eur_profit-$total_eur_loss) }} + £{{ amount_formatter($total_sale_gbp_items-$total_return_gbp_items,2) }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>


            <div class="card">
                <div class="card-header mb-0 d-flex justify-content-between">
                    <div class="mb-0">
                        <h4 class="card-title mb-0">Batch Grade Reports</h4>
                    </div>
                </div>
                <div class="card-body mt-0">
                    <table class="table table-bordered table-hover text-md-nowrap">
                        <thead>
                            <tr>
                                <th><small><b>No</b></small></th>
                                <th><small><b>Batch</b></small></th>
                                <th><small><b>Reference</b></small></th>
                                <th><small><b>Vendor</b></small></th>
                                <th><small><b>Total</b></small></th>
                                @foreach ($grades as $id=>$grade)
                                    @php
                                        if (strlen($grade) >= 5) {
                                            $gr = substr($grade, 0, 3). " ..
                                            .";
                                        } else {
                                            $gr = $grade;
                                        }
                                    @endphp
                                    <th title="{{ $grade }}"><small><b>{{ $gr }}</b></small></th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $i = 0;
                            @endphp
                            @foreach ($batch_grade_reports->groupBy('order_id') as $orderReports)
                                @php
                                    $order = $orderReports->first();
                                    $total = $orderReports->sum('quantity');
                                @endphp
                                <tr>
                                    <td>{{ $i += 1 }}</td>
                                    <td>{{ $order->reference_id }}</td>
                                    <td>{{ $order->reference }}</td>
                                    <td>{{ $order->vendor }}</td>
                                    <td><a href="{{ url('report/export_batch')}}/{{$order->order_id}}" onclick="if (confirm('Download Batch Grade Report?')){return true;}else{event.stopPropagation(); event.preventDefault();};"> {{ $total }} </a></td>
                                    @foreach ($grades as $g_id => $grade)
                                        @php
                                            $gradeReport = $orderReports->firstWhere('grade', $g_id);
                                        @endphp
                                        <td title="{{ $grade }}">{{ $gradeReport ? ($gradeReport->quantity." (".amount_formatter($gradeReport->quantity/$total * 100,1) .'%)' ) : '-' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
					<!-- row -->
					<div class="row">
						<div class="col-md-6 col-sm-12">


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
						<div class="col-md-6 col-sm-12">


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

    <div class="modal" id="modaldemo">
        <div class="modal-dialog wd-xl-400" role="document">
            <div class="modal-content">
                <div class="modal-body pd-sm-40">
                    <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal"
                        type="button"><span aria-hidden="true">&times;</span></button>
                    <h5 class="modal-title mg-b-5">Change Report Password</h5>
                    <hr>
                    <form action="{{ url('report/set_password') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label for="">Old Password</label>
                            <input class="form-control" placeholder="input old password" name="old_password" type="password" required>
                        </div>
                        <div class="form-group">
                            <label for="">New Password</label>
                            <input class="form-control" placeholder="input new password" name="new_password" type="password" required>
                        </div>
                        <div class="form-group">
                            <label for="">Confirm Password</label>
                            <input class="form-control" placeholder="input new password" name="confirm_password" type="password" required>
                        </div>
                        <button class="btn btn-primary btn-block">{{ __('locale.Submit') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
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
