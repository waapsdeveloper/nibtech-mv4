@extends('layouts.app')

    @section('styles')

                <!--- Internal Select2 css-->
                <link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet">

    @endsection

    @section('content')

        <!-- breadcrumb -->
        <div class="breadcrumb-header justify-content-between">
            <div class="left-content">
                <span class="main-content-title mg-b-0 mg-b-lg-1">Marketplace Profile</span>
            </div>
            <div class="justify-content-center mt-2">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item tx-15"><a href="{{url('v2/marketplace')}}">Marketplace</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Marketplace Profile</li>
                </ol>
            </div>
        </div>
        <!-- /breadcrumb -->
        <hr style="border-bottom: 1px solid #000">
        <!-- row -->
        <div class="card">
            <div class="card-body">
                <form action="{{url('v2/marketplace/update')}}/{{$marketplace->id}}" method="POST">
                    @csrf
                    <div class="row">

                        <div class="col-md-6">
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-4">
                                    <label class="form-label mg-b-0">Name <span class="text-danger">*</span></label>
                                </div>
                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                    <input class="form-control" placeholder="Enter marketplace name" name="name" value="{{$marketplace->name ?? ''}}" type="text" required>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-4">
                                    <label class="form-label mg-b-0">Status</label>
                                </div>
                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                    <select class="form-control" name="status">
                                        <option value="1" {{(isset($marketplace->status) && $marketplace->status == 1) ? 'selected' : ''}}>Active</option>
                                        <option value="0" {{(isset($marketplace->status) && $marketplace->status == 0) ? 'selected' : ''}}>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-2">
                                    <label class="form-label mg-b-0">Description</label>
                                </div>
                                <div class="col-md-10 mg-t-5 mg-md-t-0">
                                    <textarea class="form-control" placeholder="Enter marketplace description" name="description" rows="3">{{$marketplace->description ?? ''}}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-4">
                                    <label class="form-label mg-b-0">API Key</label>
                                </div>
                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                    <input class="form-control" placeholder="Enter API Key" name="api_key" value="{{$marketplace->api_key ?? ''}}" type="text">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-4">
                                    <label class="form-label mg-b-0">API Secret</label>
                                </div>
                                <div class="col-md-8 mg-t-5 mg-md-t-0">
                                    <input class="form-control" placeholder="Enter API Secret" name="api_secret" value="{{$marketplace->api_secret ?? ''}}" type="password">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="row row-xs align-items-center mg-b-20">
                                <div class="col-md-2">
                                    <label class="form-label mg-b-0">API URL</label>
                                </div>
                                <div class="col-md-10 mg-t-5 mg-md-t-0">
                                    <input class="form-control" placeholder="Enter API URL" name="api_url" value="{{$marketplace->api_url ?? ''}}" type="text">
                                </div>
                            </div>
                        </div>

                    </div>
                    <button class="btn btn-primary pd-x-30 mg-r-5 mg-t-5" >Update</button>
                </form>
            </div>
        </div>
        <!-- /row -->


        @endsection
    @section('scripts')

                <!-- Form-layouts js -->
                <script src="{{asset('assets/js/form-layouts.js')}}"></script>

                <!--Internal  Select2 js -->
                <script src="{{asset('assets/plugins/select2/js/select2.min.js')}}"></script>

    @endsection

