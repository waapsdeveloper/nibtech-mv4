@extends('layouts.app')

    @section('styles')

		<!--- Internal Select2 css-->
		<link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet">

    @endsection

    @section('content')

					<!-- breadcrumb -->
					<div class="breadcrumb-header justify-content-between">
						<div class="left-content">
						  <span class="main-content-title mg-b-0 mg-b-lg-1">Add Grade</span>
						</div>
						<div class="justify-content-center mt-2">
							<ol class="breadcrumb">
								<li class="breadcrumb-item tx-15"><a href="{{url('grade')}}">Grade</a></li>
								<li class="breadcrumb-item active" aria-current="page">Add Grade</li>
							</ol>
						</div>
					</div>
					<!-- /breadcrumb -->
                    <hr style="border-bottom: 1px solid #000">
					<!-- row -->
                    <div class="row">
                        <div class="col-md-2"></div>
                        <div class="col-lg-8 col-md-8">
                            <form action="{{url('insert-grade')}}" method="POST">
                                @csrf
                                <div class="card">
                                    <div class="card-body">

                                        <div class="pd-30 pd-sm-20">

                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-3">
                                                    <label class="form-label mg-b-0">Name</label>
                                                </div>
                                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="Enter grade's company" name="name" type="text" required>
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

		<!--Internal  Select2 js -->
		<script src="{{asset('assets/plugins/select2/js/select2.min.js')}}"></script>

    @endsection
