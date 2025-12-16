@php
    $variations = $variations ?? null;
    $total = $variations ? $variations->total() : 0;
    $from = $variations ? $variations->firstItem() : 0;
    $to = $variations ? $variations->lastItem() : 0;
    $currentPage = $variations ? $variations->currentPage() : 1;
    $perPage = request('per_page', 10);
    $sort = request('sort', 1);
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="card-title mg-b-0" id="page_info">
        @if($variations && $total > 0)
            From {{ $from }} To {{ $to }} Out Of {{ $total }}
        @else
            No results
        @endif
    </h5>
    <div class="d-flex p-2 justify-content-between align-items-center gap-2 flex-wrap">
        <a href="{{ url('listed_stock_verification') }}" class="btn btn-primary btn-sm" id="start_verification">
            Verification
        </a>
        @if(request('special') != 'verify_listing')
            <button class="btn btn-link" type="button" id="open_all_variations">
                Toggle All
            </button>
            <button class="btn btn-success btn-sm" type="button" id="exportListingsBtn">
                Export&nbsp;CSV
            </button>
            <button class="btn btn-link" type="button" data-bs-toggle="modal" data-bs-target="#bulkModal">
                Bulk&nbsp;Update
            </button>
        @endif
        <label for="sortSelect" class="form-label mb-0">Sort:</label>
        <select name="sort" class="form-select w-auto" id="sortSelect" onchange="this.form.submit()" form="filterForm">
            <option value="1" {{ $sort == 1 ? 'selected' : '' }}>Stock DESC</option>
            <option value="2" {{ $sort == 2 ? 'selected' : '' }}>Stock ASC</option>
            <option value="3" {{ $sort == 3 ? 'selected' : '' }}>Name DESC</option>
            <option value="4" {{ $sort == 4 ? 'selected' : '' }}>Name ASC</option>
        </select>
        <label for="perPageSelect" class="form-label mb-0">Per&nbsp;Page:</label>
        <select name="per_page" class="form-select w-auto" id="perPageSelect" onchange="this.form.submit()" form="filterForm">
            <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
            <option value="20" {{ $perPage == 20 ? 'selected' : '' }}>20</option>
            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
            <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
        </select>
    </div>
</div>

