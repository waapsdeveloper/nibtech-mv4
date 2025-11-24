# Refurbed Webhook Integration

## Overview
Implemented Refurbed Instant Order Notifications webhook handler to automatically receive and process order updates from Refurbed marketplace in real-time.

## What Was Created

### 1. RefurbedWebhookController
**Location:** `app/Http/Controllers/RefurbedWebhookController.php`

**Features:**
- ✅ HMAC-SHA256 signature verification for security
- ✅ Idempotency handling (prevents duplicate processing)
- ✅ Multiple event type support (orders, order items, offers)
- ✅ Automatic order synchronization to database
- ✅ Customer creation/update
- ✅ Order item tracking
- ✅ Stock updates from offer events
- ✅ Comprehensive error handling and logging

**Supported Event Types:**
- `order.created` - New order received
- `order.updated` - Order status changed
- `order.new` - Alternative new order event
- `order_item.state_changed` - Order item status updated
- `order_item.updated` - Order item modified
- `offer.updated` - Offer details changed
- `offer.out_of_stock` - Offer went out of stock

### 2. Zero Stock Test Endpoint
**Location:** `app/Http/Controllers/RefurbedListingsController.php` - `zeroStock()` method

**Purpose:** Quickly zero out all Refurbed listed stock for testing or inventory management

**Endpoint:** `GET /api/refurbed/listings/zero-stock`

**Features:**
- Sets quantity to 0 for all Refurbed offers via API
- Updates local database (Variation_model.listed_stock)
- Returns detailed results (updated count, failed count, errors)
- Comprehensive logging

## API Endpoints

### Webhook Endpoint (Public)
```
POST /api/refurbed/webhook
```
- **Purpose:** Receive instant notifications from Refurbed
- **Authentication:** HMAC-SHA256 signature verification
- **Headers Required:** `X-Refurbed-Signature` or `X-Signature`
- **Response:** JSON with status
- **Publicly accessible** (no middleware) for external webhooks

### Zero Stock Endpoint (Internal)
```
GET /api/refurbed/listings/zero-stock
```
- **Purpose:** Zero out all Refurbed stock
- **Authentication:** Internal only (requires internal.only middleware)
- **Response:**
```json
{
  "status": "success",
  "message": "Stock zeroed for X listings",
  "updated": 15,
  "failed": 2,
  "total": 17,
  "errors": [
    {
      "sku": "ABC123",
      "error": "API error message"
    }
  ]
}
```

## Configuration

### 1. Add Webhook Secret to .env
```env
# Refurbed API Configuration
REFURBED_API_KEY=your-api-key-here
REFURBED_WEBHOOK_SECRET=your-webhook-secret-here
```

The webhook secret is used to verify that webhook requests are genuinely from Refurbed.

### 2. Configure Webhook in Refurbed Portal

1. Log into Refurbed Merchant Portal
2. Navigate to **Settings** → **Webhooks** or **API Settings**
3. Add new webhook endpoint:
   - **URL:** `https://your-domain.com/api/refurbed/webhook`
   - **Events:** Select all order-related events:
     - Order Created
     - Order Updated
     - Order Item State Changed
   - **Secret:** Copy the secret and add to `.env` as `REFURBED_WEBHOOK_SECRET`

### 3. Webhook URL
Your webhook will be available at:
```
https://your-domain.com/api/refurbed/webhook
```

For local testing with ngrok:
```bash
ngrok http 80
# Use the https URL: https://xxxxx.ngrok.io/api/refurbed/webhook
```

## How It Works

### Order Flow

1. **Webhook Received**
   - Refurbed sends POST request to `/api/refurbed/webhook`
   - Includes `X-Refurbed-Signature` header

2. **Signature Verification**
   - Controller validates HMAC-SHA256 signature
   - Rejects invalid signatures with 401

3. **Idempotency Check**
   - Checks if event_id has been processed before
   - Uses cache (24 hour TTL) to prevent duplicates

4. **Event Processing**
   - Routes to appropriate handler based on event_type
   - For orders: Fetches full order details via API
   - Syncs to database (Order_model, Order_item_model)

5. **Database Sync**
   - Creates/updates customer (Customer_model)
   - Creates/updates order (Order_model with marketplace_id=4)
   - Creates/updates order items (Order_item_model)
   - Maps Refurbed states to internal status codes

### State Mapping

**Order States:**
```php
'NEW', 'PENDING' => 1
'ACCEPTED', 'CONFIRMED' => 2
'SHIPPED', 'IN_TRANSIT' => 3
'DELIVERED', 'COMPLETED' => 4
'CANCELLED' => 5
'RETURNED' => 6
```

**Order Item States:** Same as above

## Testing

### 1. Test Webhook Signature Verification
```bash
# Test with invalid signature (should fail)
curl -X POST https://your-domain.com/api/refurbed/webhook \
  -H "Content-Type: application/json" \
  -H "X-Refurbed-Signature: invalid" \
  -d '{"event_type":"order.created","order":{"id":"test"}}'
```

### 2. Test Locally with ngrok
```bash
# Start ngrok
ngrok http 80

# Configure Refurbed webhook with ngrok URL
# Place a test order in Refurbed
# Check Laravel logs
tail -f storage/logs/laravel.log | grep "Refurbed webhook"
```

### 3. Zero Stock Test
```bash
# Zero all Refurbed stock
curl http://your-domain/api/refurbed/listings/zero-stock

# Expected response
{
  "status": "success",
  "message": "Stock zeroed for 15 listings",
  "updated": 15,
  "failed": 0,
  "total": 15,
  "errors": []
}
```

### 4. Manual Webhook Test
Create a test script to simulate Refurbed webhook:

