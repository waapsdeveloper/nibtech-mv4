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
                @if ($process->status < 3)
                    <form class="form-inline" id="approveform" method="POST" action="{{url('topup/close').'/'.$process->id}}">
                        @csrf
                        <div class="form-floating">
                            <input type="number" class="form-control" id="quantity" name="quantity" placeholder="Enter Quantity" value="{{$process->quantity}}" required>
                            <label for="quantity">Batch Total Quantity</label>
                        </div>
                        <div class="form-floating">
                            <input type="text" class="form-control" id="description" name="description" placeholder="Enter Description" value="{{$process->description}}" required>
                            <label for="description">Description</label>
                        </div>
                        @if ($process->status == 1)
                            <button type="submit" class="btn btn-primary" name="approve" value="1">Send</button>
                        @elseif ($process->status == 2)
                            @if (session('user')->hasPermission('topup_push_without_verification'))

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="com" name="all" value="1">
                                <label class="form-check-label" for="com">Push Without Verification</label>
                            </div>

                            @endif
                            <button type="submit" class="btn btn-secondary" name="close" value="1">Close</button>
                            <button type="submit" class="btn btn-primary" name="push" value="1"
                             {{-- onclick="this.disabled=true;this.form.submit();" --}}
                             >Push & Close</button>

                        @endif
                        <a class="btn btn-danger" href="{{url('topup/delete') . "/" . $process->id }}" onclick="return confirm('Are you sure you want to delete this topup?');">Delete</a>
                        @if (session('user')->hasPermission('topup_list_stock'))
                            <a href="{{url('listing').'?process_id='.$process->id}}" class="btn btn-link">List Stock</a>
                        @endif
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
                    {{-- @if($process->status == 2) --}}


                @else
                    <br>
                    {{ $process->description }}



                    @if (session('user')->hasPermission('topup_revert_status'))
                        <br>
                        <a href="{{url('topup/revert_status').'/'.$process->id}}">Revert Back to Pending</a>
                    @endif

                @endif
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item tx-15"><a href="{{ session('previous')}}">Topup</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Topup Detail</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="d-flex justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">
            <h5>Reference: {{ $process->reference_id }} | Batch Quantity: {{ $process->quantity }} | Scanned Quantity: {{ $process->process_stocks->count() }}
                @if ($process->status > 1)
                    | Verified Quantity: {{ $process->process_stocks->where('status', 2)->count() }}
                @endif
                @if ($process->status == 3)
                    | Listed Quantity: {{ $process->listed_stocks_verification->sum('qty_change') }}
                @endif
            </h5>
        </div>

        <br>

        <div class="d-flex justify-content-between" style="border-bottom: 1px solid rgb(216, 212, 212);">

            <h4>Topup Details</h4>


            <div class="btn-group p-1" role="group">
                {{-- JS Print to Print topup Variations DIv --}}
                <button type="button" class="btn btn-primary" onclick="PrintElem('topup_variations');">Print</button>
                @if (request('show') == 1)
                    <a href="{{ url('topup/detail').'/'.$process->id }}" class="btn btn-secondary">Hide Topup</a>
                @else
                    <a href="{{ url('topup/detail').'/'.$process->id.'?show=1' }}" class="btn btn-secondary">Show Topup</a>
                @endif
            </div>
        </div>
        @if ($process->status == 1)

        <div class="p-2">


            <form class="form-inline" action="{{ url('topup/add_topup_item').'/'.$process->id }}" method="POST" id="">
                @csrf
                <div class="form-floating">
                    <input type="text" class="form-control form-control-sm" name="reference" id="reference" placeholder="Enter Reference" value="{{ session('reference') }}">
                    <label for="reference" class="">Reference: &nbsp;</label>
                </div>
                <div class="form-floating">
                    <input type="text" class="form-control" name="imei" id="imei" placeholder="Enter IMEI" onloadeddata="$(this).focus()" autofocus required>
                    <label for="imei" class="">IMEI | Serial Number: &nbsp;</label>

                </div>

                <select name="color" class="form-control form-select" style="width: 150px;">
                    <option value="">Color</option>
                    @foreach ($colors as $id => $name)
                        <option value="{{ $id }}"@if($id == session('color')) {{'selected'}}@endif>{{ $name }}</option>
                    @endforeach
                </select>
                <select name="grade" class="form-control form-select">
                    <option value="">Grade</option>
                    @foreach ($grades as $id => $name)
                        <option value="{{ $id }}" @if ($id == session('grade')) {{'selected'}}@endif>{{ $name }}</option>
                    @endforeach
                </select>

                <div>
                <div class="input-group form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="com" name="copy" value="1" @if (session('copy') == 1) {{'checked'}} @endif>&nbsp;&nbsp;
                    <label class="form-check-label" for="com">Copy Color</label>
                </div>
                <div class="input-group form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="com" name="copy_grade" value="1" @if (session('copy_grade') == 1) {{'checked'}} @endif>&nbsp;&nbsp;
                    <label class="form-check-label" for="com">Copy Grade</label>
                </div>
                <div class="input-group form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="com" name="dual-esim" value="1" @if (session('dual-esim') == 1) {{'checked'}} @endif>&nbsp;&nbsp;
                    <label class="form-check-label" for="com">Mark Dual eSIM</label>
                </div>
                <div class="input-group form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="com" name="dual-sim" value="1" @if (session('dual-sim') == 1) {{'checked'}} @endif>&nbsp;&nbsp;
                    <label class="form-check-label" for="com">Mark Dual Sim</label>
                </div>
                </div>
                <button class="btn btn-primary pd-x-20" type="submit">Insert</button>
            </form>
        </div>
        @elseif ($process->status == 2)

        <div class="p-2">


            <form class="form-inline" action="{{ url('topup/verify_topup_item').'/'.$process->id }}" method="POST" id="">
                @csrf
                <div class="form-floating">
                    <input type="text" class="form-control" name="imei" id="imei" placeholder="Enter IMEI" onloadeddata="$(this).focus()" autofocus required>
                    <label for="imei" class="">IMEI | Serial Number: &nbsp;</label>

                </div>

                <button class="btn btn-primary pd-x-20" type="submit">Verify</button>
            </form>
        </div>
        @endif
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
        @if ($process->status <= 2)

            <div class="card">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between">
                        <h4 class="card-title mg-b-0">Latest Scanned</h4>
                        <h4 class="card-title mg-b-0">Counter: {{ session('counter') }} <a href="{{ url('stock_room/reset_counter') }}">Reset</a></h4>

                        <h4 class="card-title mg-b-0">Total Scanned: {{$scanned_total}}</h4>
                        @if ($process->status == 2)
                            <h4 class="card-title mg-b-0">Total Verified: {{$verified_total}}</h4>

                        @endif
                        <form method="get" action="" class="row form-inline">
                            <label for="perPage" class="card-title inline">per page:</label>
                            <select name="per_page" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                <option value="5" {{ Request::get('per_page') == 5 ? 'selected' : '' }}>10</option>
                                <option value="10" {{ Request::get('per_page') == 10 ? 'selected' : '' }}>10</option>
                                <option value="20" {{ Request::get('per_page') == 20 ? 'selected' : '' }}>20</option>
                                <option value="50" {{ Request::get('per_page') == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ Request::get('per_page') == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </form>
                    </div>
                </div>
                <div class="card-body"><div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                        <thead>
                            <tr>
                                <th><small><b>No</b></small></th>
                                <th><small><b>SKU</b></small></th>
                                <th><small><b>Variation</b></small></th>
                                <th><small><b>IMEI | Serial Number</b></small></th>
                                <th><small><b>Reference</b></small></th>
                                <th><small><b>Operation</b></small></th>
                                <th><small><b>Vendor</b></small></th>
                                <th><small><b>Creation Date</b></small></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $i = 0;
                            @endphp
                            @foreach ($last_ten as $item)
                                <tr>
                                    @if ($item->stock == null)
                                        {{$item->stock_id}}
                                        @continue
                                    @endif
                                    @if ($item->stock->order == null)
                                        {{$item->stock_id}}
                                        @continue
                                    @endif
                                    @php
                                        $stock = $item->stock;
                                        $variation = $stock->variation;
                                        $customer = $stock->order->customer;
                                    @endphp
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $variation->sku ?? null }}</td>
                                    <td>
                                        {{ ($products[$variation->product_id] ?? "Product not found").' '.($storages[$variation->storage] ?? null).' '.($colors[$variation->color] ?? null).' '.($grades[$variation->grade] ?? "Grade not added") }} {{$grades[$variation->sub_grade] ?? '' }}
                                    </td>
                                    <td>{{ $stock->imei.$stock->serial_number }}</td>
                                    <td>{{ $item->description }}</td>
                                    <td>{{ $stock->latest_operation->description ?? null }}</td>
                                    <td>{{ $customer->first_name ?? "Purchase Entry Error" }}</td>
                                    <td style="width:220px">{{ $item->created_at }}</td>
                                    <td>
                                        @if (session('user')->hasPermission('delete_topup_item') && $process->status == 1)
                                            <a href="{{ url('topup/delete_topup_item').'/'.$item->id }}" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this item?');">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                                @php
                                    $i ++;
                                @endphp
                            @endforeach
                        </tbody>
                    </table>
                </div>
                </div>
            </div>
        @endif
        @if ($listed_stocks->count() > 0)
        <div class="card" id="print_inv">
            <div class="card-header pb-0 d-flex justify-content-between">
                <h4 class="card-title">Changed Stock</h4>
            </div>
            <div class="card-body"><div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                    <thead>
                        <tr>
                            <th><small><b>No</b></small></th>
                            <th><small><b>Veriation</b></small></th>
                            <th title="Pending Orders"><small><b>Orders</b></small></th>
                            <th title="Quantity Before"><small><b>Qty Bfr</b></small></th>
                            <th title="Quantity Added"><small><b>Qty Add</b></small></th>
                            <th title="Quantity After"><small><b>Qty Afr</b></small></th>
                            <th><small><b>Admin</b></small></th>
                            <th width="180"><small><b>Creation Date</b></small></th>
                            <th width="180"><small><b>Update Date</b></small></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $i = 0;

                            $total_pending_orders = 0;
                            $total_qty_from = 0;
                            $total_qty_change = 0;
                            $total_qty_to = 0;
                        @endphp
                        @foreach ($listed_stocks as $item)
                            @php
                                $variation = $item->variation;
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td title="{{ $variation->id }}">
                                    <a href="{{ url('listing').'?sku='.$variation->sku }}" target="_blank">
                                    {{ $products[$variation->product_id]}} {{$storages[$variation->storage] ?? null}} {{$colors[$variation->color] ?? null}} {{$grades[$variation->grade] ?? "Grade not added" }} {{$grades[$variation->sub_grade] ?? '' }}
                                    </a>
                                </td>
                                <td>{{ $item->pending_orders }}</td>
                                <td>{{ $item->qty_from }}</td>
                                <td>{{ $item->qty_change }}</td>
                                <td>{{ $item->qty_to }}</td>
                                <td>{{ $item->admin->first_name }}</td>
                                <td style="width:150px">{{ $item->created_at }}</td>
                                <td style="width:150px">{{ $item->updated_at }}</td>
                            </tr>
                            @php
                                $total_pending_orders += $item->pending_orders;
                                $total_qty_from += $item->qty_from;
                                $total_qty_change += $item->qty_change;
                                $total_qty_to += $item->qty_to;
                                $i ++;
                            @endphp
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="text-center"><b>Total</b></td>
                            <td><b>{{ $total_pending_orders }}</b></td>
                            <td><b>{{ $total_qty_from }}</b></td>
                            <td><b>{{ $total_qty_change }}</b></td>
                            <td><b>{{ $total_qty_to }}</b></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>

                </table>
            </div>
        </div>
        @endif
        @if (request('show') == 1)

        <div class="card" id="topup_variations">
            <div class="card-header d-flex justify-content-between pb-0">
                <h4 class="card-title mg-b-0">Topup Variations</h4>
                <h5 class="card-title mg-b-0">Created: {{ $process->created_at }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                        <thead>
                            <tr>
                                <th><small><b>No</b></small></th>
                                <th><small><b>SKU</b></small></th>
                                <th><small><b>Variation</b></small></th>
                                <th><small><b>Qty</b></small></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $i = 0;
                            @endphp
                            @foreach ($variations as $variation)
                                @php
                                    if($process->status = 2){
                                        // Check if any stock in this variation has status 1
                                        $has_status_1 = $stocks->where('variation_id', $variation->id)->contains(function($s) use ($process) {
                                            $ps = $s->process_stock($process->id);
                                            return $ps && $ps->status == 1;
                                        });
                                        if ($loop->first && $has_status_1) {
                                            echo `
                                            <script>
                                                document.addEventListener('DOMContentLoaded', function() {
                                                    var collapse = document.getElementById('stocks-{{ $variation->id }}');
                                                    if (collapse && !collapse.classList.contains('show')) {
                                                        new bootstrap.Collapse(collapse, {toggle: true});
                                                    }
                                                });
                                            </script>
                                            `;
                                        }
                                    }
                                @endphp
                                <tr @if ($variation->listed_stock < 0 && $variation->listed_stock + $stocks->where('variation_id', $variation->id)->count() < 0)
                                    class="bg-danger"
                                @endif>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <a href="{{ url('listing').'?sku='.$variation->sku.'&process_id='.$process->id }}" target="_blank">
                                        {{ $variation->sku ?? "Variation SKU Not added"}}
                                        </a>
                                        @if ($variation->listed_stock < 0 && $variation->listed_stock + $stocks->where('variation_id', $variation->id)->count() < 0)
                                            {{ $variation->listed_stock }}
                                        @endif
                                    </td>
                                    <td>{{ $products[$variation->product_id] ?? "Variation Model Not added"}} {{$storages[$variation->storage] ?? null}} {{$colors[$variation->color] ?? null}} {{$grades[$variation->grade] ?? "Variation Grade Not added" }}</td>
                                    <td>
                                        <a href="javascript:void(0);" data-bs-toggle="collapse" data-bs-target="#stocks-{{ $variation->id }}" aria-expanded="false" aria-controls="stocks-{{ $variation->id }}">
                                            {{ $stocks->where('variation_id', $variation->id)->count() }}
                                        </a>
                                    </td>
                                    <td></td>
                                </tr>
                                <tr class="collapse bg-lightgreen" id="stocks-{{ $variation->id }}">
                                    <td colspan="5">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th><small><b>#</b></small></th>
                                                    <th><small><b>IMEI | Serial Number</b></small></th>
                                                    <th><small><b>Operation</b></small></th>
                                                    <th><small><b>Creation Date</b></small></th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>

                                                @php
                                                    $j = 0;
                                                    if($process->status = 2){
                                                        // Check if any stock in this variation has status 1
                                                        $has_status_1 = $stocks->where('variation_id', $variation->id)->contains(function($s) use ($process) {
                                                            $ps = $s->process_stock($process->id);
                                                            return $ps && $ps->status == 1;
                                                        });
                                                        if ($loop->first && $has_status_1) {
                                                            echo `
                                                            <script>
                                                                document.addEventListener('DOMContentLoaded', function() {
                                                                    var collapse = document.getElementById('stocks-{{ $variation->id }}');
                                                                    if (collapse && !collapse.classList.contains('show')) {
                                                                        new bootstrap.Collapse(collapse, {toggle: true});
                                                                    }
                                                                });
                                                            </script>
                                                            `;
                                                        }
                                                    }
                                                @endphp
                                                @foreach ($stocks->where('variation_id', $variation->id) as $stock)
                                                    @php
                                                        $process_stock = $stock->process_stock($process->id);
                                                    @endphp
                                                    <tr
                                                        @if ($process->status == 2 && $process_stock->status == 1)
                                                            class="table-danger"
                                                        @endif
                                                    >
                                                        <td>{{ ++$j }}</td>
                                                        <td>{{ $stock->imei }}{{ $stock->serial_number }}</td>
                                                        <td>
                                                            {{ $stock->latest_operation->description ?? null }}
                                                        </td>
                                                        <td style="width:220px">{{ $process_stock->created_at }}</td>
                                                        <td>
                                                            @if (session('user')->hasPermission('delete_topup_item') && $process->status <= 2)
                                                                <a href="{{ url('topup/delete_topup_item').'/'.$stock->process_stock($process_id)->id }}" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this item?');">
                                                                    <i class="fa fa-trash"></i>
                                                                </a>

                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                                @php
                                    $i ++;
                                @endphp
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-center"><b>Total</b></td>
                                <td><b>{{ $stocks->count() }}</b></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>

                </div>
            </div>
        </div>
        @endif
    </div>
        <br>


    @endsection

    @section('scripts')

        <script>

            $(document).ready(function() {

                $('#sb_toggle').click();

            });
            function PrintElem(elem)
            {
                var mywindow = window.open('', 'PRINT', 'height=400,width=600');

                mywindow.document.write('<html><head>');
                mywindow.document.write(`<link rel="stylesheet" href="{{asset('assets/plugins/bootstrap/css/bootstrap.min.css')}}" type="text/css" />`);
                mywindow.document.write(`<link rel="stylesheet" href="{{asset('assets/css/style.css')}}" type="text/css" />`);
                mywindow.document.write('<title>' + document.title  + '</title></head><body >');
                mywindow.document.write(document.getElementById(elem).innerHTML);
                mywindow.document.write('</body></html>');

                mywindow.document.close(); // necessary for IE >= 10
                mywindow.focus(); // necessary for IE >= 10*/

                mywindow.print();
                mywindow.close();

                return true;
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
