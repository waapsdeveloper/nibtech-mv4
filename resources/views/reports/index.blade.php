@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <h1>Reports</h1>
            <div class="panel panel-default">
                <div class="panel-heading">Reports</div>
                <div class="panel-body" wire:poll.750ms>
                    {{ now() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Loading indicator to show while components are being loaded --}}
    <div wire:loading.delay class="alert alert-info text-center">
        Loading reports, please wait...
    </div>

    {{-- Load each Livewire component on page load --}}
    {{-- <div id="livewire-components">
        @livewire('sales-report')
        @livewire('batch-grade-report') --}}
        {{-- @livewire('weekly-ecommerce-sales-graph') --}}
    {{-- </div> --}}
    <!-- Blade Template with Alpine.js -->
    {{-- <div x-data="{ load: false }" x-init="load = true">
        <div x-show="!load">Loading reports...</div>
        <div x-show="load">
            @livewire('sales-report')
        </div>
    </div> --}}

    @livewire('test-component')

@endsection
