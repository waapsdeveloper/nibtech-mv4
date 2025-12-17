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
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fe fe-lock me-2"></i>All Stock Locks
                    </h5>
                    <div class="card-options">
                        <a href="javascript:void(0);" class="btn btn-sm btn-primary" onclick="refreshLocks()">
                            <i class="fe fe-refresh-cw me-1"></i>Refresh
                        </a>
                    </div>
                </div>
                <div class="card-body">
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

<script>
function refreshLocks() {
    Livewire.emit('$refresh');
}
</script>
@endsection

