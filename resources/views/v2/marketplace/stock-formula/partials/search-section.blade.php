<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header pb-0">
                <h4 class="card-title mg-b-0">Search and Select Variation</h4>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Search Variation (SKU or Model)</label>
                    <div class="input-group">
                        <input type="text" 
                               id="variation_search_input"
                               class="form-control" 
                               value="{{ $searchTerm ?? '' }}"
                               placeholder="Type at least 2 characters to search...">
                        <button class="btn btn-primary" type="button" id="search_btn">
                            <i class="fe fe-search"></i> Search
                        </button>
                    </div>
                    <small class="text-muted">Type at least 2 characters and press Enter or click Search</small>
                </div>

                <div id="search_results_container" class="mt-3" style="display: none;">
                    <h6>Search Results:</h6>
                    <div id="search_results" class="list-group search-results">
                        <!-- Results will be loaded here via AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

