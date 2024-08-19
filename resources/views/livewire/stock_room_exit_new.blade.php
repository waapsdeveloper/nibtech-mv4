@extends('layouts.new')

@section('styles')
<style type="text/css" media="print">
    @page { size: landscape; }
  </style>


@endsection

    @section('content')

    <div>
        {{-- <form class="form-inline" action="{{ url('stock_room/exit') }}" method="POST" id="">
            @csrf
            <label for="imei" class="">IMEI | Serial Number: &nbsp;</label>
            <input type="text" class="form-control form-control-sm" name="imei" id="imei" placeholder="Enter IMEI" onloadeddata="$(this).focus()" autofocus required>
            <button class="btn-sm btn-primary pd-x-20" type="submit">Exit</button>

        </form> --}}
        <form action="{{ url('stock_room/exit')}}" method="POST" id="search" class="form-inline">
            @csrf
            <div class="form-floating">
                <input type="text" class="form-control" name="imei" id="imei" placeholder="Enter IMEI" value="@isset($_GET['imei']){{$_GET['imei']}}@endisset" id="imeiInput" onload="this.focus()" autofocus>
                <label for="">IMEI</label>
            </div>
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
    <div class="card">
        <div class="card-header pb-0">
            <div class="d-flex justify-content-between">
                <h4 class="card-title mg-b-0">Latest Scanned</h4>
                <h4 class="card-title mg-b-0">Counter: {{ session('counter') }} <a href="{{ url('inventory/resume_verification?reset_counter=1') }}">Reset</a></h4>

                <h4 class="card-title mg-b-0">Total Scanned: {{$scanned_total}}</h4>
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
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $item->stock->variation->product->model ?? "Variation Model Not added"}} {{$storages[$item->stock->variation->storage] ?? null}} {{$colors[$item->stock->variation->color] ?? null}} {{$grades[$item->stock->variation->grade] ?? "Variation Grade Not added Reference: ".$item->stock->variation->reference_id }}</td>
                                <td>{{ $item->stock->imei.$item->stock->serial_number }}</td>
                                <td>{{ $item->stock->order->customer->first_name ?? "Purchase Entry Error" }}</td>
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

    @endsection

    @section('scripts')

    @endsection
