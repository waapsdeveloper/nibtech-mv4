@extends('layouts.app')

    @section('styles')

	<!--- Internal Select2 css-->
	<link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet">

	<!--  smart photo master css -->
	<link href="{{asset('assets/plugins/SmartPhoto-master/smartphoto.css')}}" rel="stylesheet">

    @endsection

    @section('content')

				<!-- breadcrumb -->
				<div class="breadcrumb-header justify-content-between">
					<div class="left-content">
						<span class="main-content-title mg-b-0 mg-b-lg-1">{{ __('locale.Profile') }}</span>
					</div>
					<div class="justify-content-center mt-2">
						<ol class="breadcrumb">
							<li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboards') }}</a></li>
							<li class="breadcrumb-item active" aria-current="page">{{ __('locale.Profile') }}</li>
						</ol>
					</div>
				</div>
				<!-- /breadcrumb -->

				<div class="row">
					<div class="col-lg-12 col-md-12">
						<div class="card custom-card">
							<div class="card-body d-md-flex">
								{{-- <div class="">
									<span class="profile-image pos-relative">
										<img class="br-5" alt="" src="{{asset('assets/img/faces/profile.jpg')}}">
										<span class="bg-success text-white wd-1 ht-1 rounded-pill profile-online"></span>
									</span>
								</div> --}}
								<div class="my-md-auto mt-4 prof-details">
									<h4 class="font-weight-semibold ms-md-4 ms-0 mb-1 pb-0">{{session('fname')." ".session('lname')}}</h4>
									<p class="text-muted ms-md-4 ms-0 mb-2"><span><i
												class="fa fa-envelope me-2"></i></span><span
											class="font-weight-semibold me-2">{{ __('locale.Email') }}:</span><span>{{$admin->email}}</span>
									</p>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Row -->
				<div class="row row-sm">
					<div class="col-lg-12 col-md-12">
						<div class="custom-card main-content-body-profile">
							<div class="tab-content">
								<div class="main-content-body border-top-0">
									<div class="card">
										<div class="card-body border-0">
											<div class="mb-4 main-content-label">{{ __('locale.Personal Information') }}</div>
											<form class="form-horizontal">
												{{-- <div class="mb-4 main-content-label">Name</div> --}}
												<div class="form-group ">
													<div class="row row-sm">
														<div class="col-md-3">
															<label class="form-label">{{ __('locale.First Name') }}</label>
														</div>
														<div class="col-md-9">
															<input type="text" class="form-control"
																placeholder="{{ __('locale.First Name') }}" value="{{$admin->first_name}}" disabled>
														</div>
													</div>
												</div>
												<div class="form-group ">
													<div class="row row-sm">
														<div class="col-md-3">
															<label class="form-label">{{ __('locale.Last Name') }}</label>
														</div>
														<div class="col-md-9">
															<input type="text" class="form-control"
																placeholder="{{ __('locale.Last Name') }}" value="{{$admin->last_name}}" disabled>
														</div>
													</div>
												</div>
												<div class="mb-4 main-content-label">{{ __('locale.Contact Info') }}</div>
												<div class="form-group ">
													<div class="row row-sm">
														<div class="col-md-3">
															<label class="form-label">{{ __('locale.Email') }}</label>
														</div>
														<div class="col-md-9">
															<input type="text" class="form-control" placeholder="Email"
																value="{{$admin->email}}" disabled>
														</div>
													</div>
												</div>
												<div class="mb-4 main-content-label">{{ __('locale.Security & Passwords') }}</div>
                                                @if (session('error'))
                                                    <div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">
                                                        <span class="alert-inner--icon"><i class="fe fe-slash"></i></span>
                                                        <span class="alert-inner--text"><strong>{{session('error')}}</strong></span>
                                                        <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
                                                    </div>
                                                @endif
                                                @if (session('success'))
                                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                                    <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
                                                    <span class="alert-inner--text"><strong>{{session('success')}}</strong></span>
                                                    <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
                                                </div>
                                                @endif
                                                <br>
												<div class="form-group ">
													<div class="row row-sm">
														<div class="col-md-3">
															<label class="form-label">{{ __('locale.Password') }}</label>
														</div>
														<div class="col-md-8 col-sm-8">
															<input type="password" class="form-control"
																placeholder="twitter" value="**********" disabled>
														</div>
														<div class="col-md-1">
															<a class="btn btn-primary" data-bs-target="#modaldemo1" data-bs-toggle="modal" href="">{{ __('locale.Change') }}</a>
														</div>
													</div>
												</div>
											</form>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- row closed -->
				<div class="modal" id="modaldemo1">
					<div class="modal-dialog wd-xl-400" role="document">
						<div class="modal-content">
							<div class="modal-body pd-sm-40">
								<button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal" type="button"><span aria-hidden="true">&times;</span></button>
								<h5 class="modal-title mg-b-5">{{ __('locale.Change Password') }}</h5>
								<form action="{{url('change')}}" method="POST">
                                    @csrf
									<input type="hidden" name="type" value="password" id="">
									<div class="form-group">
										<input class="form-control" placeholder="{{ __('locale.Email') }}" name="email" type="email" required>
									</div>
                                    <label for="">{{ __("locale.Enter your registered email ,We'll send OTP to reset your password") }}.</label>
									<button class="btn btn-primary btn-block">{{ __('locale.Submit') }}</button>
								</form>
							</div>
						</div>
					</div>
				</div>
                @php
                    session()->forget('error');
                    session()->forget('success');
                @endphp
    @endsection

    @section('scripts')

        <!-- Internal Select2 js-->
        <script src="{{asset('assets/plugins/select2/js/select2.min.js')}}"></script>
        <script src="{{asset('assets/js/select2.js')}}"></script>

        <!-- smart photo master js -->
        <script src="{{asset('assets/plugins/SmartPhoto-master/smartphoto.js')}}"></script>
        <script src="{{asset('assets/js/gallery.js')}}"></script>

    @endsection
