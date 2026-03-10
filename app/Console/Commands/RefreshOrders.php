<?php

namespace App\Console\Commands;

use App\Http\Controllers\BackMarketAPIController;
use App\Jobs\SyncShippedOrderToBackMarketJob;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Currency_model;
use App\Models\Country_model;
use App\Models\CommandRunLog;
use App\Console\Commands\BaseCommand;
use App\Models\Variation_model;
use App\Models\V2\MarketplaceStockModel;
use App\Events\V2\OrderStatusChanged;
use Illuminate\Support\Facades\DB;

class RefreshOrders extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh:orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {
        CommandRunLog::recordStart('refresh-orders');

        $bm = new BackMarketAPIController();
        $order_model = new Order_model();
        $order_item_model = new Order_item_model();

        $currency_codes = Currency_model::pluck('id','code')->toArray();
        $country_codes = Country_model::pluck('id','code')->toArray();

        $resArray1 = $bm->getNewOrders(['page-size'=>50]);
        $newCount = 0;
        $statusCorrected = 0;
        $shippedSyncDispatched = 0;
        if ($resArray1 !== null) {
            $newCount = count($resArray1);
            foreach ($resArray1 as $orderObj) {
                if (!empty($orderObj)) {
                    foreach($orderObj->orderlines as $orderline){
                        $this->validateOrderlines($orderObj->order_id, $orderline->listing, $bm);
                    }
                }
            }

            // Sync new orders into DB so processed_at (from BM date_shipping) and other fields are updated.
            foreach ($resArray1 as $orderObj) {
                if (!empty($orderObj)) {
                    $referenceId = $orderObj->order_id ?? null;
                    $marketplaceId = (int) ($orderObj->marketplace_id ?? 1);
                    $orderBefore = $referenceId ? Order_model::where('reference_id', $referenceId)
                        ->where('marketplace_id', $marketplaceId)
                        ->where('order_type_id', 3)
                        ->first() : null;
                    $oldStatus = $orderBefore ? $orderBefore->status : null;

                    $order_model->updateOrderInDB($orderObj, false, $bm, $currency_codes, $country_codes);
                    $order_item_model->updateOrderItemsInDB($orderObj, null, $bm);

                    $orderAfter = $referenceId ? Order_model::where('reference_id', $referenceId)
                        ->where('marketplace_id', $marketplaceId)
                        ->where('order_type_id', 3)
                        ->first() : null;
                    if ($orderAfter && $orderAfter->status == 3 && (int) $oldStatus !== 3) {
                        $orderItems = Order_item_model::where('order_id', $orderAfter->id)->get();
                        event(new OrderStatusChanged($orderAfter, (int) ($oldStatus ?? 0), 3, $orderItems));
                    }
                }
            }

            // Align our status with Back Market for pending orders...
            $statusCorrected = 0;
            foreach ($resArray1 as $orderObj) {
                if (empty($orderObj) || empty($orderObj->order_id)) {
                    continue;
                }
                $referenceId = $orderObj->order_id;
                $marketplaceId = (int) ($orderObj->marketplace_id ?? 1);
                $order = Order_model::where('reference_id', $referenceId)
                    ->where('marketplace_id', $marketplaceId)
                    ->where('order_type_id', 3)
                    ->first();
                if (!$order || $order->status == 2) {
                    continue;
                }
                $hasImeiAttached = Order_item_model::where('order_id', $order->id)
                    ->where('stock_id', '>', 0)
                    ->exists();
                if ($hasImeiAttached) {
                    continue;
                }
                $order->status = 2;
                $order->save();
                $statusCorrected++;
            }
            if ($statusCorrected > 0) {
                $this->info("Status corrected to pending (2): {$statusCorrected} order(s) to match Back Market.");
            }

            // We shipped (IMEI + invoice) but BM still shows pending: post to Back Market in a job...
            $shippedSyncDispatched = 0;
            foreach ($resArray1 as $orderObj) {
                if (empty($orderObj) || empty($orderObj->order_id)) {
                    continue;
                }
                $referenceId = $orderObj->order_id;
                $marketplaceId = (int) ($orderObj->marketplace_id ?? 1);
                $order = Order_model::where('reference_id', $referenceId)
                    ->where('marketplace_id', $marketplaceId)
                    ->where('order_type_id', 3)
                    ->first();
                if (!$order) {
                    continue;
                }
                if (!in_array((int) $order->status, [3, 6], true)) {
                    continue;
                }
                $hasImeiAttached = Order_item_model::where('order_id', $order->id)
                    ->where('stock_id', '>', 0)
                    ->exists();
                if (!$hasImeiAttached) {
                    continue;
                }
                $hasInvoice = $order->processed_at !== null;
                if (!$hasInvoice || !$order->tracking_number) {
                    continue;
                }
                SyncShippedOrderToBackMarketJob::dispatch($order->id);
                $shippedSyncDispatched++;
            }
            if ($shippedSyncDispatched > 0) {
                $this->info("Dispatched {$shippedSyncDispatched} job(s) to sync shipped status to Back Market.");
            }
        }

