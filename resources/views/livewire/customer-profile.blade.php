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
        <div class="breadcrumb-header justify-content-between">
            <div class="left-content">
                <span class="main-content-title mg-b-0 mg-b-lg-1">Customer Profile</span>
            </div>
            <div class="justify-content-center mt-2">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                    <li class="breadcrumb-item tx-15"><a href="{{ session('previous')}}">Customers</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $customer->company }}</li>
                </ol>
            </div>
        </div>
        <!-- /breadcrumb -->
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

    @endsection

    @section('scripts')

        <script>

        $(document).ready(function() {
            $('#currency').on('input', function() {
                var selectedCurrency = $(this).val();
                var rate = $('#currencies').find('option[value="' + selectedCurrency + '"]').data('rate');
                if (rate !== undefined) {
                    $('#rate').val(rate);
                } else {
                    $('#rate').val(''); // Clear the rate field if the currency is not in the list
                }
            });

            $('.select2').select2({
                placeholder: 'Select an option',
                allowClear: true
            });

            $('#advance_options').collapse("{{ request('show_advance') == 1 ? 'show' : 'hide' }}");
        });


        document.getElementById("open_all_imei").onclick = function(){
            @php
                foreach ($imei_list as $imei) {
                    echo "window.open('".url("imei")."?imei=".$imei."','_blank');";
                }

            @endphp
        }
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
