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
                        <div>
                            <div class="btn-group p-1" role="group">
                                <button type="button" class="btn-sm btn-secondary dropdown-toggle" id="pack_sheet" data-bs-toggle="dropdown" aria-expanded="false">
                                Orders Report
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="pack_sheet">
                                    <li><a class="dropdown-item" href="{{url('report/export')}}?packlist=2&start_date={{$start_date}}&end_date={{$end_date}}">.xlsx</a></li>
                                    <li><a class="dropdown-item" href="{{url('export_bulksale_invoice')}}?packlist=1" target="_blank">.pdf</a></li>
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

            <div class="card">
                <div class="card-header mb-0 d-flex justify-content-between">
                    <div class="mb-0">
                        <h4 class="card-title mb-0">Sales & Returns</h4>
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
                                    <td>{{ $sales->orders_qty }}</td>
                                    @if (session('user')->hasPermission('view_price'))
                                    <td>€{{ number_format($sales->eur_items_sum,2) }}</td>
                                    <td>£{{ number_format($sales->gbp_items_sum,2) }}</td>
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
                                    <td>{{ $returns->orders_qty }}</td>
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
                            </tr>
                            <tr>
                                <td colspan="2"><strong>Net</strong></td>
                                <td><strong>{{ $total_sale_orders-$total_return_orders }}</strong></td>
                                @if (session('user')->hasPermission('view_price'))
                                <td><strong>€{{ number_format($total_sale_eur_items-$total_return_eur_items,2) }}</strong></td>
                                <td><strong>£{{ number_format($total_sale_gbp_items-$total_return_gbp_items,2) }}</strong></td>
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
                                    <td><a href="{{ url('report/export_batch')}}/{{$order->id}}/1"> {{ $total }} </a></td>
                                    @foreach ($grades as $g_id => $grade)
                                        @php
                                            $gradeReport = $orderReports->firstWhere('grade', $g_id);
                                        @endphp
                                        <td title="{{ $grade }}">{{ $gradeReport ? ($gradeReport->quantity." (".number_format($gradeReport->quantity/$total * 100,1) .'%)' ) : '-' }}</td>
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
