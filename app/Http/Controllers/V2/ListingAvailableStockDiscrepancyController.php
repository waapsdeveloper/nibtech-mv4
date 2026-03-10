<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Controllers\BackMarketAPIController;
use App\Models\ListingAvailableStockDiscrepancy;
use App\Models\Variation_model;
use App\Models\Stock_model;
use App\Models\V2\MarketplaceStockModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

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

        // Total stocks (table): same query as listing page get_variation_available_stocks (pagination.total)
        $variationIds = $discrepancies->pluck('variation_id')->unique()->values()->all();
        $stocksTableCounts = $this->getStocksTableCountsForVariations($variationIds);

        return view('v2.extras.listing-available-stock-discrepancies.index', compact('discrepancies', 'data', 'stocksTableCounts'));
    }

    public function show(int $id)
    {
        $discrepancy = ListingAvailableStockDiscrepancy::with('variation.product')->findOrFail($id);
        $data['title_page'] = 'Discrepancy: ' . ($discrepancy->variation_sku ?? 'Variation #' . $discrepancy->variation_id);
        session()->put('page_title', $data['title_page']);

        // Live count: same as listing page stocks table (get_variation_available_stocks)
        $stocksTableCount = $this->getStocksTableCountsForVariations([$discrepancy->variation_id])[$discrepancy->variation_id] ?? $discrepancy->stocks_table_count;

        return view('v2.extras.listing-available-stock-discrepancies.show', compact('discrepancy', 'data', 'stocksTableCount'));
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
     * Fix: set Listed (BM) to match Total stocks (table), push to Back Market, then remove discrepancy.
     * Also sets available_count_override so the card "Available" matches.
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
        $errors = [];

        foreach ($ids as $id) {
            $discrepancy = ListingAvailableStockDiscrepancy::find($id);
            if (! $discrepancy) {
                continue;
            }

            $variation = Variation_model::find($discrepancy->variation_id);
            if (! $variation) {
                $errors[] = "Variation {$discrepancy->variation_id} not found.";
                continue;
            }

            // Use live count (same as listing page stocks table), not stored value
            $targetQty = (int) ($this->getStocksTableCountsForVariations([$variation->id])[$variation->id] ?? $discrepancy->stocks_table_count);

            // Back Market (marketplace_id = 1): update our DB and push to API
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

            $oldListed = (int) ($marketplaceStock->listed_stock ?? 0);

            if ($variation->reference_id) {
                try {
                    $bm = new BackMarketAPIController();
                    $response = $bm->updateOneListing(
                        $variation->reference_id,
                        json_encode(['quantity' => $targetQty]),
                        null,
                        true
                    );
                    if (is_string($response) || is_int($response) || $response === null) {
                        $errors[] = ($variation->sku ?? "Variation {$variation->id}") . ': BM API error.';
                        continue;
                    }
                    $apiQty = (int) (is_object($response) ? ($response->quantity ?? $targetQty) : $targetQty);
                    $targetQty = $apiQty;
                } catch (\Throwable $e) {
                    Log::warning('ListingAvailableStockDiscrepancy fix: BM API failed', [
                        'variation_id' => $variation->id,
                        'error' => $e->getMessage(),
                    ]);
                    $errors[] = ($variation->sku ?? "Variation {$variation->id}") . ': ' . $e->getMessage();
                    continue;
                }
            }

            // Update our DB: Listed (BM) = Total stocks (table)
            $marketplaceStock->listed_stock = $targetQty;
            $marketplaceStock->manual_adjustment = 0;
            $marketplaceStock->last_synced_at = now();
            $marketplaceStock->last_api_quantity = $targetQty;
            $marketplaceStock->save();

            $variation->listed_stock = $targetQty;
            $variation->save();

            // Align card "Available" with stocks table (existing behaviour)
            Variation_model::where('id', $discrepancy->variation_id)->update([
                'available_count_override' => $targetQty,
            ]);

            $discrepancy->delete();
            $fixed++;
        }

        $message = $fixed === 0 && empty($errors)
            ? 'No records fixed.'
            : "Fixed {$fixed} record(s). Listed (BM) set to Total stocks (table) and pushed to Back Market.";
        if (! empty($errors)) {
            $message .= ' Errors: ' . implode(' ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= ' (+' . (count($errors) - 3) . ' more)';
            }
        }

        return redirect()->route('v2.extras.listing-available-stock-discrepancies.index')
            ->with('success', $message);
    }

    /**
     * Same query as ListingController::get_variation_available_stocks (stocks table in listing card details).
     * Returns [ variation_id => count ] for the given variation IDs.
     */
    private function getStocksTableCountsForVariations(array $variationIds): array
    {
        if (empty($variationIds)) {
            return [];
        }

        return Stock_model::query()
            ->whereIn('variation_id', $variationIds)
            ->where('status', 1)
            ->whereHas('latest_closed_listing_or_topup')
            ->selectRaw('variation_id, count(*) as cnt')
            ->groupBy('variation_id')
            ->pluck('cnt', 'variation_id')
            ->map(fn ($c) => (int) $c)
            ->all();
    }
}
