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
                    {{-- <span class="ms-3 form-check form-switch ms-4">
                        <input type="checkbox" value="1" name="bypass_check" class="form-check-input" form="repair_item" @if (session('bypass_check') == 1) checked @endif>
                        <label class="form-check-label" for="bypass_check">Bypass Repair check</label>
                    </span> --}}
                <span class="main-content-title mg-b-0 mg-b-lg-1">Inventory Verification Batch Detail</span>
                @if ($process->status == 1)
                <form class="form-inline" id="approveform" method="POST" action="{{url('inventory_verification/ship').'/'.$process->id}}">
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
                    <a href="{{url('inventory_verification/revert_status').'/'.$process->id}}">Revert Back to Pending</a>
                @endif

                @endif
                    @if ($process->status == 2)
                    <form class="form-inline" method="POST" action="{{url('inventory_verification/approve').'/'.$process->id}}">
                        @csrf
                        <div class="form-floating">
                            <input type="text" class="form-control" id="cost" name="cost" placeholder="Enter Total Cost" required>
                            <label for="cost">Total Cost</label>
                        </div>
                        <button type="submit" class="btn btn-success">Close</button>
                    </form>

                    @endif
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item tx-15"><a href="{{ session('previous')}}">Inventory Verification</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Inventory Verification Detail</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="d-flex justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">
                {{-- <center><h4>Inventory Verification Batch Detail</h4></center> --}}
            <h5>Reference: {{ $process->reference_id }} | Total Items: {{ $process->process_stocks->count() }}</h5>
            @if ($process->status == 1)
            <div class="p-1">
                <form class="form-inline" action="{{ url('delete_repair_item') }}" method="POST" id="repair_item">
                    @csrf
                    <label for="imei" class="">IMEI | Serial Number: &nbsp;</label>
                    <input type="text" class="form-control form-control-sm" name="imei" @if (request('remove') == 1) id="imei" @endif placeholder="Enter IMEI" onloadeddata="$(this).focus()" autofocus required>
                    <input type="hidden" name="process_id" value="{{$process->id}}">
                    <input type="hidden" name="remove" value="1">
                    <button class="btn-sm btn-secondary pd-x-20" type="submit">Remove</button>

                </form>
            </div>
            @endif
        </div>

        <br>

        <div class="d-flex justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">


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
        </div>
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
        @php
            $imei_list = [];
        @endphp

        @if ($process->status == 1)

        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h4 class="card-title mg-b-0">Latest Added Items</h4>
                        </div>
                    </div>
                    <div class="card-body"><div class="table-responsive" style="max-height: 250px">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>No</b></small></th>
                                        <th><small><b>Variation</b></small></th>
                                        <th><small><b>IMEI | Serial Number</b></small></th>
                                        <th><small><b>Vendor</b></small></th>
                                        <th><small><b>Reason</b></small></th>
                                        @if (session('user')->hasPermission('view_cost'))
                                        <th><small><b>Cost</b></small></th>
                                        @endif
                                        <th><small><b>Creation Date</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 0;
                                    @endphp
                                    @foreach ($last_ten as $p_stock)
                                        @php
                                            $item = $p_stock->stock;
                                        @endphp

                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $products[$item->variation->product_id]}} {{$storages[$item->variation->storage] ?? null}} {{$colors[$item->variation->color] ?? null}} {{$grades[$item->variation->grade] ?? "Grade not added" }} {{$grades[$item->variation->sub_grade] ?? '' }}</td>
                                            <td>{{ $item->imei.$item->serial_number }}</td>
                                            <td>{{ $item->order->customer->first_name }}
                                                @if ($item->previous_repair != null)
                                                    <a href="{{url('repair/detail/'.$item->previous_repair->proces_id)}}">{{ $item->previous_repair->process->reference_id }}</a>
                                                @endif
                                            </td>
                                            <td>{{ $item->latest_operation->description ?? null }}</td>
                                            @if (session('user')->hasPermission('view_cost'))
                                            <td>{{ $currency.amount_formatter($item->purchase_item->price,2) }}</td>
                                            @endif
                                            <td style="width:220px">{{ $item->created_at }}</td>
                                        </tr>
                                        @php
                                            $i ++;
                                        @endphp
                                    @endforeach
                                </tbody>
                            </table>
                        <br>
                    </div>

                </div>
            </div>
        </div>
    </div>
        <br>
        @endif

        <div class="card" id="print_inv">
            <div class="card-header pb-0 d-flex justify-content-between">
                <h4 class="card-title">Inventory Verification Summery</h4>
            </div>
            <div class="card-body"><div class="table-responsive">
                <form method="GET" action="" target="_blank" id="search_summery">
                    <input type="hidden" name="category" value="{{ Request::get('category') }}">
                    <input type="hidden" name="brand" value="{{ Request::get('brand') }}">
                    <input type="hidden" name="color" value="{{ Request::get('color') }}">
                    @if (Request::get('grade'))

                    @foreach (Request::get('grade') as $grd)

                        <input type="hidden" name="grade[]" value="{{ $grd }}">
                    @endforeach
                    @endif
                    <input type="hidden" name="replacement" value="{{ Request::get('replacement') }}">
                    <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
                    <input type="hidden" name="status" value="{{ Request::get('status') }}">
                    <input type="hidden" name="vendor" value="{{ Request::get('vendor') }}">
                </form>
                <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                    <thead>
                        <tr>
                            <th><small><b>No</b></small></th>
                            <th><small><b>Model</b></small></th>
                            <th><small><b>Quantity</b></small></th>
                            <th><small><b>Cost</b></small></th>
                            <th><small><b>Remaining</b></small></th>
                            <th><small><b>Cost</b></small></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $i = 0;
                            $total_quantity = 0;
                            $total_cost = 0;
                            $remaining_quantity = 0;
                            $remaining_total_cost = 0;
                        @endphp
                        @foreach ($available_stock_summery as $summery)

                        @php
                            // print_r($summery);
                            // continue;
                            // if($summery['storage'] > 0){
                            //     $storage = $storages[$summery['storage']];
                            // }else{
                            //     $storage = null;
                            // }
                            $total_quantity += $summery['quantity'];
                            $total_cost += $summery['total_cost'];
                            $remaining_quantity += $summery['remaining_quantity'];
                            $remaining_total_cost += $summery['remaining_total_cost'];
                            $stock_imeis = array_merge($summery['stock_imeis'],$summery['stock_serials']);
                            $temp_array = array_unique($stock_imeis);
                            $duplicates = sizeof($temp_array) != sizeof($stock_imeis);
                            $duplicate_count = sizeof($stock_imeis) - sizeof($temp_array);

                        @endphp
                            <tr>
                                <td>{{ ++$i }}</td>
                                {{-- <td>{{ $products[$summery['product_id']]." ".$storage }}</td> --}}
                                <td><button class="btn py-0 btn-link" type="submit" form="search_summery" name="pss" value="{{$summery['pss_id']}}">{{ $summery['model'] }}</button></td>
                                <td title="{{json_encode($summery['stock_ids'])}}"><a id="test{{$i}}" href="javascript:void(0)">{{ $summery['quantity'] }}</a>
                                @if ($duplicates)
                                    <span class="badge badge-danger">{{ $duplicate_count }} Duplicate</span>
                                @endif
                                <td
                                title="{{ amount_formatter($summery['total_cost']/$summery['quantity']) }}"
                                >{{ amount_formatter($summery['total_cost'],2) }}</td>
                                <td>{{ $summery['remaining_quantity'] }}</td>
                                <td>{{ amount_formatter($summery['remaining_total_cost'],2) }}</td>

                            </tr>

                            <script type="text/javascript">


                                document.getElementById("test{{$i}}").onclick = function(){
                                    @php
                                        foreach ($stock_imeis as $val) {

                                            echo "window.open('".url("imei")."?imei=".$val."','_blank');
                                            ";
                                        }

                                    @endphp
                                }
                            </script>
                            {{-- @endif --}}
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2"><b>Total</b></td>
                            <td><b>{{ $total_quantity }}</b></td>
                            <td title="{{ amount_formatter($total_cost/$total_quantity,2) }}"><b>{{ amount_formatter($total_cost,2) }}</b></td>
                            <td><b>{{ $remaining_quantity }}</b></td>
                            <td><b>{{ amount_formatter($remaining_total_cost,2) }}</b></td>
                        </tr>
                    </tfoot>

                </table>
            </div>
        </div>
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
