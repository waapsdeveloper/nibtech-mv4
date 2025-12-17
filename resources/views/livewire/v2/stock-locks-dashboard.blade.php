@extends('layouts.app')

@section('styles')
<style>
    .stock-lock-card {
        transition: all 0.3s ease;
    }
    .stock-lock-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .lock-status-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">Stock Locks Dashboard</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Stock Locks</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <h5 class="card-title mb-0 me-3">
                            <i class="fe fe-lock me-2"></i>All Stock Locks
                        </h5>
                        @php
                            $activeLocksCount = \App\Models\V2\MarketplaceStockLock::where('lock_status', 'locked')->count();
                        @endphp
                        <span class="badge bg-warning text-dark ms-2">{{ $activeLocksCount }} Active</span>
                    </div>
                    <div class="card-options">
                        <a href="javascript:void(0);" class="btn btn-sm btn-link text-muted" onclick="refreshLocks()" style="opacity: 0.7; border: none; background: transparent; padding: 0.25rem 0.5rem;">
                            <i class="fe fe-refresh-cw"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    @livewire('v2.stock-locks', [
                        'orderId' => request('order_id'),
                        'variationId' => request('variation_id'),
                        'marketplaceId' => request('marketplace_id'),
                        'showAll' => true
                    ], key('all-stock-locks-'.request('order_id').'-'.request('variation_id').'-'.request('marketplace_id')))
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Unfreeze Stock Lock Confirmation Modal --}}
<div class="modal fade" id="unfreezeStockLockModal" tabindex="-1" aria-labelledby="unfreezeStockLockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="unfreezeStockLockModalLabel">
                    <i class="fe fe-unlock me-2"></i>Unfreeze Stock Lock - Confirmation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="fe fe-alert-triangle me-2"></i>
                    <strong>Please review the following information before proceeding:</strong>
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold mb-3">What will happen when you unfreeze this stock lock:</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fe fe-check-circle text-success me-2"></i>
                            <strong>Stock Release:</strong> <span id="unfreezeQuantity"></span> unit(s) will be released from locked stock
                        </li>
                        <li class="mb-2">
                            <i class="fe fe-check-circle text-success me-2"></i>
                            <strong>Available Stock:</strong> The stock will become available for new orders on the marketplace
                        </li>
                        <li class="mb-2">
                            <i class="fe fe-check-circle text-success me-2"></i>
                            <strong>Lock Status:</strong> The lock status will change from <span class="badge bg-warning">LOCKED</span> to <span class="badge bg-danger">CANCELLED</span>
                        </li>
                        <li class="mb-2">
                            <i class="fe fe-check-circle text-success me-2"></i>
                            <strong>Audit Trail:</strong> A history record will be created to track this action
                        </li>
                        <li class="mb-2">
                            <i class="fe fe-check-circle text-success me-2"></i>
                            <strong>Marketplace API:</strong> The marketplace will be updated with the new available stock (with buffer applied)
                        </li>
                        <li class="mb-2">
                            <i class="fe fe-info text-info me-2"></i>
                            <strong>Lock Record:</strong> The lock record will be kept for audit purposes (not deleted)
                        </li>
                    </ul>
                </div>

                <div class="card bg-light mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-2">Lock Details:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">Order Reference:</small>
                                <div class="fw-bold" id="unfreezeOrderReference"></div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Quantity to Release:</small>
                                <div class="fw-bold" id="unfreezeQuantityDetail"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mb-0">
                    <i class="fe fe-info me-2"></i>
                    <strong>Important:</strong> This action cannot be undone. The stock will immediately become available for new orders. Make sure the order is cancelled or no longer needs this stock before proceeding.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fe fe-x me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger" id="confirmUnfreezeBtn">
                    <i class="fe fe-unlock me-1"></i>Yes, Unfreeze Stock
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentLockId = null;
let currentButton = null;

function releaseLock(lockId, orderReference, quantity) {
    currentLockId = lockId;
    currentButton = event.target.closest('button');
    
    // Set modal content
    document.getElementById('unfreezeQuantity').textContent = quantity;
    document.getElementById('unfreezeQuantityDetail').textContent = quantity + ' unit(s)';
    document.getElementById('unfreezeOrderReference').textContent = orderReference;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('unfreezeStockLockModal'));
    modal.show();
}

// Handle confirm button click
document.getElementById('confirmUnfreezeBtn').addEventListener('click', function() {
    if (!currentLockId) return;
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('unfreezeStockLockModal'));
    modal.hide();
    
    // Show loading state
    const btn = currentButton;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fe fe-loader spin"></i> Releasing...';

    // Make API call
    fetch(`/v2/stock-locks/${currentLockId}/release`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            reason: 'Manual release from stock locks dashboard'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            alert('✅ ' + data.message);
            // Refresh the page to show updated data
            location.reload();
        } else {
            alert('❌ Error: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ An error occurred while releasing the lock. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
    
    // Reset
    currentLockId = null;
    currentButton = null;
});

function refreshLocks() {
    Livewire.emit('$refresh');
}
</script>
<style>
.fe-loader.spin {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
@endsection

