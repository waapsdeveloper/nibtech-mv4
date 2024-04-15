@extends('layouts.custom-app')

    @section('styles')

    @endsection

    @section('class')

	    <div class="bg-primary">

    @endsection

    @section('content')

            <div class="page-single">
                <div class="container">
                    <div class="row">
                        <div class="col-xl-5 col-lg-6 col-md-8 col-sm-8 col-xs-10 card-sigin-main mx-auto my-auto py-45 justify-content-center">
                            <div class="card-sigin mt-5 mt-md-0">
                                <!-- Demo content-->
                                <div class="main-card-signin d-md-flex">
                                    <div class="wd-100p"><div class="d-flex mb-4"><img src="{{asset('assets/img/brand/favicon1.png')}}" class="sign-favicon ht-40" alt="logo"></div>
                                        <div class="">
                                            <div class="main-signup-header">
                                                <h2>Welcome back!</h2>
                                                <h6 class="font-weight-semibold mb-4">Please sign in to continue.</h6>
                                                <div class="panel panel-primary">
                                                <div class="panel-body tabs-menu-body border-0 p-3">
                                                    <div class="tab-content">
                                                        <div class="tab-pane active" id="tab5">
                                                            <form action="{{url('/login')}}" method="POST">
                                                                @csrf
                                                                <div class="form-group">
                                                                    <label>Username</label> <input class="form-control" placeholder="Enter your username" name="username" type="text">
                                                                </div>
                                                                <div class="form-group">
                                                                    <label>Password</label> <input class="form-control" placeholder="Enter your password" name="password" type="password">
                                                                </div>
                                                                @if(isset($error))
                                                                    <div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">
                                                                        <span class="alert-inner--icon"><i class="fe fe-slash"></i></span>
                                                                        <span class="alert-inner--text"><strong>{{$error}}</strong></span>
                                                                        <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
                                                                    </div>
                                                                @endif
                                                                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                                                                </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

    @endsection

    @section('scripts')

		<!-- generate-otp js -->
		<script src="{{asset('assets/js/generate-otp.js')}}"></script>

    @endsection
