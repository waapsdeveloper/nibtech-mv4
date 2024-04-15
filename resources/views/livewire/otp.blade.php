@extends('layouts.custom-app')

    @section('styles')
        <style>
            /* Chrome, Safari, Edge, Opera */
            input::-webkit-outer-spin-button,
            input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
            }

            /* Firefox */
            input[type=number] {
            -moz-appearance: ;
            }
        </style>
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
                                    <div class="wd-100p"><div class="d-flex mb-4"><a href="{{url('index')}}"><img src="{{asset('assets/img/brand/favicon1.png')}}" class="sign-favicon ht-40" alt="logo"></a></div>
                                        <div class="">
                                            <div class="main-signup-header">
                                                <h2 class="text text-capitalize text-success">Reset {{$title}}!</h2>
                                                <div class="panel panel-primary">
                                                <div class="panel-body tabs-menu-body border-0 p-3">
                                                    <div class="tab-content">
                                                        <div class="" id="tab6">
                                                            <form action="{{url('QomeBa27WU')}}" method="POST">
                                                                @csrf
                                                                <div id="" style="display: flex" class="justify-content-around mb-4">
                                                                    <input type="hidden" name="type" id="" value="{{$title}}">
                                                                    <input type="number" class="form-control  text-center me-2" name="txt1" pattern="0"
                                                                    maxlength="1" oninput="javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength);">
                                                                    <input type="number" class="form-control  text-center me-2" name="txt2"
                                                                    maxlength="1" oninput="javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength);">
                                                                    <input type="number" class="form-control  text-center me-2" name="txt3"
                                                                    maxlength="1" oninput="javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength);">
                                                                    <input type="number" class="form-control  text-center" name="txt4"
                                                                    maxlength="1" oninput="javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength);">
                                                                </div>
                                                                @if (session('error'))
                                                                    <div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">
                                                                        <span class="alert-inner--icon"><i class="fe fe-slash"></i></span>
                                                                        <span class="alert-inner--text"><strong>{{session('error')}}</strong></span>
                                                                        <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
                                                                    </div>
                                                                    <br>
                                                                @endif
                                                                <span>Note :To reset your {{$title}} enter the OTP that has been send on your email.</span>
                                                                <div class="container-login100-form-btn mt-3">
                                                                    <button type="submit" class="btn login100-form-btn btn-success" >
                                                                            Proceed
                                                                    </button>
                                                                </div>
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
            @php
                session()->forget('error');
            @endphp
    @endsection

    @section('scripts')

		<!-- generate-otp js -->
		<script src="{{asset('assets/js/generate-otp.js')}}"></script>

    @endsection
