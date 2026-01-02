@extends('layouts.app')

@section('styles')
<link href="{{asset('assets/v2/artisan-commands/css/artisan-commands.css')}}" rel="stylesheet" />
@endsection

@section('content')
    

<div class="page">
    <div class="main-content app-content">
        <div class="main-container container-fluid">
            
<!-- Breadcrumb -->
    <div class="breadcrumb-header justify-content-between">
        <div class="left-content">
            <span class="main-content-title mg-b-0 mg-b-lg-1">V2 Artisan Commands Guide</span>
        </div>
        <div class="justify-content-center mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
                <li class="breadcrumb-item tx-15"><a href="{{ url('v2/listings') }}">V2</a></li>
                <li class="breadcrumb-item active" aria-current="page">Artisan Commands</li>
            </ol>
        </div>
    </div>
    <!-- /Breadcrumb -->

    @include('v2.artisan-commands.partials.running-jobs')
    @include('v2.artisan-commands.partials.migration-status')
    @include('v2.artisan-commands.partials.commands-list')
    @include('v2.artisan-commands.partials.documentation-list')
    @include('v2.artisan-commands.partials.migration-details-modal')
    @include('v2.artisan-commands.partials.documentation-modal')
        </div>
    </div>
</div>


@section('scripts')
<script>
// Initialize Artisan Commands Config
window.ArtisanCommandsConfig = {
    urls: {
        runMigrations: '{{ url("v2/artisan-commands/run-migrations") }}',
        recordMigration: '{{ url("v2/artisan-commands/record-migration") }}',
        migrationDetails: '{{ url("v2/artisan-commands/migration-details") }}',
        runSingleMigration: '{{ url("v2/artisan-commands/run-single-migration") }}',
        execute: '{{ url("v2/artisan-commands/execute") }}',
        checkCommandStatus: '{{ url("v2/artisan-commands/check-command-status") }}',
        documentation: '{{ url("v2/artisan-commands/documentation") }}',
        kill: '{{ url("v2/artisan-commands/kill") }}',
        restart: '{{ url("v2/artisan-commands/restart") }}',
        stockSyncLog: '{{ url("v2/logs/stock-sync") }}'
    }
};
</script>
<script src="{{ asset('assets/v2/artisan-commands/js/artisan-commands.js') }}"></script>
@endsection
