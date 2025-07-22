{{-- filepath: c:\xampp\htdocs\nibritaintech\resources\views\livewire\report-lw.blade.php --}}
<div>
    {{-- Header Section --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Reports (Livewire)</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <div class="btn-group">
                <button wire:click="exportReport('excel')" class="btn btn-primary">
                    <i class="fa fa-file-excel"></i> Export Excel
                </button>
                <button wire:click="exportReport('pdf')" class="btn btn-secondary">
                    <i class="fa fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>
    </div>

    {{-- Filters Section --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Filters</h3>
            <div class="card-options">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                    <i class="fa fa-filter"></i> Toggle Filters
                </button>
            </div>
        </div>
        <div class="collapse show" id="filtersCollapse">
            <div class="card-body">
                <div class="row">
                    {{-- Date Range --}}
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input wire:model.lazy="start_date" type="date" class="form-control" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input wire:model.lazy="end_date" type="date" class="form-control" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Time</label>
                        <input wire:model.lazy="start_time" type="time" class="form-control" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Time</label>
                        <input wire:model.lazy="end_time" type="time" class="form-control" />
                    </div>
                </div>

                <div class="row mt-3">
                    {{-- Product Filters --}}
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select wire:model="category" class="form-select">
                            <option value="">All Categories</option>
                            @foreach($categories as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Brand</label>
                        <select wire:model="brand" class="form-select">
                            <option value="">All Brands</option>
                            @foreach($brands as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Product</label>
                        <select wire:model="product" class="form-select">
                            <option value="">All Products</option>
                            @foreach($products as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Vendor</label>
                        <select wire:model="vendor" class="form-select">
                            <option value="">All Vendors</option>
                            @foreach($vendors as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row mt-3">
                    {{-- Additional Filters --}}
                    <div class="col-md-3">
                        <label class="form-label">Storage</label>
                        <select wire:model="storage" class="form-select">
                            <option value="">All Storage</option>
                            @foreach($storages as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Color</label>
                        <select wire:model="color" class="form-select">
                            <option value="">All Colors</option>
                            @foreach($colors as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Grade</label>
                        <select wire:model="grade" class="form-select">
                            <option value="">All Grades</option>
                            @foreach($grades as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Per Page</label>
                        <select wire:model="per_page" class="form-select">
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Report Type Selection --}}
    <div class="card">
        <div class="card-body">
            <div class="btn-group" role="group">
                <input wire:model="report_type" type="radio" class="btn-check" name="report_type" id="sales_returns" value="sales_returns">
                <label class="btn btn-outline-primary" for="sales_returns">Sales & Returns</label>

                <input wire:model="report_type" type="radio" class="btn-check" name="report_type" id="batch_grades" value="batch_grades">
                <label class="btn btn-outline-primary" for="batch_grades">Batch Grade Report</label>

                <input wire:model="report_type" type="radio" class="btn-check" name="report_type" id="sales_history" value="sales_history">
                <label class="btn btn-outline-primary" for="sales_history">Sales History</label>
            </div>
        </div>
    </div>

    {{-- Loading Indicator --}}
    <div wire:loading class="text-center my-3">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p>Loading report data...</p>
    </div>

    {{-- Content based on report type --}}
    @if($report_type === 'sales_returns')
        @include('livewire.reports.sales-returns')
    @elseif($report_type === 'batch_grades')
        @include('livewire.reports.batch-grades')
    @elseif($report_type === 'sales_history')
        @include('livewire.reports.sales-history')
    @endif
</div>

@push('scripts')
<script>
    // Real-time updates
    Livewire.on('reportUpdated', () => {
        // Handle any post-update actions
        console.log('Report updated');
    });

    // Export functionality
    window.addEventListener('livewire:load', function () {
        Livewire.on('exportStarted', (type) => {
            // Show export progress indicator
            Swal.fire({
                title: 'Exporting...',
                text: `Preparing ${type} export`,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });

        Livewire.on('exportCompleted', () => {
            Swal.close();
        });
    });
</script>
@endpush