```php
// test-webhook.php
$payload = [
    'event_type' => 'order.created',
    'event_id' => 'test-' . time(),
    'order' => [
        'id' => 'test-order-123',
        'order_number' => 'REF-' . time(),
        'state' => 'NEW',
        'currency' => 'EUR',
        'total_amount' => 299.99,
        'customer' => [
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'Customer',
        ],
    ],
];

$secret = 'your-webhook-secret';
$payloadJson = json_encode($payload);
$signature = hash_hmac('sha256', $payloadJson, $secret);

$ch = curl_init('http://your-domain.com/api/refurbed/webhook');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Refurbed-Signature: ' . $signature,
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
```

## Monitoring

### Check Webhook Logs
```bash
# All Refurbed webhook activity
tail -f storage/logs/laravel.log | grep "Refurbed webhook"

# Only errors
tail -f storage/logs/laravel.log | grep "Refurbed webhook.*error"

# Successful order syncs
tail -f storage/logs/laravel.log | grep "Order synced"
```

### Database Queries
```sql
-- Check recent Refurbed orders
SELECT * FROM orders 
WHERE marketplace_id = 4 
ORDER BY created_at DESC 
LIMIT 10;

-- Check order items
SELECT oi.*, o.reference_id, v.sku 
FROM order_items oi
JOIN orders o ON oi.order_id = o.id
JOIN variations v ON oi.variation_id = v.id
WHERE o.marketplace_id = 4
ORDER BY oi.created_at DESC
LIMIT 20;

-- Check webhook cache (processed events)
-- This is in cache, check with Laravel Tinker:
php artisan tinker
>>> Cache::get('refurbed_webhook_test-123');
```

## Troubleshooting

### Issue: Webhooks not being received

**Check:**
1. Webhook URL is correct in Refurbed portal
2. SSL certificate is valid (Refurbed requires HTTPS)
3. Firewall allows incoming requests
4. Check Refurbed portal for webhook delivery logs

**Solution:**
```bash
# Test if endpoint is accessible
curl -X POST https://your-domain.com/api/refurbed/webhook \
  -H "Content-Type: application/json" \
  -d '{"test":true}'
```

### Issue: Signature verification fails

**Check:**
1. `REFURBED_WEBHOOK_SECRET` matches Refurbed portal
2. Secret has no extra spaces or newlines
3. Check header name: `X-Refurbed-Signature` or `X-Signature`

**Debug:**
```php
// Temporarily log signature details in controller
Log::info('Webhook signature debug', [
    'received_signature' => $request->header('X-Refurbed-Signature'),
    'expected_signature' => hash_hmac('sha256', $request->getContent(), $secret),
    'payload' => $request->getContent(),
]);
```

### Issue: Orders not syncing to database

**Check:**
1. Variations exist for SKUs in order
2. Currency exists in currencies table
3. Country exists in countries table

**Solution:**
```sql
-- Add missing currency
INSERT INTO currencies (code, sign, name, created_at, updated_at) 
VALUES ('EUR', '€', 'Euro', NOW(), NOW());

-- Check variation exists
SELECT * FROM variations WHERE sku = 'YOUR-SKU';
```

### Issue: Duplicate webhooks

**Note:** The controller handles this automatically with 24-hour cache.

**Check cache:**
```php
php artisan tinker
>>> Cache::has('refurbed_webhook_' . $eventId);
```

## Security Considerations

1. **Signature Verification:** All webhooks MUST have valid HMAC-SHA256 signature
2. **HTTPS Only:** Refurbed requires HTTPS endpoints
3. **Idempotency:** Duplicate events are automatically ignored
4. **Rate Limiting:** Consider adding rate limiting to webhook endpoint
5. **IP Whitelisting:** Optional - add Refurbed IP ranges to firewall

### Add Rate Limiting (Optional)
```php
// routes/api.php
Route::post('/refurbed/webhook', [RefurbedWebhookController::class, 'handleWebhook'])
    ->middleware('throttle:60,1') // 60 requests per minute
    ->name('refurbed.webhook');
```

## Performance

- **Async Processing:** Consider moving heavy processing to queues for high-volume webhooks
- **Cache Duration:** Event IDs cached for 24 hours (configurable)
- **Database Queries:** Uses `firstOrNew()` to minimize queries

### Queue Implementation (Optional)
```php
// In handleWebhook() method
dispatch(new ProcessRefurbedWebhook($payload))->onQueue('refurbed-webhooks');
```

## Comparison with BackMarket

| Feature | BackMarket | Refurbed |
|---------|-----------|----------|
| **Marketplace ID** | 1 | 4 |
| **Order Sync** | Cron (RefreshOrders) | Webhook (Real-time) |
| **Listing Sync** | Cron (FunctionsThirty) | Cron (FunctionsThirty) |
| **API Controller** | BackMarketAPIController | RefurbedAPIController |
| **Webhook** | ❌ Not implemented | ✅ Implemented |

## Next Steps

1. ✅ Configure webhook in Refurbed portal
2. ✅ Add `REFURBED_WEBHOOK_SECRET` to `.env`
3. ✅ Test with ngrok locally
4. ✅ Monitor logs for first webhook
5. ⏳ Consider adding queue for async processing (if needed)
6. ⏳ Add monitoring/alerting for webhook failures
7. ⏳ Document Refurbed-specific order fields

## Related Files

- `app/Http/Controllers/RefurbedWebhookController.php` - Webhook handler
- `app/Http/Controllers/RefurbedAPIController.php` - API client
- `app/Http/Controllers/RefurbedListingsController.php` - Listings + zero stock
- `routes/api.php` - Webhook route
- `config/services.php` - Configuration
- `.env` - Secrets
