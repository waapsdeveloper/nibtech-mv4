<div>
    <div class="card">
        <div class="card-header border-bottom-0">
            <h3 class="card-title mb-0">Aftersale Inventory</h3>
        </div>
        <div class="card-body py-2">
            <table class="w-100">
                @foreach ($aftersaleInventory as $inv)
                    <tr>
                        <td>{{ $inv->grade }}:</td>
                        <td class="tx-right">
                            <a href="{{ url('inventory') }}?grade[]={{ $inv->grade_id }}&status={{ $inv->status_id }}&stock_status={{ $inv->stock_status }}" title="Go to inventory">
                                {{ $inv->quantity }}
                            </a>
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <td title="Waiting for Approval">Returns:</td>
                    <td class="tx-right">
                        <a href="{{ url('return') }}" title="Returns in Progress">{{ $returnsInProgress }}</a>
                    </td>
                </tr>
                <tr>
                    <td>RMA:</td>
                    <td class="tx-right">
                        <a href="{{ url('inventory') }}?rma=1">{{ $rma }}</a>
                    </td>
                </tr>
                <tr>
                    <td>Replacements:</td>
                    <td class="tx-right">
                        <a href="{{ url('inventory') }}?stock_status=1&replacement=1">{{ $awaitingReplacement }}</a>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
