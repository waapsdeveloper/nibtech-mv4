@extends('layouts.app')

@section('title', 'POS')
    @section('styles')
    <style>
    /* Chrome, Safari, Edge, Opera */
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
      -webkit-appearance: none;
    }

    /* Firefox */
    input[type=number] {
      -moz-appearance: textfield;
    }
    </style>
    @endsection
    @section('content')
        <!-- breadcrumb -->
            {{-- <div class="breadcrumb-header justify-content-between">
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
                        <li class="breadcrumb-item"><a href="{{ url('pos') }}">BulkSale</a></li>
                        <li class="breadcrumb-item active" aria-current="page">POS</li>
                    </ol>
                </div>
            </div> --}}
        <!-- /breadcrumb -->
        {{-- <hr style="border-bottom: 1px solid #000"> --}}
        <br>
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
            <div class="col-md-8">
                <div class="card mb-1">
                    <div class="card-header">
                        <div class="d-flex overflow-auto">
                            <div class="">
                                <input type="radio" class="btn-check" name="category" id="option" autocomplete="off" onclick="selectCategory(0)" checked>
                                <label class="btn btn-outline-primary m-0 py-1 rounded-0" for="option">Categories:</label>
                            </div>
                            @foreach ($categories as $id => $name)
                                @php
                                    $name = str_replace(' ',"&nbsp;",$name);
                                @endphp
                                <div class="">
                                    {{-- <input type="radio" name="category" class="btn btn-light"> --}}
                                    <input type="radio" class="btn-check" name="category" id="option{{$id}}" autocomplete="off" onclick="selectCategory({{ $id }})">
                                    <label class="btn btn-outline-primary m-0 py-1 rounded-0" for="option{{$id}}">{!! $name !!}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="d-flex overflow-scroll">
                            <div class="">
                                <input type="radio" class="btn-check" name="brand" id="1option" autocomplete="off" onclick="selectBrand(0)" checked>
                                <label class="btn btn-outline-primary m-0 py-1 rounded-0" for="1option">Brands:</label>
                            </div>
                            @foreach ($brands as $id => $name)
                                @php
                                    $name = str_replace(' ',"&nbsp;",$name);
                                @endphp
                                <div class="">
                                    <input type="radio" class="btn-check" name="brand" id="1option{{$id}}" autocomplete="off" onclick="selectBrand({{ $id }})">
                                    <label class="btn btn-outline-primary m-0 py-1 rounded-0" for="1option{{$id}}">{!! $name !!}</label>
                                </div>
                            @endforeach
                        </div>

                {{-- </div>
                <div class="card"> --}}
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
                    <div class="card-body pt-0" style="height: calc(100vh - 285px); overflow-y: auto;">
                        <div class="row g-3" id="product-menu"></div>
                    </div>

                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-1">
                    <div class="card-header">
                        <div class="d-flex justify-content-between">

                            <select class="form-select w-auto" id="currency" aria-label="Default select example">
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency->id }}" data-sign="{{ $currency->sign }}" @if ($currency->id == 4) selected @endif>{{ $currency->code }}</option>
                                @endforeach
                            </select>
                            <div class="d-flex">
                                <input type="radio" class="btn-check" name="mode" id="3option">
                                <label class="btn btn-outline-dark m-0" for="3option">Purchase</label>
                                <input type="radio" class="btn-check" name="mode" id="2option" checked>
                                <label class="btn btn-outline-dark m-0" for="2option">Sale</label>
                            </div>
                        </div>
                        <div class="form-floating">
                            <input type="text" class="form-control" id="customer_name" placeholder="Customer Name">
                            <label for="customer_name">Customer Name</label>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5>Cart</h5>
                        <div class="border" style="height: calc(100vh - 430px); overflow-y: auto;">
                            <table class="table m-0">
                                <thead style="position: sticky; top: 0; background: white; z-index: 1;">
                                    <tr>
                                        <th>Product</th>
                                        <th>Qty</th>
                                        <th>Price</th>
                                    </tr>
                                </thead>
                                <tbody id="cart-body" style="max-height: calc(100vh - 600px); overflow-y: auto; font-size:8px;">
                                    <tr>
                                        <td colspan="3" class="text-center">No items in cart</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <br>
                        <div class="row">
                            <div class="col-md-6">
                                Total: <span id="cart-total"></span><br>
                                Discount: <span id="cart-discount">0.00</span><br>
                                <div class="mt-2">

                                    <strong>Items: <span id="cart-items">0</span></strong><br>
                                    <strong>Subtotal: <span id="subtotal">0.00</span></strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-lg btn-light w-100">Hold</button>
                                <button class="btn btn-lg btn-secondary w-100">Checkout</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="product_detail_modal">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-body pd-sm-40">
                        <button aria-label="Close" class="close pos-absolute t-15 r-20 tx-26" data-bs-dismiss="modal" type="button">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h5 class="modal-title mg-b-5" id="product_name">Variation Details</h5>
                        <input type="hidden" id="product_id" name="product_id">

                        <div id="storage_options" class="my-3">
                            <input type="radio" class="btn-check" name="storage" id="storage_option0" value="" data-name="" checked autocomplete="off" onclick="loadProductDetails($('#product_id').val())">
                            <label class="btn btn-sm btn-outline-dark m-0" for="storage_option0">Storage:</label>
                        </div>
                        <div id="color_options" class="my-3">
                            <input type="radio" class="btn-check" name="color" id="color_option0" value="" data-name="" checked autocomplete="off" onclick="loadProductDetails($('#product_id').val())">
                            <label class="btn btn-sm btn-outline-dark m-0" for="color_option0">Color:</label>
                        </div>
                        <div id="grade_options" class="my-3">
                            <input type="radio" class="btn-check" name="grade" id="grade_option0" value="" data-name="" checked autocomplete="off" onclick="loadProductDetails($('#product_id').val())">
                            <label class="btn btn-sm btn-outline-dark m-0" for="grade_option0">Grade:</label>
                        </div>
                        <!-- Price and Quantity Section -->
                        <div class="d-flex my-3">
                            <div class="form-floating me-2">
                                <input type="number" class="form-control" name="price" id="price" value="0.00" step="0.01" min="0.01">
                                <label for="price">Price:</label>
                            </div>
                            <div class="handle-counter mx-3 wd-150">
                                <button class="counter-minus btn btn-white lh-2 shadow-none" type="button" onclick="decreaseQuantity()">
                                    <i class="fe fe-minus"></i>
                                </button>
                                <input type="number" class="form-control" name="quantity" id="quantity" value="1" min="1" />
                                <button class="counter-plus btn btn-white lh-2 shadow-none" type="button" onclick="increaseQuantity()">
                                    <i class="fe fe-plus"></i>
                                </button>
                            </div>
                            <div>
                                <button class="btn btn-dark" id="add_to_cart" onclick="addToCart()">Add&nbsp;to&nbsp;<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cart-plus" viewBox="0 0 16 16">
                                        <path d="M8 7.5a.5.5 0 0 1 .5.5v1.5h1.5a.5.5 0 0 1 0 1H8.5V12a.5.5 0 0 1-1 0v-1.5H6a.5.5 0 0 1 0-1h1.5V8a.5.5 0 0 1 .5-.5zM0 1a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L1.01 3.607 0.61 2H.5a.5.5 0 0 1-.5-.5zM3.14 4l1.25 6.5h8.22l1.25-6.5H3.14zM5 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm9 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    @endsection

    @section('scripts')
        <script>
            $(document).ready(function () {
                $('#sb_toggle').click();

                updateCartDisplay({!! json_encode($cart) !!});
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
                            fetch(`{{ url('pos') }}/get_products?category=${selectedCategoryId}&brand=${selectedBrandId}&search=${searchedText}`)
                                .then(response => response.json())
                                .then(products =>
                                {
                                    const productMenu = document.getElementById('product-menu');
                                    productMenu.innerHTML = ''; // Clear existing products

                                    // Iterate through the products and create menu items
                                    products.forEach(product => {
                                        const productDiv = document.createElement('div');
                                        productDiv.className = 'col-md-3 col-sm-6'; // Add a class for styling (optional)

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

                        function loadProductDetails(productId, trigger = null) {

                            const storage = document.querySelector('input[name="storage"]:checked').value;
                            const color = document.querySelector('input[name="color"]:checked').value;
                            const grade = document.querySelector('input[name="grade"]:checked').value;

                            fetch(`{{ url('pos') }}/get_product_variations/${productId}?storage=${storage}&color=${color}&grade=${grade}&trigger=${trigger}`)
                                .then(response => response.json())
                                .then(product => {
                                    // console.log(product);
                                    // Render the product details
                                    const storageOptions = document.getElementById('storage_options');
                                    storageOptions.innerHTML = ''; // Clear existing options
                                    const storageRadio = document.createElement('input');
                                    storageRadio.type = 'radio';
                                    storageRadio.name = 'storage';
                                    storageRadio.id = `storage_option`;
                                    storageRadio.value = '';
                                    storageRadio.dataset.name = '';
                                    storageRadio.className = 'btn-check';
                                    storageRadio.onclick = () => loadProductDetails(productId);
                                    if (product.selected_storage == null) {
                                        storageRadio.checked = true;
                                    }

                                    const storageLabel = document.createElement('label');
                                    storageLabel.htmlFor = `storage_option`;
                                    storageLabel.className = 'btn btn-sm btn-outline-dark m-0';
                                    storageLabel.innerHTML = 'Storage:';


                                    storageOptions.appendChild(storageRadio);
                                    storageOptions.appendChild(storageLabel);

                                    for (const [key, value] of Object.entries(product.storages)) {
                                        const storageRadio = document.createElement('input');
                                        storageRadio.type = 'radio';
                                        storageRadio.name = 'storage';
                                        storageRadio.id = `storage_option_${key}`;
                                        storageRadio.value = key;
                                        storageRadio.dataset.name = value;
                                        storageRadio.className = 'btn-check';
                                        storageRadio.onclick = () => loadProductDetails(productId, 'storage');
                                        if (product.selected_storage == key) {
                                            storageRadio.checked = true;
                                        }
                                        if (key in product.available_storages) {} else {
                                            storageRadio.disabled = true;
                                        }

                                        const storageLabel = document.createElement('label');
                                        storageLabel.htmlFor = `storage_option_${key}`;
                                        storageLabel.className = 'btn btn-sm btn-outline-dark m-0';
                                        storageLabel.innerHTML = value;


                                        storageOptions.appendChild(storageRadio);
                                        storageOptions.appendChild(storageLabel);
                                    }

                                    const colorOptions = document.getElementById('color_options');
                                    colorOptions.innerHTML = ''; // Clear existing options
                                    const colorRadio = document.createElement('input');
                                    colorRadio.type = 'radio';
                                    colorRadio.name = 'color';
                                    colorRadio.id = `color_option`;
                                    colorRadio.value = '';
                                    colorRadio.dataset.name = '';
                                    colorRadio.className = 'btn-check';
                                    colorRadio.onclick = () => loadProductDetails(productId);
                                    if (product.selected_color == null) {
                                        colorRadio.checked = true;
                                    }

                                    const colorLabel = document.createElement('label');
                                    colorLabel.htmlFor = `color_option`;
                                    colorLabel.className = 'btn btn-sm btn-outline-dark m-0';
                                    colorLabel.innerHTML = 'color:';


                                    colorOptions.appendChild(colorRadio);
                                    colorOptions.appendChild(colorLabel);

                                    for (const [key, value] of Object.entries(product.colors)) {
                                        const colorRadio = document.createElement('input');
                                        colorRadio.type = 'radio';
                                        colorRadio.name = 'color';
                                        colorRadio.id = `color_option_${key}`;
                                        colorRadio.value = key;
                                        colorRadio.dataset.name = value;
                                        colorRadio.className = 'btn-check';
                                        colorRadio.onclick = () => loadProductDetails(productId, 'color');
                                        if (product.selected_color == key) {
                                            colorRadio.checked = true;
                                        }

                                        const colorLabel = document.createElement('label');
                                        colorLabel.htmlFor = `color_option_${key}`;
                                        colorLabel.className = 'btn btn-sm btn-outline-dark m-0';
                                        colorLabel.innerHTML = value;


                                        colorOptions.appendChild(colorRadio);
                                        colorOptions.appendChild(colorLabel);
                                    }


                                    const gradeOptions = document.getElementById('grade_options');
                                    gradeOptions.innerHTML = ''; // Clear existing options
                                    const gradeRadio = document.createElement('input');
                                    gradeRadio.type = 'radio';
                                    gradeRadio.name = 'grade';
                                    gradeRadio.id = `grade_option`;
                                    gradeRadio.value = '';
                                    gradeRadio.dataset.name = '';
                                    gradeRadio.className = 'btn-check';
                                    gradeRadio.onclick = () => loadProductDetails(productId);
                                    if (product.selected_grade == null) {
                                        gradeRadio.checked = true;
                                    }

                                    const gradeLabel = document.createElement('label');
                                    gradeLabel.htmlFor = `grade_option`;
                                    gradeLabel.className = 'btn btn-sm btn-outline-dark m-0';
                                    gradeLabel.innerHTML = 'grade:';


                                    gradeOptions.appendChild(gradeRadio);
                                    gradeOptions.appendChild(gradeLabel);

                                    for (const [key, value] of Object.entries(product.grades)) {
                                        const gradeRadio = document.createElement('input');
                                        gradeRadio.type = 'radio';
                                        gradeRadio.name = 'grade';
                                        gradeRadio.id = `grade_option_${key}`;
                                        gradeRadio.value = key;
                                        gradeRadio.dataset.name = value;
                                        gradeRadio.className = 'btn-check';
                                        gradeRadio.onclick = () => loadProductDetails(productId, 'grade');
                                        if (product.selected_grade == key) {
                                            gradeRadio.checked = true;
                                        }

                                        const gradeLabel = document.createElement('label');
                                        gradeLabel.htmlFor = `grade_option_${key}`;
                                        gradeLabel.className = 'btn btn-sm btn-outline-dark m-0';
                                        gradeLabel.innerHTML = value;


                                        gradeOptions.appendChild(gradeRadio);
                                        gradeOptions.appendChild(gradeLabel);
                                    }



                                // <input type="radio" class="btn-check" name="category" id="option{{$id}}" autocomplete="off" onclick="selectCategory({{ $id }})">
                                // <label class="btn btn-outline-dark m-0" for="option{{$id}}">{!! $name !!}</label>

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

                            // Decrease quantity function
function decreaseQuantity(id = 'quantity') {
    const quantityInput = document.getElementById(id);
    let currentVal = parseInt(quantityInput.value);
    if (currentVal > 1) {
        quantityInput.value = currentVal - 1;
    }
    if (id != 'quantity') {
        updateCart(id.substring(8));
    }
}

// Increase quantity function
function increaseQuantity(id = 'quantity') {
    const quantityInput = document.getElementById(id);
    let currentVal = parseInt(quantityInput.value);
    quantityInput.value = currentVal + 1;
    if (id != 'quantity') {
        updateCart(id.substring(8));
    }
}

// Add to Cart function
function addToCart() {
    const productName = document.getElementById('product_name').innerText;
    const productId = document.getElementById('product_id').value;
    const price = parseFloat(document.getElementById('price').value);
    const quantity = parseInt(document.getElementById('quantity').value);
    const storage = document.querySelector('input[name="storage"]:checked').value;
    const storageName = document.querySelector('input[name="storage"]:checked').dataset.name;
    const color = document.querySelector('input[name="color"]:checked').value;
    const colorName = document.querySelector('input[name="color"]:checked').dataset.name;
    const grade = document.querySelector('input[name="grade"]:checked').value;
    const gradeName = document.querySelector('input[name="grade"]:checked').dataset.name;

    fetch(`{{ url('pos') }}/add`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            product_name: productName,
            product_id: productId,
            price: price,
            quantity: quantity,
            storage: storage,
            storage_name: storageName,
            color: color,
            color_name: colorName,
            grade: grade,
            grade_name: gradeName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // alert(data.message);
            updateCartDisplay(data.cart);
            $('#product_detail_modal').modal('hide');
            // Update the cart display if necessary
        }
    });
}


function updateCartDisplay(cart) {
    const cartBody = document.getElementById('cart-body');
    cartBody.innerHTML = '';

    let total = 0;
    let discount = 0;
    let items = 0;
    let i = 0;
    Object.keys(cart).forEach(cartKey => {
        const item = cart[cartKey];
        total += item.price * item.quantity;
        if (item.discount){
            discount += parseFloat(item.discount);
        }
        items += parseInt(item.quantity);


        const cartItem = document.createElement('tr');
        cartItem.className = 'cart-item';
        cartItem.dataset.bsToggle = 'collapse';
        cartItem.dataset.bsTarget = '#collapse'+i;
        cartItem.innerHTML = `
            <td>${item.product_name} (${item.storage_name}, ${item.color_name}, ${item.grade_name})</td>
            <td>${item.quantity}</td>
            <td>${item.price}</td>
        `;
        const collapse = document.createElement('tr');
        collapse.className = 'collapse bg-light';
        collapse.id = 'collapse'+i;
        collapse.innerHTML = `
            <td colspan="3">
                <div class="d-flex justify-content-between">
                    <div class="handle-counter wd-150">
                        <button class="counter-minus btn btn-white lh-2 shadow-none" type="button" onclick="decreaseQuantity('quantity${cartKey}')">
                            <i class="fe fe-minus"></i>
                        </button>
                        <input type="number" class="form-control w-50" name="quantity${cartKey}" id="quantity${cartKey}" value="${item.quantity}" min="1" />
                        <button class="counter-plus btn btn-white lh-2 shadow-none" type="button" onclick="increaseQuantity('quantity${cartKey}')">
                            <i class="fe fe-plus"></i>
                        </button>
                    </div>
                    <div class="form-floating">
                        <input type="number" class="form-control" name="price${cartKey}" id="price${cartKey}" value="${item.price ?? 0}">
                        <label for="price">Price:</label>
                    </div>
                    <div class="form-floating">
                        <input type="number" class="form-control" name="discount${cartKey}" id="discount${cartKey}" value="${item.discount ?? 0}" max="${item.price}">
                        <label for="discount">Discount:</label>
                    </div>
                    <div>
                        <button class="btn btn-outline-danger" onclick="removeFromCart('${cartKey}')">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </td>
        `;

        cartBody.appendChild(cartItem);
        cartBody.appendChild(collapse);

        // Add event listeners for quantity, price, and discount inputs to trigger the updateCart function
        document.getElementById(`quantity${cartKey}`).addEventListener('change', function() {
            updateCart(cartKey);
        });
        document.getElementById(`price${cartKey}`).addEventListener('change', function() {
            updateCart(cartKey);
        });
        document.getElementById(`discount${cartKey}`).addEventListener('change', function() {
            updateCart(cartKey);
        });
        i++;
    });

    document.getElementById('cart-total').innerText = total.toFixed(2);
    document.getElementById('cart-discount').innerText = discount.toFixed(2);
    document.getElementById('cart-items').innerText = items;
    document.getElementById('subtotal').innerText = (total - discount).toFixed(2);
}
function updateCart(cartKey) {
    const quantity = document.getElementById('quantity' + cartKey).value;
    const price = document.getElementById('price' + cartKey).value;
    const discount = document.getElementById('discount' + cartKey).value;

    $.ajax({
        url: `{{ url('pos') }}/update`, // your update route
        type: 'POST',
        data: {
            cart_key: cartKey,
            quantity: quantity,
            price: price,
            discount: discount,
            _token: '{{ csrf_token() }}' // Ensure CSRF token is included
        },
        success: function(response) {
            if (response.success) {
                // Update cart display with new cart data
                updateCartDisplay(response.cart);
            }
        },
        error: function(error) {
            console.error('Error updating cart:', error);
        }
    });
}


// Remove from cart
function removeFromCart(cartKey) {
    fetch(`{{ url('pos') }}/remove`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ cart_key: cartKey })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartDisplay(data.cart);
        }
    });
}



                    </script>

    @endsection
