@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <h1>Reports</h1>
            <div class="panel panel-default">
                <div class="panel-heading">Reports</div>
                <div class="panel-body">
                </div>
            </div>
        </div>
    </div>
    @livewire('<reports>sales-report')
    @livewire('batch-grade-report')
@endsection
