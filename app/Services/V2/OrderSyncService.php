<?php

namespace App\Services\V2;

use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Customer_model;
use App\Models\Currency_model;
use App\Models\Country_model;
use App\Events\V2\OrderCreated;
use App\Events\V2\OrderStatusChanged;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * V2 Order Sync Service
 * Handles order creation and updates with event firing
 */
class OrderSyncService
{
    protected $currencyCodes;
    protected $countryCodes;

    public function __construct()
    {
        $this->currencyCodes = Currency_model::pluck('id', 'code')->toArray();
        $this->countryCodes = Country_model::pluck('id', 'code')->toArray();
    }

    /**
     * Sync a single order from marketplace API response
     * Fires OrderCreated event if new, OrderStatusChanged if status changed
     * 
     * @param object $orderObj Order object from marketplace API
     * @param object $apiController Marketplace API controller instance
     * @param bool $fireEvents Whether to fire events (default: true)
     * @return Order_model|null
     */
    public function syncOrder($orderObj, $apiController, $fireEvents = true)
    {
        if (!isset($orderObj->order_id)) {
            Log::warning('OrderSyncService: Order object missing order_id', [
                'order_obj' => $orderObj
            ]);
            return null;
        }

        $marketplaceId = (int) ($orderObj->marketplace_id ?? 1);
        
        // Find or create order
        $order = Order_model::firstOrNew([
            'reference_id' => $orderObj->order_id,
            'marketplace_id' => $marketplaceId,
        ]);

        $isNewOrder = !$order->exists;
        $oldStatus = $order->status;

        // Update order data
        $this->updateOrderData($order, $orderObj, $apiController, $marketplaceId);
        
        // Save order
        $order->save();

        // Sync order items
        $orderItems = $this->syncOrderItems($order, $orderObj, $apiController);

        // Fire events
        if ($fireEvents) {
            if ($isNewOrder) {
                // Fire OrderCreated event
                event(new OrderCreated($order, $orderItems));
                Log::info('OrderSyncService: OrderCreated event fired', [
                    'order_id' => $order->id,
                    'reference_id' => $order->reference_id,
                    'marketplace_id' => $marketplaceId
                ]);
            } elseif ($oldStatus !== null && $order->status != $oldStatus) {
                // Fire OrderStatusChanged event
                event(new OrderStatusChanged($order, $oldStatus, $order->status, $orderItems));
                Log::info('OrderSyncService: OrderStatusChanged event fired', [
                    'order_id' => $order->id,
                    'reference_id' => $order->reference_id,
                    'old_status' => $oldStatus,
                    'new_status' => $order->status
                ]);
            }
        }

        return $order;
    }

    /**
     * Update order data from API response
     */
    protected function updateOrderData($order, $orderObj, $apiController, $marketplaceId)
    {
        // Update customer if not set
        if ($order->customer_id == null) {
            $customerModel = new Customer_model();
            $order->customer_id = $customerModel->updateCustomerInDB(
                $orderObj,
                false,
                $this->currencyCodes,
                $this->countryCodes
            );
        }

        // Map order status
        $order->status = $this->mapStateToStatus($orderObj);
        
        if ($order->status === null) {
            Log::warning('OrderSyncService: Order status is null', [
                'order_id' => $orderObj->order_id,
                'order_obj' => $orderObj
            ]);
        }

        // Set order properties
        $order->currency = $this->currencyCodes[$orderObj->currency] ?? null;
        $order->order_type_id = 3; // Marketplace order
        $order->marketplace_id = $marketplaceId;
        $order->price = $orderObj->price ?? 0;
        $order->delivery_note_url = $orderObj->delivery_note ?? null;

        // Get label URL if not set
        if ($order->label_url == null && method_exists($apiController, 'getOrderlabel')) {
            $label = $apiController->getOrderlabel($orderObj->order_id);
            if ($label != null && isset($label->results) && !empty($label->results)) {
                $order->label_url = $label->results[0]->labelUrl ?? null;
            }
        }

        // Set payment method
        if (isset($orderObj->payment_method) && $orderObj->payment_method != null) {
            $paymentMethod = \App\Models\Payment_method_model::firstOrNew([
                'name' => $orderObj->payment_method
            ]);
            $paymentMethod->save();
            $order->payment_method_id = $paymentMethod->id;
        }

        // Set tracking number if not set
        if ($order->tracking_number == null && isset($orderObj->tracking_number)) {
            $order->tracking_number = $orderObj->tracking_number;
        }

        // Set timestamps
        if (isset($orderObj->date_creation)) {
            $order->created_at = Carbon::parse($orderObj->date_creation)->format('Y-m-d H:i:s');
        }
        if (isset($orderObj->date_modification)) {
            $order->updated_at = Carbon::parse($orderObj->date_modification)->format('Y-m-d H:i:s');
        }
        if (isset($orderObj->date_shipping) && $order->processed_at == null) {
            $order->processed_at = Carbon::parse($orderObj->date_shipping)->format('Y-m-d H:i:s');
        }
    }

    /**
     * Sync order items from API response
     */
    protected function syncOrderItems($order, $orderObj, $apiController)
    {
        if (!isset($orderObj->orderlines) || empty($orderObj->orderlines)) {
            return collect();
        }

        $orderItemModel = new Order_item_model();
        
        // Update order items (this method handles all items in the order)
        $orderItemModel->updateOrderItemsInDB($orderObj, null, $apiController);
        
        // Get all order items for this order
        $orderItems = Order_item_model::where('order_id', $order->id)->get();

        return $orderItems;
    }

    /**
     * Map marketplace order state to our order status
     */
    protected function mapStateToStatus($orderObj)
    {
        // This should match the logic in Order_model->mapStateToStatus
        // Default mapping for BackMarket
        if (isset($orderObj->state)) {
            switch ($orderObj->state) {
                case 0:
                case 8:
                    return 0; // Pending
                case 1:
                    return 1; // Processing
                case 2:
                    return 2; // Confirmed
                case 3:
                    return 3; // Completed
                case 4:
                    return 4; // Cancelled
                case 5:
                    return 5; // Refunded
                default:
                    return 0;
            }
        }

        // If no state, check orderlines
        if (isset($orderObj->orderlines) && !empty($orderObj->orderlines)) {
            foreach ($orderObj->orderlines as $orderline) {
                if (isset($orderline->state)) {
                    if ($orderline->state == 3) return 3;
                    if ($orderline->state == 4) return 4;
                    if ($orderline->state == 5) return 5;
                }
            }
        }

        return 0; // Default to pending
    }
}

