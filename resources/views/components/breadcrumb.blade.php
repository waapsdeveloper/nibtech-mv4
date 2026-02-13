@props(['title' => '', 'items' => []])

<!-- breadcrumb -->
<div class="breadcrumb-header justify-content-between">
    <div class="left-content">
        @if($title)
            <span class="main-content-title mg-b-0 mg-b-lg-1">{{ $title }}</span>
        @else
            {{ $slot }}
        @endif
    </div>
    <div class="justify-content-center mt-2">
        <ol class="breadcrumb">
            <li class="breadcrumb-item tx-15"><a href="/">{{ __('locale.Dashboard') }}</a></li>
            @foreach($items as $item)
                @if($loop->last)
                    <li class="breadcrumb-item active" aria-current="page">{{ $item['label'] }}</li>
                @else
                    <li class="breadcrumb-item tx-15"><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
                @endif
            @endforeach
        </ol>
    </div>
</div>
<!-- /breadcrumb -->
