<div class="card" wire:init="loadAftersaleMetrics">
    <div class="card-header border-bottom-0 d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Aftersale Inventory</h3>
        @if ($readyToLoad)
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="refreshAftersaleMetrics" wire:loading.attr="disabled" wire:target="refreshAftersaleMetrics">
                <i class="fe fe-refresh-cw"></i>
            </button>
        @endif
    </div>
    <div class="card-body py-2">
        <div class="py-3 text-center" wire:loading.flex wire:target="loadAftersaleMetrics,refreshAftersaleMetrics">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span class="ms-2">Loading...</span>
        </div>
        <div wire:loading.remove wire:target="loadAftersaleMetrics,refreshAftersaleMetrics">
            @if ($aftersaleInventory->isEmpty() && $returnsInProgress === 0 && $rma === 0 && $awaitingReplacement === 0)
                <p class="text-muted mb-0">No aftersale activity to display.</p>
            @else
                <table class="w-100">
                    @foreach ($aftersaleInventory as $inv)
                        <tr>
                            <td>{{ $inv->grade }}:</td>
                            <td class="tx-right">
                                <a href="{{ url('belfast_inventory') }}?grade[]={{ $inv->grade_id }}&amp;status={{ $inv->stock_status }}" title="Go to orders page">
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
                            <a href="{{ url('inventory') }}?stock_status=1&amp;replacement=1">{{ $awaitingReplacement }}</a>
                        </td>
                    </tr>
                </table>
            @endif
        </div>
    </div>
</div>
