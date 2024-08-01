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
                <a href="javascript:void(0);" class="btn btn-success float-right" data-bs-target="#modaldemo" data-bs-toggle="modal">
                    <i class="mdi mdi-plus"></i> Add Product </a>
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Products</li>
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

                <div class="col-lg-4 col-xl-4 col-md-4 col-sm-4">
                    <div class="card-header">
                        <h4 class="card-title mb-1">Category</h4>
                    </div>
                    <select name="category" class="form-control form-select select2" data-bs-placeholder="Select Category">
                        <option value="">Select</option>
                        @foreach ($categories as $category)
                            <option value="{{$category->id}}" @if(isset($_GET['category']) && $category->id == $_GET['category']) {{'selected'}}@endif>{{$category->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-4 col-xl-4 col-md-4 col-sm-4">
                    <div class="card-header">
                        <h4 class="card-title mb-1">Brand</h4>
                    </div>
                    <select name="brand" class="form-control form-select select2" data-bs-placeholder="Select Brand">
                        <option value="">Select</option>
                        @foreach ($brands as $brand)
                            <option value="{{$brand->id}}" @if(isset($_GET['brand']) && $brand->id == $_GET['brand']) {{'selected'}}@endif>{{$brand->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-4 col-xl-4 col-md-4 col-sm-4">
                    <div class="card-header">
                        <h4 class="card-title mb-1">Model</h4>
                    </div>
                    <input type="text" class="form-control" name="model" placeholder="Enter Model" value="@isset($_GET['model']){{$_GET['model']}}@endisset">
                </div>
            </div>
            <div class=" p-2">
                <button class="btn btn-primary pd-x-20" type="submit">{{ __('locale.Search') }}</button>
                <a href="{{url('product')}}?per_page=10" class="btn btn-default pd-x-20">Reset</a>
            </div>

            <input type="hidden" name="page" value="{{ Request::get('page') }}">
            <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
            <input type="hidden" name="sort" value="{{ Request::get('sort') }}">
        </form>
        <br>
        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Products</h4></center>
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
                            <h5 class="card-title mg-b-0">{{ __('locale.From') }} {{$products->firstItem()}} {{ __('locale.To') }} {{$products->lastItem()}} {{ __('locale.Out Of') }} {{$products->total()}} </h5>

                            <div class=" mg-b-0">
                                <form method="get" action="" class="row form-inline">
                                    <label for="perPage" class="card-title inline">Sort:</label>
                                    <select name="sort" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                        <option value="1" {{ Request::get('sort') == 1 ? 'selected' : '' }}>Product Name ASC</option>
                                        <option value="2" {{ Request::get('sort') == 2 ? 'selected' : '' }}>Product Name DESC</option>
                                    </select>
                                    {{-- <button type="submit">Apply</button> --}}
                                    <input type="hidden" name="category" value="{{ Request::get('category') }}">
                                    <input type="hidden" name="brand" value="{{ Request::get('brand') }}">
                                    <input type="hidden" name="model" value="{{ Request::get('model') }}">
                                    <input type="hidden" name="page" value="{{ Request::get('page') }}">
                                    <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
                                </form>
                                <form method="get" action="" class="row form-inline">
                                    <label for="perPage" class="card-title inline">per page:</label>
                                    <select name="per_page" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                        <option value="10" {{ Request::get('per_page') == 10 ? 'selected' : '' }}>10</option>
                                        <option value="20" {{ Request::get('per_page') == 20 ? 'selected' : '' }}>20</option>
                                        <option value="50" {{ Request::get('per_page') == 50 ? 'selected' : '' }}>50</option>
                                        <option value="100" {{ Request::get('per_page') == 100 ? 'selected' : '' }}>100</option>
                                    </select>
                                    {{-- <button type="submit">Apply</button> --}}
                                    <input type="hidden" name="category" value="{{ Request::get('category') }}">
                                    <input type="hidden" name="brand" value="{{ Request::get('brand') }}">
                                    <input type="hidden" name="model" value="{{ Request::get('model') }}">
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
                                        <th><small><b>Category</b></small></th>
                                        <th><small><b>Brand</b></small></th>
                                        <th><small><b>Model</b></small></th>
                                        <th><small><b>Datetime</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = $products->firstItem() - 1;
                                    @endphp
                                    @foreach ($products as $index => $product)
                                        <form method="post" action="{{url('product/update_product')}}/{{ $product->id }}" class="row form-inline">
                                            @csrf
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>
                                                <select name="update[category]" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                                    <option value="">None</option>
                                                    @foreach ($categories as $cat)
                                                        <option value="{{ $cat->id }}" {{ $product->category == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select name="update[brand]" class="form-select form-select-sm" id="perPage" onchange="this.form.submit()">
                                                    <option value="">None</option>
                                                    @foreach ($brands as $bran)
                                                        <option value="{{ $bran->id }}" {{ $product->brand == $bran->id ? 'selected' : '' }}>{{ $bran->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td><input type="text" value="{{ $product->model }}" name="update[model]" class="form-control form-control-sm"></td>
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
                        {{ $products->onEachSide(1)->links() }} {{ __('locale.From') }} {{$products->firstItem()}} {{ __('locale.To') }} {{$products->lastItem()}} {{ __('locale.Out Of') }} {{$products->total()}}
                    </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="modal" id="modaldemo">
            <div class="modal-dialog wd-xl-400" role="document">
                <div class="modal-content">
                    <div class="modal-body pd-sm-40">
                        <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal"
                            type="button"><span aria-hidden="true">&times;</span></button>
                        <h5 class="modal-title mg-b-5">Add Product</h5>
                        <hr>
                        <form action="{{ url('add_product') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <label for="">Category</label>
                                <select class="form-select" placeholder="Input Category" name="product[category]" required>
                                    <option>Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>

                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="">Brand</label>
                                <select class="form-select" placeholder="Input Brand" name="product[brand]" required>
                                    <option>Select Brand</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>

                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="">Model</label>
                                <input class="form-control" placeholder="Input Model" name="product[model]" type="text" required>
                            </div>
                            <div class="form-group">
                                <label for="">Description</label>
                                <textarea class="form-control" placeholder="Input Description" name="product[description]"></textarea>
                            </div>
                            <button class="btn btn-primary btn-block">{{ __('locale.Submit') }}</button>
                        </form>
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
