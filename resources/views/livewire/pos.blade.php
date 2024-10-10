@extends('layouts.app')

    @section('content')
        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                <span class="main-content-title mg-b-0 mg-b-lg-1 d-flex">
                    POS MODE: &nbsp;
                        <div class="">
                            <input type="radio" class="btn-check" name="mode" id="3option">
                            <label class="btn btn-outline-dark m-0" for="3option">Purchase</label>
                        </div>
                        <div class="">
                            <input type="radio" class="btn-check" name="mode" id="2option" checked>
                            <label class="btn btn-outline-dark m-0" for="2option">Sale</label>
                        </div>
                </span>
                </div>
                <div class="justify-content-center mt-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('locale.Dashboards') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ url('wholesale') }}">BulkSale</a></li>
                        <li class="breadcrumb-item active" aria-current="page">POS</li>
                    </ol>
                </div>
            </div>
        <!-- /breadcrumb -->
        <hr style="border-bottom: 1px solid #000">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <span class="alert-inner--icon"><i class="fe fe-thumbs-up"></i></span>
                <span class="alert-inner--text"><strong>{{session('success')}}</strong></span>
                <button aria-label="Close" class="btn-close" data-bs-dismiss="alert" type="button"><span aria-hidden="true">&times;</span></button>
            </div>
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
            @php
            session()->forget('error');
            @endphp
        @endif
        <div class="row">
            <div class="col-md-9">
                <div class="card p-3">
                    <div class="d-flex overflow-auto">
                        <div class="">
                            <input type="radio" class="btn-check" name="category" id="option" autocomplete="off" onclick="selectCategory(0)" checked>
                            <label class="btn btn-outline-dark m-0" for="option">Categories:</label>
                        </div>
                        @foreach ($categories as $id => $name)
                            @php
                                $name = str_replace(' ',"&nbsp;",$name);
                            @endphp
                            <div class="">
                                {{-- <input type="radio" name="category" class="btn btn-light"> --}}
                                <input type="radio" class="btn-check" name="category" id="option{{$id}}" autocomplete="off" onclick="selectCategory({{ $id }})">
                                <label class="btn btn-outline-dark m-0" for="option{{$id}}">{!! $name !!}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="d-flex overflow-scroll">
                        <div class="">
                            <input type="radio" class="btn-check" name="brand" id="1option" autocomplete="off" onclick="selectBrand(0)" checked>
                            <label class="btn btn-outline-dark m-0" for="1option">Brands:</label>
                        </div>
                        @foreach ($brands as $id => $name)
                            @php
                                $name = str_replace(' ',"&nbsp;",$name);
                            @endphp
                            <div class="">
                                <input type="radio" class="btn-check" name="brand" id="1option{{$id}}" autocomplete="off" onclick="selectBrand({{ $id }})">
                                <label class="btn btn-outline-dark m-0" for="1option{{$id}}">{!! $name !!}</label>
                            </div>
                        @endforeach
                    </div>

                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="basic-addon1">
                                    <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="20" height="20" viewBox="0 0 50 50">
                                        <path d="M 21 3 C 11.621094 3 4 10.621094 4 20 C 4 29.378906 11.621094 37 21 37 C 24.710938 37 28.140625 35.804688 30.9375 33.78125 L 44.09375 46.90625 L 46.90625 44.09375 L 33.90625 31.0625 C 36.460938 28.085938 38 24.222656 38 20 C 38 10.621094 30.378906 3 21 3 Z M 21 5 C 29.296875 5 36 11.703125 36 20 C 36 28.296875 29.296875 35 21 35 C 12.703125 35 6 28.296875 6 20 C 6 11.703125 12.703125 5 21 5 Z"></path>
                                    </svg>
                                </span>
                            </div>
                            <input type="text" class="form-control" placeholder="Search" oninput="searchProducts(this.value)" aria-label="Search" aria-describedby="basic-addon1">
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row g-3" id="product-menu"></div>
                    </div>

                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">

                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="product_detail_modal">
            <div class="modal-dialog wd-xl-400" role="document">
                <div class="modal-content">
                    <div class="modal-body pd-sm-40">
                        <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal"
                            type="button"><span aria-hidden="true">&times;</span></button>
                        <h5 class="modal-title mg-b-5" id="product_name">Variation Details</h5>
                        <hr>
                        <form action="{{ url('order/correction') }}" method="POST" onsubmit="if ($('#correction_imei').val() == ''){ if (confirm('Remove IMEI from Order')){return true;}else{event.stopPropagation(); event.preventDefault();};};">
                            @csrf
                            <div class="form-group">
                                <label for="">Order Number</label>
                                <input class="form-control" name="correction[id]" type="text" id="order_reference" disabled>
                            </div>
                            <div class="form-group">
                                <label for="">Tester</label>
                                <input class="form-control" placeholder="input Tester Initial" name="correction[tester]" type="text">
                            </div>
                            <div class="form-group">
                                <label for="">IMEI / Serial Number</label>
                                <input class="form-control" placeholder="input IMEI / Serial Number" id="correction_imei" name="correction[imei]" type="text">
                            </div>
                            <div class="form-group">
                                <label for="">Reason</label>
                                <textarea class="form-control" name="correction[reason]">Wrong Dispatch</textarea>
                            </div>
                            <input type="hidden" id="item_id" name="correction[item_id]" value="">

                            <button class="btn btn-primary btn-block">{{ __('locale.Submit') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>


    @endsection

    @section('scripts')
        <script>
            $(document).ready(function () {
                $('#sb_toggle').click();
            })
                        // Set selected category and brand from the request, if available
                        let selectedCategoryId = {{ request('category') ?? 'null' }};
                        let selectedBrandId = {{ request('brand') ?? 'null' }};
                        let searchedText = {{ request('search') ?? 'null' }};
                        let selectedProductId = {{ request('product') ?? 'null' }};

                        // Fetch the products when the page loads
                        get_products(selectedCategoryId, selectedBrandId, searchedText);

                        // Function to update the selected category and fetch products
                        function selectCategory(categoryId) {

                            selectedCategoryId = categoryId;
                            get_products(selectedCategoryId, selectedBrandId, searchedText);
                        }

                        // Function to update the selected brand and fetch products
                        function selectBrand(brandId) {
                            selectedBrandId = brandId;
                            get_products(selectedCategoryId, selectedBrandId, searchedText);
                        }

                        function searchProducts(search) {
                            searchedText = search;
                            get_products(selectedCategoryId, selectedBrandId, searchedText);
                        }

                        // Function to fetch and render products based on the selected category and brand
                        function get_products(selectedCategoryId, selectedBrandId, searchedText) {
                            fetch(`{{ url('wholesale') }}/get_products?category=${selectedCategoryId}&brand=${selectedBrandId}&search=${searchedText}`)
                                .then(response => response.json())
                                .then(products =>
                                {
                                    const productMenu = document.getElementById('product-menu');
                                    productMenu.innerHTML = ''; // Clear existing products

                                    // Iterate through the products and create menu items
                                    products.forEach(product => {
                                        const productDiv = document.createElement('div');
                                        productDiv.className = 'col-md-3'; // Add a class for styling (optional)

                                        const productLink = document.createElement('a');
                                        productLink.href = 'javascript:void(0);';
                                        productLink.dataset.bsTarget = '#product_detail_modal';
                                        productLink.dataset.bsToggle = 'modal';
                                        productLink.dataset.title = product.model;

                                        productLink.onclick = () => loadProductDetails(product.id);

                                        productLink.className = 'tx-center btn btn-light d-flex align-items-center justify-content-center br-5 ht-100 tx-15';
                                        productLink.innerHTML = `${product.model}`;

                                        // Check if the product matches the selected product from the request
                                        if (product.id == selectedProductId) {
                                            productLink.classList.add('active'); // Add 'active' class to highlight the selected product
                                        }
                                        productDiv.appendChild(productLink);

                                        productMenu.appendChild(productDiv);
                                    });

                                })
                                .catch(error => console.error('Error fetching products:', error));
                        }

                        function loadProductDetails(productId) {
                            fetch(`{{ url('wholesale') }}/get_product_variations/${productId}`)
                                .then(response => response.json())
                                .then(product => {
                                    // Render the product details

                                })
                                .catch(error => console.error('Error fetching product details:', error));
                        }
                        $('#product_detail_modal').on('show.bs.modal', function (event) {
                            var button = $(event.relatedTarget) // Button that triggered the modal
                            var title = button.data('title') // Extract info from data-* attributesv
                            var modal = $(this)
                            modal.find('.modal-body #product_name').html(title)
                            // modal.find('.modal-body #item_id').val(item)
                            })

                    </script>

    @endsection
