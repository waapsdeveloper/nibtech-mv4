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
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">products</span> --}}
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Product Variation</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Search</h4></center>
            </div>
        </div>
        <br>
        <form action="" method="GET" id="search">
            <div class="row">
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="reference_id" name="reference_id" placeholder="Enter IMEI" value="@isset($_GET['reference_id']){{$_GET['reference_id']}}@endisset">
                        <label for="reference_id">Reference ID</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input type="text" id="product" name="product" list="products" class="form-control" data-bs-placeholder="Select Status" value="@isset($_GET['product']){{$_GET['product']}}@endisset">
                        <label for="product">Product</label>
                    </div>
                        <datalist id="products">
                            @foreach ($products as $product)
                                <option value="{{$product->id}}" @if(isset($_GET['product']) && $product->id == $_GET['product']) {{'selected'}}@endif>{{$product->model}}</option>
                            @endforeach
                        </datalist>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-floating">
                        <input type="text" class="form-control" name="sku" placeholder="Enter IMEI" value="@isset($_GET['sku']){{$_GET['sku']}}@endisset">
                        <label for="">SKU</label>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <select name="color" class="form-control form-select" data-bs-placeholder="Select Status">
                        <option value="">Color</option>
                        @foreach ($colors as $color)
                            <option value="{{$color->id}}" @if(isset($_GET['color']) && $color->id == $_GET['color']) {{'selected'}}@endif>{{$color->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md col-sm-6">
                    <select name="storage" class="form-control form-select" data-bs-placeholder="Select Status">
                        <option value="">Storage</option>
                        @foreach ($storages as $storage)
                            <option value="{{$storage->id}}" @if(isset($_GET['storage']) && $storage->id == $_GET['storage']) {{'selected'}}@endif>{{$storage->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md col-sm-6">
                    <select name="grade" class="form-control form-select" data-bs-placeholder="Select Status">
                        <option value="">Grade</option>
                        @foreach ($grades as $grade)
                            <option value="{{$grade->id}}" @if(isset($_GET['grade']) && $grade->id == $_GET['grade']) {{'selected'}}@endif>{{$grade->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md col-sm-6">
                    <select name="duplicate" class="form-control form-select" data-bs-placeholder="Select Status">
                        <option value="">duplicate</option>
                        <option value="1">Show</option>
                    </select>
                </div>
                <div class="">
                    <button class="btn btn-primary pd-x-20" type="submit">{{ __('locale.Search') }}</button>
                    <a href="{{url(session('url').'order')}}?per_page=10" class="btn btn-default pd-x-20">Reset</a>
                </div>
            </div>

            <input type="hidden" name="page" value="{{ Request::get('page') }}">
            <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
            <input type="hidden" name="sort" value="{{ Request::get('sort') }}">
        </form>
        <br>
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Product Variations</h4></center>
            </div>
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
                            <h4 class="card-title mg-b-0">
                            </h4>
                            @php
                                // if(request('duplicate')){
                                //     $variations = $variations->whereHas('duplicate');
                                // }
                            @endphp
                            <h5 class="card-title mg-b-0">{{ __('locale.From') }} {{$variations->firstItem()}} {{ __('locale.To') }} {{$variations->lastItem()}} {{ __('locale.Out Of') }} {{$variations->total()}} </h5>

                            <div class=" mg-b-0">
                                <form method="get" action="" class="row form-inline">
                                    <label for="perPage" class="card-title inline">per page:</label>
                                    <select name="per_page" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                        <option value="10" {{ Request::get('per_page') == 10 ? 'selected' : '' }}>10</option>
                                        <option value="20" {{ Request::get('per_page') == 20 ? 'selected' : '' }}>20</option>
                                        <option value="50" {{ Request::get('per_page') == 50 ? 'selected' : '' }}>50</option>
                                        <option value="100" {{ Request::get('per_page') == 100 ? 'selected' : '' }}>100</option>
                                    </select>
                                    {{-- <button type="submit">Apply</button> --}}
                                    <input type="hidden" name="start_date" value="{{ Request::get('start_date') }}">
                                    <input type="hidden" name="end_date" value="{{ Request::get('end_date') }}">
                                    <input type="hidden" name="status" value="{{ Request::get('status') }}">
                                    <input type="hidden" name="order_id" value="{{ Request::get('order_id') }}">
                                    <input type="hidden" name="sku" value="{{ Request::get('sku') }}">
                                    <input type="hidden" name="imei" value="{{ Request::get('imei') }}">
                                    <input type="hidden" name="page" value="{{ Request::get('page') }}">
                                    <input type="hidden" name="sort" value="{{ Request::get('sort') }}">
                                </form>
                            </div>

                        </div>
                    </div>
                    <div class="card-body"><div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>No</b></small></th>
                                        <th><small><b>Reference ID</b></small></th>
                                        <th><small><b>Product</b></small></th>
                                        <th><small><b>Name</b></small></th>
                                        <th><small><b>SKU</b></small></th>
                                        <th><small><b>Color</b></small></th>
                                        <th><small><b>Storage</b></small></th>
                                        <th><small><b>Grade</b></small></th>
                                        <th><small><b>Stock</b></small></th>
                                        <th><small><b>Price</b></small></th>
                                        <th><small><b>Datetime</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php

                                        $i = $variations->firstItem() - 1;
                                    @endphp
                                    @foreach ($variations as $index => $product)
                                        <form method="post" action="{{url(session('url').'variation/update_product')}}/{{ $product->id }}" class="row form-inline">
                                            @csrf
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $product->reference_id }}</td>
                                            <td>
                                                <select name="update[product_id]" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                                    <option value="">None</option>
                                                    @foreach ($products as $prod)
                                                        <option value="{{ $prod->id }}" {{ $product->product_id == $prod->id ? 'selected' : '' }}>{{ $prod->series." ".$prod->model }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>{{ $product->name }}</td>
                                            <td>{{ $product->sku }}</td>
                                            <td>
                                                <select name="update[color]" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                                    <option value="">None</option>
                                                    @foreach ($colors as $color)
                                                        <option value="{{ $color->id }}" {{ $product->color == $color->id ? 'selected' : '' }}>{{ $color->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="update[storage]" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                                    <option value="">None</option>
                                                    @foreach ($storages as $storage)
                                                        <option value="{{ $storage->id }}" {{ $product->storage == $storage->id ? 'selected' : '' }}>{{ $storage->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="update[grade]" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                                    <option value="">None</option>
                                                    @foreach ($grades as $grade)
                                                        <option value="{{ $grade->id }}" {{ $product->grade == $grade->id ? 'selected' : '' }}>{{ $grade->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>{{ $product->stock }}</td>
                                            <td>{{ $product->price }}</td>
                                            <td>{{ $product->updated_at }}</td>
                                        </tr>
                                        </form>

                                        @php
                                            $i ++;
                                        @endphp
                                    @endforeach
                                </tbody>
                            </table>
                        <br>
                        {{ $variations->onEachSide(1)->links() }} {{ __('locale.From') }} {{$variations->firstItem()}} {{ __('locale.To') }} {{$variations->lastItem()}} {{ __('locale.Out Of') }} {{$variations->total()}}
                    </div>

                    </div>
                </div>
            </div>
        </div>
    @endsection

    @section('scripts')
		<!--Internal Sparkline js -->
		<script src="{{asset('assets/plugins/jquery-sparkline/jquery.sparkline.min.js')}}"></script>

		<!-- Internal Piety js -->
		<script src="{{asset('assets/plugins/peity/jquery.peity.min.js')}}"></script>

		<!-- Internal Chart js -->
		<script src="{{asset('assets/plugins/chartjs/Chart.bundle.min.js')}}"></script>

    @endsection
