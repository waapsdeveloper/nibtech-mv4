<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\OrdersInBetweenSummary;
use Illuminate\Http\Request;

/**
 * CRUD for the single orders_in_between summary row (listed-stock:orders-in-between job result).
 */
class OrdersInBetweenSummaryController extends Controller
{
    /**
     * Show the summary (one row). Includes form to edit.
     */
    public function index()
    {
        $data['title_page'] = 'Orders In Between Summary';
        session()->put('page_title', $data['title_page']);
        $summary = OrdersInBetweenSummary::getSummary();

        return view('v2.extras.orders-in-between-summary.index', compact('summary', 'data'));
    }

    /**
     * Update the summary row.
     */
    public function update(Request $request)
    {
        $request->validate([
            'last_run_at' => 'nullable|date',
            'total_updated' => 'required|integer|min:0',
            'days_back' => 'nullable|integer|min:0',
            'backfill_only' => 'boolean',
        ]);

        $summary = OrdersInBetweenSummary::getSummary();
        $summary->update([
            'last_run_at' => $request->filled('last_run_at') ? $request->input('last_run_at') : null,
            'total_updated' => (int) $request->input('total_updated'),
            'days_back' => $request->filled('days_back') ? (int) $request->input('days_back') : null,
            'backfill_only' => $request->boolean('backfill_only'),
        ]);

        return redirect()->route('v2.extras.orders-in-between-summary.index')
            ->with('success', 'Summary updated.');
    }
}
