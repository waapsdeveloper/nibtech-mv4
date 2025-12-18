<?php

namespace App\Http\Livewire\V2;

/**
 * V2 Sales page.
 *
 * Reuses the existing Sales/Order Livewire logic but renders a V2 Blade view
 * where we can add stock lock/stock sync visualizations without affecting V1.
 */
class Sales extends \App\Http\Livewire\Order
{
    protected string $viewName = 'livewire.v2.sales';
}


