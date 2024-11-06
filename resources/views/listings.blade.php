@extends('layouts.app')

@section('styles')
<link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet" />
    <style>
        .card {
            border: 1px solid #016a5949;
            border-radius: 8px;
            margin-bottom: 20px;
            padding: 15px;
            transition: box-shadow 0.3s;
        }
        .card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .card-title {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        .card-body {
            font-size: 1rem;
            color: #333;
            display: flex;
            direction: rtl;
        }
        .card-body * {
            direction: ltr;
        }
        .table-responsive {
            max-height: 790px;
            overflow: scroll;
        }
        .breadcrumb-header {
            padding: 15px;
            background-color: #f8f9fa;
        }
.form-floating>.form-control,
.form-floating>.form-control-plaintext,
.form-floating>.form-select {
  height: calc(2.3rem + 2px) !important;
}
    </style>
@endsection

@section('content')
    <!-- Breadcrumb -->
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <h4>Listings</h4>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                <li class="breadcrumb-item active" aria-current="page">Listings</li>
            </ol>
        </div>
    </div>
    <!-- /Breadcrumb -->

    <form action="" method="GET" id="search" onsubmit="fetchVariations()">
        <livewire:search-listing />
    </form>

    <div class="d-flex justify-content-between">
        <h5 class="card-title mg-b-0" id="page_info"> </h5>
        <div class="d-flex p-2 justify-content-between">
            <label for="perPage" class="form-label">Sort:</label>
            <select name="sort" class="form-select" id="perPage" onchange="this.form.submit()" form="search">
                <option value="1" {{ Request::get('sort') == 1 ? 'selected' : '' }}>Stock DESC</option>
                <option value="2" {{ Request::get('sort') == 2 ? 'selected' : '' }}>Stock ASC</option>
                <option value="3" {{ Request::get('sort') == 3 ? 'selected' : '' }}>Name DESC</option>
                <option value="4" {{ Request::get('sort') == 4 ? 'selected' : '' }}>Name ASC</option>
            </select>
            <label for="perPage" class="form-label">Per&nbsp;Page:</label>
            <select name="per_page" class="form-select" id="perPage" onchange="this.form.submit()" form="search">
                <option value="10" {{ Request::get('per_page') == 10 ? 'selected' : '' }}>10</option>
                <option value="20" {{ Request::get('per_page') == 20 ? 'selected' : '' }}>20</option>
                <option value="50" {{ Request::get('per_page') == 50 ? 'selected' : '' }}>50</option>
                <option value="100" {{ Request::get('per_page') == 100 ? 'selected' : '' }}>100</option>
            </select>
        </div>
    </div>
    <div id="variations"></div>
    <nav aria-label="Page navigation">
        <ul id="pagination-container" class="pagination justify-content-center"></ul>
    </nav>


@endsection

@section('scripts')
    <script>

        function toggleButtonOnChange(variationId, inputElement) {

            // Get the original value
            var originalValue = inputElement.defaultValue;

            // Get the current value
            var currentValue = inputElement.value;

            // Show the button only if the value has changed
            if (currentValue != originalValue) {
                $('#send_' + variationId).removeClass('d-none');
            } else {
                $('#send_' + variationId).addClass('d-none');
            }
        }

        function submitForm(event, variationId) {
            event.preventDefault(); // avoid executing the actual submit of the form.

            var form = $('#change_qty_' + variationId);
            var actionUrl = form.attr('action');

            $.ajax({
                type: "POST",
                url: actionUrl,
                data: form.serialize(), // serializes the form's elements.
                success: function(data) {
                    alert("Success: Quantity changed to " + data); // show response from the PHP script.
                    $('#send_' + variationId).addClass('d-none'); // hide the button after submission
                    $('quantity_' + variationId).val(data)
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert("Error: " + textStatus + " - " + errorThrown);
                }
            });
        }

        function checkMinPriceDiff(listingId){

            let min = $('#min_price_' + listingId);
            let price = $('#price_' + listingId);
            let min_val = min.val();
            let price_val = price.val();
            if (min_val > price_val || min_val < price_val*0.85) {
                min.addClass('bg-red');
                min.removeClass('bg-green');
                price.addClass('bg-red');
                price.removeClass('bg-green');
            }else{
                min.removeClass('bg-red');
                price.removeClass('bg-red');
            }
        }

        function submitForm2(event, listingId) {
            event.preventDefault(); // avoid executing the actual submit of the form.

            var form = $('#change_min_price_' + listingId);
            var actionUrl = "{{ url('listing/update_price') }}/" + listingId;

            $.ajax({
                type: "POST",
                url: actionUrl,
                data: form.serialize(), // serializes the form's elements.
                success: function(data) {
                    // alert("Success: Min Price changed to " + data); // show response from the PHP script.
                    $('#min_price_' + listingId).addClass('bg-green'); // hide the button after submission
                    // $('quantity_' + listingId).val(data)

                    checkMinPriceDiff(listingId);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert("Error: " + textStatus + " - " + errorThrown);
                }
            });
        }

        function submitForm3(event, listingId, actionUrl) {
            event.preventDefault(); // avoid executing the actual submit of the form.

            var form = $('#change_price_' + listingId);
            var actionUrl = "{{ url('listing/update_price') }}/" + listingId;

            $.ajax({
                type: "POST",
                url: actionUrl,
                data: form.serialize(), // serializes the form's elements.
                success: function(data) {
                    // alert("Success: Price changed to " + data); // show response from the PHP script.
                    $('#price_' + listingId).addClass('bg-green'); // hide the button after submission
                    // $('#send_' + listingId).addClass('d-none'); // hide the button after submission
                    // $('quantity_' + listingId).val(data)
                    checkMinPriceDiff(listingId);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert("Error: " + textStatus + " - " + errorThrown);
                }
            });
        }

        function updateAverageCost(variationId, prices) {
            if (prices.length > 0) {
                let average = prices.reduce((a, b) => parseFloat(a) + parseFloat(b), 0) / prices.length;
                $(`#average_cost_${variationId}`).text(`€${average.toFixed(2)}`);
                $('#best_price_'+variationId).text(`€${(parseFloat(average)+(parseFloat(average)*0.12)+20).toFixed(2)}`);
            } else {
                $(`#average_cost_${variationId}`).text('€0.00');
                // $('#best_price_'+variationId).text('€0.00');
            }
        }
        function fetchUpdatedQuantity(variationId, bm) {
            return $.ajax({
                url: `{{ url('api/internal/get_updated_quantity') }}/${variationId}`,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    // Assuming the response contains 'updatedQuantity'
                    // console.log(response.updatedQuantity);
                    // return response.updatedQuantity;
                    $('#quantity_'+variationId).val(response.updatedQuantity);
                    // You can then update the DOM or any UI elements with this value
                },
                error: function(xhr) {
                    console.error("Error fetching quantity:", xhr.responseText);
                }
            });
        }
        function getStocks(variationId){

            let stocksTable = '';
            let stockPrices = [];
            $.ajax({
                url: "{{ url('api/internal/get_variation_available_stocks') }}/" + variationId,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    datass = '';
                    count = 0;
                    data.stocks.forEach(function(item, index) {
                        count++;
                        // console.log(data.stock_costs[item.id]);
                        let price = data.stock_costs[item.id];
                        stockPrices.push(price);
                        // Load stock cost via AJAX
                        datass += `
                            <tr>
                                <td>${index + 1}</td>
                                <td data-stock="${item.id}">
                                    <a href="{{ url('imei?imei=') }}${item.imei}${item.serial_number}" target="_blank">
                                        ${item.imei ?? ''}${item.serial_number ?? ''}
                                    </a>
                                </td>
                                <td id="cost_${item.id}">€${price}</td>
                            </tr>`;


                    });
                    updateAverageCost(variationId, stockPrices);
                    stocksTable = datass;
                    $('#stocks_'+variationId).html(datass);
                    // $('#available_stock_'+variationId).html(count + ' Available');
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                }
            });
        }

        function getListings(variationId, eurToGbp, m_min_price, m_price) {

            let listingsTable = '';
            let countries = {!! json_encode($countries) !!};
            $.ajax({
                url: "{{ url('api/internal/get_competitors') }}/" + variationId,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    listingsTable += data.error ? `<tr><td colspan="6">${data.error}</td></tr>` : '';
                    data.listings.forEach(function(listing) {
                        let p_append = '';
                        let pm_append = '';
                        if (listing.currency_id == 5) {
                            p_append = 'break: £'+parseFloat((parseFloat(m_price)*parseFloat(eurToGbp))).toFixed(2);
                            pm_append = 'break: £'+parseFloat(m_min_price)*parseFloat(eurToGbp);
                        }
                        listingsTable += `
                            <tr ${listing.buybox !== 1 ? 'style="background: pink;"' : ''}>
                                <td title="${listing.id} ${countries[listing.country].title}">
                                    <a href="https://www.backmarket.${countries[listing.country].market_url}/${countries[listing.country].market_code}/p/gb/${listing.reference_uuid}" target="_blank">
                                    <img src="{{ asset('assets/img/flags/') }}/${countries[listing.country].code.toLowerCase()}.svg" height="15">
                                    ${countries[listing.country].code}
                                    </a>
                                </td>
                                <td>${listing.buybox_price}</td>
                                <td>${listing.buybox_winner_price}</td>
                                <td>
                                    <form class="form-inline" method="POST" id="change_min_price_${listing.id}">
                                        @csrf
                                        <input type="submit" hidden>
                                    </form>
                                    <form class="form-inline" method="POST" id="change_price_${listing.id}">
                                        @csrf
                                        <input type="submit" hidden>
                                    </form>
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="min_price_${listing.id}" name="min_price" step="0.01" value="${listing.min_price}" form="change_min_price_${listing.id}">
                                        <label for="">Min Price</label>
                                    </div>
                                    ${pm_append}
                                </td>
                                <td>
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="price_${listing.id}" name="price" step="0.01" value="${listing.price}" form="change_price_${listing.id}">
                                        <label for="">Price</label>
                                    </div>
                                    ${p_append}
                                </td>
                                <td>${new Date(listing.updated_at).toGMTString()}</td>
                            </tr>`;
                        $(document).ready(function() {
                            $("#change_min_price_" + listing.id).on('submit', function(e) {
                                submitForm2(e, listing.id);
                            });

                            $("#change_price_" + listing.id).on('submit', function(e) {
                                submitForm3(e, listing.id);
                            });
                        });
                    });
                    $('#listings_'+variationId).html(listingsTable);
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                }
            });
        }

        function getVariationDetails(variationId) {
            getStocks(variationId);
            getListings(variationId);
        }

        $(document).ready(function() {
            $('.select2').select2();


            let storages = {!! json_encode($storages) !!};
            let colors = {!! json_encode($colors) !!};
            let grades = {!! json_encode($grades) !!};
            let eurToGbp = {!! json_encode($eur_gbp) !!};

            fetchVariations(); // Fetch variations on page load

            function fetchVariations(page = 1) {
                // Collect form data or input values to create query parameters
                let params = {
                    reference_id: $('#reference_id').val(),
                    product: $('#product').val(),
                    sku: $('input[name="sku"]').val(),
                    color: $('select[name="color"]').val(),
                    storage: $('select[name="storage"]').val(),
                    grade: $('select[name="grade[]"]').val(), // Use .val() for multiple selects if needed
                    category: $('select[name="category"]').val(),
                    brand: $('select[name="brand"]').val(),
                    listed_stock: $('select[name="listed_stock"]').val(),
                    available_stock: $('select[name="available_stock"]').val(),
                    state: $('select[name="state"]').val(),
                    sort: $('select[name="sort"]').val(),
                    per_page: $('select[name="per_page"]').val(),
                    page: page
                };

                // Convert params object to a query string
                let queryString = $.param(params);

                // Append query string to the URL
                let url = "{{ url('api/internal/get_variations') }}" + '?' + queryString;

                $.ajax({
                    url: url,
                    type: 'GET',
                    dataType: 'json', // Expecting a JSON response
                    success: function(data) {
                        displayVariations(data);
                        updatePagination(data);
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText); // Log any errors for debugging
                    }
                });
            }

            function updatePagination(response) {
                let paginationContainer = $('#pagination-container');
                paginationContainer.empty(); // Clear existing pagination

                // Add Previous button
                if (response.prev_page_url) {
                    paginationContainer.append(`
                        <li class="page-item">
                            <a class="page-link w-auto" href="#" data-page="${new URL(response.first_page_url).searchParams.get('page')}">First</a>
                        </li>
                    `);
                } else {
                    paginationContainer.append('<li class="page-item disabled"><span class="page-link w-auto">First</span></li>');
                }

                // Add page links
                response.links.forEach(link => {
                    if (link.url) {
                        paginationContainer.append(`
                            <li class="page-item ${link.active ? 'active' : ''}">
                                <a class="page-link w-auto" href="#" data-page="${new URL(link.url).searchParams.get('page')}">${link.label}</a>
                            </li>
                        `);
                    } else {
                        paginationContainer.append(`
                            <li class="page-item disabled"><span class="page-link w-auto">${link.label}</span></li>
                        `);
                    }
                });

                // Add Next button
                if (response.next_page_url) {
                    paginationContainer.append(`
                        <li class="page-item">
                            <a class="page-link w-auto" href="#" data-page="${new URL(response.last_page_url).searchParams.get('page')}">Last</a>
                        </li>
                    `);
                } else {
                    paginationContainer.append('<li class="page-item disabled"><span class="page-link w-auto">Last</span></li>');
                }
            }
            $(document).on('click', '#pagination-container .page-link', function(event) {
                event.preventDefault(); // Prevent default link behavior

                const page = $(this).data('page'); // Get the page number from the clicked link
                fetchVariations(page); // Call the function to fetch variations with the selected page
            });



            function displayVariations(variations) {
                let variationsContainer = $('#variations'); // The container where data will be displayed
                variationsContainer.empty(); // Clear any existing content
                // console.log(variations);
                $('#page_info').text(`From ${variations.from} To ${variations.to} Out Of ${variations.total}`);
                // Check if there's data
                if (variations.data.length > 0) {
                    variations.data.forEach(function(variation) {
                        // load("{{ url('api/internal/get_competitors')}}/${variation.id}");
                        let stocksTable = '';
                        let listingsTable = '';
                        let stockPrices = [];
                        let listedStock = fetchUpdatedQuantity(variation.id);
                        let m_min_price = Math.min(...variation.listings.filter(listing => listing.currency_id === 4).map(listing => listing.min_price));
                        let m_price = Math.min(...variation.listings.filter(listing => listing.currency_id === 4).map(listing => listing.price));

                        // variation.listings.forEach(function(listing) {
                        //     let p_append = '';
                        //     let pm_append = '';
                        //     if (listing.currency_id == 5) {
                        //         p_append = 'break: £'+(m_price*eurToGbp).toFixed(2);
                        //         pm_append = 'break: £'+(m_min_price*eurToGbp).toFixed(2);
                        //     }
                        //     let name = listing.name;
                        //     if (name != null) {
                        //         name = name.replace(/ /g,'-');
                        //     }
                        //     listingsTable += `
                        //         <tr ${listing.buybox !== 1 ? 'style="background: pink;"' : ''}>
                        //             <td title="${listing.id} ${listing.country_id.title}">
                        //                 <a href="https://www.backmarket.${listing.country_id.market_url}/${listing.country_id.market_code}/p/gb/${listing.reference_uuid}" target="_blank">
                        //                 <img src="{{ asset('assets/img/flags/') }}/${listing.country_id.code.toLowerCase()}.svg" height="15">
                        //                 ${listing.country_id.code}
                        //                 </a>
                        //             </td>
                        //             <td>${listing.buybox_price}</td>
                        //             <td>${listing.buybox_winner_price}</td>
                        //             <td>
                        //                 <form class="form-inline" method="POST" id="change_min_price_${listing.id}">
                        //                     @csrf
                        //                     <input type="submit" hidden>
                        //                 </form>
                        //                 <form class="form-inline" method="POST" id="change_price_${listing.id}">
                        //                     @csrf
                        //                     <input type="submit" hidden>
                        //                 </form>
                        //                 <div class="form-floating">
                        //                     <input type="number" class="form-control" id="min_price_${listing.id}" name="min_price" step="0.01" value="${listing.min_price}" form="change_min_price_${listing.id}">
                        //                     <label for="">Min Price</label>
                        //                 </div>
                        //                 ${pm_append}
                        //             </td>
                        //             <td>
                        //                 <div class="form-floating">
                        //                     <input type="number" class="form-control" id="price_${listing.id}" name="price" step="0.01" value="${listing.price}" form="change_price_${listing.id}">
                        //                     <label for="">Price</label>
                        //                 </div>
                        //                 ${p_append}
                        //             </td>
                        //             <td>${new Date(listing.updated_at).toGMTString()}</td>
                        //         </tr>`;
                        //     $(document).ready(function() {
                        //         $("#change_min_price_" + listing.id).on('submit', function(e) {
                        //             submitForm2(e, listing.id);

                        //         });

                        //         $("#change_price_" + listing.id).on('submit', function(e) {
                        //             submitForm3(e, listing.id);
                        //         });
                        //     });


                        // });

                        // Create variation card
                        variationsContainer.append(`
                            <div class="card">
                                <div class="card-header py-0 d-flex justify-content-between">
                                    <div>
                                        <h5>
                                            <a href="https://www.backmarket.fr/bo_merchant/listings/active?sku=${variation.sku}" title="View BM Ad" target="_blank">
                                                <span style="background-color: ${colors[variation.color]}; width: 30px; height: 16px; display: inline-block;"></span>
                                                ${variation.sku} - ${variation.product.model} ${storages[variation.storage] || ''} ${colors[variation.color] || ''} ${grades[variation.grade] || ''}
                                            </a>
                                        </h5>
                                        <span id="sales_${variation.id}"></span>
                                    </div>


                                    <form class="form-inline" method="POST" id="change_qty_${variation.id}" action="{{url('listing/update_quantity')}}/${variation.id}">
                                        @csrf
                                        <div class="form-floating">
                                            <input type="number" class="form-control" name="stock" id="quantity_${variation.id}" value="${listedStock || 0}" style="width:80px;" oninput="toggleButtonOnChange(${variation.id}, this)">
                                            <label for="">Stock</label>
                                        </div>
                                        <button id="send_${variation.id}" class="btn btn-light d-none" onclick="submitForm(event, ${variation.id})">Push</button>
                                    </form>

                                    <div class="text-center">
                                        <h6 class="mb-0">
                                        <a class="" href="{{url('order').'?sku='}}$(variation.sku)" target="_blank">
                                            Pending Order Items: ${variation.pending_orders.length || 0}
                                        </a></h6>
                                        <h6 class="mb-0" id="available_stock_${variation.id}">Available: ${variation.available_stocks.length || 0}</h6>
                                        <h6 class="mb-0">Difference: ${variation.available_stocks.length - variation.pending_orders.length}</h6>
                                    </div>
                                    <button class="btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#details_${variation.id}" aria-expanded="false" aria-controls="details_${variation.id}" onClick="getVariationDetails(${variation.id}, ${eurToGbp}, ${m_min_price}, ${m_price})">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>

                                </div>
                                <div class="card-body p-2 collapse" id="details_${variation.id}">
                                    <div class="col-md-auto">
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                                <thead>
                                                    <tr>
                                                        <th><small><b>No</b></small></th>
                                                        <th><small><b>IMEI/Serial</b></small></th>
                                                        <th><small><b>Cost</b> (<b id="average_cost_${variation.id}"></b>)</small></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="stocks_${variation.id}">
                                                    ${stocksTable}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-md">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0 text-md-nowrap">
                                                <thead>
                                                    <tr>
                                                        <th><small><b>Country</b></small></th>
                                                        <th><small><b>BuyBox</b></small></th>
                                                        <th title="Buybox Winner Price"><small><b>Winner</b></small></th>
                                                        <th title="Min Price" width="150"><small><b>Min </b>(<b id="best_price_${variation.id}"></b>)</small></th>
                                                        <th width="150"><small><b>Price</b></small></th>
                                                        <th><small><b>Date</b></small></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="listings_${variation.id}">
                                                    ${listingsTable}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `);

                        $("#change_qty_"+variation.id).submit(function(e) {
                            submitForm(e, variation.id);
                        });
                        $('#sales_'+variation.id).load("{{ url('listing/get_sales') . '/'}}"+variation.id);
                    });
                } else {
                    variationsContainer.append('<p>No variations found.</p>');
                }
            }



        });
    </script>


    <!-- INTERNAL Select2 js -->
    <script src="{{asset('assets/plugins/select2/js/select2.full.min.js')}}"></script>
    <script src="{{asset('assets/js/select2.js')}}"></script>
@endsection
