@extends('layouts.app')

    @section('content')
        <!-- breadcrumb -->
            <div class="breadcrumb-header justify-content-between">
                <div class="left-content">
                <span class="main-content-title mg-b-0 mg-b-lg-1">POS</span>
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
            <div class="col-md-9">
                <div class="card p-3">
                    <div class="d-flex bg-light tx-center overflow-auto border">
                        @foreach ($categories as $id => $name)
                            @php
                                $name = str_replace(' ',"&nbsp;",$name);
                            @endphp
                            <div class="border wd-auto">
                                <a href="javascript:void();" class="btn btn-link" onchange="selectCategory({{ $id }})"> {!! $name !!} </a>
                            </div>
                        @endforeach
                    </div>
                    <div class="d-flex bg-light tx-center overflow-scroll border">
                        @foreach ($brands as $id => $name)
                            <div class="border wd-auto">
                                <a href="#" class="btn btn-link" onchange="selectBrand({{ $id }})"> {{ $name }} </a>
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
                            <input type="text" class="form-control" placeholder="Search" aria-label="Search" aria-describedby="basic-addon1">
                        </div>
                    </div>
                    <script>
                        @if (request('category'))
                            let selectedCategoryId = {{ request('category') }};
                        @else
                            let selectedCategoryId = null;
                        @endif
                        @if (request('brand'))
                            let selectedBrandId = {{ request('brand') }};
                        @else
                            let selectedBrandId = null;
                        @endif

                        get_products(selectedCategoryId, selectedBrandId);

                        // const colorData = {!! json_encode($colors) !!};
                        // const storageData = {!! json_encode($storages) !!};
                        // const gradeData = {!! json_encode($grades) !!};

                        function selectCategory(categoryId) {
                            selectedCategoryId = categoryId;
                            get_products(selectedCategoryId, selectedBrandId);
                        }
                        function selectBrand(brandId) {
                            selectedBrandId = brandId;
                            get_products(selectedCategoryId, selectedBrandId);
                        }

                        // function selectBrand(brandId) {
                        //     // Use the selectedCategoryId variable here to fetch stocks based on both category and brand
                        //     if (selectedCategoryId !== null) {
                        //         fetch("{{ url('wholesale') }}/get_products?category=" + selectedCategoryId + "&brand=" + brandId)
                        //             .then(response => response.json())
                        //             .then(products => {
                        //                 const productMenu = document.getElementById('product-menu');
                        //                 productMenu.innerHTML = ''; // Clear existing variation menu items

                        //                 products.forEach(product => {
                        //                     const productLink = document.createElement('a');
                        //                     productLink.href = `{{ url('wholesale') }}/detail?category=${selectedCategoryId}&brand=${brandId}&product=`;
                        //                     productLink.class = 'tx-center btn btn-light d-flex align-items-center justify-content-center br-5 ht-100 tx-15';
                        //                     // productLink.value = `${product.id}`;
                        //                     productLink.innerHTML = `${product.model}`;
                        //                     @if(request('product'))
                        //                         // Check if the request parameter matches the product's ID
                        //                         if (product.id == {{ request('product') }}) {
                        //                             productLink.active = true; // Set the 'selected' attribute
                        //                         }
                        //                     @endif
                        //                     productMenu.appendChild(productLink);
                        //                 });
                        //             })
                        //             .catch(error => console.error('Error fetching products:', error));
                        //     } else {
                        //         console.error('Please select a category first.');
                        //     }
                        // }
                        function get_products(selectedCategoryId, selectedBrandId){
                            fetch("{{ url('wholesale') }}/get_products?category=" + selectedCategoryId + "&brand=" + selectedBrandId)
                                .then(response => response.json())
                                .then(products => {
                                    const productMenu = document.getElementById('product-menu');
                                    productMenu.innerHTML = ''; // Clear existing variation menu items

                                    products.forEach(product => {
                                        const productLink = document.createElement('a');
                                        productLink.href = `{{ url('wholesale') }}/detail?category=${selectedCategoryId}&brand=${selectedBrandId}&product=`;
                                        productLink.class = 'tx-center btn btn-light d-flex align-items-center justify-content-center br-5 ht-100 tx-15';
                                        // productLink.value = `${product.id}`;
                                        productLink.innerHTML = `${product.model}`;
                                        @if(request('product'))
                                            // Check if the request parameter matches the product's ID
                                            if (product.id == {{ request('product') }}) {
                                                productLink.active = true; // Set the 'selected' attribute
                                            }
                                        @endif
                                        productMenu.appendChild(productLink);
                                    });
                                })
                                .catch(error => console.error('Error fetching products:', error));
                        }
                    </script>
                    <div class="card-body">
                        <div class="row g-3">
                            {{-- @foreach ($products as $id => $model) --}}

                                <div class="col-md-3" id="product-menu">
                                        {{-- <a href="" class="tx-center btn btn-light d-flex align-items-center justify-content-center br-5 ht-100 tx-15">{{ $model }}</a> --}}
                                    {{-- </div> --}}
                                </div>
                            {{-- @endforeach --}}
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                            <div class="col-md-3 p-2">
                                <div class="d-flex align-items-center justify-content-center br-5  ht-100 bg-gray-200">
                                    .ht-100
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        Mode:
                        <div class="main-toggle main-toggle-secondary on">
                            <span></span>
                        </div>
                        <div data-toggle="buttons-radio">
                            <button class="btn btn-secondary">Purchase</button>
                            <button class="btn btn-secondary">Sale</button>
                        </div>
                        <div class="btn-group btn-group-toggle" data-toggle="buttons">
                            <label class="btn btn-light active">
                              <input type="radio" name="options" id="option1" onclick="$().button('toggle')"> Sale
                            </label>
                            <label class="btn btn-light">
                              <input type="radio" name="options" id="option2" onclick="$().button('toggle')"> Purchase
                            </label>
                        </div>
                        <div class="btn-group btn-group-toggle" data-bs-toggle="buttons">
                            <label class="btn btn-light active">
                                <input type="radio" name="options" id="option1" autocomplete="off" checked> Sale
                            </label>
                            <label class="btn btn-light">
                                <input type="radio" name="options" id="option2" autocomplete="off"> Purchase
                            </label>
                        </div>
                    </div>
                    <div class="card-body">

                    </div>
                </div>
            </div>
        </div>
        @php
            session()->forget('success');
        @endphp
    @endsection

    @section('scripts')
        <script>
            $(document).ready(function () {
                $('#sb_toggle').click();
            })
        </script>

    @endsection
