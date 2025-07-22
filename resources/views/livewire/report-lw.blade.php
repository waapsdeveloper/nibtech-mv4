@extends('layouts.app')

@section('content')
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
                            @if(is_array($categories))
                                @foreach($categories as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Brand</label>
                        <select wire:model="brand" class="form-select">
                            <option value="">All Brands</option>
                            @if(is_array($brands))
                                @foreach($brands as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Product</label>
                        <select wire:model="product" class="form-select">
                            <option value="">All Products</option>
                            @if(is_array($products) || is_object($products))
                                @foreach($products as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Vendor</label>
                        <select wire:model="vendor" class="form-select">
                            <option value="">All Vendors</option>
                            @if(is_array($vendors) || is_object($vendors))
                                @foreach($vendors as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                </div>

                <div class="row mt-3">
                    {{-- Additional Filters --}}
                    <div class="col-md-3">
                        <label class="form-label">Storage</label>
                        <select wire:model="storage" class="form-select">
                            <option value="">All Storage</option>
                            @if(is_array($storages))
                                @foreach($storages as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Color</label>
                        <select wire:model="color" class="form-select">
                            <option value="">All Colors</option>
                            @if(is_array($colors))
                                @foreach($colors as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Grade</label>
                        <select wire:model="grade" class="form-select">
                            <option value="">All Grades</option>
                            @if(is_array($grades))
                                @foreach($grades as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            @endif
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
        <div class="row">
            {{-- Sales Summary --}}
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Sales Summary</h3>
                    </div>
                    <div class="card-body">
                        @if(!empty($aggregated_sales) && is_array($aggregated_sales))
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Orders</th>
                                            <th>EUR Sales</th>
                                            <th>GBP Sales</th>
                                            <th>Charges</th>
                                            <th>Cost</th>
                                            <th>Profit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($aggregated_sales as $sale)
                                            @php
                                                $sale = (object) $sale; // Convert array to object
                                                $category_name = $categories[$sale->category_id] ?? 'Unknown';
                                                $cost = $aggregated_sales_cost[$sale->category_id] ?? 0;
                                                $profit = $sale->eur_items_sum - $cost - ($sale->items_repair_sum ?? 0);
                                            @endphp
                                            <tr>
                                                <td>{{ $category_name }}</td>
                                                <td>{{ number_format($sale->orders_qty ?? 0) }}</td>
                                                <td>€{{ number_format($sale->eur_items_sum ?? 0, 2) }}</td>
                                                <td>£{{ number_format($sale->gbp_items_sum ?? 0, 2) }}</td>
                                                <td>€{{ number_format($sale->charges ?? 0, 2) }}</td>
                                                <td>€{{ number_format($cost, 2) }}</td>
                                                <td class="{{ $profit >= 0 ? 'text-success' : 'text-danger' }}">
                                                    €{{ number_format($profit, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center text-muted py-4">
                                <i class="fa fa-chart-line fa-3x mb-3"></i>
                                <p>No sales data found for the selected criteria.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Returns Summary --}}
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Returns Summary</h3>
                    </div>
                    <div class="card-body">
                        @if(!empty($aggregated_returns) && is_array($aggregated_returns))
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Returns</th>
                                            <th>EUR Returns</th>
                                            <th>GBP Returns</th>
                                            <th>Cost</th>
                                            <th>Impact</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($aggregated_returns as $return)
                                            @php
                                                $return = (object) $return; // Convert array to object
                                                $category_name = $categories[$return->category_id] ?? 'Unknown';
                                                $cost = $aggregated_return_cost[$return->category_id] ?? 0;
                                                $impact = ($return->eur_items_sum ?? 0) + $cost;
                                            @endphp
                                            <tr>
                                                <td>{{ $category_name }}</td>
                                                <td>{{ number_format($return->orders_qty ?? 0) }}</td>
                                                <td>€{{ number_format($return->eur_items_sum ?? 0, 2) }}</td>
                                                <td>£{{ number_format($return->gbp_items_sum ?? 0, 2) }}</td>
                                                <td>€{{ number_format($cost, 2) }}</td>
                                                <td class="text-danger">
                                                    -€{{ number_format($impact, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center text-muted py-4">
                                <i class="fa fa-undo fa-3x mb-3"></i>
                                <p>No returns data found for the selected criteria.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    @elseif($report_type === 'batch_grades')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Batch Grade Reports</h3>
                <div class="card-options">
                    <span class="badge bg-primary">{{ $pending_orders_count ?? 0 }} Pending Orders</span>
                </div>
            </div>
            <div class="card-body">
                @if(!empty($batch_grade_reports) && is_array($batch_grade_reports))
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Grade</th>
                                    <th>Order ID</th>
                                    <th>Reference ID</th>
                                    <th>Reference</th>
                                    <th>Vendor</th>
                                    <th>Quantity</th>
                                    <th>Average Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($batch_grade_reports as $report)
                                    @php
                                        $report = (object) $report; // Convert array to object if needed
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="badge bg-info">
                                                {{ $grades[$report->grade ?? ''] ?? ($report->grade ?? 'N/A') }}
                                            </span>
                                        </td>
                                        <td>{{ $report->order_id ?? 'N/A' }}</td>
                                        <td>{{ $report->reference_id ?? 'N/A' }}</td>
                                        <td>{{ $report->reference ?? 'N/A' }}</td>
                                        <td>{{ $report->vendor ?? 'N/A' }}</td>
                                        <td>{{ number_format($report->quantity ?? 0) }}</td>
                                        <td>€{{ number_format($report->average_cost ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center text-muted py-4">
                        <i class="fa fa-boxes fa-3x mb-3"></i>
                        <p>No batch grade data found for the selected criteria.</p>
                    </div>
                @endif
            </div>
        </div>

    @elseif($report_type === 'sales_history')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Sales History (Last 7 Days)</h3>
            </div>
            <div class="card-body">
                @if(!empty($sales_history) && is_array($sales_history))
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    @if(is_array($currencies))
                                        @foreach($currencies as $currency_id => $sign)
                                            <th>{{ $sign }} Quantity</th>
                                            <th>{{ $sign }} Total</th>
                                            <th>{{ $sign }} Average</th>
                                        @endforeach
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sales_history as $day => $data)
                                    <tr>
                                        <td><strong>{{ $day }}</strong></td>
                                        @if(is_array($currencies))
                                            @foreach($currencies as $currency_id => $sign)
                                                @php
                                                    $currency_data = $data[$currency_id] ?? ['quantity' => 0, 'total_sales' => '0', 'average_price' => '0'];
                                                @endphp
                                                <td>{{ number_format($currency_data['quantity'] ?? 0) }}</td>
                                                <td>{{ $sign }}{{ $currency_data['total_sales'] ?? '0' }}</td>
                                                <td>{{ $sign }}{{ $currency_data['average_price'] ?? '0' }}</td>
                                            @endforeach
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center text-muted py-4">
                        <i class="fa fa-history fa-3x mb-3"></i>
                        <p>No sales history data available.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Check if Livewire is loaded
        if (typeof Livewire !== 'undefined') {
            // Real-time updates
            Livewire.on('reportUpdated', () => {
                console.log('Report updated');
            });

            // Export functionality
            Livewire.on('exportStarted', (type) => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Exporting...',
                        text: `Preparing ${type} export`,
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                } else {
                    console.log(`Exporting ${type}...`);
                }
            });

            Livewire.on('exportCompleted', () => {
                if (typeof Swal !== 'undefined') {
                    Swal.close();
                } else {
                    console.log('Export completed');
                }
            });
        } else {
            console.warn('Livewire is not loaded');
        }
    });
</script>
@endpush

@endsection
