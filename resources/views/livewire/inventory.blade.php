@extends('layouts.app')

    @section('styles')
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
<br>
    @section('content')
        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Inventory</li>
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

                <div class="col-md col-sm-2">
                    {{-- <div class="card-header">
                        <h4 class="card-title mb-1">Category</h4>
                    </div> --}}
                    <select name="category" class="form-control form-select" data-bs-placeholder="Select Category" onchange="selectCategory(this.value)">
                        <option value="">Category</option>
                        @foreach ($categories as $category)
                            <option value="{{$category->id}}" @if(isset($_GET['category']) && $category->id == $_GET['category']) {{'selected'}}@endif>{{$category->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md col-sm-2">
                    {{-- <div class="card-header">
                        <h4 class="card-title mb-1">Brand</h4>
                    </div> --}}
                    <select name="brand" class="form-control form-select" data-bs-placeholder="Select Brand" onchange="selectBrand(this.value)">
                        <option value="">Brand</option>
                        @foreach ($brands as $brand)
                            <option value="{{$brand->id}}" @if(isset($_GET['brand']) && $brand->id == $_GET['brand']) {{'selected'}}@endif>{{$brand->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md col-sm-3">
                    {{-- <div class="card-header">
                        <h4 class="card-title mb-1">Model</h4>
                    </div> --}}
                    <div class="form-floating">
                        <input type="text" name="product" value="{{ Request::get('product') }}" class="form-control" data-bs-placeholder="Select Model" list="product-menu">
                        <label for="product">Product</label>
                    </div>
                    <datalist id="product-menu">
                        <option value="">Select</option>
                        @foreach ($products as $id => $model)
                            <option value="{{ $id }}" @if(request('product') != null && $id == request('product')) {{'selected'}}@endif>{{ $model }}</option>
                        @endforeach
                    </datalist>
                </div>
                <div class="col-md col-sm-2">
                    {{-- <div class="card-header">
                        <h4 class="card-title mb-1">Storage</h4>
                    </div> --}}
                    <select name="storage" class="form-control form-select">
                        <option value="">Storage</option>
                        @foreach ($storages as $id=>$name)
                            <option value="{{ $id }}" @if(request('storage') != null && $id == request('storage')) {{'selected'}}@endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md col-sm-2">
                    {{-- <div class="card-header">
                        <h4 class="card-title mb-1">Storage</h4>
                    </div> --}}
                    <select name="color" class="form-control form-select">
                        <option value="">Color</option>
                        @foreach ($colors as $id=>$name)
                            <option value="{{ $id }}" @if(isset($_GET['color']) && $id == $_GET['color']) {{'selected'}}@endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-2">
                    {{-- <div class="card-header">
                        <h4 class="card-title mb-1">Grade</h4>
                    </div> --}}
                    <select name="grade[]" class="form-control form-select select2" multiple>
                        <option value="">Grade</option>
                        @foreach ($grades as $id=>$name)
                            <option value="{{ $id }}" @if(isset($_GET['grade']) && in_array($id,$_GET['grade'])) {{'selected'}}@endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md col-sm-2">
                    <select name="vendor" class="form-control form-select">
                        <option value="">Vendor</option>
                        @foreach ($vendors as $id=>$name)
                            <option value="{{ $id }}" @if(isset($_GET['vendor']) && $id == $_GET['vendor']) {{'selected'}}@endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary pd-x-20" type="submit">{{ __('locale.Search') }}</button>
                <a href="{{url('inventory')}}?per_page=10" class="btn btn-default pd-x-20">Reset</a>
                <button class="btn btn-primary pd-x-20" name="verify" value="1" type="submit">Verify</button>
            </div>

            <input type="hidden" name="replacement" value="{{ Request::get('replacement') }}">
            <input type="hidden" name="status" value="{{ Request::get('status') }}">
            <input type="hidden" name="page" value="{{ Request::get('page') }}">
            <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
            @if (request('summery') == 1)
                <input type="hidden" name="summery" value="1">

            @endif
        </form>
        <br>
        <script>
            @if (request('category'))
                let selectedCategoryId = {{ request('category') }};
                @if (request('brand'))
                    document.addEventListener('DOMContentLoaded', function() {
                        selectBrand({{ request('brand') }})
                    })
                    // @if (request('product'))
                    //     document.addEventListener('DOMContentLoaded', function() {
                    //         selectProduct({{ request('product') }})
                    //     })
                    // @endif
                @endif
            @else
                let selectedCategoryId = null;
            @endif

            // const colorData = {!! json_encode($colors) !!};
            // const storageData = {!! json_encode($storages) !!};
            // const gradeData = {!! json_encode($grades) !!};

            function selectCategory(categoryId) {
                selectedCategoryId = categoryId;
            }
            function selectBrand(brandId) {
                // Use the selectedCategoryId variable here to fetch stocks based on both category and brand
                if (selectedCategoryId !== null) {
                    fetch("{{ url('inventory') }}/get_products?category=" + selectedCategoryId + "&brand=" + brandId)
                        .then(response => response.json())
                        .then(products => {
                            const productMenu = document.getElementById('product-menu');
                            productMenu.innerHTML = '<option value="">Model</option>'; // Clear existing variation menu items

                            products.forEach(product => {
                                const productLink = document.createElement('option');
                                productLink.value = `${product.id}`;
                                productLink.innerHTML = `${product.model}`+' ('+`${product.quantity}`+')';
                                @if(request('product'))
                                    // Check if the request parameter matches the product's ID
                                    if (product.id == {{ request('product') }}) {
                                        productLink.selected = true; // Set the 'selected' attribute
                                    }
                                @endif
                                productMenu.appendChild(productLink);
                            });
                        })
                        .catch(error => console.error('Error fetching products:', error));
                } else {
                    console.error('Please select a category first.');
                }
            }
        </script>

        <div class="row">
            <div class="col-md-12" style="border-bottom: 1px solid rgb(216, 212, 212);">
                <center><h4>Inventory</h4></center>
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
        @if (session('user')->hasPermission('view_inventory_summery') && request('summery') != 1)
        @if (session('user')->hasPermission('view_cost') && $stocks->count() > 0)
            <div class="" id="vendor_wise_average">
                {{-- Vendor wise average:
                @foreach ($vendor_average_cost as $v_cost)
                    {{ $vendors[$v_cost->customer_id] ?? "Vendor Type Not Defined Correctly" }}:
                    {{ amount_formatter($v_cost->average_price,2) }} x
                    {{ $v_cost->total_qty }} =
                    {{ amount_formatter($v_cost->total_price,2) }} ({{amount_formatter($v_cost->total_qty/$stocks->total()*100,2)}}%) ||

                @endforeach --}}
            </div>
            @endif
        @endif
        <div class="d-flex justify-content-between">
            <div>

                <a href="{{url('inventory')}}?status=3&grade[]=1&grade[]=2&grade[]=3&grade[]=5&grade[]=7&grade[]=9" class="btn btn-link @if (request('status') == 3 && request('grade') == [1,2,3,5,7,9]) bg-white @endif ">RTG</a>
                <a href="{{url('inventory')}}?status=3" class="btn btn-link @if (request('status') == 3) bg-white @endif ">Active</a>
                <a href="{{url('inventory')}}?status=2" class="btn btn-link @if (request('status') == 2) bg-white @endif ">Pending</a>
                <a href="{{url('inventory')}}" class="btn btn-link @if (request('status') == null) bg-white @endif">All</a>
                @if (session('user')->hasPermission('view_inventory_summery'))
                <button class="btn btn-link  @if (request('summery') == 1) bg-white @endif" type="submit" form="summery">Summery</button>
                <form method="GET" action="" id="summery">
                    <input type="hidden" name="summery" value="1">
                    <input type="hidden" name="category" value="{{ Request::get('category') }}">
                    <input type="hidden" name="brand" value="{{ Request::get('brand') }}">
                    <input type="hidden" name="product" value="{{ Request::get('product') }}">
                    <input type="hidden" name="storage" value="{{ Request::get('storage') }}">
                    <input type="hidden" name="vendor" value="{{ Request::get('vendor') }}">
                    @if (Request::get('grade'))
                    @foreach (Request::get('grade') as $grd)

                        <input type="hidden" name="grade[]" value="{{ $grd }}">
                    @endforeach
                    @endif
                    <input type="hidden" name="status" value="{{ Request::get('status') }}">
                </form>
                @endif
            </div>

            @if (request('summery') != 1)
            @if ($active_inventory_verification != null)
                <div>
                    <form class="form-inline" action="{{ url('inventory/add_verification_imei').'/'.$active_inventory_verification->id }}" method="POST" id="wholesale_item">
                        @csrf
                        <label for="imei" class="">IMEI | Serial Number: &nbsp;</label>
                        <input type="text" class="form-control form-control-sm" name="imei" id="imei" placeholder="Enter IMEI" onloadeddata="$(this).focus()" autofocus required>
                        <button class="btn-sm btn-primary pd-x-20" type="submit">Insert</button>

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
                <a onclick="window.open('{{url('inventory/verification')}}','print_popup','width=1600,height=600');" class="btn btn-link">Verification Window</a>
            @endif
            <div class="">
                @if ($active_inventory_verification == null)
                <a class="btn btn-sm btn-secondary pd-x-20 " href="{{url('inventory/start_verification')}}">Start Inventory Verification</a>
                <a class="btn btn-sm btn-secondary pd-x-20 " href="{{url('inventory/resume_verification')}}">Resume Inventory Verification</a>

                <button class="btn btn-sm btn-secondary pd-x-20 " type="submit" form="export" name="inventorysheet" value="1">Export Sheet</button>

                @else

                <form action="{{ url('inventory/end_verification')}}" method="POST" class="form-inline">
                    @csrf
                    <input type="text" class="form-control form-control-sm" name="description" value="{{$active_inventory_verification->description}}" placeholder="Enter Reason" id="description" required>
                    <button class="btn btn-sm btn-primary pd-x-20" type="submit">End Verification</button>
                </form>

                @endif
            </div>
            @else

            <button class="btn btn-sm btn-secondary" id="print_btn" onclick="PrintElem('print_inv')"><i class="fa fa-print"></i></button>
            @endif
        </div>
        <form id="export" method="POST" target="_blank" action="{{url('inventory/export')}}">
            @csrf
            <input type="hidden" name="category" value="{{ Request::get('category') }}">
            <input type="hidden" name="brand" value="{{ Request::get('brand') }}">
            <input type="hidden" name="product" value="{{ Request::get('product') }}">
            <input type="hidden" name="storage" value="{{ Request::get('storage') }}">
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
        <br>


        @if (session('user')->hasPermission('view_inventory_summery') && request('summery') && request('summery') == 1)
        <div class="card" id="print_inv">
            <div class="card-header pb-0 d-flex justify-content-between">
                <h4 class="card-title">Available Stock Summery</h4>
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
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $i = 0;
                            $total_quantity = 0;
                            $total_cost = 0;
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
                        </tr>
                    </tfoot>

                </table>
            </div>
        </div>
        @else

        @if ($active_inventory_verification != null)
        <div class="row">
            <div class="col-xl-12">
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
            </div>
        </div>
        @endif
        <div class="row">
            <div @if ($active_inventory_verification == null)
                 class="col-xl-12"
                 @else
                 class="col-xl-9"
            @endif>
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h5 class="card-title mg-b-0">{{ __('locale.From') }} {{$stocks->firstItem()}} {{ __('locale.To') }} {{$stocks->lastItem()}} {{ __('locale.Out Of') }} {{$stocks->total()}} </h5>

                            @if (session('user')->hasPermission('view_cost'))
                            <h5 id="average_cost"></h5>
                            @endif
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
                                    <input type="hidden" name="replacement" value="{{ Request::get('replacement') }}">
                                    <input type="hidden" name="category" value="{{ Request::get('category') }}">
                                    <input type="hidden" name="brand" value="{{ Request::get('brand') }}">
                                    <input type="hidden" name="product" value="{{ Request::get('product') }}">
                                    <input type="hidden" name="storage" value="{{ Request::get('storage') }}">
                                    <input type="hidden" name="vendor" value="{{ Request::get('vendor') }}">
                                    @if (Request::get('grade'))
                                    @foreach (Request::get('grade') as $grd)

                                        <input type="hidden" name="grade[]" value="{{ $grd }}">
                                    @endforeach
                                    @endif
                                    <input type="hidden" name="page" value="{{ Request::get('page') }}">
                                    <input type="hidden" name="status" value="{{ Request::get('status') }}">
                                </form>
                            </div>

                        </div>
                    </div>
                    <div class="card-body"><div class="table-responsive">



                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                <thead>
                                    <tr>
                                        <th><small><b>No</b></small></th>
                                        <th><small><b>Product</b></small></th>
                                        <th><small><b>IMEI / Serial Number</b></small></th>
                                        <th><small><b>Vendor</b></small></th>
                                        <th><small><b>Reference</b></small></th>
                                        @if (session('user')->hasPermission('view_cost'))
                                        <th><small><b>Cost</b></small></th>
                                        @endif
                                        <th><small><b>Datetime</b></small></th>
                                        <th><small><b>Added By</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = $stocks->firstItem() - 1;
                                    @endphp
                                    @foreach ($stocks as $index => $stock)
                                        <tr>
                                            <td title="{{ $stock->id }}">{{ $i + 1 }}</td>
                                            <td><a title="Filter this variation" href="{{url('inventory').'?product='.$stock->variation->product_id.'&storage='.$stock->variation->storage.'&grade[]='.$stock->variation->grade}}">{{ $stock->variation->product->model . " " . (isset($stock->variation->storage) ? $storages[$stock->variation->storage] . " " : null) . " " .
                                            (isset($stock->variation->color) ? $colors[$stock->variation->color] . " " : null) . $grades[$stock->variation->grade] . (isset($stock->variation->sub_grade) ? " ".$grades[$stock->variation->sub_grade] : null) }} </a></td>
                                            <td><a title="{{$stock->id}} | Search Serial" href="{{url('imei')."?imei=".$stock->imei.$stock->serial_number}}" target="_blank"> {{$stock->imei.$stock->serial_number }} </a></td>
                                            <td><a title="Vendor Profile" href="{{url('edit-customer').'/'.$stock->order->customer_id}}" target="_blank"> {{ $stock->order->customer->first_name ?? null}} </a></td>
                                            <td>
                                                <a title="Purchase Order Details" href="{{url('purchase/detail').'/'.$stock->order_id}}?status=1" target="_blank"> {{ $stock->order->reference_id }} </a>
                                                @if ($stock->latest_return)
                                                 &nbsp;<a title="Sales Return Details" href="{{url('return/detail').'/'.$stock->latest_return->order->id}}" target="_blank"> {{ $stock->latest_return->order->reference_id }} </a>
                                                @endif
                                                @if ($stock->latest_repair)
                                                    &nbsp; {{ $stock->latest_repair->process->reference_id }}
                                                @endif
                                                @if ($stock->latest_verification)
                                                    &nbsp; {{ $stock->latest_verification->process->reference_id }}
                                                @endif
                                            </td>
                                            @if (session('user')->hasPermission('view_cost'))
                                            <td>{{ $stock->order->currency_id->sign ?? null }}{{$stock->purchase_item->price ?? null }}</td>
                                            @endif
                                            <td>{{ $stock->updated_at }}</td>
                                            @if ($stock->latest_operation)
                                            <td>{{ $stock->latest_operation->admin->first_name ?? null }}</td>
                                            <td>
                                                {{ $stock->latest_operation->description }}
                                            </td>
                                            @else
                                            <td>{{ $stock->admin->first_name ?? null }}</td>

                                            @endif
                                        </tr>

                                        @php
                                            $i ++;
                                        @endphp
                                    @endforeach
                                </tbody>
                            </table>
                        <br>
                        {{ $stocks->onEachSide(1)->links() }} {{ __('locale.From') }} {{$stocks->firstItem()}} {{ __('locale.To') }} {{$stocks->lastItem()}} {{ __('locale.Out Of') }} {{$stocks->total()}}
                    </div>

                    </div>
                </div>
            </div>
            @if ($active_inventory_verification != null)
                <div class="col-xl-3">
                    <div class="card">
                        <div class="card-header pb-0">
                            <div class="d-flex justify-content-between">
                                <h5 class="card-title mg-b-0">{{ __('locale.From') }} {{$verified_stocks->firstItem()}} {{ __('locale.To') }} {{$verified_stocks->lastItem()}} {{ __('locale.Out Of') }} {{$verified_stocks->total()}} </h5>
                            </div>
                        </div>
                        <div class="card-body"><div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                    <thead>
                                        <tr>
                                            <th><small><b>No</b></small></th>
                                            <th><small><b>IMEI / Serial Number</b></small></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $i = $verified_stocks->firstItem() - 1;
                                        @endphp
                                        @foreach ($verified_stocks as $index => $verified_stock)
                                            @php
                                                $stock = $verified_stock->stock;
                                            @endphp
                                            <tr>
                                                <td title="{{ $verified_stock->id }}">{{ $i + 1 }}</td>
                                                <td><a title="Search Serial {{ $stock->variation->product->model . " " . (isset($stock->variation->storage) ? $storages[$stock->variation->storage] . " " : null) . " " .
                                                    (isset($stock->variation->color) ? $colors[$stock->variation->color] . " " : null) . $stock->variation->grade_id->name . (isset($stock->variation->sub_grade) ? " ".$grades[$stock->variation->sub_grade] : null) }} " href="{{url('imei')."?imei=".$stock->imei.$stock->serial_number}}" target="_blank"> {{$stock->imei.$stock->serial_number }} </a></td>
                                            </tr>

                                            @php
                                                $i ++;
                                            @endphp
                                        @endforeach
                                    </tbody>
                                </table>
                            <br>
                            {{ $verified_stocks->onEachSide(1)->links() }} {{ __('locale.From') }} {{$verified_stocks->firstItem()}} {{ __('locale.To') }} {{$verified_stocks->lastItem()}} {{ __('locale.Out Of') }} {{$verified_stocks->total()}}
                        </div>

                        </div>
                    </div>

                </div>
            @endif
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

        @endif
    @endsection

    @section('scripts')
        <script>
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
            function get_average_cost(){
                let params = {
                    category: "{{ request('category') }}",
                    brand: "{{ request('brand') }}",
                    product: "{{ request('product') }}",
                    storage: "{{ request('storage') }}",
                    color: "{{ request('color') }}",
                    grade: "{{ json_encode(request('grade')) }}",
                    vendor: "{{ request('vendor') }}",
                    status: "{{ request('status') }}",
                }

                let queryString = $.param(params);
                $.ajax({
                    url: "{{url('api/internal/inventory_get_average_cost')}}?"+queryString,
                    type: 'GET',
                    success: function(data) {
                        console.log(data);
                        $('#average_cost').html('Average Cost: '+parseFloat(data.average_cost.average_price).toFixed(2)+' | Total Cost: '+parseFloat(data.average_cost.total_price).toFixed(2));
                        $('#average_cost').attr('title', 'Total Stocks: '+data.average_cost.total_qty);
                    }
                });
            }
            function get_vendor_wise_average(total_stocks = 0){
                vendors = {!! json_encode($vendors) !!};
                let params = {
                    category: "{{ request('category') }}",
                    brand: "{{ request('brand') }}",
                    product: "{{ request('product') }}",
                    storage: "{{ request('storage') }}",
                    color: "{{ request('color') }}",
                    grade: "{{ json_encode(request('grade')) }}",
                    vendor: "{{ request('vendor') }}",
                    status: "{{ request('status') }}",
                };

                let queryString = $.param(params);
                $.ajax({
                    url: "{{url('api/internal/inventory_get_vendor_wise_average')}}?"+queryString,
                    type: 'GET',
                    success: function(data) {
                        console.log(data);
                        let vendorWiseAverage = '';
                        data.vendor_average_cost.forEach(function(v_cost) {
                            vendor_name = vendors[v_cost.customer_id] ?? "Vendor Type Not Defined Correctly";
                            vendorWiseAverage += `${vendor_name}: ${parseFloat(v_cost.average_price).toFixed(2)} x ${v_cost.total_qty} = ${parseFloat(v_cost.total_price).toFixed(2)} (${parseFloat((v_cost.total_qty / total_stocks) * 100).toFixed(2)}%) || `;
                        });
                        $('#vendor_wise_average').html('Vendor wise average: ' + vendorWiseAverage);
                    },
                    error: function(xhr) {
                        console.error("Error fetching quantity:", xhr.responseText);
                    }
                });
            }

            $(document).ready(function(){
                $('.select2').select2();
                get_average_cost();

                let total_stocks = 0;

                @if (request('summery') != 1)
                    @if (session('user')->hasPermission('view_cost') && $stocks->count() > 0)
                        total_stocks = {{ $stocks->total() }};
                    @endif
                @endif

                get_vendor_wise_average(total_stocks);

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
