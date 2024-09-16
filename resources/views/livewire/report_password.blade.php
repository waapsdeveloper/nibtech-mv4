@extends('layouts.app')

    @section('content')

					<!-- breadcrumb -->
					<div class="breadcrumb-header justify-content-between">
						<div class="left-content">
						  <span class="main-content-title mg-b-0 mg-b-lg-1">Report</span>
						</div>
						<div class="justify-content-center mt-2">
							<ol class="breadcrumb">
								<li class="breadcrumb-item tx-15"><a href="{{url('/')}}">Dashboard</a></li>
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
                    <hr style="border-bottom: 1px solid #000">
					<!-- row -->
                    <div class="row">
                        <div class="col-md-2"></div>
                        <div class="col-lg-8 col-md-8">
                            <form action="{{url('report/check_password')}}" method="POST">
                                @csrf
                                <div class="card">
                                    <div class="card-body">

                                        <div class="pd-30 pd-sm-20">

                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-3">
                                                    <label class="form-label mg-b-0">Password</label>
                                                </div>
                                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="Enter Report Password" name="password" type="password" required>
                                                </div>
                                            </div>
                                            <button class="btn btn-primary pd-x-30 mg-r-5 mg-t-5 float-end" >{{ __('locale.Add') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-2"></div>
                    </div>
					<!-- /row -->

                    @endsection
    @section('scripts')

		<!-- Form-layouts js -->
		<script src="{{asset('assets/js/form-layouts.js')}}"></script>


    @endsection
