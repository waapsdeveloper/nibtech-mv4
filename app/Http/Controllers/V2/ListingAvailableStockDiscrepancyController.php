<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\ListingAvailableStockDiscrepancy;
use App\Models\Variation_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ListingAvailableStockDiscrepancyController extends Controller
{
    public function index()
    {
        $data['title_page'] = 'Listing Available vs Stocks Table Discrepancies';
        session()->put('page_title', $data['title_page']);

        $discrepancies = ListingAvailableStockDiscrepancy::query()
            ->with('variation.product')
            ->orderByDesc('difference')
            ->paginate(50);

        return view('v2.extras.listing-available-stock-discrepancies.index', compact('discrepancies', 'data'));
    }

    public function show(int $id)
    {
        $discrepancy = ListingAvailableStockDiscrepancy::with('variation.product')->findOrFail($id);
        $data['title_page'] = 'Discrepancy: ' . ($discrepancy->variation_sku ?? 'Variation #' . $discrepancy->variation_id);
        session()->put('page_title', $data['title_page']);

        return view('v2.extras.listing-available-stock-discrepancies.show', compact('discrepancy', 'data'));
    }

    public function destroy(int $id)
    {
        $discrepancy = ListingAvailableStockDiscrepancy::findOrFail($id);
        $discrepancy->delete();

        return redirect()->route('v2.extras.listing-available-stock-discrepancies.index')
            ->with('success', 'Discrepancy record deleted.');
    }

    /**
     * Run the check command and redirect back with message.
     */
    public function runCheck(Request $request)
    {
        $chunk = $request->input('chunk', 500);
        Artisan::call('listing:available-stock-discrepancy-check', ['--chunk' => $chunk]);
        $output = trim(Artisan::output());

        return redirect()->route('v2.extras.listing-available-stock-discrepancies.index')
            ->with('success', $output);
    }

    /**
     * Fix numbers: set variation.available_count_override = stocks_table_count (source of truth), then remove discrepancy record.
     */
    public function fix(Request $request)
    {
        $ids = $request->input('ids', []);
        if (is_string($ids)) {
            $ids = $ids ? array_filter(array_map('intval', explode(',', $ids))) : [];
        } elseif (! is_array($ids)) {
            $ids = $ids ? [ (int) $ids ] : [];
        } else {
            $ids = array_filter(array_map('intval', $ids));
        }

        $fixed = 0;
        foreach ($ids as $id) {
            $discrepancy = ListingAvailableStockDiscrepancy::find($id);
            if (! $discrepancy) {
                continue;
            }
            Variation_model::where('id', $discrepancy->variation_id)->update([
                'available_count_override' => $discrepancy->stocks_table_count,
            ]);
            $discrepancy->delete();
            $fixed++;
        }

        $message = $fixed === 0
            ? 'No records fixed.'
            : "Fixed {$fixed} record(s). Card \"Available\" will show stocks table count.";

        return redirect()->route('v2.extras.listing-available-stock-discrepancies.index')
            ->with('success', $message);
    }
}
