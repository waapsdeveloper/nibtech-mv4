<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Currency_model;
use App\Models\Order_model;
use App\Models\Order_status_model;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * V2 Orders (Sales) - plain Blade index (no Livewire).
     *
     * Step 1: Simple list using the same parent table layout as V1 Sales,
     * but without the heavy interactive logic. Expansions will be added next.
     */
    public function index(Request $request)
    {
        $data['title_page'] = 'V2 Orders';
        session()->put('page_title', $data['title_page']);

        $perPage = (int) ($request->get('per_page', 10));
        if ($perPage <= 0) {
            $perPage = 10;
        }
        if ($perPage > 200) {
            $perPage = 200;
        }

        // Keep it light for step 1: basic relations needed for the table.
        $orders = Order_model::query()
            ->with([
                'customer',
                'order_status',
                'order_items',
                'order_items.variation',
                'order_items.variation.product',
                'order_items.stock',
            ])
            ->where('order_type_id', 3) // Sales
            ->orderByDesc('reference_id')
            ->paginate($perPage)
            ->withQueryString();

        $data['orders'] = $orders;
        $data['order_statuses'] = Order_status_model::pluck('name', 'id');
        $data['currencies'] = Currency_model::pluck('sign', 'id');

        return view('v2.order.index', $data);
    }
}


