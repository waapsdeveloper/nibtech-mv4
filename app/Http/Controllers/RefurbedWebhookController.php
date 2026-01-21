<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateOrderInDB;
use App\Models\Order_model;
use App\Models\Order_item_model;
use App\Models\Customer_model;
use App\Models\Currency_model;
use App\Models\Country_model;
use App\Models\Variation_model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;

class RefurbedWebhookController extends Controller
{
    /**
     * Handle incoming Refurbed webhook notifications
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        // Verify webhook signature
        if (!$this->verifySignature($request)) {
            Log::warning('Refurbed webhook: Invalid signature', [
                'ip' => $request->ip(),
                'headers' => $request->headers->all(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $eventType = $payload['event_type'] ?? $payload['type'] ?? 'unknown';
        $eventId = $payload['event_id'] ?? $payload['id'] ?? Str::uuid();

        Log::info('Refurbed webhook received', [
            'event_type' => $eventType,
            'event_id' => $eventId,
            'payload' => $payload,
        ]);

        // Check for duplicate webhook (idempotency)
        if ($this->isDuplicateEvent($eventId)) {
            Log::info('Refurbed webhook: Duplicate event ignored', ['event_id' => $eventId]);
            return response()->json(['status' => 'duplicate', 'message' => 'Event already processed'], 200);
        }

        try {
            // Process different event types
            switch ($eventType) {
                case 'order.created':
                case 'order.updated':
                case 'order.new':
                    $this->handleOrderEvent($payload);
                    break;

                case 'order_item.state_changed':
                case 'order_item.updated':
                    $this->handleOrderItemEvent($payload);
                    break;

                case 'offer.updated':
                case 'offer.out_of_stock':
                    $this->handleOfferEvent($payload);
                    break;

                default:
                    Log::info('Refurbed webhook: Unhandled event type', ['event_type' => $eventType]);
            }

            // Mark event as processed
            $this->markEventProcessed($eventId);

            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Refurbed webhook: Processing error', [
                'event_type' => $eventType,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Verify webhook signature using HMAC-SHA256
     */
    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Refurbed-Signature') ?? $request->header('X-Signature');

        if (!$signature) {
            return false;
        }

        $secret = config('services.refurbed.webhook_secret') ?? env('REFURBED_WEBHOOK_SECRET');

