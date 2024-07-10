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
            <form method="get" action="" class="">
        <div class="row">
            <div class="col-md col-sm-6">

                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Types</option>
                    <option value="1" {{ Request::get('type') == 1 ? 'selected' : '' }}>Vendors</option>
                    <option value="2" {{ Request::get('type') == 2 ? 'selected' : '' }}>BulkSale Purchasers</option>
                    <option value="3" {{ Request::get('type') == 3 ? 'selected' : '' }}>Repairer</option>
                    <option value="4" {{ Request::get('type') == 4 ? 'selected' : '' }}>Customers</option>
                </select>
            </div>
            <div class="col-md col-sm-6">

                <div class="form-floating">
                    <input type="text" class="form-control" name="order_id" placeholder="Enter Company" value="@isset($_GET['order_id']){{$_GET['order_id']}}@endisset">
                    <label for="">Order Number</label>
                </div>
            </div>
            <div class="col-md col-sm-6">

                <div class="form-floating">
                    <input type="text" class="form-control" name="company" placeholder="Enter Company" value="@isset($_GET['company']){{$_GET['company']}}@endisset">
                    <label for="">Company</label>
                </div>
            </div>
            <div class="col-md col-sm-6">

                <div class="form-floating">
                    <input type="text" class="form-control" name="first_name" placeholder="Enter First Name" value="@isset($_GET['first_name']){{$_GET['first_name']}}@endisset">
                    <label for="">First Name</label>
                </div>
            </div>
            <div class="col-md col-sm-6">

                <div class="form-floating">
                    <input type="text" class="form-control" name="last_name" placeholder="Enter Last Name" value="@isset($_GET['last_name']){{$_GET['last_name']}}@endisset">
                    <label for="">Last Name</label>
                </div>
            </div>
            <div class="col-md col-sm-6">

                <div class="form-floating">
                    <input type="text" class="form-control" name="phone" placeholder="Enter Phone" value="@isset($_GET['phone']){{$_GET['phone']}}@endisset">
                    <label for="">Phone</label>
                </div>
            </div>
            <div class="col-md col-sm-6">

                <div class="form-floating">
                    <input type="text" class="form-control" name="email" placeholder="Enter Email" value="@isset($_GET['email']){{$_GET['email']}}@endisset">
                    <label for="">Email</label>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="{{url(session('url').'customer')}}" class="btn btn-default">Reset</a>
            </div>

        </div>
            </form>
        <br>
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
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">Customers</h4>
                            <a href="{{url('add-customer')}}" class="btn btn-sm btn-success float-right"><i class="mdi mdi-plus"></i> Add Customer</a>
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
                                            <td>{{$customer->orders_count}}</td>
                                            <td><center><a href="edit-customer/{{$customer->id}}" class="text text-success w-100 vh-100">{{ __('locale.Edit') }}</a></center></td>
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
