{{-- filepath: c:\xampp\htdocs\nibritaintech\resources\views\livewire\reports\batch-grades.blade.php --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Batch Grade Reports</h3>
        <div class="card-options">
            <span class="badge bg-primary">{{ $pending_orders_count }} Pending Orders</span>
        </div>
    </div>
    <div class="card-body">
        @if(!empty($batch_grade_reports))
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batch_grade_reports['data'] ?? [] as $report)
                            <tr>
                                <td>
                                    <span class="badge bg-info">
                                        {{ $grades[$report['grade']] ?? $report['grade'] }}
                                    </span>
                                </td>
                                <td>{{ $report['order_id'] }}</td>
                                <td>{{ $report['reference_id'] }}</td>
                                <td>{{ $report['reference'] }}</td>
                                <td>{{ $report['vendor'] }}</td>
                                <td>{{ number_format($report['quantity']) }}</td>
                                <td>€{{ amount_formatter($report['average_cost']) }}</td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-primary"
                                                onclick="viewBatchDetails({{ $report['order_id'] }})">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-success"
                                                onclick="exportBatch({{ $report['order_id'] }})">
                                            <i class="fa fa-download"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if(isset($batch_grade_reports['links']))
                <div class="d-flex justify-content-center">
                    {!! $batch_grade_reports['links'] !!}
                </div>
            @endif
        @else
            <div class="text-center text-muted py-4">
                <i class="fa fa-boxes fa-3x mb-3"></i>
                <p>No batch grade data found for the selected criteria.</p>
            </div>
        @endif
    </div>
</div>
