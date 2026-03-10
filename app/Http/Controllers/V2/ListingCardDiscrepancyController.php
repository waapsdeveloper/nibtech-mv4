<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\ListingCardDiscrepancy;
use App\Models\Variation_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Listing card mismatches: Stock (listed) vs Available (card) vs Stocks table count.
 * Run check to populate; Fix sets Listed and available_count_override to Stocks table count (DB only).
 */
class ListingCardDiscrepancyController extends Controller
{
    public function index()
    {
        $data['title_page'] = 'Listing card mismatches (Stock vs Available vs Table)';
        session()->put('page_title', $data['title_page']);

        $discrepancies = ListingCardDiscrepancy::query()
            ->with('variation.product')
            ->orderByRaw('ABS(listed_stock - stocks_table_count) + ABS(available_count - stocks_table_count) DESC')
            ->paginate(50);

        return view('v2.extras.listing-card-discrepancies.index', compact('discrepancies', 'data'));
    }

    public function runCheck(Request $request)
    {
        $chunk = $request->input('chunk', 500);
        Artisan::call('listing:listing-card-discrepancy-check', ['--chunk' => $chunk]);
        $output = trim(Artisan::output());

        return redirect()->route('v2.extras.listing-card-discrepancies.index')
            ->with('success', $output);
    }

    public function destroy(int $id)
    {
        ListingCardDiscrepancy::findOrFail($id)->delete();

        return redirect()->route('v2.extras.listing-card-discrepancies.index')
            ->with('success', 'Record deleted.');
    }

    /**
     * Fix: set variation.listed_stock and available_count_override to stocks_table_count (DB only).
     */
    public function fix(Request $request)
    {
        $ids = $request->input('ids', []);
        if (is_string($ids)) {
            $ids = $ids ? array_filter(array_map('intval', explode(',', $ids))) : [];
        } elseif (! is_array($ids)) {
            $ids = $ids ? [(int) $ids] : [];
        } else {
            $ids = array_filter(array_map('intval', $ids));
        }

        $fixed = 0;
        foreach ($ids as $id) {
            $d = ListingCardDiscrepancy::find($id);
            if (! $d) {
                continue;
            }
            $variation = Variation_model::find($d->variation_id);
            if (! $variation) {
                continue;
            }
            $target = (int) $d->stocks_table_count;
            $variation->listed_stock = $target;
            $variation->available_count_override = $target;
            $variation->save();
            $d->delete();
            $fixed++;
        }

        $message = $fixed === 0
            ? 'No records fixed.'
            : "Fixed {$fixed} record(s). Listed and Available override set to Stocks table count (DB only).";

        return redirect()->route('v2.extras.listing-card-discrepancies.index')
            ->with('success', $message);
    }
}
