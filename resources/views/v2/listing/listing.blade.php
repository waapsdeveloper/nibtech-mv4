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
            <h4>{{ $title_page ?? 'Listings V2' }}</h4>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboards</a></li>
                <li class="breadcrumb-item active" aria-current="page">Listings V2</li>
            </ol>
        </div>
    </div>
    <!-- /Breadcrumb -->

    @if(isset($variations) && $variations->count() > 0)
        @foreach($variations as $variation)
            @include('v2.listing.partials.variation-card', [
                'variation' => $variation,
                'colors' => $colors,
                'storages' => $storages,
                'grades' => $grades,
                'marketplaces' => $marketplaces ?? [],
                'process_id' => $process_id ?? null
            ])
        @endforeach

        {{-- Pagination --}}
        <div class="d-flex justify-content-center">
            {{ $variations->links() }}
        </div>
    @else
        <div class="card">
            <div class="card-body">
                <p class="text-muted">No variations found.</p>
            </div>
        </div>
    @endif

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
    function show_variation_history(variationId, variationName) {
        $('#variationHistoryModal').modal('show');

        $('#variation_name').text(variationName);
        $('#variationHistoryTable').html('Loading...');
        $.ajax({
            url: "{{ url('v2/listings/get_variation_history') }}/" + variationId,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                let historyTable = '';
                if (data.listed_stock_verifications && data.listed_stock_verifications.length > 0) {
                    data.listed_stock_verifications.forEach(function(item) {
                        historyTable += `
                            <tr>
                                <td>${item.process_ref ?? ''}</td>
                                <td>${item.pending_orders ?? ''}</td>
                                <td>${item.qty_from ?? ''}</td>
                                <td>${item.qty_change ?? ''}</td>
                                <td>${item.qty_to ?? ''}</td>
                                <td>${item.admin ?? ''}</td>
                                <td>${item.created_at ? new Date(item.created_at).toLocaleString('en-GB', { timeZone: 'Europe/London', hour12: true }) : ''}</td>
                            </tr>`;
                    });
                } else {
                    historyTable = '<tr><td colspan="7" class="text-center text-muted">No history found</td></tr>';
                }
                $('#variationHistoryTable').html(historyTable);
            },
            error: function(xhr) {
                console.error(xhr.responseText);
                $('#variationHistoryTable').html('<tr><td colspan="7" class="text-center text-danger">Error loading history</td></tr>');
            }
        });
    }
</script>
@endsection
