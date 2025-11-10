<div class="card" wire:init="loadRepairingCount">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title mb-1">Repairing Count</h4>
        @if ($readyToLoad)
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="refreshRepairingCount" wire:loading.attr="disabled" wire:target="refreshRepairingCount">
                <i class="fe fe-refresh-cw"></i>
            </button>
        @endif
    </div>
    <div class="card-body py-2">
        <div class="py-3 text-center" wire:loading.flex wire:target="loadRepairingCount,refreshRepairingCount">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span class="ms-2">Loading...</span>
        </div>
        <div wire:loading.remove wire:target="loadRepairingCount,refreshRepairingCount">
            @if ($repairingCount->isEmpty())
                <p class="text-muted mb-0">No repairing activity found for the selected range.</p>
            @else
                <table class="w-100">
                    @foreach ($repairingCount as $technician)
                        <tr>
                            <td>{{ $technician->first_name }}:</td>
                            <td class="tx-right">
                                <a href="{{ url('move_inventory') }}?start_date={{ $startDate }}&amp;end_date={{ $endDate }}&amp;adm={{ $technician->id }}" title="Go to Move Inventory page">
                                    {{ $technician->stock_operations_count }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>
</div>