        $modification = false;
        $resArray = $bm->getAllOrders(1, ['page-size'=>50], $modification);
        $modifiedCount = 0;
        if ($resArray !== null) {
            $modifiedCount = count($resArray);
            foreach ($resArray as $orderObj) {
                if (!empty($orderObj)) {
                    $referenceId = $orderObj->order_id ?? null;
                    $marketplaceId = (int) ($orderObj->marketplace_id ?? 1);
                    $orderBefore = $referenceId ? Order_model::where('reference_id', $referenceId)
                        ->where('marketplace_id', $marketplaceId)
                        ->where('order_type_id', 3)
                        ->first() : null;
                    $oldStatus = $orderBefore ? $orderBefore->status : null;

                    $order_model->updateOrderInDB($orderObj, false, $bm, $currency_codes, $country_codes);
                    $order_item_model->updateOrderItemsInDB($orderObj, null, $bm);

                    $orderAfter = $referenceId ? Order_model::where('reference_id', $referenceId)
                        ->where('marketplace_id', $marketplaceId)
                        ->where('order_type_id', 3)
                        ->first() : null;
                    if ($orderAfter && $orderAfter->status == 3 && (int) $oldStatus !== 3) {
                        $orderItems = Order_item_model::where('order_id', $orderAfter->id)->get();
                        event(new OrderStatusChanged($orderAfter, (int) ($oldStatus ?? 0), 3, $orderItems));
                    }
                }
            }
        } else {
            echo 'No orders have been modified in 3 months!';
        }

        $totalProcessed = $newCount + $modifiedCount;
        $note = "New: {$newCount}, Modified: {$modifiedCount}";
        if ($statusCorrected > 0) $note .= "; status corrected: {$statusCorrected}";
        if ($shippedSyncDispatched > 0) $note .= "; shipped sync jobs: {$shippedSyncDispatched}";

        // Sync stock from Back Market only for listings touched by this run (orders just processed)
        $stockSynced = $this->syncStockForProcessedListings($bm, $resArray1 ?? [], $resArray ?? []);
        if ($stockSynced > 0) {
            $note .= "; BM stock synced: {$stockSynced} listing(s)";
        }

        CommandRunLog::recordEnd('refresh-orders', $totalProcessed, $totalProcessed, 0, $note, 'completed');

        return 0;
    }

    private function validateOrderlines($order_id, $sku, $bm)
    {
        $end_point = 'orders/' . $order_id;
        $new_state = 2;

        // construct the request body
        $request = ['order_id' => $order_id, 'new_state' => $new_state, 'sku' => $sku];
        $request_JSON = json_encode($request);

        $result = $bm->apiPost($end_point, $request_JSON);

        return $result;
    }

    /**
     * Sync stock from Back Market only for listings that appear in the orders
     * just processed by this run (new + modified). One getOneListing API call per
     * unique listing – keeps the run short instead of full bulk sync.
     *
     * @param \App\Http\Controllers\BackMarketAPIController $bm
     * @param array $newOrders    Orders from getNewOrders()
     * @param array $modifiedOrders Orders from getAllOrders() (modified)
     * @return int Number of listings whose stock was updated
     */
    private function syncStockForProcessedListings($bm, array $newOrders, array $modifiedOrders): int
    {
        $listingIds = [];

        foreach (array_merge($newOrders, $modifiedOrders) as $orderObj) {
            if (empty($orderObj->orderlines)) {
                continue;
            }
            foreach ($orderObj->orderlines as $orderline) {
                $listingId = $orderline->listing_id ?? $orderline->listing ?? null;
                if ($listingId !== null && $listingId !== '') {
                    $listingIds[(string) $listingId] = true;
                }
            }
        }

        $listingIds = array_keys($listingIds);
        if (empty($listingIds)) {
            return 0;
        }

        $synced = 0;
        $marketplaceId = 1; // Back Market

        foreach ($listingIds as $referenceId) {
            $referenceId = trim((string) $referenceId);
            if ($referenceId === '') {
                continue;
            }

            try {
                $apiListing = $bm->getOneListing($referenceId);
                if (!$apiListing || !isset($apiListing->quantity)) {
                    continue;
                }
                $apiQuantity = (int) $apiListing->quantity;
            } catch (\Throwable $e) {
                continue;
            }

            $variation = Variation_model::where('reference_id', $referenceId)->first();
            if (!$variation) {
                continue;
            }

            $marketplaceStock = MarketplaceStockModel::where('marketplace_id', $marketplaceId)
                ->where('variation_id', $variation->id)
                ->first();
            if (!$marketplaceStock) {
                continue;
            }

            $oldListedStock = $marketplaceStock->listed_stock ?? 0;
            $lockedStock = $marketplaceStock->locked_stock ?? 0;
            $marketplaceStock->listed_stock = $apiQuantity;
            $marketplaceStock->available_stock = max(0, $apiQuantity - $lockedStock);
            $marketplaceStock->last_synced_at = now();
            $marketplaceStock->last_api_quantity = $apiQuantity;
            $marketplaceStock->save();

            $variation->listed_stock = $apiQuantity;
            $variation->save();

            $synced++;
        }

        return $synced;
    }
}
