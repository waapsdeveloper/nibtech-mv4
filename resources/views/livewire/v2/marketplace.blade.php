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
<br>
    @section('content')
        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                <span class="main-content-title mg-b-0 mg-b-lg-1">Marketplaces</span>
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboards') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                                Marketplaces
                        </li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <hr style="border-bottom: 1px solid #000">
        <br>
        <div class="tx-right">

                <a href="{{url('v2/marketplace/add')}}" class="btn btn-success float-right"><i class="mdi mdi-plus"></i> Add Marketplace</a>
        </div>
        <br>
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
            <script>
                alert("{{session('error')}}");
            </script>
        <br>
        @php
        session()->forget('error');
        @endphp
        @endif
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">Marketplaces</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>{{ __('locale.S.No') }}</b></small></th>
                                        <th><small><b>Name</b></small></th>
                                        <th><small><b>Description</b></small></th>
                                        <th><small><b>Status</b></small></th>
                                        <th><small><b>API Key</b></small></th>
                                        <th><small><b>API Secret</b></small></th>
                                        <th><small><b>API URL</b></small></th>
                                        <th><small><b>Actions</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 0;
                                    @endphp
                                    @foreach ($marketplaces as $marketplace)
                                    @php
                                        $i++;
                                    @endphp
                                        <tr>
                                            <td title="{{$marketplace->id}}">{{$i}}</td>
                                            <td>{{$marketplace->name}}</td>
                                            <td>{{$marketplace->description ?? '-'}}</td>
                                            <td>
                                                @if(isset($marketplace->status) && $marketplace->status == 1)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($marketplace->api_key)
                                                    <div class="input-group">
                                                        <input type="password" 
                                                               class="form-control form-control-sm api-key-field" 
                                                               id="api_key_{{$marketplace->id}}" 
                                                               value="{{$marketplace->api_key}}" 
                                                               readonly 
                                                               style="font-size: 0.75rem;">
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-secondary toggle-api-key" 
                                                                data-target="api_key_{{$marketplace->id}}"
                                                                title="Show/Hide">
                                                            <i class="fe fe-eye"></i>
                                                        </button>
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($marketplace->api_secret)
                                                    <div class="input-group">
                                                        <input type="password" 
                                                               class="form-control form-control-sm api-secret-field" 
                                                               id="api_secret_{{$marketplace->id}}" 
                                                               value="{{$marketplace->api_secret}}" 
                                                               readonly 
                                                               style="font-size: 0.75rem;">
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-secondary toggle-api-secret" 
                                                                data-target="api_secret_{{$marketplace->id}}"
                                                                title="Show/Hide">
                                                            <i class="fe fe-eye"></i>
                                                        </button>
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($marketplace->api_url)
                                                    <small>{{$marketplace->api_url}}</small>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <center>
                                                    <a href="{{url('v2/marketplace/edit')}}/{{$marketplace->id}}" class="text text-success w-100 vh-100">{{ __('locale.Edit') }}</a>
                                                </center>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <br>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @php
            session()->forget('success');
        @endphp
    @endsection

    @section('scripts')

                <!--Internal Sparkline js -->
                <script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

                <!-- Internal Piety js -->
                <script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

                <!-- Internal Chart js -->
                <script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

                <script>
                    // Toggle API Key visibility
                    document.addEventListener('DOMContentLoaded', function() {
                        // Handle API Key toggle
                        document.querySelectorAll('.toggle-api-key').forEach(function(button) {
                            button.addEventListener('click', function() {
                                const targetId = this.getAttribute('data-target');
                                const input = document.getElementById(targetId);
                                const icon = this.querySelector('i');
                                
                                if (input.type === 'password') {
                                    input.type = 'text';
                                    icon.classList.remove('fe-eye');
                                    icon.classList.add('fe-eye-off');
                                } else {
                                    input.type = 'password';
                                    icon.classList.remove('fe-eye-off');
                                    icon.classList.add('fe-eye');
                                }
                            });
                        });

                        // Handle API Secret toggle
                        document.querySelectorAll('.toggle-api-secret').forEach(function(button) {
                            button.addEventListener('click', function() {
                                const targetId = this.getAttribute('data-target');
                                const input = document.getElementById(targetId);
                                const icon = this.querySelector('i');
                                
                                if (input.type === 'password') {
                                    input.type = 'text';
                                    icon.classList.remove('fe-eye');
                                    icon.classList.add('fe-eye-off');
                                } else {
                                    input.type = 'password';
                                    icon.classList.remove('fe-eye-off');
                                    icon.classList.add('fe-eye');
                                }
                            });
                        });
                    });
                </script>

    @endsection

