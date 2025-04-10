@extends('layouts.app')

    @section('styles')
    <!-- INTERNAL Select2 css -->
    <link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet" />
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
            <div class="breadcrumb-header justify-content-between mt-0">
                <div class="left-content">
                    {{-- <span class="ms-3 form-check form-switch ms-4">
                        <input type="checkbox" value="1" name="bypass_check" class="form-check-input" form="repair_item" @if (session('bypass_check') == 1) checked @endif>
                        <label class="form-check-label" for="bypass_check">Bypass Repair check</label>
                    </span> --}}
                {{-- <span class="main-content-title">Topup Batch Detail</span> --}}
                @if ($process->status == 1)
                <form class="form-inline" id="approveform" method="POST" action="{{url('topup/ship').'/'.$process->id}}">
                    @csrf
                    <div class="form-floating">
                        <input type="text" class="form-control" id="description" name="description" placeholder="Enter Description" value="{{$process->description}}" required>
                        <label for="description">Description</label>
                    </div>
                    <button type="submit" class="btn btn-success" name="approve" value="1">Ship</button>
                    <a class="btn btn-danger" href="{{url('delete_verification') . "/" . $process->id }}">Delete</a>
                </form>

                <script>
                    function submitForm() {
                        var form = $("#approveform");
                        var actionUrl = form.attr('action');

                        $.ajax({
                            type: "POST",
                            url: actionUrl,
                            data: form.serialize(), // serializes the form's elements.
                            success: function(data) {
                                alert("Success: " + data); // show response from the PHP script.
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
                                alert("Error: " + textStatus + " - " + errorThrown);
                            }
                        });
                    }

                </script>
                @else
                <br>
                {{ $process->description }}



                @if (session('user')->hasPermission('verification_revert_status'))
                    <br>
                    <a href="{{url('topup/revert_status').'/'.$process->id}}">Revert Back to Pending</a>
                @endif

                @endif
                    {{-- @if ($process->status == 2)
                    <form class="form-inline" method="POST" action="{{url('topup/approve').'/'.$process->id}}">
                        @csrf
                        <div class="form-floating">
                            <input type="text" class="form-control" id="cost" name="cost" placeholder="Enter Total Cost" required>
                            <label for="cost">Total Cost</label>
                        </div>
                        <button type="submit" class="btn btn-success">Close</button>
                    </form>

                    @endif --}}
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item tx-15"><a href="{{ session('previous')}}">listed_stock Verification</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Topup Detail</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="d-flex justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">
                {{-- <center><h4>listed_stock Verification Batch Detail</h4></center> --}}
            <h5>Reference: {{ $process->reference_id }} | Batch Quantity: {{ $process->quantity }} | Scanned Quantity: {{ $process->process_stocks->count() }}</h5>
        </div>

        <br>

        {{-- <div class="d-flex justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">


            <div class="p-2">
                <h4>Receive External Repair Item</h4>

            </div>

            <div class="btn-group p-1" role="group">
                <a href="{{url('repair_email')}}/{{ $process->id }}" target="_blank"><button class="btn btn-secondary">Send Email</button></a>
                <a href="{{url('export_repair_invoice')}}/{{ $process->id }}" target="_blank"><button class="btn btn-secondary">Invoice</button></a>
                @if ($process->exchange_rate != null)
                <a href="{{url('export_repair_invoice')}}/{{ $process->id }}/1" target="_blank"><button class="btn btn-secondary">{{$process->currency_id->sign}} Invoice</button></a>

                @endif
                <button type="button" class="btn btn-secondary dropdown-toggle" id="pack_sheet" data-bs-toggle="dropdown" aria-expanded="false">
                Pack Sheet
                </button>
                <ul class="dropdown-menu" aria-labelledby="pack_sheet">
                    <li><a class="dropdown-item" href="{{url('export_repair_invoice')}}/{{ $process->id }}?packlist=2&id={{ $process->id }}">.xlsx</a></li>
                    <li><a class="dropdown-item" href="{{url('export_repair_invoice')}}/{{ $process->id }}?packlist=1" target="_blank">.pdf</a></li>
                </ul>
            </div>
        </div> --}}
            <script>
                window.onload = function() {
                    document.getElementById('imei').focus();
                    document.getElementById('imei').click();
                    setTimeout(function(){ document.getElementById('imei').focus();$('#imei').focus(); }, 500);
                };
                document.addEventListener('DOMContentLoaded', function() {
                    var input = document.getElementById('imei');
                    input.focus();
                    input.select();
                    document.getElementById('imei').click();
                    setTimeout(function(){ document.getElementById('imei').focus();$('#imei').focus(); }, 500);
                });
            </script>
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

    </div>
        <br>


    @endsection

    @section('scripts')

        <script>

        </script>
		<!--Internal Sparkline js -->
		<script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

		<!-- Internal Piety js -->
		<script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

		<!-- Internal Chart js -->
		<script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

		<!-- INTERNAL Select2 js -->
		<script src="{{asset('assets/plugins/select2/js/select2.full.min.js')}}"></script>
		<script src="{{asset('assets/js/select2.js')}}"></script>
    @endsection
