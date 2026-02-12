<?php

namespace App\Console\Commands;

use App\Http\Controllers\BackMarketAPIController;
use App\Jobs\SyncShippedOrderToBackMarketJob;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\ListingThirtyOrderRef;
use App\Models\Currency_model;
use App\Models\Country_model;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshOrders extends Command
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
        $bm = new BackMarketAPIController();
        $order_model = new Order_model();
        $order_item_model = new Order_item_model();

        $currency_codes = Currency_model::pluck('id','code')->toArray();
        $country_codes = Country_model::pluck('id','code')->toArray();

        $resArray1 = $bm->getNewOrders(['page-size'=>50]);
        if ($resArray1 !== null) {
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
                    $order_model->updateOrderInDB($orderObj, false, $bm, $currency_codes, $country_codes);
                    $order_item_model->updateOrderItemsInDB($orderObj, null, $bm);
                }
            }

            // Record new orders to listing_thirty_order_refs (independent sync record)
            foreach ($resArray1 as $orderObj) {
                if (empty($orderObj) || empty($orderObj->order_id)) {
                    continue;
                }
                $bmOrderId = $orderObj->order_id;
                $order = Order_model::where('reference_id', $bmOrderId)
                    ->where('order_type_id', 3)
                    ->first();
                if (!$order) {
                    continue;
                }
                $firstItem = Order_item_model::where('order_id', $order->id)->first();
                ListingThirtyOrderRef::firstOrCreate(
                    ['order_id' => $order->id],
                    [
                        'variation_id' => $firstItem->variation_id ?? null,
                        'bm_order_id' => $bmOrderId,
                        'source_command' => 'refresh:orders',
                        'synced_at' => now(),
                    ]
                );
            }

            // Align our status with Back Market for pending orders: if BM says pending (state=1) but we have status != 2,
            // set our status to 2 (pending) — but only when no IMEI is attached (order not yet processed).
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

            // We shipped (IMEI + invoice) but BM still shows pending: post to Back Market in a job to update their status.
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
        if ($resArray !== null) {
            foreach ($resArray as $orderObj) {
                if (!empty($orderObj)) {
                $order_model->updateOrderInDB($orderObj, false, $bm, $currency_codes, $country_codes);
                $order_item_model->updateOrderItemsInDB($orderObj,null,$bm);
                }
            }
        } else {
            echo 'No orders have been modified in 3 months!';
        }

        // Backfill processed_at so orders don't appear in "missing processed_at" when they are already shipped.
        $backfilled = Order_model::where('order_type_id', 3)
            ->whereIn('status', [3, 6])
            ->whereNull('processed_at')
            ->where(function ($q) {
                $q->whereNotNull('tracking_number')->orWhereNotNull('label_url');
            })
            ->update(['processed_at' => DB::raw('updated_at')]);
        if ($backfilled > 0) {
            $this->info("Backfilled processed_at for {$backfilled} order(s) so they no longer appear as missing invoice.");
        }

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

}
