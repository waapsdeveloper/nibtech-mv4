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

    <br>
    <livewire:search-listing />

    <div id="variations">
        <!-- Variations will be loaded here via AJAX -->
    </div>
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
                        $('#send_' + listingId).addClass('d-none'); // hide the button after submission
                        // $('quantity_' + listingId).val(data)
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        alert("Error: " + textStatus + " - " + errorThrown);
                    }
                });
            }

        $(document).ready(function() {
            $('.select2').select2();


            let storages = {!! json_encode($storages) !!};
            let colors = {!! json_encode($colors) !!};
            let grades = {!! json_encode($grades) !!};

            fetchVariations(); // Fetch variations on page load
            function fetchVariations() {
                $.ajax({
                    url: "{{ url('api/internal/get_variations') }}", // Adjust the URL to your route
                    type: 'GET',
                    dataType: 'json', // Expecting a JSON response
                    success: function(data) {
                        displayVariations(data);
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText); // Log any errors for debugging
                    }
                });
            }

            function displayVariations(variations) {
                let variationsContainer = $('#variations'); // The container where data will be displayed
                variationsContainer.empty(); // Clear any existing content
                console.log(variations);
                // Check if there's data
                if (variations.data.length > 0) {
                    variations.data.forEach(function(variation) {
                        let stocksTable = '';
                        let listingsTable = '';
                        let stockPrices = [];
                        let listedStock = fetchUpdatedQuantity(variation.id);
                        let m_min_price = Math.min(...variation.listings.filter(listing => listing.currency_id === 4).map(listing => listing.min_price));
                        let m_price = Math.min(...variation.listings.filter(listing => listing.currency_id === 4).map(listing => listing.price));

                        $.ajax({
                            url: "{{ url('api/internal/get_variation_available_stocks') }}/" + variation.id,
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
                                updateAverageCost(variation.id, stockPrices);
                                stocksTable = datass;
                                $('#stocks_'+variation.id).html(datass);
                                $('#available_stock_'+variation.id).html(count + ' Available');
                            },
                            error: function(xhr) {
                                console.error(xhr.responseText);
                            }
                        });
                        variation.listings.forEach(function(listing) {
                            let p_append = '';
                            let pm_append = '';
                            if (listing.currency_id == 5) {
                                p_append = 'Min: £'+m_price.toFixed(2);
                                pm_append = 'Min: £'+m_min_price.toFixed(2);
                            }
                            listingsTable += `
                                <tr ${listing.buybox !== 1 ? 'style="background: pink;"' : ''}>
                                    <td title="${listing.id} ${listing.country_id.title}">
                                        <img src="{{ asset('assets/img/flags/') }}/${listing.country_id.code.toLowerCase()}.svg" height="15">
                                        ${listing.country_id.code}
                                    </td>
                                    <td><a href="{{ url('listing/get_competitors') }}/${listing.id}" target="_blank">${listing.buybox_price}</a></td>
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
                                    <td>${listing.max_price}</td>
                                    <td>${listing.updated_at}</td>
                                </tr>`;

                            $("#change_min_price_"+listing.id).submit(function(e) {
                                submitForm2(e, listing.id);
                            });
                            $("#change_price_"+listing.id).submit(function(e) {
                                submitForm3(e, listing.id);
                            });

                        });

                        // Create variation card
                        variationsContainer.append(`
                            <div class="card">
                                <div class="card-header pb-0 d-flex justify-content-between">
                                    <div>
                                        <h5>
                                            <a href="https://www.backmarket.fr/bo_merchant/listings/active?sku=${variation.sku}" title="View BM Ad" target="_blank">
                                                ${variation.sku} - ${variation.product.model} ${storages[variation.storage] || ''} ${colors[variation.color] || ''} ${grades[variation.grade] || ''}
                                            </a>
                                        </h5>
                                        <span id="sales_${variation.id}"></span>
                                    </div>


                                    <div>
                                        <form class="form-inline" method="POST" id="change_qty_${variation.id}" action="{{url('listing/update_quantity')}}/${variation.id}">
                                            @csrf
                                            <div class="form-floating">
                                                <input type="number" class="form-control" name="stock" id="quantity_${variation.id}" value="${listedStock || 0}" style="width:80px;" oninput="toggleButtonOnChange(${variation.id}, this)">
                                                <label for="">Stock</label>
                                            </div>
                                            <button id="send_${variation.id}" class="btn btn-light d-none" onclick="submitForm(event, ${variation.id})">Push</button>
                                        </form>
                                    </div>

                                    <div>
                                        <a class="btn btn-link" href="{{url('order').'?sku='}}$(variation.sku)" target="_blank">
                                            Pending Order Items: ${variation.pending_orders.length || 0}
                                        </a>
                                    </div>

                                    <span class="" id="available_stock_${variation.id}">${variation.available_stocks.length || 0} Available</span>
                                </div>
                                <div class="card-body p-2">
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
                                                        <th title="Min Price" width="150"><small><b>Min </b>(<b id="best_price_${variation.id}"></b>)</small></th>
                                                        <th width="150"><small><b>Price</b></small></th>
                                                        <th title="Max Price"><small><b>Max</b></small></th>
                                                        <th><small><b>Date</b></small></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
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

            function updateAverageCost(variationId, prices) {
                if (prices.length > 0) {

                    let average = prices.reduce((a, b) => parseInt(a) + parseInt(b), 0) / prices.length;
                    $(`#average_cost_${variationId}`).text(`€${average.toFixed(2)}`);
                    $('#best_price_'+variationId).text(`€${(parseInt(average)+(parseInt(average)*0.15)).toFixed(2)}`);
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


        });
    </script>


    <!-- INTERNAL Select2 js -->
    <script src="{{asset('assets/plugins/select2/js/select2.full.min.js')}}"></script>
    <script src="{{asset('assets/js/select2.js')}}"></script>
@endsection
