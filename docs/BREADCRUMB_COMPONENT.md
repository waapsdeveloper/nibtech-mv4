# Breadcrumb Component Usage

## Overview
The breadcrumb component provides a standardized way to display breadcrumbs across all pages in the system.

## Location
`resources/views/components/breadcrumb.blade.php`

## Basic Usage

### Simple breadcrumb with title
```blade
<x-breadcrumb title="Page Title" :items="[['label' => 'Page Title']]" />
```

### Breadcrumb with multiple levels
```blade
<x-breadcrumb title="Details" :items="[
    ['label' => 'Section', 'url' => url('section')],
    ['label' => 'Subsection', 'url' => url('subsection')],
    ['label' => 'Details']
]" />
```

### Breadcrumb with custom left content
```blade
<x-breadcrumb :items="[['label' => 'Orders']]">
    <a href="{{ url('refresh_order') }}" class="btn btn-primary">Recheck All</a>
    <a href="{{url('check_new')}}" class="btn btn-primary">Check for New</a>
</x-breadcrumb>
```

## Properties

- **title** (optional): The page title displayed on the left side
- **items** (required): Array of breadcrumb items
  - `label`: The text to display (required)
  - `url`: The link URL (optional, not needed for the last/active item)

## Features

- Automatically adds "Dashboard" as the first breadcrumb item
- Supports translation via `{{ __('locale.Dashboard') }}`
- Last item is automatically marked as active
- Clean, consistent styling across all pages

## Migration

Old breadcrumb code:
```blade
<div class="breadcrumb-header justify-content-between">
    <div class="left-content">
        <span class="main-content-title mg-b-0 mg-b-lg-1">Page Title</span>
    </div>
    <div class="justify-content-center mt-2">
        <ol class="breadcrumb">
            <li class="breadcrumb-item tx-15"><a href="/">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Page Title</li>
        </ol>
    </div>
</div>
```

New breadcrumb code:
```blade
<x-breadcrumb title="Page Title" :items="[['label' => 'Page Title']]" />
```

## Standardization

All breadcrumbs now use:
- `{{ __('locale.Dashboard') }}` instead of hardcoded "Dashboard" or "Dashboards"
- Consistent terminology (singular "Dashboard")
- Proper translation support
