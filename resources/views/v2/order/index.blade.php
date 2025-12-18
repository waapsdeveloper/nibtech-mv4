@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">V2 Orders</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item active" aria-current="page">Orders</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="fw-bold">Orders</div>
                    <div class="d-flex align-items-center gap-2">
                        <form method="GET" class="d-flex align-items-center gap-2">
                            <label class="small text-muted mb-0">Per page</label>
                            <select name="per_page" class="form-select form-select-sm" onchange="this.form.submit()" style="width: 110px;">
                                @foreach ([10,25,50,100] as $pp)
                                    <option value="{{ $pp }}" @selected((int) request('per_page', 10) === $pp)>{{ $pp }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        @include('v2.order.partials.orders-table', [
                            'orders' => $orders,
                            'currencies' => $currencies ?? [],
                            'order_statuses' => $order_statuses ?? [],
                        ])
                    </div>
                </div>

                <div class="card-footer">
                    {{ $orders->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


