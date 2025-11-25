@extends('layouts.app')

@section('styles')
<link href="{{asset('assets/plugins/select2/css/select2.min.css')}}" rel="stylesheet" />
    <style>
        /* Chrome, Safari, Edge, Opera */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
        }

        /* Firefox */
        input[type=number] {
        -moz-appearance: textfield;
        }
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
            max-height: 805px;
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
            <h4>{{ $title_page }}</h4>
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
        <input type="hidden" name="special" value="{{ Request::get('special') }}">
    </form>

    <div class="d-flex justify-content-between">
        <h5 class="card-title mg-b-0" id="page_info"> </h5>
        <div class="d-flex p-2 justify-content-between">
        {{-- <a href="{{ url('listing/start_listing_verification') }}" class="btn btn-primary" id="start_verification" onclick="return confirm('Are You Sure you want to Zero All Listing?')">Start&nbsp;Verification</a> --}}
        <a href="{{ url('listed_stock_verification') }}" class="btn btn-primary btn-sm" id="start_verification">Verification</a>
            @if (request('special') != 'verify_listing')
            <button class="btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target=".multi_collapse" id="open_all_variations">Toggle&nbsp;All</button>
            {{-- <button class="btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target=".multi_collapse_handler" id="open_all_handlers">Toggle&nbsp;Handlers</button> --}}
            {{-- <button class="btn btn-link" type="button" data-bs-toggle="modal" data-bs-target="#bulkModal">Bulk&nbsp;Update</button> --}}
            <button class="btn btn-success btn-sm ms-2" type="button" id="exportListingsBtn">Export&nbsp;CSV</button>
            {{-- <input class="form-check-input" type="radio" id="open_all" name="open_all" value="1" onchange="this.form.submit()" form="search"> --}}
            @endif
            <label for="perPage" class="form-label">Sort:</label>
            <select name="sort" class="form-select w-auto" id="perPage" onchange="this.form.submit()" form="search">
                <option value="1" {{ Request::get('sort') == 1 ? 'selected' : '' }}>Stock DESC</option>
                <option value="2" {{ Request::get('sort') == 2 ? 'selected' : '' }}>Stock ASC</option>
                <option value="3" {{ Request::get('sort') == 3 ? 'selected' : '' }}>Name DESC</option>
                <option value="4" {{ Request::get('sort') == 4 ? 'selected' : '' }}>Name ASC</option>
            </select>
            <label for="perPage" class="form-label">Per&nbsp;Page:</label>
            <select name="per_page" class="form-select w-auto" id="perPage" onchange="this.form.submit()" form="search">
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

    <!-- Bulk Update Modal -->
    <div class="modal fade" id="bulkModal" tabindex="-1" aria-labelledby="bulkModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="bulkUpdateForm" action="" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="bulkModalLabel">Bulk Update</h5>
                        <button type="button" class="btn-close " data-bs-dismiss="modal" aria-label="Close">
                            <i data-feather="x" class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Model</th>
                                    <th>Target Price</th>
                                    <th>Target Percentage</th>
                                </tr>
                            </thead>
                            <tbody id="bulkUpdateTable">
                                <tr>
                                    <td>

                                    <td>
                                        <input type="number" class="form-control" name="target_price" id="target_price" step="0.01">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control" name="target_percentage" id="target_percentage" step="0.01">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- /Bulk Update Modal -->

    {{-- Variation History Modal --}}
    <div class="modal fade" id="variationHistoryModal" tabindex="-1" aria-labelledby="variationHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="variation_name"></h5>
                    <h5 class="modal-title" id="variationHistoryModalLabel"> &nbsp; History</h5>
                    <button type="button" class="btn-close " data-bs-dismiss="modal" aria-label="Close">
                        <i data-feather="x" class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Topup Ref</th>
                                <th>Pending Orders</th>
                                <th>Qty Before</th>
                                <th>Qty Added</th>
                                <th>Qty After</th>
                                <th>Admin</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="variationHistoryTable">
                            <!-- Data will be populated here via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    {{-- /Variation History Modal --}}


@endsection

@section('scripts')
    <script>
        function moveToNextInput(currentInput, prefix, moveUp = false) {
            const inputs = document.querySelectorAll(`input[id^="${prefix}"]`);
            const currentIndex = Array.from(inputs).indexOf(currentInput);
            if (currentIndex !== -1) {
                if (moveUp && currentIndex > 0) {
                    inputs[currentIndex - 1].focus();
                } else if (!moveUp && currentIndex < inputs.length - 1) {
                    inputs[currentIndex + 1].focus();
                }
            }
        }
        function show_variation_history(variationId, variationName) {
            $('#variationHistoryModal').modal('show');

            $('#variation_name').text(variationName);
            $('#variationHistoryTable').html('Loading...');
            $.ajax({
                url: "{{ url('listing/get_variation_history') }}/" + variationId,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    let historyTable = '';
                    data.listed_stock_verifications.forEach(function(item) {
                        historyTable += `
                            <tr>
                                <td>${item.process_ref ?? ''}</td>
                                <td>${item.pending_orders}</td>
                                <td>${item.qty_from}</td>
                                <td>${item.qty_change}</td>
                                <td>${item.qty_to}</td>
                                <td>${item.admin}</td>
                                <td>${new Date(item.created_at).toLocaleString('en-GB', { timeZone: 'Europe/London', hour12: true })}</td>
                            </tr>`;
                    });
                    $('#variationHistoryTable').html(historyTable);
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                }
            });
        }


        function buildListingFilters(overrides) {
            overrides = overrides || {};

            let params = {
                product_name: $('#product_name').val(),
                reference_id: $('#reference_id').val(),
                product: $('#product').val(),
                sku: $('input[name="sku"]').val(),
                color: $('select[name="color"]').val(),
                storage: $('select[name="storage"]').val(),
                grade: $('select[name="grade[]"]').val(),
                category: $('select[name="category"]').val(),
                brand: $('select[name="brand"]').val(),
                marketplace: $('select[name="marketplace"]').val(),
                listed_stock: $('select[name="listed_stock"]').val(),
                available_stock: $('select[name="available_stock"]').val(),
                handler_status: $('select[name="handler_status"]').val(),
                state: $('select[name="state"]').val(),
                sort: $('select[name="sort"]').val(),
                per_page: $('select[name="per_page"]').val(),
                open_all: $('input[name="open_all"]').val(),
                special: "{{ Request::get('special') }}",
                sale_40: "{{ Request::get('sale_40') }}",
                variation_id: "{{ Request::get('variation_id') }}",
                process_id: "{{ Request::get('process_id') }}",
                show: "{{ Request::get('show') }}",
                csrf: "{{ csrf_token() }}"
            };

            return Object.assign(params, overrides);
        }


        $('#bulkModal').on('show.bs.modal', function (event) {

            let filters = buildListingFilters({
                page: "{{ Request::get('page') ?? 1 }}"
            });

            // Convert params object to a query string
            let queryString = $.param(filters);

            // Append query string to the URL
            let url = "{{ url('listing/get_target_variations') }}" + '?' + queryString;

            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json', // Expecting a JSON response
                success: function(data) {
                    let bulkUpdateTable = $('#bulkUpdateTable');
                    bulkUpdateTable.empty(); // Clear any existing content
                    data.data.forEach(function(variation) {
                        bulkUpdateTable.append(`
                            <tr>

                                <td>
                                <form class="form-inline" method="POST" id="bulk_target_${variation.product_id+'_'+variation.storage+'_'+variation.grade}">
                                    @csrf
                                    <input type="submit" hidden>
                                </form>
                                ${variation.product_name} ${variation.storage_name} ${variation.grade_name}</td>
                                <td>
                                    <input type="number" class="form-control" name="target" id="target_price_${variation.product_id+'_'+variation.storage+'_'+variation.grade}" step="0.01" value="${variation.target_price}" form="bulk_target_${variation.product_id+'_'+variation.storage+'_'+variation.grade}">
                                </td>
                                <td>
                                    <input type="number" class="form-control" name="percent" id="target_percentage_${variation.product_id+'_'+variation.storage+'_'+variation.grade}" step="0.01" value="${variation.target_percentage}" form="bulk_target_${variation.product_id+'_'+variation.storage+'_'+variation.grade}">
                                </td>
                                <input type="hidden" name="variation_ids[]" value="${variation.ids}" form="bulk_target_${variation.product_id+'_'+variation.storage+'_'+variation.grade}">
                                <input type="hidden" id="listing_ids_${variation.product_id+'_'+variation.storage+'_'+variation.grade}" name="listing_ids[]" value="${variation.listing_ids}" form="bulk_target_${variation.product_id+'_'+variation.storage+'_'+variation.grade}">
                            </tr>
                        `);
                        $(document).ready(function() {
                            $('#bulk_target_'+variation.product_id+'_'+variation.storage+'_'+variation.grade).on('submit', function(e) {

                                submitForm7(e, variation.product_id+'_'+variation.storage+'_'+variation.grade);
                            });
                        });
                    });
                },
                error: function(xhr) {
                    console.error(xhr.responseText); // Log any errors for debugging
                }
            });


        });

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
            var quantity = $('#quantity_' + variationId).val();
            // disable form submission twice
            $('#send_' + variationId).addClass('d-none');
            // disable submission on enter key
            $('#send_' + variationId).prop('disabled', true);

            $('#add_' + variationId).val(0);
            var form = $('#change_qty_' + variationId);
            var actionUrl = form.attr('action');

            $.ajax({
                type: "POST",
                url: actionUrl,
                data: form.serialize(), // serializes the form's elements.
                success: function(data) {
                    // alert("Success: Quantity changed to " + data); // show response from the PHP script.
                    $('#send_' + variationId).addClass('d-none'); // hide the button after submission

                    $('#quantity_' + variationId).val(data)
                    $('#success_' + variationId).text("Quantity changed to " + data);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert("Error: " + textStatus + " - " + errorThrown);
                }
            });
        }

        function submitForm1(event, variationId) {
            event.preventDefault(); // avoid executing the actual submit of the form.


            var form = $('#add_qty_' + variationId);
            var actionUrl = form.attr('action');

            var quantity = $('#add_' + variationId).val();
            // disable form submission twice
            $('#send_' + variationId).addClass('d-none');
            // disable submission on enter key for 2 seconds
            $('#send_' + variationId).prop('disabled', true);
            setTimeout(function() {
                $('#send_' + variationId).prop('disabled', false);
            }, 2000);


            $.ajax({
                type: "POST",
                url: actionUrl,
                data: form.serialize(), // serializes the form's elements.
                success: function(data) {
                    // alert("Success: Quantity changed to " + data); // show response from the PHP script.
                    $('#send_' + variationId).addClass('d-none'); // hide the button after submission
                    $('#quantity_' + variationId).val(data)
                    $('#success_' + variationId).text("Quantity changed by " + quantity + " to " + data);
                    $('#add_' + variationId).val(0);
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
            if (min_val > price_val || min_val*1.08 < price_val) {
                min.addClass('bg-red');
                min.removeClass('bg-green');
                price.addClass('bg-red');
                price.removeClass('bg-green');
            }else{
                min.removeClass('bg-red');
                price.removeClass('bg-red');
            }
        }

        function submitForm2(event, listingId, marketplaceId, callback) {
            event.preventDefault(); // avoid executing the actual submit of the form.

            var form = $('#change_min_price_' + listingId);
            var actionUrl = "{{ url('listing/update_price') }}/" + listingId;

            // Add marketplace_id to form data if provided
            var formData = form.serialize();
            if (marketplaceId) {
                formData += '&marketplace_id=' + marketplaceId;
            }

            $.ajax({
                type: "POST",
                url: actionUrl,
                data: formData, // serializes the form's elements with marketplace_id.
                success: function(data) {
                    // alert("Success: Min Price changed to " + data); // show response from the PHP script.
                    $('#min_price_' + listingId).addClass('bg-green'); // hide the button after submission
                    // $('quantity_' + listingId).val(data)

                    checkMinPriceDiff(listingId);

                    // Call callback if provided
                    if (callback && typeof callback === 'function') {
                        callback();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert("Error: " + textStatus + " - " + errorThrown);
                    // Call callback even on error to continue processing
                    if (callback && typeof callback === 'function') {
                        callback();
                    }
                }
            });
        }

        function submitForm3(event, listingId, marketplaceId, callback) {
            event.preventDefault(); // avoid executing the actual submit of the form.

            var form = $('#change_price_' + listingId);
            var actionUrl = "{{ url('listing/update_price') }}/" + listingId;

            // Add marketplace_id to form data if provided
            var formData = form.serialize();
            if (marketplaceId) {
                formData += '&marketplace_id=' + marketplaceId;
            }

            $.ajax({
                type: "POST",
                url: actionUrl,
                data: formData, // serializes the form's elements with marketplace_id.
                success: function(data) {
                    // alert("Success: Price changed to " + data); // show response from the PHP script.
                    $('#price_' + listingId).addClass('bg-green'); // hide the button after submission
                    // $('#send_' + listingId).addClass('d-none'); // hide the button after submission
                    // $('quantity_' + listingId).val(data)
                    checkMinPriceDiff(listingId);

                    // Call callback if provided
                    if (callback && typeof callback === 'function') {
                        callback();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert("Error: " + textStatus + " - " + errorThrown);
                    // Call callback even on error to continue processing
                    if (callback && typeof callback === 'function') {
                        callback();
                    }
                }
            });
        }

        function submitForm4(event, variationId, listings, marketplaceId) {
            event.preventDefault(); // avoid executing the actual submit of the form.

            var form = $('#change_all_price_' + variationId);
            var min_price = $('#all_min_price_' + variationId).val();
            var price = $('#all_price_' + variationId).val();

            // Filter listings by marketplace_id if provided
            var listingsToUpdate = listings || [];

            console.log('Listings to update before filter:', listingsToUpdate);
            if (marketplaceId) {
                listingsToUpdate = listings.filter(function(listing) {
                    return listing.marketplace_id == marketplaceId;
                });

                // If no listings found, create one for this marketplace
                if (listingsToUpdate.length === 0) {
                    createListingForMarketplace(variationId, marketplaceId, function(createdListing) {
                        // Add the created listing to the array and proceed
                        listingsToUpdate = [createdListing];
                        applyPriceChanges(listingsToUpdate, min_price, price, marketplaceId, variationId);
                    });
                    return;
                }
            }

            applyPriceChanges(listingsToUpdate, min_price, price, marketplaceId, variationId);
        }

        function applyPriceChanges(listingsToUpdate, min_price, price, marketplaceId, variationId) {
            var updateCount = 0;
            var totalUpdates = 0;

            // Count total updates needed
            listingsToUpdate.forEach(function(listing) {
                if (min_price > 0) totalUpdates++;
                if (price > 0) totalUpdates++;
            });

            if (totalUpdates === 0) {
                // No updates needed, just refresh
                let eurToGbp = {!! json_encode($eur_gbp) !!};
                let m_min_price = 0;
                let m_price = 0;
                if (window.eur_listings[variationId] && window.eur_listings[variationId].length > 0) {
                    m_min_price = Math.min(...window.eur_listings[variationId].map(l => l.min_price || 999999));
                    m_price = Math.min(...window.eur_listings[variationId].map(l => l.price || 999999));
                }
                getListings(variationId, eurToGbp, m_min_price, m_price, 0);
                return;
            }

            listingsToUpdate.forEach(function(listing) {
                if (min_price > 0){
                    $('#min_price_' + listing.id).val(min_price);
                    // Pass marketplace_id to submitForm2
                    submitForm2(event, listing.id, marketplaceId, function() {
                        updateCount++;
                        if (updateCount === totalUpdates) {
                            // Refresh table after all updates
                            let eurToGbp = {!! json_encode($eur_gbp) !!};
                            let m_min_price = 0;
                            let m_price = 0;
                            if (window.eur_listings[variationId] && window.eur_listings[variationId].length > 0) {
                                m_min_price = Math.min(...window.eur_listings[variationId].map(l => l.min_price || 999999));
                                m_price = Math.min(...window.eur_listings[variationId].map(l => l.price || 999999));
                            }
                            getListings(variationId, eurToGbp, m_min_price, m_price, 0);
                        }
                    });
                }
                if (price > 0){
                    $('#price_' + listing.id).val(price);
                    // Pass marketplace_id to submitForm3
                    submitForm3(event, listing.id, marketplaceId, function() {
                        updateCount++;
                        if (updateCount === totalUpdates) {
                            // Refresh table after all updates
                            let eurToGbp = {!! json_encode($eur_gbp) !!};
                            let m_min_price = 0;
                            let m_price = 0;
                            if (window.eur_listings[variationId] && window.eur_listings[variationId].length > 0) {
                                m_min_price = Math.min(...window.eur_listings[variationId].map(l => l.min_price || 999999));
                                m_price = Math.min(...window.eur_listings[variationId].map(l => l.price || 999999));
                            }
                            getListings(variationId, eurToGbp, m_min_price, m_price, 0);
                        }
                    });
                }
            });
        }
        function submitForm5(event, listingId, marketplaceId, callback) {
            event.preventDefault(); // avoid executing the actual submit of the form.

            var form = $('#change_limit_' + listingId);
            var actionUrl = "{{ url('listing/update_limit') }}/" + listingId;

            // Add marketplace_id to form data if provided
            var formData = form.serialize();
            if (marketplaceId) {
                formData += '&marketplace_id=' + marketplaceId;
            }

            $.ajax({
                type: "POST",
                url: actionUrl,
                data: formData, // serializes the form's elements with marketplace_id.
                success: function(data) {
                    // alert("Success: Min Price changed to " + data); // show response from the PHP script.
                    $('#min_price_limit_' + listingId).addClass('bg-green'); // hide the button after submission
                    $('#price_limit_' + listingId).addClass('bg-green'); // hide the button after submission
                    // $('quantity_' + listingId).val(data)

                    checkMinPriceDiff(listingId);

                    // Call callback if provided
                    if (callback && typeof callback === 'function') {
                        callback();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert("Error: " + textStatus + " - " + errorThrown);
                    // Call callback even on error to continue processing
                    if (callback && typeof callback === 'function') {
                        callback();
                    }
                }
            });
        }
        function submitForm6(event, listingId) {
            event.preventDefault(); // avoid executing the actual submit of the form.

            var form = $('#change_target_' + listingId);
            var actionUrl = "{{ url('listing/update_target') }}/" + listingId;

            $.ajax({
                type: "POST",
                url: actionUrl,
                data: form.serialize(), // serializes the form's elements.
                success: function(data) {
                    // alert("Success: Min Price changed to " + data); // show response from the PHP script.
                    $('#target_' + listingId).addClass('bg-green'); // hide the button after submission
                    $('#percent_' + listingId).addClass('bg-green'); // hide the button after submission
                    // $('quantity_' + listingId).val(data)
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert("Error: " + textStatus + " - " + errorThrown);
                }
            });
        }
        function submitForm7(event, variationId) {
            event.preventDefault(); // avoid executing the actual submit of the form.

            var form = $('#bulk_target_' + variationId);
            var listing_ids = $('#listing_ids_' + variationId).val();


            ids = listing_ids.split(',');

            ids.forEach(function(id) {
                var actionUrl = "{{ url('listing/update_target') }}/" + id;
                $.ajax({
                    type: "POST",
                    url: actionUrl,
                    data: form.serialize(), // serializes the form's elements.
                    success: function(data) {
                        // alert("Success: Min Price changed to " + data); // show response from the PHP script.
                        $('#target_price_' + variationId).addClass('bg-green'); // hide the button after submission
                        $('#target_percentage_' + variationId).addClass('bg-green'); // hide the button after submission
                        // $('quantity_' + listingId).val(data)
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        alert("Error: " + textStatus + " - " + errorThrown);
                    }
                });
            });



        }

        function submitForm8(event, variationId, listings, marketplaceId) {
            event.preventDefault(); // avoid executing the actual submit of the form.

            var form = $('#change_all_handler_' + variationId);
            var min_price = $('#all_min_handler_' + variationId).val();
            var price = $('#all_handler_' + variationId).val();

            // Filter listings by marketplace_id if provided
            var listingsToUpdate = listings || [];
            if (marketplaceId) {
                listingsToUpdate = listings.filter(function(listing) {
                    return listing.marketplace_id == marketplaceId;
                });

                // If no listings found, create one for this marketplace
                if (listingsToUpdate.length === 0) {
                    createListingForMarketplace(variationId, marketplaceId, function(createdListing) {
                        // Add the created listing to the array and proceed
                        listingsToUpdate = [createdListing];
                        applyHandlerChanges(listingsToUpdate, min_price, price, marketplaceId, variationId);
                    });
                    return;
                }
            }

            applyHandlerChanges(listingsToUpdate, min_price, price, marketplaceId, variationId);
        }

        function applyHandlerChanges(listingsToUpdate, min_price, price, marketplaceId, variationId) {
            var updateCount = 0;
            var totalCount = listingsToUpdate.length;

            listingsToUpdate.forEach(function(listing) {
                if (min_price > 0){
                    $('#min_price_limit_' + listing.id).val(min_price);
                }
                if (price > 0){
                    $('#price_limit_' + listing.id).val(price);
                }
                // Pass marketplace_id to submitForm5
                submitForm5(event, listing.id, marketplaceId, function() {
                    updateCount++;
                    // Refresh table after all updates are complete
                    if (updateCount === totalCount) {
                        // Get variation details to refresh the table
                        let eurToGbp = {!! json_encode($eur_gbp) !!};
                        let m_min_price = 0;
                        let m_price = 0;
                        if (window.eur_listings[variationId] && window.eur_listings[variationId].length > 0) {
                            m_min_price = Math.min(...window.eur_listings[variationId].map(l => l.min_price || 999999));
                            m_price = Math.min(...window.eur_listings[variationId].map(l => l.price || 999999));
                        }
                        getListings(variationId, eurToGbp, m_min_price, m_price, 0);
                    }
                });
            });
        }

        function createListingForMarketplace(variationId, marketplaceId, callback) {
            $.ajax({
                url: "{{ url('listing/get_or_create_listing') }}/" + variationId + "/" + marketplaceId,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.listing) {
                        // Add to eur_listings array
                        window.eur_listings[variationId] = window.eur_listings[variationId] || [];
                        window.eur_listings[variationId].push(response.listing);

                        // Create form elements for the new listing if they don't exist
                        var csrfToken = '{{ csrf_token() }}';
                        if (!$('#change_limit_' + response.listing.id).length) {
                            // Create form for handler limits
                            $('body').append('<form class="form-inline" method="POST" id="change_limit_' + response.listing.id + '" style="display:none;"><input type="hidden" name="_token" value="' + csrfToken + '"><input type="submit" hidden></form>');
                        }
                        if (!$('#change_min_price_' + response.listing.id).length) {
                            // Create form for min price
                            $('body').append('<form class="form-inline" method="POST" id="change_min_price_' + response.listing.id + '" style="display:none;"><input type="hidden" name="_token" value="' + csrfToken + '"><input type="submit" hidden></form>');
                        }
                        if (!$('#change_price_' + response.listing.id).length) {
                            // Create form for price
                            $('body').append('<form class="form-inline" method="POST" id="change_price_' + response.listing.id + '" style="display:none;"><input type="hidden" name="_token" value="' + csrfToken + '"><input type="submit" hidden></form>');
                        }

                        callback(response.listing);
                    } else {
                        alert('Error creating listing: ' + (response.error || 'Unknown error'));
                    }
                },
                error: function(xhr) {
                    alert('Error creating listing: ' + xhr.responseText);
                }
            });
        }

        function submitForm4ForMarketplace(event, variationId, listings, marketplaceId) {
            event.preventDefault();

            // Filter listings by marketplace_id
            var filteredListings = listings.filter(function(listing) {
                return listing.marketplace_id == marketplaceId;
            });

            if (filteredListings.length === 0) {
                alert('No listings found for this marketplace');
                return;
            }

            // Call submitForm4 with filtered listings
            submitForm4(event, variationId, filteredListings);
        }

        function populateHandlerDropdownOnClick(variationId) {
            // Populate dropdown when button is clicked - show ALL marketplaces
            var marketplaces = window.marketplaces || {};
            populateHandlerDropdown(variationId, null, marketplaces);
        }

        function populatePriceDropdownOnClick(variationId) {
            // Populate dropdown when button is clicked - show ALL marketplaces
            var marketplaces = window.marketplaces || {};
            populatePriceDropdown(variationId, null, marketplaces);
        }

        function populateHandlerDropdown(variationId, listings, marketplaces) {
            var dropdownMenu = $('#change_handler_menu_' + variationId);
            if (!dropdownMenu.length) {
                console.log('Handler dropdown not found for variation:', variationId);
                return; // Dropdown doesn't exist yet
            }
            dropdownMenu.empty();

            // Use global marketplaces if not provided
            if (!marketplaces || Object.keys(marketplaces).length === 0) {
                marketplaces = window.marketplaces || {};
            }

            // Show ALL marketplaces, not just those with listings
            if (!marketplaces || Object.keys(marketplaces).length === 0) {
                dropdownMenu.append('<li><span class="dropdown-item-text">No marketplaces available</span></li>');
                return;
            }

            // Add "All Marketplaces" option (no marketplace_id filter)
            dropdownMenu.append('<li><a class="dropdown-item" href="#" onclick="event.preventDefault(); submitForm8(event, ' + variationId + ', window.eur_listings[' + variationId + '] || []);">All Marketplaces</a></li>');
            dropdownMenu.append('<li><hr class="dropdown-divider"></li>');

            // Add ALL marketplace options (not filtered by listings)
            Object.keys(marketplaces).forEach(function(marketplaceId) {
                var marketplace = marketplaces[marketplaceId];
                if (marketplace && marketplace.name) {
                    var marketplaceName = marketplace.name;
                    // Pass marketplace_id to submitForm8
                    dropdownMenu.append('<li><a class="dropdown-item" href="#" onclick="event.preventDefault(); submitForm8(event, ' + variationId + ', window.eur_listings[' + variationId + '] || [], ' + marketplaceId + ');">' + marketplaceName + '</a></li>');
                }
            });
        }

        function populatePriceDropdown(variationId, listings, marketplaces) {
            var dropdownMenu = $('#change_price_menu_' + variationId);
            if (!dropdownMenu.length) {
                console.log('Price dropdown not found for variation:', variationId);
                return; // Dropdown doesn't exist yet
            }
            dropdownMenu.empty();

            // Use global marketplaces if not provided
            if (!marketplaces || Object.keys(marketplaces).length === 0) {
                marketplaces = window.marketplaces || {};
            }

            // Show ALL marketplaces, not just those with listings
            if (!marketplaces || Object.keys(marketplaces).length === 0) {
                dropdownMenu.append('<li><span class="dropdown-item-text">No marketplaces available</span></li>');
                return;
            }

            // Add "All Marketplaces" option (no marketplace_id filter)
            dropdownMenu.append('<li><a class="dropdown-item" href="#" onclick="event.preventDefault(); submitForm4(event, ' + variationId + ', window.eur_listings[' + variationId + '] || []);">All Marketplaces</a></li>');
            dropdownMenu.append('<li><hr class="dropdown-divider"></li>');

            // Add ALL marketplace options (not filtered by listings)
            Object.keys(marketplaces).forEach(function(marketplaceId) {
                var marketplace = marketplaces[marketplaceId];
                if (marketplace && marketplace.name) {
                    var marketplaceName = marketplace.name;
                    // Pass marketplace_id to submitForm4
                    dropdownMenu.append('<li><a class="dropdown-item" href="#" onclick="event.preventDefault(); submitForm4(event, ' + variationId + ', window.eur_listings[' + variationId + '] || [], ' + marketplaceId + ');">' + marketplaceName + '</a></li>');
                }
            });
        }
        function updateAverageCost(variationId, prices) {
            if (prices.length > 0) {
                let average = prices.reduce((a, b) => parseFloat(a) + parseFloat(b), 0) / prices.length;
                $(`#average_cost_${variationId}`).text(`€${average.toFixed(2)}`);
                $('#best_price_'+variationId).text(`${((parseFloat(average)+20)/0.88).toFixed(2)}`);
            } else {
                $(`#average_cost_${variationId}`).text('€0.00');
                // $('#best_price_'+variationId).text('€0.00');
            }
        }
        function fetchUpdatedQuantity(variationId, bm) {
            let params = {
                csrf: "{{ csrf_token() }}"
            };
            let queryString = $.param(params);
            return $.ajax({
                url: `{{ url('listing/get_updated_quantity') }}/${variationId}?${queryString}`,
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
            let params = {
                // csrf: "{{ csrf_token() }}"
            };
            let queryString = $.param(params);
            $.ajax({
                url: "{{ url('listing/get_variation_available_stocks') }}/" + variationId+"?"+queryString,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    datass = '';
                    count = 0;
                    data.stocks.forEach(function(item, index) {
                        count++;
                        // console.log(data.stock_costs[item.id]);
                        let price = data.stock_costs[item.id];
                        let vendor = data.vendors[data.po[item.order_id]];
                        let reference_id = data.reference[item.order_id];
                        let topup_ref = data.topup_reference[data.latest_topup_items[item.id]];
                        stockPrices.push(price);
                        // Load stock cost via AJAX
                        datass += `
                            <tr>
                                <td>${index + 1}</td>
                                <td data-stock="${item.id}" title="${topup_ref}">
                                    <a href="{{ url('imei?imei=') }}${item.imei ?? item.serial_number}" target="_blank">
                                        ${item.imei ?? item.serial_number ?? ''}
                                    </a>
                                </td>
                                <td id="cost_${item.id}" title="${reference_id}">€${price} (${vendor})</td>

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

        function getListings(variationId, eurToGbp, m_min_price, m_price, check = 0){

            let listingsTable = '';
            let countries = {!! json_encode($countries) !!};
            let exchange_rates = {!! json_encode($exchange_rates) !!};
            let currencies = {!! json_encode($currencies) !!};
            let currency_sign = {!! json_encode($currency_sign) !!};
            let params = {
                csrf: "{{ csrf_token() }}"
            };
            let queryString = $.param(params);
            $.ajax({
                url: "{{ url('listing/get_competitors') }}/" + variationId+"/"+check+"?"+queryString,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    listingsTable += data.error ? `<tr><td colspan="8">${data.error}</td></tr>` : '';
                    // Clear and repopulate eur_listings for this variation when listings are loaded via AJAX
                    window.eur_listings[variationId] = [];

                    data.listings.forEach(function(listing) {
                        let best_price = $('#best_price_'+variationId).text().replace('€', '') ?? 0;
                        let exchange_rates_2 = exchange_rates;
                        let currencies_2 = currencies;
                        let currency_sign_2 = currency_sign;
                        let p_append = '';
                        let pm_append = '';
                        let pm_append_title = '';
                        let possible = 0;
                        let classs = '';
                        let cost = 0;
                        if (listing.currency_id == 5) {
                            // p_append = 'France: £'+(parseFloat(m_price)*parseFloat(eurToGbp)).toFixed(2);
                            // pm_append = 'France: £'+(parseFloat(m_min_price)*parseFloat(eurToGbp)).toFixed(2);
                        }
                        if (listing.currency_id != 4) {

                            let rates = exchange_rates_2[currencies_2[listing.currency_id]];
                            p_append = 'Fr: '+currency_sign_2[listing.currency_id]+(parseFloat(m_price)*parseFloat(rates)).toFixed(2);
                            pm_append = 'Fr: '+currency_sign_2[listing.currency_id]+(parseFloat(m_min_price)*parseFloat(rates)).toFixed(2);
                            pm_append_title = 'Break Even: '+currency_sign_2[listing.currency_id]+(parseFloat(best_price)*parseFloat(rates)).toFixed(2);
                        } else {
                            // Add EUR listings to eur_listings array when loaded via AJAX
                            window.eur_listings[variationId] = window.eur_listings[variationId] || [];
                            window.eur_listings[variationId].push(listing);
                        }
                        // if(listing.target_price > 0 && listing.target_percentage > 0){
                        //     cost = $('#average_cost_'+variationId).text().replace('€', '');
                        //     target = ((parseFloat(cost)+20)/ ((100-parseFloat(listing.target_percentage))/100));
                        //     if(target <= listing.target_price){
                        //         possible = 1;
                        //     }
                        //     if(listing.target_price >= listing.min_price && listing.target_price <= listing.price && listing.target_price >= listing.buybox_price){
                        //         classs = 'bg-lightgreen';
                        //     }
                        // }

                        let buybox_button = '';
                        if (listing.buybox !== 1 && listing.buybox_price > 0) {
                            buybox_button = `<button class="${(best_price > 0 && best_price < listing.buybox_price) ? 'btn btn-success' : 'btn btn-warning'}" id="get_buybox_${listing.id}" onclick="getBuybox(${listing.id}, ${variationId}, ${listing.buybox_price})">
                                        Get Buybox
                                    </button>`;
                        }

                        // Get marketplace name
                        let marketplaceName = 'N/A';
                        if (listing.marketplace_id && window.marketplaces && window.marketplaces[listing.marketplace_id]) {
                            marketplaceName = window.marketplaces[listing.marketplace_id].name || 'Marketplace ' + listing.marketplace_id;
                        } else if (listing.marketplace && listing.marketplace.name) {
                            marketplaceName = listing.marketplace.name;
                        }

                        listingsTable += `
                            <tr class="${classs}" ${listing.buybox !== 1 ? 'style="background: pink;"' : ''}>
                                <td title="${listing.id} ${countries[listing.country].title}">
                                    <a href="https://www.backmarket.${countries[listing.country].market_url}/${countries[listing.country].market_code}/p/gb/${listing._2_2}" target="_blank">
                                    <img src="{{ asset('assets/img/flags/') }}/${countries[listing.country].code.toLowerCase()}.svg" height="15">
                                    ${countries[listing.country].code}
                                    </a>
                                </td>
                                <td>
                                    <small>${marketplaceName}</small>
                                </td>
                                <td>
                                    <form class="form-inline" method="POST" id="change_limit_${listing.id}">
                                        @csrf
                                        <input type="submit" hidden>
                                    </form>
                                    <input type="number" class="form-control ${listing.handler_status == 2 ? 'text-danger':''}" id="min_price_limit_${listing.id}" name="min_price_limit" step="0.01" value="${listing.min_price_limit}" form="change_limit_${listing.id}">
                                </td>
                                <td>
                                    <input type="number" class="form-control ${listing.handler_status == 2 ? 'text-danger':''}" id="price_limit_${listing.id}" name="price_limit" step="0.01" value="${listing.price_limit}" form="change_limit_${listing.id}" on>
                                </td>
                                <td>${listing.buybox_price}
                                    <span class="text-danger" title="Buybox Winner Price">
                                        ${listing.buybox !== 1 ? '('+listing.buybox_winner_price+')' : ''}
                                    </span>
                                </td>
                                <td>
                                    <form class="form-inline" method="POST" id="change_min_price_${listing.id}">
                                        @csrf
                                        <input type="submit" hidden>
                                    </form>
                                    <form class="form-inline" method="POST" id="change_price_${listing.id}">
                                        @csrf
                                        <input type="submit" hidden>
                                    </form>
                                    <form class="form-inline" method="POST" id="change_target_${listing.id}">
                                        @csrf
                                        <input type="submit" hidden>
                                    </form>
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="min_price_${listing.id}" name="min_price" step="0.01" value="${listing.min_price}" form="change_min_price_${listing.id}">
                                        <label for="">Min Price</label>
                                    </div>
                                    <span id="pm_append_${listing.id}" title="${pm_append_title}">
                                    ${pm_append}
                                    </span>
                                </td>
                                <td>
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="price_${listing.id}" name="price" step="0.01" value="${listing.price}" form="change_price_${listing.id}">
                                        <label for="">Price</label>
                                    </div>
                                    ${p_append}
                                </td>
                                <td>
                                    ${new Date(listing.updated_at).toLocaleString('en-GB', { timeZone: 'Europe/London', hour12: true })}
                                    ${buybox_button}
                                </td>
                            </tr>`;

                                // <td>
                                //     <div class="form-floating">
                                //         <input type="number" class="form-control" id="target_${listing.id}" name="target" step="0.01" value="${listing.target_price}" form="change_target_${listing.id}">
                                //         <label for="">Target</label>
                                //     </div>
                                //     ${possible == 1 ? '<span class="text-success">Possible</span>' : ''}
                                // </td>
                                // <td>
                                //     <div class="form-floating">
                                //         <input type="number" class="form-control" id="percent_${listing.id}" name="percent" step="0.01" value="${listing.target_percentage}" form="change_target_${listing.id}">
                                //         <label for="">%</label>
                                //     </div>
                                // </td>
                        $(document).ready(function() {
                            $("#change_min_price_" + listing.id).on('submit', function(e) {
                                submitForm2(e, listing.id);
                            });

                            $("#change_price_" + listing.id).on('submit', function(e) {
                                submitForm3(e, listing.id);
                            });
                            $('#change_limit_'+listing.id).on('submit', function(e) {
                                submitForm5(e, listing.id);
                            });
                            $('#change_target_'+listing.id).on('submit', function(e) {
                                submitForm6(e, listing.id);
                            });
                        });
                    });
                    $('#listings_'+variationId).html(listingsTable);

                    // Dropdowns will be populated on click - no need to repopulate here
                    // console.log(data);
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                }
            });
        }

        function getVariationDetails(variationId, eurToGbp, m_min_price, m_price, check = 0) {
            getListings(variationId, eurToGbp, m_min_price, m_price, check);
        }
        // Make eur_listings and marketplaces global so onclick handlers can access it
        window.eur_listings = [];
        window.marketplaces = {!! json_encode($marketplaces ?? []) !!};

        // Debug: log marketplaces to console
        console.log('Marketplaces loaded:', window.marketplaces);

        $(document).ready(function() {
            $('.select2').select2();


            let storages = {!! json_encode($storages) !!};
            let colors = {!! json_encode($colors) !!};
            let grades = {!! json_encode($grades) !!};
            let eurToGbp = {!! json_encode($eur_gbp) !!};

            window.fetchVariations = function(page = 1) {
                let params = buildListingFilters({ page: page });

                // Convert params object to a query string
                let queryString = $.param(params);

                // Append query string to the URL
                let url = "{{ url('listing/get_variations') }}" + '?' + queryString;

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
            };

            $('#exportListingsBtn').on('click', function () {
                let params = buildListingFilters();
                let queryString = $.param(params);
                window.open("{{ url('listing/export') }}" + '?' + queryString, '_blank');
            });

            let page = new URL(window.location.href).searchParams.get('page') || 1;
            fetchVariations(page); // Fetch variations on page load

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
                // change the URL to reflect the current page
                window.history.pushState("", "", `?page=${page}`);
                // Move to top of page
                window.scrollTo(0, 0);
            });



            function displayVariations(variations) {
                let variation_ids = variations.data.map(variation => variation.id);
                let countries = {!! json_encode($countries) !!}; // Make countries available in this scope
                let marketplaces = {!! json_encode($marketplaces) !!}; // Make marketplaces available in this scope

                let variationsContainer = $('#variations'); // The container where data will be displayed
                variationsContainer.empty(); // Clear any existing content
                // console.log(variations);
                $('#page_info').text(`From ${variations.from} To ${variations.to} Out Of ${variations.total}`);
                // Check if there's data
                if (variations.data.length > 0) {
                    variations.data.forEach(function(variation) {

                        // load("{{ url('listing/get_competitors')}}/${variation.id}");
                        // let withBuybox = '';
                        let withoutBuybox = '';
                        let stocksTable = '';
                        let listingsTable = '';
                        let stockPrices = [];
                        let listedStock = fetchUpdatedQuantity(variation.id);
                        let m_min_price = Math.min(...variation.listings.filter(listing => listing.country === 73).map(listing => listing.min_price));
                        let m_price = Math.min(...variation.listings.filter(listing => listing.country === 73).map(listing => listing.price));
                        let exchange_rates = {!! json_encode($exchange_rates) !!};
                        let currencies = {!! json_encode($currencies) !!};
                        let currency_sign = {!! json_encode($currency_sign) !!};

                        switch (variation.state) {
                            case 0:
                                state = 'Missing price or comment';
                                break;
                            case 1:
                                state = 'Pending validation';
                                break;
                            case 2:
                                state = 'Online';
                                break;
                            case 3:
                                state = 'Offline';
                                break;
                            case 4:
                                state = 'Deactivated';
                                break;
                            default:
                                state = 'Unknown';
                        }

                        getStocks(variation.id);
                        // $('#open_all_variations').on('click', function() {
                        //     getVariationDetails(variation.id, eurToGbp, m_min_price, m_price, 1)
                        // });
                        variation.listings.forEach(function(listing) {
                            let best_price = $('#best_price_'+variation.id).text().replace('€', '');
                            let exchange_rates_2 = exchange_rates;
                            let currencies_2 = currencies;
                            let currency_sign_2 = currency_sign;
                            let p_append = '';
                            let pm_append = '';
                            let pm_append_title = '';
                            let possible = 0;
                            let classs = '';
                            let cost = 0;
                            // if (listing.currency_id == 5) {
                            //     p_append = 'France: £'+(m_price*eurToGbp).toFixed(2);
                            //     pm_append = 'France: £'+(m_min_price*eurToGbp).toFixed(2);
                            if (listing.currency_id != 4) {

                                let rates = exchange_rates_2[currencies_2[listing.currency_id]];
                                p_append = 'Fr: '+currency_sign_2[listing.currency_id]+(parseFloat(m_price)*parseFloat(rates)).toFixed(2);
                                pm_append = 'Fr: '+currency_sign_2[listing.currency_id]+(parseFloat(m_min_price)*parseFloat(rates)).toFixed(2);
                                pm_append_title = 'Break Even: '+currency_sign_2[listing.currency_id]+(parseFloat(best_price)*parseFloat(rates)).toFixed(2);

                            }else{
                                window.eur_listings[variation.id] = window.eur_listings[variation.id] || [];
                                window.eur_listings[variation.id].push(listing);

                            }
                            let name = listing.name;
                            if (name != null) {
                                name = name.replace(/ /g,'-');
                            }
                            if(listing.buybox == 1){
                                // withBuybox += `<a href="https://www.backmarket.${listing.country_id.market_url}/${listing.country_id.market_code}/p/gb/${listing._2}" target="_blank" class="btn btn-link border p-1 m-1">
                                //     <img src="{{ asset('assets/img/flags/') }}/${listing.country_id.code.toLowerCase()}.svg" height="10">
                                //     ${listing.country_id.code}
                                // </a>`;
                            }else{
                                withoutBuybox += `<a href="https://www.backmarket.${listing.country_id.market_url}/${listing.country_id.market_code}/p/gb/${listing._2_2}" target="_blank" class="btn btn-link text-danger border border-danger p-1 m-1">
                                        <img src="{{ asset('assets/img/flags/') }}/${listing.country_id.code.toLowerCase()}.svg" height="10">
                                        ${listing.country_id.code}
                                        </a>`;
                            }
                            if(listing.target_price > 0 && listing.target_percentage > 0){
                                cost = $('#average_cost_'+variationId).text().replace('€', '');
                                target = ((parseFloat(cost)+20)/ ((100-parseFloat(listing.target_percentage))/100));
                                if(target <= listing.target_price){
                                    possible = 1;
                                }
                                if(listing.target_price >= listing.min_price && listing.target_price <= listing.price && listing.target_price >= listing.buybox_price){
                                    classs = 'bg-lightgreen';
                                }
                            }
                            // Get marketplace name for initial display
                            let marketplaceNameDisplay = 'N/A';
                            if (listing.marketplace_id && marketplaces && marketplaces[listing.marketplace_id]) {
                                marketplaceNameDisplay = marketplaces[listing.marketplace_id].name || 'Marketplace ' + listing.marketplace_id;
                            } else if (listing.marketplace && listing.marketplace.name) {
                                marketplaceNameDisplay = listing.marketplace.name;
                            }

                            listingsTable += `
                                <tr class="${classs}" ${listing.buybox !== 1 ? 'style="background: pink;"' : ''}>
                                    <td title="${listing.id} ${listing.country_id.title}">
                                        <a href="https://www.backmarket.${listing.country_id.market_url}/${listing.country_id.market_code}/p/gb/${listing.reference_uuid_2}" target="_blank">
                                        <img src="{{ asset('assets/img/flags/') }}/${listing.country_id.code.toLowerCase()}.svg" height="15">
                                        ${listing.country_id.code}
                                        </a>
                                    </td>
                                    <td>
                                        <small>${marketplaceNameDisplay}</small>
                                    </td>
                                    <td>
                                        <form class="form-inline" method="POST" id="change_limit_${listing.id}">
                                            @csrf
                                            <input type="submit" hidden>
                                        </form>
                                        <input type="number" class="form-control ${listing.handler_status == 2 ? 'text-danger':''}" id="min_price_limit_${listing.id}" name="min_price_limit" step="0.01" value="${listing.min_price_limit}" form="change_limit_${listing.id}">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control ${listing.handler_status == 2 ? 'text-danger':''}" id="price_limit_${listing.id}" name="price_limit" step="0.01" value="${listing.price_limit}" form="change_limit_${listing.id}">
                                    </td>
                                    <td>${listing.buybox_price}
                                        <span class="text-danger" title="Buybox Winner Price">
                                            ${listing.buybox !== 1 ? '('+listing.buybox_winner_price+')' : ''}
                                        </span>
                                    </td>
                                    <td>
                                        <form class="form-inline" method="POST" id="change_min_price_${listing.id}">
                                            @csrf
                                            <input type="submit" hidden>
                                        </form>
                                        <form class="form-inline" method="POST" id="change_price_${listing.id}">
                                            @csrf
                                            <input type="submit" hidden>
                                        </form>
                                        <form class="form-inline" method="POST" id="change_target_${listing.id}">
                                            @csrf
                                            <input type="submit" hidden>
                                        </form>
                                        <div class="form-floating">
                                            <input type="number" class="form-control" id="min_price_${listing.id}" name="min_price" step="0.01" value="${listing.min_price}" form="change_min_price_${listing.id}">
                                            <label for="">Min Price</label>
                                        </div>
                                        <span id="pm_append_${listing.id}" title="${pm_append_title}">
                                            ${pm_append}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="form-floating">
                                            <input type="number" class="form-control" id="price_${listing.id}" name="price" step="0.01" value="${listing.price}" form="change_price_${listing.id}">
                                            <label for="">Price</label>
                                        </div>
                                        ${p_append}
                                    </td>
                                    <td>${new Date(listing.updated_at).toLocaleString('en-GB', { timeZone: 'Europe/London', hour12: true })}</td>
                                </tr>`;

                                    // <td>
                                    //     <div class="form-floating">
                                    //         <input type="number" class="form-control" id="target_${listing.id}" name="target" step="0.01" value="${listing.target_price}" form="change_target_${listing.id}">
                                    //         <label for="">Target</label>
                                    //     </div>
                                    // ${possible == 1 ? '<span class="text-success">Possible</span>' : ''}
                                    // </td>
                                    // <td>
                                    //     <div class="form-floating">
                                    //         <input type="number" class="form-control" id="percent_${listing.id}" name="percent" step="0.01" value="${listing.target_percentage}" form="change_target_${listing.id}">
                                    //         <label for="">%</label>
                                    //     </div>
                                    // </td>
                            $(document).ready(function() {
                                $("#change_min_price_" + listing.id).on('submit', function(e) {
                                    submitForm2(e, listing.id);
                                });

                                $("#change_price_" + listing.id).on('submit', function(e) {
                                    submitForm3(e, listing.id);
                                });

                                $("#change_limit_" + listing.id).on('submit', function(e) {
                                    submitForm5(e, listing.id);
                                });
                                $("#change_target_" + listing.id).on('submit', function(e) {
                                    submitForm6(e, listing.id);
                                });
                            });


                        });

                        // if (variation.duplicates) {
                        //     console.log(variation);
                        // }

                        variationsContainer.append(`
                            <div class="card">
                                <div class="card-header py-0 d-flex justify-content-between">
                                    <div>
                                        <h5>
                                            <a href="{{url('inventory')}}?sku=${variation.sku}" title="View Inventory" target="_blank">
                                                <span style="background-color: ${colors[variation.color]}; width: 30px; height: 16px; display: inline-block;"></span>
                                                ${variation.sku}
                                            </a>
                                            <a href="https://www.backmarket.fr/bo-seller/listings/active?sku=${variation.sku}" title="View BM Ad" target="_blank">- ${variation.product.model} ${storages[variation.storage] || ''} ${colors[variation.color] || ''} ${grades[variation.grade] || ''}
                                            </a>
                                        </h5>
                                        <span id="sales_${variation.id}"></span>
                                    </div>

                                    <a href="javascript:void(0)" class="btn btn-link" id="variation_history_${variation.id}" onClick="show_variation_history(${variation.id}, '${variation.sku} ${variation.product.model} ${storages[variation.storage] || ''} ${colors[variation.color] || ''} ${grades[variation.grade] || ''}')" data-bs-toggle="modal" data-bs-target="#modal_history">
                                        <i class="fas fa-history"></i>
                                    </a>

                                    <form class="form-inline wd-150" method="POST" id="add_qty_${variation.id}" action="{{url('listing/add_quantity')}}/${variation.id}">
                                        @csrf
                                        <input type="hidden" name="process_id" value="{{$process_id}}">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" name="stock" id="quantity_${variation.id}" value="${listedStock || 0}" style="width:50px;" disabled>
                                            <label for="">Stock</label>
                                        </div>
                                        <div class="form-floating">
                                            <input type="number" class="form-control" name="stock" id="add_${variation.id}" value="" style="width:60px;" oninput="toggleButtonOnChange(${variation.id}, this)" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'add_'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'add_', true); }">
                                            <label for="">Add</label>
                                        </div>
                                        <button id="send_${variation.id}" class="btn btn-light d-none" onclick="submitForm1(event, ${variation.id})">Push</button>
                                        <span class="text-success" id="success_${variation.id}"></span>
                                    </form>

                                    <div class="text-center">
                                        <h6 class="mb-0">
                                        <a class="" href="{{url('order').'?sku='}}${variation.sku}&status=2" target="_blank">
                                            Pending Order Items: ${variation.pending_orders.length || 0}
                                        </a></h6>
                                        <h6 class="mb-0" id="available_stock_${variation.id}">
                                            <a href="{{url('inventory').'?product='}}${variation.product_id}&storage=${variation.storage}&color=${variation.color}&grade[]=${variation.grade}" target="_blank">
                                                Available: ${variation.available_stocks.length || 0}
                                            </a>
                                        </h6>
                                        <h6 class="mb-0">Difference: ${variation.available_stocks.length - variation.pending_orders.length}</h6>
                                    </div>

                                    <button class="btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#details_${variation.id}" aria-expanded="false" aria-controls="details_${variation.id}" onClick="getVariationDetails(${variation.id}, ${eurToGbp}, ${m_min_price}, ${m_price})">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>

                                </div>
                                <div class="d-flex justify-content-between">

                                    <div class="pt-1">
                                        <h6 class="d-inline">Change&nbsp;All&nbsp;€&nbsp;handlers</h6>
                                        <form class="form-inline" method="POST" id="change_all_handler_${variation.id}">
                                            @csrf
                                            <div class="form-floating d-inline">
                                                <input type="number" class="form-control" id="all_min_handler_${variation.id}" name="all_min_handler" step="0.01" value="" style="width:80px;" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'all_min_handler_'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'all_min_handler_', true); }">
                                                <label for="">Min&nbsp;Handler</label>
                                            </div>
                                            <div class="form-floating d-inline">
                                                <input type="number" class="form-control" id="all_handler_${variation.id}" name="all_handler" step="0.01" value="" style="width:80px;" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'all_handler_'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'all_handler_', true); }">
                                                <label for="">Handler</label>
                                            </div>
                                            <div class="btn-group d-inline">
                                                <button type="button" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="change_handler_dropdown_${variation.id}" onclick="populateHandlerDropdownOnClick(${variation.id})">
                                                    Change
                                                </button>
                                                <ul class="dropdown-menu" id="change_handler_menu_${variation.id}">
                                                    <li><span class="dropdown-item-text">Loading...</span></li>
                                                </ul>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="pt-1">
                                        <h6 class="d-inline">Change&nbsp;All&nbsp;€&nbsp;prices</h6>
                                        <form class="form-inline" method="POST" id="change_all_price_${variation.id}">
                                            @csrf
                                            <div class="form-floating d-inline">
                                                <input type="number" class="form-control" id="all_min_price_${variation.id}" name="all_min_price" step="0.01" value="" style="width:80px;" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'all_min_price_'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'all_min_price_', true); }">
                                                <label for="">Min&nbsp;Price</label>
                                            </div>
                                            <div class="form-floating d-inline">
                                                <input type="number" class="form-control" id="all_price_${variation.id}" name="all_price" step="0.01" value="" style="width:80px;" onkeydown="if(event.ctrlKey && event.key === 'ArrowDown') { event.preventDefault(); moveToNextInput(this, 'all_price_'); } else if(event.ctrlKey && event.key === 'ArrowUp') { event.preventDefault(); moveToNextInput(this, 'all_price_', true); }">
                                                <label for="">Price</label>
                                            </div>
                                            <div class="btn-group d-inline">
                                                <button type="button" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="change_price_dropdown_${variation.id}" onclick="populatePriceDropdownOnClick(${variation.id})">
                                                    Push
                                                </button>
                                                <ul class="dropdown-menu" id="change_price_menu_${variation.id}">
                                                    <li><span class="dropdown-item-text">Loading...</span></li>
                                                </ul>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="pt-3">
                                        <h6 class="d-inline">Without&nbsp;Buybox</h6>
                                        ${withoutBuybox}
                                    </div>
                                    <div class="pt-4">
                                        <h6 class="badge bg-light text-dark">
                                            ${state}
                                        </h6>
                                    </div>
                                </div>
                                <div class="card-body p-2 collapse multi_collapse" id="details_${variation.id}">
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
                                                        <th width="80"><small><b>Country</b></small></th>
                                                        <th width="100"><small><b>Marketplace</b></small></th>
                                                        <th width="100" title="Minimum Price Handler"><small><b>Min Hndlr</b></small></th>
                                                        <th width="100" title="Price Handler"><small><b>Price Hndlr</b></small></th>
                                                        <th width="80"><small><b>BuyBox</b></small></th>
                                                        <th title="Min Price" width="120"><small><b>Min </b>(€<b id="best_price_${variation.id}"></b>)</small></th>
                                                        <th width="120"><small><b>Price</b></small></th>
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
                                                        // <th width="120"><small><b>Target</b></small></th>
                                                        // <th width="80"><small><b>%</b></small></th>
                                                        // <th title="Buybox Winner Price"><small><b>Winner</b></small></th>

                        $("#change_qty_"+variation.id).submit(function(e) {
                            submitForm(e, variation.id);
                        });
                        $('#sales_'+variation.id).load("{{ url('listing/get_sales') . '/'}}"+variation.id+"?csrf={{ csrf_token() }}");

                        $(document).ready(function() {

                            // Remove form submit handlers since we're using dropdowns now
                            // The dropdowns will handle submissions directly

                            // Dropdowns will be populated on click via onclick handlers
                            // No need to pre-populate since we show ALL marketplaces
                        });
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
