@extends('layouts.app')

    @section('styles')
    <style>
        .rows{
            border: 1px solid #016a5949;
        }
        .columns{
            background-color:#016a5949;
            padding-top:5px
        }
        .childs{
            padding-top:5px
        }
    </style>
    @endsection
<br>
    @section('content')
        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                <span class="main-content-title mg-b-0 mg-b-lg-1">Customer</span>
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboards') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                                Customer
                        </li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <hr style="border-bottom: 1px solid #000">
        <br>
        <div class="d-flex justify-content-between">

            <form method="get" action="" class="row form-inline">
                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Types</option>
                    <option value="1" {{ Request::get('type') == 1 ? 'selected' : '' }}>Vendors</option>
                    <option value="2" {{ Request::get('type') == 2 ? 'selected' : '' }}>BulkSale Purchasers</option>
                    <option value="3" {{ Request::get('type') == 3 ? 'selected' : '' }}>Customers</option>
                </select>
            </form>
                <a href="{{url('add-customer')}}" class="btn btn-success float-right"><i class="mdi mdi-plus"></i> Add Customer</a>
        </div>
        <br>
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
                <span class="alert-inner--text"><strong>{{session('success')}}</strong></span>
                <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
            </div>
        @endif
        <br>
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">Customers</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>{{ __('locale.S.No') }}</b></small></th>
                                        <th><small><b>Company</b></small></th>
                                        <th><small><b>{{ __('locale.First Name') }}</b></small></th>
                                        <th><small><b>{{ __('locale.Last Name') }}</b></small></th>
                                        <th><small><b>Phone</b></small></th>
                                        <th><small><b>Country</b></small></th>
                                        <th><small><b>Orders</b></small></th>
                                        <th colspan="2"><center><small><b>{{ __('locale.Action') }}</b></small></center></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = $customers->firstItem()-1;
                                    @endphp
                                    @foreach ($customers as $customer)
                                    @php
                                        $i++;
                                    @endphp
                                        <tr>
                                            <td>{{$i}}</td>
                                            <td>{{$customer->company}}</td>
                                            <td>{{$customer->first_name}}</td>
                                            <td>{{$customer->last_name}}</td>
                                            <td>{{$customer->phone}}</td>
                                            <td>{{$customer->country_id->title ?? null}}</td>
                                            <td>{{$customer->orders->count()}}</td>
                                            <td><center><a href="edit-customer/{{$customer->id}}" class="text text-success w-100 vh-100">{{ __('locale.Edit') }}</a></center></td>
                                            <td>
                                                <center>
                                                    @if ($customer->status == 1)
                                                    <a href="update-status/{{$customer->id}}" class="text text-success w-100 vh-100" title="Click to Deactivate">{{ __('locale.Active') }}</a>
                                                    @else
                                                    <a href="update-status/{{$customer->id}}" class="text text-success w-100 vh-100" title="Click to Activate">{{ __('locale.Inactive') }}</a>
                                                    @endif
                                                </center>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <br>
                            {{$customers->onEachSide(1)->links()}} {{ __('locale.From') }} {{$customers->firstItem()}} {{ __('locale.To') }} {{$customers->lastItem()}} {{ __('locale.Out Of') }} {{$customers->total()}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @php
            session()->forget('success');
        @endphp
    @endsection

    @section('scripts')

		<!--Internal Sparkline js -->
		<script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

		<!-- Internal Piety js -->
		<script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

		<!-- Internal Chart js -->
		<script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

    @endsection
