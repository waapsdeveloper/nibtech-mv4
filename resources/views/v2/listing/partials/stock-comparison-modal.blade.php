{{-- Stock Comparison Modal --}}
<div class="modal fade" id="stockComparisonModal" tabindex="-1" aria-labelledby="stockComparisonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="flex-grow-1">
                    <h5 class="modal-title mb-1" id="stockComparisonModalLabel">Stock Comparison</h5>
                    <small class="text-muted" id="stockComparisonVariationHeading">Loading variation details...</small>
                </div>
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
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0">
                                    <tbody>
                                        <tr>
                                            <th class="text-end" style="width: 50%;">Total Stock (System):</th>
                                            <td class="text-center">
                                                <span class="badge bg-info fs-6" id="comparisonTotalStock">0</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-2">
                                <small class="text-muted">This is the total stock quantity we have for this variation across all marketplaces</small>
                            </div>
                        </div>
                    </div>
                    
                    {{-- API Stock vs Our Stock Comparison --}}
                    <div class="card mb-3" id="apiComparisonCard" style="display: none;">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">Backmarket API vs Our Stock</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width: 33.33%;">API Stock</th>
                                            <th class="text-center" style="width: 33.33%;">Our Available Stock</th>
                                            <th class="text-center" style="width: 33.33%;">Difference</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="text-center">
                                                <span class="badge bg-info fs-6" id="comparisonApiStock">-</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success fs-6" id="comparisonOurStock">-</span>
                                            </td>
                                            <td class="text-center" id="comparisonDifference">
                                                <span class="badge fs-6" id="comparisonDiffValue">0</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
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
                <button type="button" class="btn btn-warning" id="fixStockMismatchBtn" onclick="fixStockMismatch()" style="display: none;">
                    <i class="fas fa-wrench me-1"></i>Fix Stock Mismatch
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
{{-- /Stock Comparison Modal --}}

