@extends('layouts.app')

@section('styles')
@include('v2.marketplace.stock-formula.partials.styles')
@endsection

@section('content')
@include('v2.marketplace.stock-formula.partials.breadcrumb')

<hr style="border-bottom: 1px solid #000">

<div id="alert-container"></div>

@include('v2.marketplace.stock-formula.partials.search-section')

@if($selectedVariation)
    @include('v2.marketplace.stock-formula.partials.variation-info')
    @include('v2.marketplace.stock-formula.partials.formulas-section')
@endif

@endsection

@section('scripts')
@include('v2.marketplace.stock-formula.partials.scripts')
@endsection

