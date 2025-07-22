{{-- filepath: c:\xampp\htdocs\nibritaintech\resources\views\livewire\reports\sales-returns.blade.php --}}
<div class="row">
    {{-- Sales Summary --}}
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Sales Summary</h3>
            </div>
            <div class="card-body">
                @if(!empty($aggregated_sales))
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
                                        $category_name = $categories[$sale->category_id] ?? 'Unknown';
                                        $cost = $aggregated_sales_cost[$sale->category_id] ?? 0;
                                        $profit = $sale->eur_items_sum - $cost - $sale->items_repair_sum;
                                    @endphp
                                    <tr>
                                        <td>{{ $category_name }}</td>
                                        <td>{{ number_format($sale->orders_qty) }}</td>
                                        <td>€{{ amount_formatter($sale->eur_items_sum) }}</td>
                                        <td>£{{ amount_formatter($sale->gbp_items_sum) }}</td>
                                        <td>€{{ amount_formatter($sale->charges) }}</td>
                                        <td>€{{ amount_formatter($cost) }}</td>
                                        <td class="{{ $profit >= 0 ? 'text-success' : 'text-danger' }}">
                                            €{{ amount_formatter($profit) }}
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
                @if(!empty($aggregated_returns))
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
                                        $category_name = $categories[$return->category_id] ?? 'Unknown';
                                        $cost = $aggregated_return_cost[$return->category_id] ?? 0;
                                        $impact = $return->eur_items_sum + $cost;
                                    @endphp
                                    <tr>
                                        <td>{{ $category_name }}</td>
                                        <td>{{ number_format($return->orders_qty) }}</td>
                                        <td>€{{ amount_formatter($return->eur_items_sum) }}</td>
                                        <td>£{{ amount_formatter($return->gbp_items_sum) }}</td>
                                        <td>€{{ amount_formatter($cost) }}</td>
                                        <td class="text-danger">
                                            -€{{ amount_formatter($impact) }}
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
