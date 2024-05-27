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
                        <li class="breadcrumb-item active" aria-current="page">Belfast Inventory</li>
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
                    </datalist>
                </div>
                <div class="col-md col-sm-2">
                    {{-- <div class="card-header">
                        <h4 class="card-title mb-1">Storage</h4>
                    </div> --}}
                    <select name="storage" class="form-control form-select">
                        <option value="">Storage</option>
                        @foreach ($storages as $id=>$name)
                            <option value="{{ $id }}" @if(isset($_GET['storage']) && $id == $_GET['storage']) {{'selected'}}@endif>{{ $name }}</option>
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
                <a href="{{url(session('url').'inventory')}}?per_page=10" class="btn btn-default pd-x-20">Reset</a>
            </div>

            <input type="hidden" name="status" value="{{ Request::get('status') }}">
            <input type="hidden" name="page" value="{{ Request::get('page') }}">
            <input type="hidden" name="per_page" value="{{ Request::get('per_page') }}">
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
                    fetch("{{ url(session('url').'inventory') }}/get_products?category=" + selectedCategoryId + "&brand=" + brandId)
                        .then(response => response.json())
                        .then(products => {
                            const productMenu = document.getElementById('product-menu');
                            productMenu.innerHTML = '<option value="">Model</option>'; // Clear existing variation menu items

                            products.forEach(product => {
                                const productLink = document.createElement('option');
                                productLink.value = `${product.id}`;
                                productLink.innerHTML = `${product.model}`;
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
                <center><h4>Belfast Inventory</h4></center>
            </div>
        </div>
        <br>

        <div class="d-flex justify-content-between">
            <div>
                <a href="{{url('belfast_inventory')}}?status=1" class="btn btn-link">Active</a>
                <a href="{{url('belfast_inventory')}}?status=2" class="btn btn-link">AfterSale</a>
                <a href="{{url('belfast_inventory')}}" class="btn btn-link">All</a>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-xl-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="d-flex justify-content-between">
                            <h5 class="card-title mg-b-0">{{ __('locale.From') }} {{$stocks->firstItem()}} {{ __('locale.To') }} {{$stocks->lastItem()}} {{ __('locale.Out Of') }} {{$stocks->total()}} </h5>

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
                                    <input type="hidden" name="category" value="{{ Request::get('category') }}">
                                    <input type="hidden" name="brand" value="{{ Request::get('brand') }}">
                                    <input type="hidden" name="product" value="{{ Request::get('product') }}">
                                    <input type="hidden" name="storage" value="{{ Request::get('storage') }}">
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
                                        <th><small><b>IMEI / Serial </b></small></th>
                                        <th><small><b>Vendor</b></small></th>
                                        <th><small><b>Order</b></small></th>
                                        @if (session('user')->hasPermission('view_cost'))
                                        <th><small><b>Cost</b></small></th>
                                        @endif
                                        <th><small><b>Operator</b></small></th>
                                        <th><small><b>Reason</b></small></th>
                                        <th><small><b>Datetime</b></small></th>
                                        <th><small><b>Action</b></small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = $stocks->firstItem() - 1;
                                    @endphp
                                    @foreach ($stocks as $index => $stock)

                                        @php
                                            $item = $stock->last_item();
                                        @endphp
                                        <tr>
                                            <td title="{{ $stock->id }}">{{ $i + 1 }}</td>
                                            <td><a title="Filter this variation" href="{{url('inventory').'?product='.$stock->variation->product_id.'&storage='.$stock->variation->storage.'&grade[]='.$stock->variation->grade}}">{{ $stock->variation->product->model . " " . (isset($stock->variation->color_id) ? $stock->variation->color_id->name . " " : null) .
                                                (isset($stock->variation->storage_id) ? $stock->variation->storage_id->name . " " : null) . " " . $stock->variation->grade_id->name }} </a></td>
                                            <td><a title="Search Serial" href="{{url('imei')."?imei=".$stock->imei.$stock->serial_number}}" target="_blank"> {{$stock->imei.$stock->serial_number }} </a></td>
                                            <td><a title="Vendor Profile" href="{{url('edit-customer').'/'.$stock->order->customer_id}}" target="_blank"> {{ $stock->order->customer->first_name ?? null}} </a> <br> <a title="Purchase Order Details" href="{{url('purchase/detail').'/'.$stock->order_id}}" target="_blank"> {{ $stock->order->reference_id }} </a></td>
                                            <td><a href="https://www.backmarket.fr/bo_merchant/orders/all?orderId={{ $item->order->reference_id }}" target="_blank">{{ $item->order->reference_id }}</a></td>
                                            @if (session('user')->hasPermission('view_cost'))
                                            <td>{{ $stock->order->currency_id->sign ?? null }}{{$stock->purchase_item->price ?? null }}</td>
                                            @endif
                                            @if ($stock->latest_operation)
                                            <td>{{ $stock->latest_operation->admin->first_name }}</td>
                                            <td> {{ $stock->latest_operation->description }} </td>
                                            <td>{{ $stock->latest_operation->updated_at }}</td>
                                            @else
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            @endif
                                            <td>
                                                {{-- <form action="{{ url('belfast_inventory/aftersale_action').'/'.$stock->id }}" method="POST" id="aftersale_action" class="form-inline">
                                                    <div class="form-floating">
                                                        <input type="text" class="form-control" name="description" placeholder="Enter Reason">
                                                        <label for="description">Reason</label>
                                                    </div>
                                                    <select name="send" class="form-control form-select">
                                                        <option value="">Send to</option>
                                                        <option value="resend">Customer</option>
                                                        <option value="aftersale_repair">Aftersale Repair</option>
                                                        <option value="return">Return Batch</option>
                                                        <option value="rma">RMA</option>
                                                    </select>
                                                    <button class="btn btn-secondary">Send</button>
                                                </form> --}}

                                                <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fe fe-more-vertical tx-18"></i></a>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" id="action_{{ $stock->id }}" href="javascript:void(0);"
                                                        data-bs-target="#action_model"
                                                        data-bs-toggle="modal"
                                                        data-bs-reference="Send Back to Customer"
                                                        data-bs-product="{{ $item->variation->product_id }}"
                                                        data-bs-storage="{{ $item->variation->storage }}"
                                                        data-bs-color="{{ $item->variation->color }}"
                                                        data-bs-grade="{{ $item->variation->grade }}"
                                                        data-bs-reference_id="{{ $item->order->reference_id }}"
                                                        data-bs-stock_id="{{ $stock->id }}"
                                                        data-bs-price="{{ $item->price }}"
                                                        data-bs-linked_id="{{ $item->id }}"
                                                        data-bs-action="{{ url('belfast_inventory/aftersale_action').'/'.$stock->id.'/resend' }}"
                                                        > Send Back to Customer </a>

                                                    <a class="dropdown-item" id="action_{{ $stock->id }}" href="javascript:void(0);"
                                                        data-bs-target="#action_model"
                                                        data-bs-toggle="modal"
                                                        data-bs-reference="Send for Aftersale Repair"
                                                        data-bs-product="{{ $item->variation->product_id }}"
                                                        data-bs-storage="{{ $item->variation->storage }}"
                                                        data-bs-color="{{ $item->variation->color }}"
                                                        data-bs-grade="8"
                                                        data-bs-reference_id="{{ $item->order->reference_id }}"
                                                        data-bs-stock_id="{{ $stock->id }}"
                                                        data-bs-price="{{ $item->price }}"
                                                        data-bs-linked_id="{{ $item->id }}"
                                                        data-bs-action="{{ url('belfast_inventory/aftersale_action').'/'.$stock->id.'/aftersale_repair' }}"
                                                        > Send for Aftersale Repair </a>

                                                    <a class="dropdown-item" id="action_{{ $stock->id }}" href="javascript:void(0);"
                                                        data-bs-target="#action_model"
                                                        data-bs-toggle="modal"
                                                        data-bs-reference="Return as RMA"
                                                        data-bs-product="{{ $item->variation->product_id }}"
                                                        data-bs-storage="{{ $item->variation->storage }}"
                                                        data-bs-color="{{ $item->variation->color }}"
                                                        data-bs-grade="10"
                                                        data-bs-reference_id="{{ $item->order->reference_id }}"
                                                        data-bs-stock_id="{{ $stock->id }}"
                                                        data-bs-price="{{ $item->price }}"
                                                        data-bs-linked_id="{{ $item->id }}"
                                                        data-bs-action="{{ url('add_return_item').'/'.$return_order->id}}"
                                                        > Return as RMA </a>

                                                    <a class="dropdown-item" id="action_{{ $stock->id }}" href="javascript:void(0);"
                                                        data-bs-target="#action_model"
                                                        data-bs-toggle="modal"
                                                        data-bs-reference="Return as WIP"
                                                        data-bs-product="{{ $item->variation->product_id }}"
                                                        data-bs-storage="{{ $item->variation->storage }}"
                                                        data-bs-color="{{ $item->variation->color }}"
                                                        data-bs-grade="9"
                                                        data-bs-reference_id="{{ $item->order->reference_id }}"
                                                        data-bs-stock_id="{{ $stock->id }}"
                                                        data-bs-price="{{ $item->price }}"
                                                        data-bs-linked_id="{{ $item->id }}"
                                                        data-bs-action="{{ url('add_return_item').'/'.$return_order->id}}"
                                                        > Return as WIP </a>

                                                </div>

                                            </td>
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
        </div>

        <div class="modal" id="action_model">
            <div class="modal-dialog wd-xl-400" role="document">
                <div class="modal-content">
                    <div class="modal-body pd-sm-40">
                        <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal"
                            type="button"><span aria-hidden="true">&times;</span></button>
                        <h3 class="modal-title mg-b-5">Update Stock Status</h3>
                        <hr>
                        <form action="" method="POST" id="action_form">
                            @csrf
                            <div class="form-group">
                                <label for="">Action</label>
                                <input class="form-control" name="return[id]" type="text" id="order_reference" readonly>
                            </div>
                            <div class="form-group">
                                <label for="">Reason</label>
                                <textarea class="form-control" id="reason" name="return[description]"></textarea>
                            </div>
                            <input type="hidden" id="product" name="return[product]">
                            <input type="hidden" id="storage" name="return[storage]">
                            <input type="hidden" id="color" name="return[color]">
                            <input type="hidden" id="grade" name="return[grade]">
                            <input type="hidden" id="order_id" name="return[order_id]" value="{{$return_order->id}}">
                            <input type="hidden" id="reference_id" name="return[reference_id]">
                            <input type="hidden" id="stock_id" name="return[stock_id]">
                            <input type="hidden" id="price" name="return[price]">
                            <input type="hidden" id="linked_id" name="return[linked_id]">


                            <button class="btn btn-primary btn-block">Send</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endsection

    @section('scripts')
        <script>

            $('#action_model').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget) // Button that triggered the modal
            var action = button.data('bs-action') // Extract info from data-* attributesv
            var reference = button.data('bs-reference') // Extract info from data-* attributesv
            var product = button.data('bs-product') // Extract info from data-* attributes
            var storage = button.data('bs-storage') // Extract info from data-* attributes
            var color = button.data('bs-color') // Extract info from data-* attributes
            var grade = button.data('bs-grade') // Extract info from data-* attributes
            var reference_id = button.data('bs-reference_id') // Extract info from data-* attributes
            var stock_id = button.data('bs-stock_id') // Extract info from data-* attributes
            var price = button.data('bs-price') // Extract info from data-* attributes
            var linked_id = button.data('bs-linked_id') // Extract info from data-* attributes
            // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
            // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
            var modal = $(this)
            if(reference == "Send Back to Customer"){
                modal.find('.modal-body #reason').val("Warranty Exclusion Approved")
            }
            modal.find('.modal-body #action_form').attr('action', action);
            modal.find('.modal-body #order_reference').val(reference)
            modal.find('.modal-body #product').val(product)
            modal.find('.modal-body #storage').val(storage)
            modal.find('.modal-body #color').val(color)
            modal.find('.modal-body #grade').val(grade)
            modal.find('.modal-body #reference_id').val(reference_id)
            modal.find('.modal-body #stock_id').val(stock_id)
            modal.find('.modal-body #price').val(price)
            modal.find('.modal-body #linked_id').val(linked_id)
            })
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
