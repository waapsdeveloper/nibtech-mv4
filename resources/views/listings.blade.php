@extends('layouts.app')

@section('styles')
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
            max-height: 683px;
            overflow: scroll;
        }
        .breadcrumb-header {
            padding: 15px;
            background-color: #f8f9fa;
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
        $(document).ready(function() {
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

                        $.ajax({
                            url: "{{ url('api/internal/get_variation_available_stocks') }}/" + variation.id,
                            type: 'GET',
                            dataType: 'json',
                            success: function(data) {
                                datass = '';
                                data.stocks.forEach(function(item, index) {
                                    // console.log(data.stock_costs[item.id]);
                                    let price = data.stock_costs[item.id];
                                    stockPrices.push(price);
                                    // Load stock cost via AJAX
                                    datass += `
                                        <tr>
                                            <td>${index + 1}</td>
                                            <td data-stock="${item.id}">
                                                <a href="{{ url('imei?imei=') }}${item.imei}${item.serial_number}" target="_blank">
                                                    ${item.imei}${item.serial_number}
                                                </a>
                                            </td>
                                            <td id="cost_${item.id}">€${price}</td>
                                        </tr>`;


                                });
                                updateAverageCost(variation.id, stockPrices);
                                stocksTable = datass;
                                $('#stocks_'+variation.id).html(datass);
                            },
                            error: function(xhr) {
                                console.error(xhr.responseText);
                            }
                        });
                        // variation.available_stocks.forEach(function(item, index) {
                        //     // Load stock cost via AJAX
                        //     $.ajax({
                        //         url: "{{ url('get_stock_cost') }}/" + item.id,
                        //         type: 'GET',
                        //         dataType: 'json',
                        //         success: function(price) {
                        //             stockPrices.push(price);
                        //             stocksTable += `
                        //                 <tr>
                        //                     <td>${index + 1}</td>
                        //                     <td data-stock="${item.id}">
                        //                         <a href="{{ url('imei?imei=') }}${item.imei}${item.serial_number}" target="_blank">
                        //                             ${item.imei}${item.serial_number}
                        //                         </a>
                        //                     </td>
                        //                     <td id="cost_${item.id}">€${price}</td>
                        //                 </tr>`;

                        //             // Update average cost
                        //             updateAverageCost(variation.id, stockPrices);
                        //         },
                        //         error: function(xhr) {
                        //             console.error(xhr.responseText);
                        //         }
                        //     });
                        // });

                        variation.listings.forEach(function(listing) {
                            listingsTable += `
                                <tr ${listing.buybox !== 1 ? 'style="background: pink;"' : ''}>
                                    <td title="${listing.id} ${listing.country_id.title}">
                                        <img src="{{ asset('assets/img/flags/') }}/${listing.country_id.code.toLowerCase()}.svg" height="15">
                                        ${listing.country_id.code}
                                    </td>
                                    <td><a href="{{ url('listing/get_competitors') }}/${listing.id}" target="_blank">${listing.buybox_price}</a></td>
                                    <td>
                                        <div class="form-floating">
                                            <input type="number" class="form-control" id="min_price_${listing.id}" name="min_price" step="0.01" value="${listing.min_price}">
                                            <label for="">Min Price</label>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-floating">
                                            <input type="number" class="form-control" id="price_${listing.id}" name="price" step="0.01" value="${listing.price}">
                                            <label for="">Price</label>
                                        </div>
                                    </td>
                                    <td>${listing.max_price}</td>
                                    <td>${listing.updated_at}</td>
                                </tr>`;
                        });

                        // Create variation card
                        variationsContainer.append(`
                            <div class="card">
                                <div class="card-title">${variation.product.model}</div>
                                <div class="card-body">
                                    <div class="col-md-5">
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover mb-0 text-md-nowrap">
                                                <thead>
                                                    <tr>
                                                        <th><small><b>No</b></small></th>
                                                        <th><small><b>IMEI/Serial</b></small></th>
                                                        <th><small><b>Cost</b></small></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="stocks_${variation.id}">
                                                    ${stocksTable}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-md-7">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0 text-md-nowrap">
                                                <thead>
                                                    <tr>
                                                        <th><small><b>Country</b></small></th>
                                                        <th><small><b>BuyBox Price</b></small></th>
                                                        <th><small><b>Min Price</b></small></th>
                                                        <th><small><b>Price</b></small></th>
                                                        <th><small><b>Max Price</b></small></th>
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
                    });
                } else {
                    variationsContainer.append('<p>No variations found.</p>');
                }
            }

            function updateAverageCost(variationId, prices) {
                if (prices.length > 0) {
                    let average = prices.reduce((a, b) => a + b, 0) / prices.length;
                    $(`#average_cost_${variationId}`).text(`€${average.toFixed(2)}`);
                } else {
                    $(`#average_cost_${variationId}`).text('€0.00');
                }
            }
        });
    </script>
@endsection
