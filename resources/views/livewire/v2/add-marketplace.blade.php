@extends('layouts.app')

    @section('styles')

                <!--- Internal Select2 css-->
                <link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet">

    @endsection

    @section('content')

                                        <!-- breadcrumb -->
                                        <div class="breadcrumb-header justify-content-between">
                                                <div class="left-content">
                                                  <span class="main-content-title mg-b-0 mg-b-lg-1">Add Marketplace</span>
                                                </div>
                                                <div class="justify-content-center mt-2">
                                                        <ol class="breadcrumb">
                                                                <li class="breadcrumb-item tx-15"><a href="{{url('v2/marketplace')}}">Marketplace</a></li>
                                                                <li class="breadcrumb-item active" aria-current="page">Add Marketplace</li>
                                                        </ol>
                                                </div>
                                        </div>
                                        <!-- /breadcrumb -->
                    <hr style="border-bottom: 1px solid #000">
                                        <!-- row -->
                    <div class="row">
                        <div class="col-md-2"></div>
                        <div class="col-lg-8 col-md-8">
                            <form action="{{url('v2/marketplace/insert')}}" method="POST">
                                @csrf
                                <div class="card">
                                    <div class="card-body">

                                        <div class="pd-30 pd-sm-20">

                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-3">
                                                    <label class="form-label mg-b-0">Name <span class="text-danger">*</span></label>
                                                </div>
                                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="Enter marketplace name" name="name" type="text" required>
                                                </div>
                                            </div>

                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-3">
                                                    <label class="form-label mg-b-0">Description</label>
                                                </div>
                                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                                    <textarea class="form-control" placeholder="Enter marketplace description" name="description" rows="3"></textarea>
                                                </div>
                                            </div>

                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-3">
                                                    <label class="form-label mg-b-0">Status</label>
                                                </div>
                                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                                    <select class="form-control" name="status">
                                                        <option value="1" selected>Active</option>
                                                        <option value="0">Inactive</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-3">
                                                    <label class="form-label mg-b-0">API Key</label>
                                                </div>
                                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="Enter API Key (optional)" name="api_key" type="text">
                                                </div>
                                            </div>

                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-3">
                                                    <label class="form-label mg-b-0">API Secret</label>
                                                </div>
                                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="Enter API Secret (optional)" name="api_secret" type="password">
                                                </div>
                                            </div>

                                            <div class="row row-xs align-items-center mg-b-20">
                                                <div class="col-md-3">
                                                    <label class="form-label mg-b-0">API URL</label>
                                                </div>
                                                <div class="col-md-9 mg-t-5 mg-md-t-0">
                                                    <input class="form-control" placeholder="Enter API URL (optional)" name="api_url" type="text">
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

