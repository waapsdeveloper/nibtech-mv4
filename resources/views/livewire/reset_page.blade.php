@extends('layouts.custom-app')

    @section('styles')

    @endsection

    @section('class')

	    <div class="bg-success">

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
                                                <div class="panel panel-primary">
                                                <div class="panel-body tabs-menu-body border-0 p-3">
                                                    <div class="tab-content">
                                                        <div class="tab-pane active" id="tab5">
                                                            <form action="{{url('reset')}}" method="POST">
                                                                @csrf
                                                                <input type="hidden" name="type" id="" value="password">
                                                                <div class="form-group">
                                                                    <label>New password</label> <input class="form-control" placeholder="Enter new password" name="new_pass" type="password">
                                                                </div>
                                                                <div class="form-group">
                                                                    <label>Confirm password</label> <input class="form-control" placeholder="confirm your password" name="confirm_pass" type="password">
                                                                </div>
                                                                @if(session('error'))
                                                                    <div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">
                                                                        <span class="alert-inner--icon"><i class="fe fe-slash"></i></span>
                                                                        <span class="alert-inner--text"><strong>{{session('error')}}</strong></span>
                                                                        <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
                                                                    </div>
                                                                    <br>
                                                                @endif
                                                                <button type="submit" class="btn btn-success btn-block">Sign In</button>
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
