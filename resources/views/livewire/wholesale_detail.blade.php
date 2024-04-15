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

    @if (session('error'))
        <div class="toast-container position-fixed top-0 end-0 p-3">
            <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header text-danger">
                    <strong class="me-auto">Error</strong>
                    <button type="button" class="btn" data-bs-dismiss="toast" aria-label="Close">x</button>
                </div>
                <div class="toast-body">{{ session('error') }}</div>
            </div>
        </div>
        @php
        session()->forget('error');
        @endphp
    @endif

    @if (session('success'))
        <div class="toast-container position-fixed top-0 end-0 p-3 mt-5 pt-5">
            <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header text-success bg-light">
                    <strong class="me-auto">Success</strong>
                    <button type="button" class="btn" data-bs-dismiss="toast" aria-label="Close">x</button>
                </div>
                <div class="toast-body">{{ session('success') }} Hello</div>
            </div>
        </div>
        @php
        session()->forget('success');
        @endphp
    @endif

    @if (session('copy'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Function to copy text to clipboard
                function copyToClipboard(text) {
                    var tempInput = document.createElement('textarea');
                    tempInput.value = text;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                }

                // Check if there is a copy message in the session
                var copiedText = "{{ session('copy') }}";
                if (copiedText) {
                    // Copy the IMEI number to the clipboard
                    copyToClipboard(copiedText);

                    // Show success toast
                    var toastContainer = document.querySelector('.toast-container');
                    var toastBody = document.querySelector('.toast-body');
                    toastBody.innerText = "Message copied to clipboard: \n" + copiedText;
                    var toast = new bootstrap.Toast(document.querySelector('.toast'));
                    toast.show();
                }
            });
        </script>
        @php
        session()->forget('copy');
        @endphp
    @endif

        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">Wholesale</span> --}}
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Wholesale Detail</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="row">
            <div class="col-md-12 tx-center" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Wholesale Order Detail</h4></center>
                <h5>Reference: {{ $order->reference_id }} | Vendor: {{ $order->customer->first_name }} | Total Items: {{ $order->order_items->count() }} | Total Cost: {{ $order->currency_id->sign.number_format($order->order_items->sum('price'),2) }}</h5>
            </div>
        </div>
        <br>

        <form action="{{ url('add_wholesale_item').'/'.$order_id }}" method="POST">
            @csrf
            <div class="row">

                <div class="col-lg-4 col-xl-4 col-md-4 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title">IMEI</h4>
                    </div>
                    <input type="text" class="form-control" list="imeis" name="imei" id="imei" placeholder="Enter IMEI">
                    <datalist id="imeis">
                        @foreach ($imeis as $stock)
                            <option value="{{ $stock->imei.$stock->serial_number }}" data-sr-price="{{ $stock->purchase_item->price }}">
                        @endforeach
                    </datalist>
                </div>
                <div class="col-lg-4 col-xl-4 col-md-4 col-sm-6">
                    <div class="card-header">
                        <h4 class="card-title">Cost</h4>
                    </div>
                    <input type="text" class="form-control" name="price" id="price" placeholder="Enter Price">
                </div>
                <div class="col-lg-4 col-xl-4 col-md-4 col-sm-6 align-self-end mb-1 tx-center">
                    <h4>Add Wholesaled Item</h4>
                    <button class="btn btn-primary pd-x-20" type="submit">Insert</button>
                </div>
            </div>
        </form>
        <script>
            // Add event listener to IMEI input field
            document.getElementById('imei').addEventListener('change', function() {
                // Get the selected IMEI value
                var selectedImei = this.value;

                // Find the corresponding option in the datalist
                var selectedOption = document.querySelector('datalist#imeis option[value="' + selectedImei + '"]');

                // If a matching option is found, update the price field
                if (selectedOption) {
                    var price = selectedOption.getAttribute('data-sr-price');
                    document.getElementById('price').value = price;
                } else {
                    // If no matching option is found, reset the price field
                    document.getElementById('price').value = '';
                }
            });
        </script>
        <br>
        <br>

        <div class="row">

            @foreach ($variations as $variation)
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header pb-0">
                        @php
                            isset($variation->color_id)?$color = $variation->color_id->name:$color = null;
                            isset($variation->storage)?$storage = $storages[$variation->storage]:$storage = null;
                        @endphp
                        {{ $variation->product->model." ".$storage." ".$color." ".$variation->grade_id->name }}
                    </div>
                            {{-- {{ $variation }} --}}
                    <div class="card-body"><div class="table-responsive" style="max-height: 400px">

                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>No</b></small></th>
                                        <th><small><b>IMEI/Serial</b></small></th>
                                        @if (session('user')->hasPermission('view_cost'))
                                        <th><small><b>Cost</b></small></th>
                                        @endif
                                        @if (session('user')->hasPermission('delete_wholesale_item'))
                                        <th></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 1;
                                        $id = [];
                                    @endphp
                                    @php
                                        $stocks = $variation->stocks;
                                        // $items = $stocks->order_item;
                                        $j = 0;
                                        // print_r($variation);
                                    @endphp

                                    @foreach ($stocks as $item)
                                        @if($item->order_item[0]->order_id != $order_id)
                                        <tr>
                                            <td>{{ $i }}</td>
                                            <td>{{ $item->imei.$item->serial_number }}</td>
                                            @if (session('user')->hasPermission('view_cost'))
                                            <td>{{ $currency.$item->order_item[0]->price }}</td>
                                            @endif
                                            @if (session('user')->hasPermission('delete_wholesale_item'))
                                            <td><a href="{{ url('delete_order_item').'/'.$item->order_item[0]->id }}"><i class="fa fa-trash"></i></a></td>
                                            @endif
                                        </tr>
                                        @php
                                            $i ++;
                                        @endphp
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        <br>
                    </div>

                    </div>
                </div>
            </div>
            @endforeach
        </div>

    @endsection

    @section('scripts')
        <script>
            $(document).ready(function() {
                $('.test').select2();
            });

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
