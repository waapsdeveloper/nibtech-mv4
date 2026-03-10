<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\ListingAvailableStockDiscrepancy;
use App\Models\Order_item_model;
use App\Models\Process_stock_model;
use App\Models\Stock_model;
use App\Models\V2\MarketplaceStockModel;
use App\Models\Variation_model;
use App\Models\Order_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Draft board for dashboard "Total Listed vs Should Be" discrepancies.
 * Lists variations (grade < 6) where variation.listed_stock != computed should_be.
 * Fix: set listed_stock = should_be in DB only (for both negative and positive discrepancies; no push to Back Market).
 */
class ListingAvailableStockDiscrepancyController extends Controller
{
    public function index(Request $request)
    {
        $data['title_page'] = 'Listed vs Should Be (Dashboard draft board)';
        session()->put('page_title', $data['title_page']);

        $discrepancies = ListingAvailableStockDiscrepancy::query()
            ->with('variation.product')
            ->orderByRaw('ABS(difference) DESC')
            ->paginate(50);

        $totals = $this->getDashboardTotals();

        return view('v2.extras.listing-available-stock-discrepancies.index', compact('discrepancies', 'data', 'totals'));
    }

    public function show(int $id)
    {
        $discrepancy = ListingAvailableStockDiscrepancy::with('variation.product')->findOrFail($id);
        $data['title_page'] = 'Discrepancy: ' . ($discrepancy->variation_sku ?? 'Variation #' . $discrepancy->variation_id);
        session()->put('page_title', $data['title_page']);
        $totals = $this->getDashboardTotals();

        return view('v2.extras.listing-available-stock-discrepancies.show', compact('discrepancy', 'data', 'totals'));
    }

    public function destroy(int $id)
    {
        ListingAvailableStockDiscrepancy::findOrFail($id)->delete();

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
     * Fix: set variation.listed_stock and marketplace_stock.listed_stock to should_be (DB only; no Back Market push).
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
            $discrepancy = ListingAvailableStockDiscrepancy::find($id);
            if (! $discrepancy) {
                continue;
            }

            $variation = Variation_model::find($discrepancy->variation_id);
            if (! $variation) {
                continue;
            }

            $targetQty = (int) $discrepancy->should_be;

            $marketplaceStock = MarketplaceStockModel::firstOrCreate(
                [
                    'variation_id' => $variation->id,
                    'marketplace_id' => 1,
                ],
                [
                    'listed_stock' => 0,
                    'manual_adjustment' => 0,
                    'locked_stock' => 0,
                ]
            );

            $marketplaceStock->listed_stock = $targetQty;
            $marketplaceStock->save();

            $variation->listed_stock = $targetQty;
            $variation->save();

            $discrepancy->delete();
            $fixed++;
        }

        $message = $fixed === 0
            ? 'No records fixed.'
            : "Fixed {$fixed} record(s). Listed set to Should Be in DB only (no Back Market push).";

        return redirect()->route('v2.extras.listing-available-stock-discrepancies.index')
            ->with('success', $message);
    }

    /**
     * Dashboard-style totals: total listed (sum variation.listed_stock) and global should_be (widget formula).
     * Also breaks down Total Listed by grade so we can see when the excess is from grade 6+ (not in Should Be).
     */
    private function getDashboardTotals(): array
    {
        $listedTotal = (int) Variation_model::where('listed_stock', '>', 0)->sum('listed_stock');

        $listedGradeUnder6 = (int) Variation_model::where('listed_stock', '>', 0)->where('grade', '<', 6)->sum('listed_stock');
        $listedGrade6Plus = (int) Variation_model::where('listed_stock', '>', 0)->where('grade', '>=', 6)->sum('listed_stock');

        $aftersaleStockIds = Order_item_model::whereHas('order', function ($query) {
            $query->where('order_type_id', 4)->where('status', '<', 3);
        })->pluck('stock_id')->toArray();

        $gradedInventory = Stock_model::select('variation.grade as grade_id', DB::raw('COUNT(*) as quantity'))
            ->when(! empty($aftersaleStockIds), function ($query) use ($aftersaleStockIds) {
                $query->whereNotIn('stock.id', $aftersaleStockIds);
            })
            ->where('stock.status', 1)
            ->join('variation', 'stock.variation_id', '=', 'variation.id')
            ->groupBy('variation.grade')
            ->get();

        $processCount = Process_stock_model::whereHas('process', function ($query) {
            $query->where('process_type_id', 22)->where('status', '<', 3);
        })->count();

        $pendingOrderCount = Order_model::where('status', 2)->where('order_type_id', 3)->count();

        $shouldBeTotal = max(0, $gradedInventory->where('grade_id', '<', 6)->sum('quantity') - $processCount - $pendingOrderCount);

        return [
            'listed_total' => $listedTotal,
            'listed_grade_under_6' => $listedGradeUnder6,
            'listed_grade_6_plus' => $listedGrade6Plus,
            'should_be_total' => $shouldBeTotal,
            'difference_total' => $listedTotal - $shouldBeTotal,
        ];
    }
}
