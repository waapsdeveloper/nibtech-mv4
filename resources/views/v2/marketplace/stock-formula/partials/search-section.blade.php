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

                @php
                    $showDefaultList = !empty($defaultVariationsWithFormula) && $defaultVariationsWithFormula->isNotEmpty() && empty($selectedVariation);
                @endphp
                <div id="search_results_container" class="mt-3" style="{{ $showDefaultList ? '' : 'display: none;' }}">
                    <h6 id="search_results_heading">{{ $showDefaultList ? 'Variations with formula set' : 'Search Results:' }}</h6>
                    <div id="search_results" class="list-group search-results">
                        @if($showDefaultList)
                            @foreach($defaultVariationsWithFormula as $v)
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-start">
                                    <a href="javascript:void(0)" class="flex-grow-1 text-body text-decoration-none" onclick="selectVariation({{ $v['id'] }})">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">{{ $v['sku'] }}</h6>
                                        </div>
                                        <p class="mb-0">
                                            <strong>Model:</strong> {{ $v['model'] }}<br>
                                            <strong>Storage:</strong> {{ $v['storage'] }} |
                                            <strong>Color:</strong> {{ $v['color'] }} |
                                            <strong>Grade:</strong> {{ $v['grade'] }}
                                        </p>
                                    </a>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger ms-2 stock-formula-delete-all-btn"
                                            data-variation-id="{{ $v['id'] }}"
                                            title="Delete formula altogether">
                                        <i class="fe fe-trash-2"></i>
                                    </button>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