        if (!$secret) {
            Log::warning('Refurbed webhook: No webhook secret configured');
            return true; // Allow in development if no secret is set
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Check if event has already been processed (idempotency)
     */
    protected function isDuplicateEvent(string $eventId): bool
    {
        return \Illuminate\Support\Facades\Cache::has("refurbed_webhook_{$eventId}");
    }

    /**
     * Mark event as processed to prevent duplicates
     */
    protected function markEventProcessed(string $eventId): void
    {
        // Store for 24 hours
        \Illuminate\Support\Facades\Cache::put("refurbed_webhook_{$eventId}", true, now()->addHours(24));
    }

    /**
     * Handle order creation/update events
     */
    protected function handleOrderEvent(array $payload): void
    {
        $orderData = $payload['order'] ?? $payload['data'] ?? null;

        if (!$orderData) {
            Log::warning('Refurbed webhook: No order data in payload');
            return;
        }

        $orderId = $orderData['id'] ?? null;
        $orderNumber = $orderData['order_number'] ?? $orderData['reference'] ?? null;

        if (!$orderId || !$orderNumber) {
            Log::warning('Refurbed webhook: Missing order ID or number');
            return;
        }

        // Fetch full order details from API
        $refurbed = new RefurbedAPIController();

        try {
            $fullOrder = $refurbed->getOrder($orderId);
            $this->syncOrderToDB($fullOrder);

            Log::info('Refurbed webhook: Order synced', [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
            ]);

        } catch (\Exception $e) {
            Log::error('Refurbed webhook: Failed to fetch order details', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle order item state change events
     */
    protected function handleOrderItemEvent(array $payload): void
    {
        $itemData = $payload['order_item'] ?? $payload['data'] ?? null;

        if (!$itemData) {
            Log::warning('Refurbed webhook: No order item data in payload');
            return;
        }

        $orderId = $itemData['order_id'] ?? null;

        if ($orderId) {
            // Fetch and sync the full order
            $refurbed = new RefurbedAPIController();

            try {
                $fullOrder = $refurbed->getOrder($orderId);
                $this->syncOrderToDB($fullOrder);

            } catch (\Exception $e) {
                Log::error('Refurbed webhook: Failed to fetch order for item update', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle offer update events
     */
    protected function handleOfferEvent(array $payload): void
    {
        $offerData = $payload['offer'] ?? $payload['data'] ?? null;

        if (!$offerData) {
            Log::warning('Refurbed webhook: No offer data in payload');
            return;
        }

        $sku = $offerData['sku'] ?? $offerData['merchant_sku'] ?? null;
        $quantity = $offerData['quantity'] ?? $offerData['stock'] ?? null;

        if ($sku && $quantity !== null) {
            // Update variation stock
            $variation = Variation_model::where('sku', $sku)->first();

            if ($variation) {
                $variation->listed_stock = $quantity;
                $variation->save();

                Log::info('Refurbed webhook: Stock updated', [
                    'sku' => $sku,
                    'quantity' => $quantity,
                ]);
            }
        }
    }

    /**
     * Sync Refurbed order to database (similar to BackMarket pattern)
     */
    protected function syncOrderToDB(array $orderData): void
    {
        $marketplace_id = 4; // Refurbed marketplace ID

        $orderId = $orderData['id'] ?? null;
        $orderNumber = $orderData['order_number'] ?? $orderData['reference'] ?? null;
        $orderState = $orderData['state'] ?? 'NEW';

        // Get or create customer
        $customerData = $orderData['customer'] ?? $orderData['buyer'] ?? [];
        $customer = $this->getOrCreateCustomer($customerData);

        // Get currency (prefer settlement currency so values align with EUR payouts)
        $currencyCode = $orderData['settlement_currency_code']
            ?? $orderData['currency']
            ?? $orderData['currency_code']
            ?? 'EUR';
        $currency = Currency_model::where('code', $currencyCode)->first();

        if (!$currency) {
            Log::warning('Refurbed webhook: Currency not found', ['currency' => $currencyCode]);
            return;
        }

        // Get country
        $countryCode = $orderData['country'] ?? $orderData['shipping_address']['country'] ?? 'DE';
        $country = Country_model::where('code', $countryCode)->first();

        if (!$country) {
            Log::warning('Refurbed webhook: Country not found', ['country' => $countryCode]);
            $country = Country_model::first(); // Fallback to first country
        }

        // Create or update order
        try {
            // Use updateOrCreate to prevent race conditions - atomic operation
            $order = Order_model::updateOrCreate(
                [
                    'reference_id' => $orderNumber,
                    'marketplace_id' => $marketplace_id,
                ],
                [
                    // Default values only used when creating new order
                    'marketplace_id' => $marketplace_id,
                    'status' => $this->mapOrderState($orderState),
                ]
            );
            
            // Update fields that should always be updated (not just on create)
            $order->customer_id = $customer->id;
            $order->currency_id = $currency->id;
            $order->country_id = $country->id ?? 1;
            $order->status = $this->mapOrderState($orderState);

            if (! $order->reference) {
                $order->reference = $orderId;
            }

            // Set additional fields from order data
            if (isset($orderData['created_at'])) {
                $order->created_at = $orderData['created_at'];
            }

            $settlementTotal = $orderData['settlement_total_paid']
                ?? $orderData['total_amount']
                ?? $orderData['total_paid']
                ?? null;

            if ($settlementTotal !== null) {
                $order->price = $settlementTotal;
            }

            $order->save();
            
        } catch (QueryException $e) {
            // Handle duplicate key exception (race condition)
            if ($e->getCode() == 23000) {
                // Duplicate entry - fetch existing order and continue
                $order = Order_model::where([
                    'reference_id' => $orderNumber,
                    'marketplace_id' => $marketplace_id,
                ])->first();
                
                if ($order) {
                    Log::warning('RefurbedWebhook: Duplicate order creation prevented (race condition)', [
                        'reference_id' => $orderNumber,
                        'marketplace_id' => $marketplace_id,
                        'order_id' => $order->id,
                    ]);
                    
                    // Continue with update flow
                    $order->customer_id = $customer->id;
                    $order->currency_id = $currency->id;
                    $order->country_id = $country->id ?? 1;
                    $order->status = $this->mapOrderState($orderState);

                    if (! $order->reference) {
                        $order->reference = $orderId;
                    }

                    // Set additional fields from order data
                    if (isset($orderData['created_at'])) {
                        $order->created_at = $orderData['created_at'];
                    }

                    $settlementTotal = $orderData['settlement_total_paid']
                        ?? $orderData['total_amount']
                        ?? $orderData['total_paid']
                        ?? null;

                    if ($settlementTotal !== null) {
                        $order->price = $settlementTotal;
                    }

                    $order->save();
                } else {
                    // Order not found even after duplicate error - log and re-throw
                    Log::error('RefurbedWebhook: Duplicate order error but order not found after retry', [
                        'reference_id' => $orderNumber,
                        'marketplace_id' => $marketplace_id,
                    ]);
                    throw $e;
                }
            } else {
                // Other database errors - re-throw
                throw $e;
            }
        }

        // Sync order items
        $items = $orderData['items'] ?? $orderData['order_items'] ?? [];

        if (empty($items)) {
            // Fetch items separately if not in order data
            try {
                $refurbed = new RefurbedAPIController();
                $itemsResponse = $refurbed->listOrderItems($orderId);
                $items = $itemsResponse['order_items'] ?? $itemsResponse['items'] ?? [];
            } catch (\Exception $e) {
                Log::error('Refurbed webhook: Failed to fetch order items', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($items as $itemData) {
            $this->syncOrderItem($order, $itemData);
        }

        Log::info('Refurbed webhook: Order synced to database', [
            'order_id' => $order->id,
            'reference_id' => $order->reference_id,
        ]);
    }

    /**
     * Sync order item to database
     */
    protected function syncOrderItem(Order_model $order, array $itemData): void
    {
        $sku = $itemData['sku'] ?? $itemData['merchant_sku'] ?? null;

        if (!$sku) {
            Log::warning('Refurbed webhook: Order item missing SKU');
            return;
        }

        $variation = Variation_model::where('sku', $sku)->first();

        if (!$variation) {
            Log::warning('Refurbed webhook: Variation not found', ['sku' => $sku]);
            return;
        }

        $itemId = $itemData['id'] ?? null;
        $itemState = $itemData['state'] ?? 'NEW';

        // Create or update order item
        $orderItem = Order_item_model::firstOrNew([
            'order_id' => $order->id,
            'variation_id' => $variation->id,
            'reference_id' => $itemId,
        ]);

        $orderItem->quantity = (int) ($itemData['quantity'] ?? 1);
        if ($orderItem->quantity === 0) {
            $orderItem->quantity = 1;
        }

        $price = $this->resolveOrderItemPrice($itemData);
        if ($price !== null) {
            $orderItem->price = $price;
        }
        $orderItem->status = $this->mapOrderItemState($itemState);

        $orderItem->save();
    }

    /**
     * Get or create customer from order data
     */
    protected function getOrCreateCustomer(array $customerData): Customer_model
    {
        $email = $customerData['email'] ?? null;
        $firstName = $customerData['first_name'] ?? $customerData['firstname'] ?? 'Refurbed';
        $lastName = $customerData['last_name'] ?? $customerData['lastname'] ?? 'Customer';
        $phone = $customerData['phone'] ?? $customerData['telephone'] ?? '';

        if ($email) {
            $customer = Customer_model::firstOrNew(['email' => $email]);
        } else {
            // Create anonymous customer
            $customer = new Customer_model();
        }

        $customer->first_name = $firstName;
        $customer->last_name = $lastName;
        $customer->phone = $phone;
        $customer->status = 1;

        $customer->save();

        return $customer;
    }

    /**
     * Map Refurbed order state to internal status
     */
    protected function mapOrderState(string $state): int
    {
        return match (strtoupper($state)) {
            'NEW', 'PENDING' => 1,
            'ACCEPTED', 'CONFIRMED' => 2,
            'SHIPPED', 'IN_TRANSIT' => 3,
            'DELIVERED', 'COMPLETED' => 4,
            'CANCELLED' => 5,
            'RETURNED' => 6,
            default => 1,
        };
    }

    /**
     * Map Refurbed order item state to internal status
     */
    protected function mapOrderItemState(string $state): int
    {
        return match (strtoupper($state)) {
            'NEW', 'PENDING' => 1,
            'ACCEPTED', 'CONFIRMED' => 2,
            'SHIPPED' => 3,
            'DELIVERED' => 4,
            'CANCELLED' => 5,
            'RETURNED' => 6,
            default => 1,
        };
    }

    protected function resolveOrderItemPrice(array $itemData): ?float
    {
        $candidates = [
            'unit_price' => $itemData['unit_price'] ?? null,
            'price' => $itemData['price'] ?? null,
            'settlement_unit_price' => $itemData['settlement_unit_price'] ?? null,
            'settlement_price' => $itemData['settlement_price'] ?? null,
            'total_price' => $itemData['total_price'] ?? null,
            'price_total' => $itemData['price_total'] ?? null,
            'gross_price' => $itemData['gross_price'] ?? null,
            'net_price' => $itemData['net_price'] ?? null,
        ];

        foreach ($candidates as $label => $value) {
            $numeric = $this->normalizePriceValue($value);
            if ($numeric === null) {
                continue;
            }

            if (in_array($label, ['total_price', 'price_total'], true)) {
                $quantity = (float) ($itemData['quantity'] ?? 0);
                if ($quantity > 0) {
                    return round($numeric / $quantity, 2);
                }
            }

            return $numeric;
        }

        $quantity = (float) ($itemData['quantity'] ?? 0);
        if ($quantity > 0) {
            $total = $this->normalizePriceValue($itemData['total_price'] ?? null);
            if ($total !== null) {
                return round($total / $quantity, 2);
            }
        }

        return null;
    }

    protected function normalizePriceValue($value): ?float
    {
        if (is_array($value)) {
            if (isset($value['amount'])) {
                $value = $value['amount'];
            } elseif (isset($value['value'])) {
                $value = $value['value'];
            }
        }

        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
