{{-- Stock Comparison Modal --}}
<div class="modal fade" id="stockComparisonModal" tabindex="-1" aria-labelledby="stockComparisonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stockComparisonModalLabel">Stock Comparison</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="stockComparisonLoading" class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading stock comparison data...</p>
                </div>
                <div id="stockComparisonContent" style="display: none;">
                    <div class="mb-3">
                        <strong>Variation SKU:</strong> <span id="comparisonVariationSku"></span>
                    </div>
                    
                    {{-- Total Stock Summary --}}
                    <div class="card mb-3">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">Total Stock We Have</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><strong>Total Stock (System):</strong></span>
                                <span class="badge bg-info fs-6" id="comparisonTotalStock">0</span>
                            </div>
                            <small class="text-muted">This is the total stock quantity we have for this variation across all marketplaces</small>
                        </div>
                    </div>
                    
                    {{-- API Stock vs Our Stock Comparison --}}
                    <div class="card mb-3" id="apiComparisonCard" style="display: none;">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">Backmarket API vs Our Stock</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><strong>API Stock:</strong></span>
                                        <span class="badge bg-info" id="comparisonApiStock">-</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><strong>Our Available Stock:</strong></span>
                                        <span class="badge bg-success" id="comparisonOurStock">-</span>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-warning mb-0 mt-2" id="comparisonDifference">
                                <strong>Difference:</strong> <span id="comparisonDiffValue">0</span>
                            </div>
                        </div>
                    </div>

                    {{-- Marketplace Stock Breakdown --}}
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0">Marketplace Stock Breakdown</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Marketplace</th>
                                            <th class="text-center" title="Stock allocated to this marketplace">Listed Stock</th>
                                            <th class="text-center" title="Available for sale (Listed - Locked)">Available Stock</th>
                                            <th class="text-center" title="Reserved/Pending orders">Locked Stock</th>
                                            <th class="text-center">Listings</th>
                                        </tr>
                                    </thead>
                                    <tbody id="comparisonMarketplaceTableBody">
                                        <!-- Data will be populated here -->
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-info">
                                            <th>Total</th>
                                            <th class="text-center" id="comparisonTotalListed">0</th>
                                            <th class="text-center" id="comparisonTotalAvailable">0</th>
                                            <th class="text-center" id="comparisonTotalLocked">0</th>
                                            <th class="text-center" id="comparisonTotalListings">0</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="stockComparisonError" class="alert alert-danger" style="display: none;">
                    <strong>Error:</strong> <span id="stockComparisonErrorMessage"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
{{-- /Stock Comparison Modal --}}

