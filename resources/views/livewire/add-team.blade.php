@extends('layouts.app')

    @section('styles')

		<!--- Internal Select2 css-->
		<link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet">

    @endsection

    @section('content')

					<!-- breadcrumb -->
					<div class="breadcrumb-header justify-content-between">
						<div class="left-content">
						  <span class="main-content-title mg-b-0 mg-b-lg-1">{{ __('locale.Add Member') }}</span>
						</div>
						<div class="justify-content-center mt-2">
							<ol class="breadcrumb">
								<li class="breadcrumb-item tx-15"><a href="{{url('team')}}">{{ __('locale.Team') }}</a></li>
								<li class="breadcrumb-item active" aria-current="page">{{ __('locale.Add Member') }}</li>
							</ol>
						</div>
					</div>
					<!-- /breadcrumb -->
                    <hr style="border-bottom: 1px solid #000">
					<!-- row -->
                    <div class="row">
                        <div class="col-md-2"></div>
                        <div class="col-lg-8 col-md-8">
                            <form action="{{url('insert-member')}}" method="POST">
                                @csrf
                                <div class="card">
                                    <div class="card-body">
                                        <div class="main-content-label mg-b-5">
                                           <img src="{{asset('assets/img/brand/favicon1.png')}}" height="50" width="50" alt="">
                                        </div>
                                        <p class="mg-b-20">{{ __('locale.Add a member to your team') }}.</p>

                                        <div class="pd-30 pd-sm-20">

                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-4">
                                                    <label class="form-label mg-b-0">Team Lead</label>
                                                </div>
                                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                                    <select class="form-select" placeholder="Input Status" name="parent" required>
                                                        <option>Select Team Lead</option>
                                                        @foreach ($parents as $parent)
                                                            <option value="{{ $parent->id }}">{{ $parent->first_name." ".$parent->last_name }}</option>

                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-4">
                                                    <label class="form-label mg-b-0">Role</label>
                                                </div>
                                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                                    <select class="form-select" placeholder="Input Status" name="role" required>
                                                        <option>Select Role</option>
                                                        @foreach ($roles as $role)
                                                            <option value="{{ $role->id }}">{{ $role->name }}</option>

                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-4">
                                                    <label class="form-label mg-b-0">{{ __('locale.First Name') }}</label>
                                                </div>
                                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="{{ __('locale.Enter') }} {{ __("locale.Member's") }} {{ __('locale.First Name') }}" name="fname" required type="text">
                                                </div>
                                            </div>
                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-4">
                                                    <label class="form-label mg-b-0">{{ __('locale.Last Name') }}</label>
                                                </div>
                                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="{{ __('locale.Enter') }} {{ __("locale.Member's") }} {{ __('locale.Last Name') }}" name="lname" required type="text">
                                                </div>
                                            </div>
                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-4">
                                                    <label class="form-label mg-b-0">{{ __('locale.Email') }}</label>
                                                </div>
                                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="{{ __('locale.Enter') }} {{ __("locale.Member's") }} {{ __('locale.Email') }}" name="email" required type="email">
                                                </div>
                                            </div>
                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-4">
                                                    <label class="form-label mg-b-0">Username</label>
                                                </div>
                                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="{{ __('locale.Enter') }} {{ __("locale.Member's") }} Username" name="username" required type="text">
                                                </div>
                                            </div>
                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-4">
                                                    <label class="form-label mg-b-0">{{ __('locale.Password') }}</label>
                                                </div>
                                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="{{ __('locale.Enter') }} {{ __('locale.Password') }} {{ __('locale.For') }} {{ __("locale.Member") }}" name="password" required type="password">
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
