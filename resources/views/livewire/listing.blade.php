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
.form-floating>.form-control,
.form-floating>.form-control-plaintext,
.form-floating>.form-select {
  height: calc(2.3rem + 2px) !important;
}

        </style>
    @endsection
    @section('content')


        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                {{-- <span class="main-content-title mg-b-0 mg-b-lg-1">Purchase</span> --}}

                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Listings</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <br>
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
                    {{-- <div class="card-header">
                        <h4 class="card-title mb-1">Category</h4>
                    </div> --}}
                    <select name="category" class="form-control form-select" data-bs-placeholder="Select Category">
                        <option value="">Category</option>
                        @foreach ($categories as $category)
                            <option value="{{$category->id}}" @if(isset($_GET['category']) && $category->id == $_GET['category']) {{'selected'}}@endif>{{$category->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md col-sm-6">
                    {{-- <div class="card-header">
                        <h4 class="card-title mb-1">Brand</h4>
                    </div> --}}
                    <select name="brand" class="form-control form-select" data-bs-placeholder="Select Brand">
                        <option value="">Brand</option>
                        @foreach ($brands as $brand)
                            <option value="{{$brand->id}}" @if(isset($_GET['brand']) && $brand->id == $_GET['brand']) {{'selected'}}@endif>{{$brand->name}}</option>
                        @endforeach
                    </select>
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
                        @foreach ($colors as $id => $color)
                            <option value="{{$id}}" @if(isset($_GET['color']) && $id == $_GET['color']) {{'selected'}}@endif>{{$color}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md col-sm-6">
                    <select name="storage" class="form-control form-select" data-bs-placeholder="Select Status">
                        <option value="">Storage</option>
                        @foreach ($storages as $id => $storage)
                            <option value="{{$id}}" @if(isset($_GET['storage']) && $id == $_GET['storage']) {{'selected'}}@endif>{{$storage}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md col-sm-6">
                    <select name="grade" class="form-control form-select" data-bs-placeholder="Select Status">
                        <option value="">Grade</option>
                        @foreach ($grades as $id => $grade)
                            <option value="{{$id}}" @if(isset($_GET['grade']) && $id == $_GET['grade']) {{'selected'}}@endif>{{$grade}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md col-sm-6">
                    <select name="listed_stock" class="form-control form-select" data-bs-placeholder="Select listed Stock">
                        <option value="">Listed Stock</option>
                        <option value="1" @if(isset($_GET['listed_stock']) && $_GET['listed_stock'] == 1) {{'selected'}}@endif>With Listing</option>
                        <option value="2" @if(isset($_GET['listed_stock']) && $_GET['listed_stock'] == 2) {{'selected'}}@endif>Without Listing</option>
                    </select>
                </div>
                <div class="col-md col-sm-6">
                    <select name="available_stock" class="form-control form-select" data-bs-placeholder="Select Available Stock">
                        <option value="">Available Stock</option>
                        <option value="1" @if(isset($_GET['available_stock']) && $_GET['available_stock'] == 1) {{'selected'}}@endif>With Stock</option>
                        <option value="2" @if(isset($_GET['available_stock']) && $_GET['available_stock'] == 2) {{'selected'}}@endif>Without Stock</option>
                    </select>
                </div>
                <div class="col-md col-sm-6">
                    <select name="state" class="form-control form-select" data-bs-placeholder="Select Publication State">
                        <option value="">Publication State</option>
                        <option value="0" @if(isset($_GET['state']) && $_GET['state'] == 0) {{'selected'}}@endif>Missing price or comment</option>
                        <option value="1" @if(isset($_GET['state']) && $_GET['state'] == 1) {{'selected'}}@endif>Pending validation</option>
                        <option value="2" @if(isset($_GET['state']) && $_GET['state'] == 2) {{'selected'}}@endif>Online</option>
                        <option value="3" @if(isset($_GET['state']) && $_GET['state'] == 3) {{'selected'}}@endif>Offline</option>
                        <option value="4" @if(isset($_GET['state']) && $_GET['state'] == 4) {{'selected'}}@endif>Deactivated</option>
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

        @if (isset($variations) && (!request('status') || request('status') == 1))
        {{-- <div class="row"> --}}
            <h5 class="card-title mg-b-0">{{ __('locale.From') }} {{$variations->firstItem()}} {{ __('locale.To') }} {{$variations->lastItem()}} {{ __('locale.Out Of') }} {{$variations->total()}} </h5>

            @foreach ($variations as $variation)
            {{-- <div class="col-md-4"> --}}
                <div class="card">
                    <div class="card-header pb-0 d-flex justify-content-between">
                        <div>
                            @php
                                isset($variation->color_id)?$color = $variation->color_id->name:$color = null;
                                isset($variation->storage)?$storage = $storages[$variation->storage]:$storage = null;
                                if(isset($variation->grade) && array_key_exists($variation->grade, $grades)){
                                    $grade = $grades[$variation->grade];
                                }else{
                                    $grade = null;
                                }
                                $sku = str_replace('+','%2B',$variation->sku);
                            @endphp
                            <a href="https://www.backmarket.fr/bo_merchant/listings/active?sku={{ $sku }}" title="View BM Ad" target="_blank">
                            {{ $variation->sku." - ".$variation->product->model." ".$storage." ".$color." ".$grade }}
                            </a>
                        </div>
                        <div>
                            <div class="form-floating w-50">
                                <input type="number" class="form-control" name="stock" value="{{ $variation->variation_listing_qty->quantity ?? 0 }}">
                                <label for="">Stock</label>
                            </div>
                        </div>
                        <div>
                            Pending Order Items: {{ $variation->pending_orders->count() }}
                        </div>
                        <div>
                            status: {{ $variation->status }}
                        </div>
                    </div>
                            {{-- {{ $variation }} --}}
                    <div class="card-body row">
                        <div class="col-md-7">
                            <div class="table-responsive">
                            <table class="table table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>Country</b></small></th>
                                        @if (session('user')->hasPermission('view_price'))
                                        <th><small><b>BuyBox Price</b></small></th>
                                        <th width="150"><small><b>Min Price</b></small></th>
                                        <th width="150"><small><b>Price</b></small></th>
                                        <th><small><b>Max Price</b></small></th>
                                        @endif
                                        <th><small><b>Date</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 0;
                                        $id = [];
                                    @endphp
                                    @php
                                        $listings = $variation->listings;
                                        // $items = $stocks->order_item;
                                        // print_r($stocks);
                                        $min_prices = [];
                                        $prices = [];
                                    @endphp

                                    @foreach ($listings as $listing)
                                        {{-- @dd($item) --}}
                                        {{-- @if($item->order_item[0]->order_id == $order_id) --}}
                                        @php
                                        $sign = $listing->currency->sign;
                                    @endphp
                                        <tr @if ($listing->buybox != 1) style="background: pink;" @endif>
                                            <td title="{{$listing->id." ".$listing->country_id->title}}"><img src="{{ asset('assets/img/flags/').'/'.strtolower($listing->country_id->code).'.svg' }}" height="15"> {{ $listing->country_id->code }}</td>
                                            @if (session('user')->hasPermission('view_price'))
                                            <td>{{$sign.$listing->buybox_price}}</td>
                                            <td>
                                                <div class="form-floating">
                                                    <input type="number" class="form-control" name="min_price" value="{{$listing->min_price}}">
                                                    <label for="">Min Price ({{$sign}})</label>
                                                </div>
                                                @if ($listing->currency_id == 5 && count($min_prices) > 0)
                                                    Minimum: £{{number_format(min($min_prices)*$eur_gbp,2)}}
                                                @else
                                                    @php
                                                    if($listing->min_price > 0){
                                                    $min_prices[] = $listing->min_price;
                                                    }
                                                    @endphp
                                                @endif
                                            </td>
                                            <td>
                                                <div class="form-floating">
                                                    <input type="number" class="form-control" name="price" value="{{$listing->price}}">
                                                    <label for="">Price ({{$sign}})</label>
                                                </div>
                                                @if ($listing->currency_id == 5 && count($prices)>0)
                                                    Minimum: £{{number_format(min($prices)*$eur_gbp,2)}}
                                                @else
                                                    @php
                                                    if($listing->price > 0){
                                                    $prices[] = $listing->price;
                                                    }
                                                    @endphp
                                                @endif
                                            </td>
                                            <td>{{$sign.$listing->max_price}}</td>
                                            {{-- <td>{{ $currency}}{{$item->purchase_item->price ?? "Error in Purchase Entry" }}</td> --}}
                                            @endif
                                            <td>{{ $listing->updated_at }}</td>
                                        </tr>
                                        {{-- @endif --}}
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>No</b></small></th>
                                        <th><small><b>IMEI/Serial</b></small></th>
                                        @if (session('user')->hasPermission('view_cost'))
                                        <th><small><b>Cost</b></small></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 0;
                                        $id = [];
                                    @endphp
                                    @php
                                        $stocks = $variation->available_stocks;
                                        // $items = $stocks->order_item;
                                        $j = 0;
                                        $prices = [];
                                        // print_r($stocks);
                                    @endphp

                                    @foreach ($stocks as $item)
                                        {{-- @dd($item) --}}
                                        {{-- @if($item->order_item[0]->order_id == $order_id) --}}
                                        @php
                                        $i ++;
                                        $prices[] = $item->purchase_item->price ?? 0;
                                    @endphp
                                        <tr>
                                            <td>{{ $i }}</td>
                                            <td data-stock="{{ $item->id }}"><a href="{{ url('imei?imei=').$item->imei.$item->serial_number }}" target="_blank">{{ $item->imei.$item->serial_number }}</a></td>
                                            @if (session('user')->hasPermission('view_cost'))
                                            <td>€{{$item->purchase_item->price ?? "Error in Purchase Entry" }}</td>
                                            @endif
                                        </tr>
                                        {{-- @endif --}}
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        </div>
                        <br>
                    {{-- <div class="text-end">Average Cost: {{array_sum($prices)/count($prices) }} &nbsp;&nbsp;&nbsp; Total: {{$i }}</div> --}}
                    </div>
                </div>
            {{-- </div> --}}
            @endforeach
            {{ $variations->onEachSide(1)->links() }} {{ __('locale.From') }} {{$variations->firstItem()}} {{ __('locale.To') }} {{$variations->lastItem()}} {{ __('locale.Out Of') }} {{$variations->total()}}
            {{-- </div> --}}
        @endif
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
