<?php

namespace App\Services\V2;

use App\Models\Marketplace_model;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Http\Controllers\BackMarketAPIController;
use App\Services\V2\OrderSyncService;
use App\Services\V2\SlackLogService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * V2 Marketplace Order Sync Service
 * Generic service to sync orders from any marketplace
 */
class MarketplaceOrderSyncService
{
    protected $orderSyncService;

    public function __construct(OrderSyncService $orderSyncService)
    {
        $this->orderSyncService = $orderSyncService;
    }

    /**
     * Get API controller for a marketplace
     */
    protected function getAPIController($marketplaceId)
    {
        $marketplace = Marketplace_model::find($marketplaceId);
        
        if (!$marketplace) {
            throw new \Exception("Marketplace not found: {$marketplaceId}");
        }

        // Map marketplace to API controller
        // This can be extended for other marketplaces
        switch ($marketplace->id) {
            case 1: // BackMarket
                return new BackMarketAPIController();
            // Add other marketplaces here
            // case 2: // Refurbed
            //     return new RefurbedAPIController();
            default:
                throw new \Exception("No API controller configured for marketplace: {$marketplace->name} (ID: {$marketplace->id})");
        }
    }

    /**
     * Sync new orders from marketplace
     */
    public function syncNewOrders($marketplaceId = null, $params = [])
    {
        $marketplaces = $marketplaceId 
            ? [Marketplace_model::find($marketplaceId)]
            : Marketplace_model::all();

        $totalSynced = 0;
        $totalErrors = 0;

        foreach ($marketplaces as $marketplace) {
            try {
                $apiController = $this->getAPIController($marketplace->id);
                
                if (!method_exists($apiController, 'getNewOrders')) {
                    Log::warning("MarketplaceOrderSyncService: getNewOrders not available for marketplace", [
                        'marketplace_id' => $marketplace->id,
                        'marketplace_name' => $marketplace->name
                    ]);
                    continue;
                }

                $orders = $apiController->getNewOrders($params);
                
                if ($orders === null || empty($orders)) {
                    Log::info("MarketplaceOrderSyncService: No new orders found", [
                        'marketplace_id' => $marketplace->id
                    ]);
                    continue;
                }

                foreach ($orders as $orderObj) {
                    if (empty($orderObj)) {
                        continue;
                    }

                    try {
                        // Validate orderlines (set state to 2)
                        $this->validateOrderlines($orderObj, $apiController);
                        
                        // Sync order
                        $order = $this->orderSyncService->syncOrder($orderObj, $apiController, true);
                        
                        if ($order) {
                            $totalSynced++;
                        }
                    } catch (\Exception $e) {
                        $totalErrors++;
                        Log::error("MarketplaceOrderSyncService: Error syncing order", [
                            'marketplace_id' => $marketplace->id,
                            'order_id' => $orderObj->order_id ?? 'unknown',
                            'error' => $e->getMessage()
                        ]);
                        
                        // Send critical errors to Slack (only for first few to avoid spam)
                        if ($totalErrors <= 5) {
                            SlackLogService::post('order_sync', 'error', "Error syncing order: {$e->getMessage()}", [
                                'marketplace_id' => $marketplace->id,
                                'marketplace_name' => $marketplace->name ?? 'unknown',
                                'order_id' => $orderObj->order_id ?? 'unknown',
                                'error' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                $totalErrors++;
                Log::error("MarketplaceOrderSyncService: Error syncing marketplace", [
                    'marketplace_id' => $marketplace->id,
                    'error' => $e->getMessage()
                ]);
                
                // Send marketplace-level errors to Slack
                SlackLogService::post('order_sync', 'error', "Error syncing marketplace: {$e->getMessage()}", [
                    'marketplace_id' => $marketplace->id,
                    'marketplace_name' => $marketplace->name ?? 'unknown',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
        }

        return [
            'synced' => $totalSynced,
            'errors' => $totalErrors
        ];
    }

    /**
     * Sync modified orders from marketplace
     */
    public function syncModifiedOrders($marketplaceId = null, $params = [])
    {
        $marketplaces = $marketplaceId 
            ? [Marketplace_model::find($marketplaceId)]
            : Marketplace_model::all();

        $totalSynced = 0;
        $totalErrors = 0;

        foreach ($marketplaces as $marketplace) {
            try {
                $apiController = $this->getAPIController($marketplace->id);
                
                if (!method_exists($apiController, 'getAllOrders')) {
                    Log::warning("MarketplaceOrderSyncService: getAllOrders not available for marketplace", [
                        'marketplace_id' => $marketplace->id,
                        'marketplace_name' => $marketplace->name
                    ]);
                    continue;
                }

                // Get all modified orders (default: last 3 months)
                $orders = $apiController->getAllOrders(1, $params, false);
                
                if ($orders === null || empty($orders)) {
                    Log::info("MarketplaceOrderSyncService: No modified orders found", [
                        'marketplace_id' => $marketplace->id
                    ]);
                    continue;
                }

                foreach ($orders as $orderObj) {
                    if (empty($orderObj)) {
                        continue;
                    }

                    try {
                        // Sync order (will fire OrderStatusChanged if status changed)
                        $order = $this->orderSyncService->syncOrder($orderObj, $apiController, true);
                        
                        if ($order) {
                            $totalSynced++;
                        }
                    } catch (\Exception $e) {
                        $totalErrors++;
                        Log::error("MarketplaceOrderSyncService: Error syncing order", [
                            'marketplace_id' => $marketplace->id,
                            'order_id' => $orderObj->order_id ?? 'unknown',
                            'error' => $e->getMessage()
                        ]);
                        
                        // Send critical errors to Slack (only for first few to avoid spam)
                        if ($totalErrors <= 5) {
                            SlackLogService::post('order_sync', 'error', "Error syncing order: {$e->getMessage()}", [
                                'marketplace_id' => $marketplace->id,
                                'marketplace_name' => $marketplace->name ?? 'unknown',
                                'order_id' => $orderObj->order_id ?? 'unknown',
                                'error' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                $totalErrors++;
                Log::error("MarketplaceOrderSyncService: Error syncing marketplace", [
                    'marketplace_id' => $marketplace->id,
                    'error' => $e->getMessage()
                ]);
                
                // Send marketplace-level errors to Slack
                SlackLogService::post('order_sync', 'error', "Error syncing marketplace: {$e->getMessage()}", [
                    'marketplace_id' => $marketplace->id,
                    'marketplace_name' => $marketplace->name ?? 'unknown',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
        }

        return [
            'synced' => $totalSynced,
            'errors' => $totalErrors
        ];
    }

    /**
     * Sync care/replacement records from marketplace
     */
    public function syncCareRecords($marketplaceId = null, $params = [])
    {
        $marketplaces = $marketplaceId 
            ? [Marketplace_model::find($marketplaceId)]
            : Marketplace_model::where('id', 1)->get(); // Only BackMarket for now

        $totalSynced = 0;
        $totalErrors = 0;

        foreach ($marketplaces as $marketplace) {
            try {
                $apiController = $this->getAPIController($marketplace->id);
                
                if (!method_exists($apiController, 'getAllCare')) {
                    Log::warning("MarketplaceOrderSyncService: getAllCare not available for marketplace", [
                        'marketplace_id' => $marketplace->id,
                        'marketplace_name' => $marketplace->name
                    ]);
                    continue;
                }

                // Get latest care_id
                $lastCareId = Order_item_model::select('care_id')
                    ->where('care_id', '!=', null)
                    ->whereHas('order', function($query) use ($marketplace) {
                        $query->where('marketplace_id', $marketplace->id);
                    })
                    ->orderByDesc('care_id')
                    ->first();

                $careParams = array_merge([
                    'last_id' => $lastCareId->care_id ?? null,
                    'page-size' => 50
                ], $params);

                $careRecords = $apiController->getAllCare(false, $careParams);
                
                if (empty($careRecords)) {
                    Log::info("MarketplaceOrderSyncService: No care records found", [
                        'marketplace_id' => $marketplace->id
                    ]);
                    continue;
                }

                // Map care records to order items
                $careLine = collect($careRecords)->pluck('id', 'orderline')->toArray();

                foreach ($careLine as $orderlineReferenceId => $careId) {
                    try {
                        $updated = Order_item_model::where('reference_id', $orderlineReferenceId)
                            ->update(['care_id' => $careId]);
                        
                        if ($updated > 0) {
                            $totalSynced++;
                        }
                    } catch (\Exception $e) {
                        $totalErrors++;
                        Log::error("MarketplaceOrderSyncService: Error updating care record", [
                            'marketplace_id' => $marketplace->id,
                            'orderline_reference_id' => $orderlineReferenceId,
                            'care_id' => $careId,
                            'error' => $e->getMessage()
                        ]);
                        
                        // Send critical errors to Slack (only for first few to avoid spam)
                        if ($totalErrors <= 5) {
                            SlackLogService::post('order_sync', 'error', "Error updating care record: {$e->getMessage()}", [
                                'marketplace_id' => $marketplace->id,
                                'orderline_reference_id' => $orderlineReferenceId,
                                'care_id' => $careId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                $totalErrors++;
                Log::error("MarketplaceOrderSyncService: Error syncing care records", [
                    'marketplace_id' => $marketplace->id,
                    'error' => $e->getMessage()
                ]);
                
                // Send marketplace-level errors to Slack
                SlackLogService::post('order_sync', 'error', "Error syncing care records: {$e->getMessage()}", [
                    'marketplace_id' => $marketplace->id,
                    'marketplace_name' => $marketplace->name ?? 'unknown',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
        }

        return [
            'synced' => $totalSynced,
            'errors' => $totalErrors
        ];
    }

    /**
     * Sync incomplete orders (missing labels/delivery notes)
     */
    public function syncIncompleteOrders($marketplaceId = null, $daysBack = 2)
    {
        $query = Order_model::whereIn('status', [0, 1, 2])
            ->where(function($q) {
                $q->whereNull('delivery_note_url')
                  ->orWhereNull('label_url');
            })
            ->where('order_type_id', 3) // Marketplace orders
            ->where('created_at', '>=', Carbon::now()->subDays($daysBack));

        if ($marketplaceId) {
            $query->where('marketplace_id', $marketplaceId);
        }

        $orders = $query->pluck('reference_id');
        $totalSynced = 0;
        $totalErrors = 0;

        foreach ($orders as $orderReferenceId) {
            try {
                $order = Order_model::where('reference_id', $orderReferenceId)->first();
                if (!$order) {
                    continue;
                }

                $apiController = $this->getAPIController($order->marketplace_id);
                
                if (!method_exists($apiController, 'getOneOrder')) {
                    continue;
                }

                $orderObj = $apiController->getOneOrder($orderReferenceId);
                
                if (isset($orderObj->order_id)) {
                    $this->orderSyncService->syncOrder($orderObj, $apiController, false); // Don't fire events for updates
                    $totalSynced++;
                }
            } catch (\Exception $e) {
                $totalErrors++;
                Log::error("MarketplaceOrderSyncService: Error syncing incomplete order", [
                    'order_reference_id' => $orderReferenceId,
                    'error' => $e->getMessage()
                ]);
                
                // Send critical errors to Slack (only for first few to avoid spam)
                if ($totalErrors <= 5) {
                    SlackLogService::post('order_sync', 'error', "Error syncing incomplete order: {$e->getMessage()}", [
                        'order_reference_id' => $orderReferenceId,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                }
            }
        }

        return [
            'synced' => $totalSynced,
            'errors' => $totalErrors
        ];
    }

    /**
     * Validate orderlines (set state to 2)
     */
    protected function validateOrderlines($orderObj, $apiController)
    {
        if (!isset($orderObj->orderlines) || empty($orderObj->orderlines)) {
            return;
        }

        if (!method_exists($apiController, 'apiPost')) {
            return;
        }

        foreach ($orderObj->orderlines as $orderline) {
            try {
                $endpoint = 'orders/' . $orderObj->order_id;
                $request = [
                    'order_id' => $orderObj->order_id,
                    'new_state' => 2,
                    'sku' => $orderline->listing ?? null
                ];
                $requestJson = json_encode($request);

                $apiController->apiPost($endpoint, $requestJson);
            } catch (\Exception $e) {
                Log::warning("MarketplaceOrderSyncService: Error validating orderline", [
                    'order_id' => $orderObj->order_id ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}

