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
    @section('content')
        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                <span class="main-content-title mg-b-0 mg-b-lg-1">POS</span>
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('locale.Dashboards') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ url('wholesale') }}">BulkSale</a></li>
                        <li class="breadcrumb-item active" aria-current="page">POS</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <hr style="border-bottom: 1px solid #000">
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
            <div class="col-md-9">
                <div class="card p-3">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="basic-addon1">
                                <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="20" height="20" viewBox="0 0 50 50">
                                    <path d="M 21 3 C 11.621094 3 4 10.621094 4 20 C 4 29.378906 11.621094 37 21 37 C 24.710938 37 28.140625 35.804688 30.9375 33.78125 L 44.09375 46.90625 L 46.90625 44.09375 L 33.90625 31.0625 C 36.460938 28.085938 38 24.222656 38 20 C 38 10.621094 30.378906 3 21 3 Z M 21 5 C 29.296875 5 36 11.703125 36 20 C 36 28.296875 29.296875 35 21 35 C 12.703125 35 6 28.296875 6 20 C 6 11.703125 12.703125 5 21 5 Z"></path>
                                </svg>
                            </span>
                        </div>
                        <input type="text" class="form-control" placeholder="Search" aria-label="Search" aria-describedby="basic-addon1">
                    </div>

                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex bg-light tx-center flex-wrap overflow-auto" style="white-space: nowrap;">
                            @foreach ($categories as $id => $name)
                                <div class="border wd-auto">
                                    <a href="#" class="btn btn-link"> {{ $name }} </a>
                                </div>
                            @endforeach
                        </div>
                        <div class="d-flex bg-light tx-center overflow-scroll">
                            @foreach ($brands as $id => $name)
                                <div class="border wd-auto">
                                    <a href="#" class="btn btn-link"> {{ $name }} </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">

                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="col-md-3">
                <div class="card">

                </div>
            </div>
        </div>
        @php
            session()->forget('success');
        @endphp
    @endsection

    @section('scripts')
        <script>
            $(document).ready(function () {
                $('#sb_toggle').click();
            })
        </script>
		<!--Internal Sparkline js -->
		<script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

		<!-- Internal Piety js -->
		<script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

		<!-- Internal Chart js -->
		<script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

    @endsection
