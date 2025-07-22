{{-- filepath: c:\xampp\htdocs\nibritaintech\resources\views\livewire\reports\sales-history.blade.php --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Sales History (Last 7 Days)</h3>
    </div>
    <div class="card-body">
        @if(!empty($sales_history))
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Day</th>
                            @foreach($currencies as $currency_id => $sign)
                                <th>{{ $sign }} Quantity</th>
                                <th>{{ $sign }} Total</th>
                                <th>{{ $sign }} Average</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sales_history as $day => $data)
                            <tr>
                                <td><strong>{{ $day }}</strong></td>
                                @foreach($currencies as $currency_id => $sign)
                                    @php
                                        $currency_data = $data[$currency_id] ?? ['quantity' => 0, 'total_sales' => '0', 'average_price' => '0'];
                                    @endphp
                                    <td>{{ number_format($currency_data['quantity']) }}</td>
                                    <td>{{ $sign }}{{ $currency_data['total_sales'] }}</td>
                                    <td>{{ $sign }}{{ $currency_data['average_price'] }}</td>
                                @endforeach
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

{{-- Sales Chart --}}
<div class="card mt-4">
    <div class="card-header">
        <h3 class="card-title">Sales Trend Chart</h3>
    </div>
    <div class="card-body">
        <canvas id="salesChart" height="100"></canvas>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sales chart implementation
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json(array_keys($sales_history)),
            datasets: [
                @foreach($currencies as $currency_id => $sign)
                {
                    label: '{{ $sign }} Sales',
                    data: @json(array_map(function($data) use ($currency_id) {
                        return isset($data[$currency_id]) ? (float)str_replace(',', '', $data[$currency_id]['total_sales']) : 0;
                    }, $sales_history)),
                    borderColor: 'rgb({{ rand(0,255) }}, {{ rand(0,255) }}, {{ rand(0,255) }})',
                    tension: 0.1
                },
                @endforeach
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>
@endpush
