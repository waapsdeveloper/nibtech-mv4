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
                        <th><small><b>#</b></small></th>
                        <th><small><b>Process</b></small></th>
                        <th><small><b>IMEI/Serial</b></small></th>
                        {{-- @if (session('user')->hasPermission('view_cost')) --}}
                        <th><small><b>Name</b></small></th>
                        {{-- @endif --}}
                        <th><small><b>Last Updated</b></small></th>

                        @if (session('user')->hasPermission('delete_repair_item'))
                        {{-- <th></th> --}}
                        @endif
                    </tr>
                </thead>
                <tbody>
                    {{-- <form method="POST" action="{{url('repair')}}/update_prices" id="update_prices_{{ $variation->id }}"> --}}
                        @csrf
                    @php
                        $i = 0;
                        $id = [];
                    @endphp
                    @php
                        // $items = $stocks->order_item;
                        $j = 0;
                        $total = 0;
                        // print_r($variation);
                    @endphp

                    @foreach ($processed_stocks as $processed_stock)
                        {{-- @dd($item->sale_item) --}}
                        @php
                            $item = $processed_stock->stock;
                            $variation = $item->variation;
                            $i ++;

                            isset($variation->product)?$product = $products[$variation->product_id]:$product = null;
                            isset($variation->storage)?$storage = $storages[$variation->storage]:$storage = null;
                            isset($variation->color)?$color = $colors[$variation->color]:$color = null;
                            isset($variation->grade)?$grade = $grades[$variation->grade]:$grade = null;
                            isset($variation->sub_grade)?$sub_grade = $grades[$variation->sub_grade]:$sub_grade = null;

                        @endphp
                        <tr>
                            <td>{{ $i }}</td>
                            <td>{{ $processed_stock->process->reference_id }}</td>
                            <td>{{ $item->imei.$item->serial_number }}</td>
                            <td>
                                {{ $product." ".$storage." ".$color." ".$grade." ".$sub_grade }}
                            </td>
                            <td>{{$processed_stock->updated_at}}</td>
                            @if (session('user')->hasPermission('delete_repair_item'))
                            {{-- <td><a href="{{ url('delete_repair_item').'/'.$item->process_stock($process_id)->id }}"><i class="fa fa-trash"></i></a></td> --}}
                            @endif
                        </tr>
                    @endforeach
                    </form>
                </tbody>
            </table>
        </div>
    </div>

    @endsection

    @section('scripts')

    @endsection
