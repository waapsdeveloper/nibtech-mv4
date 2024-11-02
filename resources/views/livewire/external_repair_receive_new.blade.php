@extends('layouts.new')

@section('styles')
<style type="text/css" media="print">
    @page { size: landscape; }
  </style>


@endsection

    @section('content')

    <div>
        <form action="{{ url('receive_repair_items')}}" method="POST" id="search" class="form-inline">
            @csrf

            <div class="form-floating">
                <input type="text" class="form-control" name="imei" id="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset" id="imeiInput" onload="this.focus()" autofocus>
                <label for="">IMEI</label>
            </div>

            <div class="form-floating">
                <input type="number" class="form-control form-control-sm" name="check_testing_days" placeholder="Days" value="{{session('check_testing_days')}}">
                <label for="">Tested __ Days Ago</label>
            </div>

            <input type="hidden" name="admin_id" value="{{request('admin_id')}}">
            <button class="btn btn-primary pd-x-20" type="submit">Exit</button>
        </form>

    </div>
    <script>

        window.onload = function() {
            document.getElementById('imei').focus();
        };
        document.addEventListener('DOMContentLoaded', function() {
            var input = document.getElementById('imei');
            input.focus();
            input.select();
        });
    </script>

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
@if (session('info'))
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
    <span class="alert-inner--text"><strong>{{session('info')}}</strong></span>
    <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
</div>
<br>
@php
session()->forget('info');
@endphp
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <span class="alert-inner--icon"><i class="fe fe-thumbs-down"></i></span>
        <span class="alert-inner--text"><strong>{{session('error')}}</strong></span>
        <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
    </div>
<br>
@php
session()->forget('error');
@endphp
@endif
@if (isset($processed_stocks))

    <div class="card">
        <div class="card-header pb-0">
            <div class="d-flex justify-content-between">
                <h4 class="card-title mg-b-0">Latest Scanned</h4>
            </div>
        </div>
        <div class="card-body"><div class="table-responsive">

            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                <thead>
                    <tr>
                        <th><small><b>No</b></small></th>
                        <th><small><b>Reference ID</b></small></th>
                        <th><small><b>Repairer</b></small></th>
                        <th><small><b>Price</b></small></th>
                        <th><small><b>IMEI</b></small></th>
                        <th><small><b>Status</b></small></th>
                        <th><small><b>Creation Date | TN</b></small></th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $i = 0;
                        $id = [];
                    @endphp
                    @foreach ($processed_stocks as $index => $p_stock)
                        @php
                            $process = $p_stock->process;
                            $j = 0;
                        @endphp

                            <tr>
                                <td title="{{ $p_stock->id }}">{{ $i + 1 }}</td>
                                <td><a href="{{url('repair/detail/'.$process->id)}}?status=1">{{ $process->reference_id }}</a></td>
                                <td>@if ($process->customer)
                                    {{ $process->customer->first_name." ".$process->customer->last_name }}
                                @endif</td>
                                <td>
                                    {{ $process->currency_id->sign.amount_formatter($p_stock->price,2) }}
                                </td>
                                <td style="width:240px" class="text-success text-uppercase" title="{{ $p_stock->stock_id }}" id="copy_imei_{{ $process->id }}">
                                    @isset($p_stock->stock->imei) {{ $p_stock->stock->imei }}&nbsp; @endisset
                                    @isset($p_stock->stock->serial_number) {{ $p_stock->stock->serial_number }}&nbsp; @endisset
                                    @isset($p_stock->admin_id) | {{ $p_stock->admin->first_name }} |
                                    @else
                                    @isset($process->processed_by) | {{ $process->admin->first_name }} | @endisset
                                    @endisset
                                </td>
                                <td>@if ($p_stock->status == 1)
                                    Sent
                                    @else
                                    Received
                                @endif</td>
                                <td style="width:220px">{{ $p_stock->created_at}} <br> {{ $process->tracking_number }}</td>
                            </tr>
                        @php
                            $i ++;
                        @endphp
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

    @endsection

    @section('scripts')

    @endsection
