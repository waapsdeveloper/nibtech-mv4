# Testing Order Stock Locking - Step by Step Guide

## Product Details
- **SKU:** 00895
- **Product:** iPhone 14 Pro 256GB Deep Purple Good
- **Marketplace ID:** 1 (Back Market)
- **Variation ID:** 1248

---

## Prerequisites

1. **Check Current Stock Status**
   ```bash
   php artisan tinker
   ```
   ```php
   $variation = \App\Models\Variation_model::find(1248);
   $marketplaceStock = \App\Models\V2\MarketplaceStockModel::where('variation_id', 1248)->where('marketplace_id', 1)->first();
   
   echo "Variation SKU: " . $variation->sku . "\n";
   echo "Current Listed Stock: " . ($marketplaceStock->listed_stock ?? 0) . "\n";
   echo "Current Locked Stock: " . ($marketplaceStock->locked_stock ?? 0) . "\n";
   echo "Current Available Stock: " . ($marketplaceStock->available_stock ?? 0) . "\n";
   ```

2. **Ensure Stock Exists**
   - If `marketplaceStock` is null, create it:
   ```php
   \App\Models\V2\MarketplaceStockModel::create([
       'variation_id' => 1248,
       'marketplace_id' => 1,
       'listed_stock' => 10,  // Set initial stock
       'locked_stock' => 0,
       'available_stock' => 10,
       'buffer_percentage' => 10.00
   ]);
   ```

---

## Step 1: Create Test Order

### Option A: Using Tinker (Recommended for Testing)

```bash
php artisan tinker
```

```php
// Get or create customer
$customer = \App\Models\Customer_model::firstOrCreate(
    ['email' => 'test@example.com'],
    [
        'first_name' => 'Test',
        'last_name' => 'Customer',
        'phone' => '1234567890'
    ]
);

// Get currency (EUR for Back Market)
$currency = \App\Models\Currency_model::where('code', 'EUR')->first();
if (!$currency) {
    $currency = \App\Models\Currency_model::first();
}

// Get country
$country = \App\Models\Country_model::where('code', 'FR')->first();
if (!$country) {
    $country = \App\Models\Country_model::first();
}

// Create order
$order = \App\Models\Order_model::create([
    'reference_id' => 'TEST-' . time(),  // Unique reference
    'customer_id' => $customer->id,
    'marketplace_id' => 1,  // Back Market
    'order_type_id' => 3,   // Marketplace order
    'status' => 1,          // Pending
    'currency_id' => $currency->id,
    'country_id' => $country->id,
    'created_at' => now(),
]);

// Create order item
$orderItem = \App\Models\Order_item_model::create([
    'order_id' => $order->id,
    'variation_id' => 1248,
    'quantity' => 1,
    'price' => 500.00,
    'status' => 1,
]);

echo "Order created: ID={$order->id}, Reference={$order->reference_id}\n";
echo "Order Item created: ID={$orderItem->id}\n";

// Fire V2 OrderCreated event
$orderItems = collect([$orderItem]);
event(new \App\Events\V2\OrderCreated($order, $orderItems));

echo "V2 OrderCreated event fired!\n";
```

### Option B: Using Test Script

Create a test command:
```bash
php artisan make:command TestOrderStockLocking
```

---

## Step 2: Verify Stock is Locked

### Check Database
```php
// In tinker
$marketplaceStock = \App\Models\V2\MarketplaceStockModel::where('variation_id', 1248)->where('marketplace_id', 1)->first();

echo "After Order Creation:\n";
echo "Listed Stock: " . $marketplaceStock->listed_stock . "\n";
echo "Locked Stock: " . $marketplaceStock->locked_stock . "\n";
echo "Available Stock: " . $marketplaceStock->available_stock . "\n";

// Check lock record
$lock = \App\Models\V2\MarketplaceStockLock::where('order_id', $order->id)->first();
if ($lock) {
    echo "Lock found: ID={$lock->id}, Quantity={$lock->quantity_locked}, Status={$lock->lock_status}\n";
} else {
    echo "No lock found!\n";
}

// Check history
$history = \App\Models\V2\MarketplaceStockHistory::where('order_id', $order->id)->first();
if ($history) {
    echo "History found: Change Type={$history->change_type}, Quantity Change={$history->quantity_change}\n";
}
```

### Check Logs
```bash
tail -f storage/logs/laravel.log | grep "Stock locked"
```

You should see:
```
V2: Stock locked for order {"order_id":X,"order_reference":"TEST-...","variation_id":1248,"marketplace_id":1,"quantity_locked":1,"available_stock_after":9}
```

### Check Visual (Order Detail Page)
1. Go to: `/order?order_id={$order->reference_id}`
2. Scroll down after order items
3. You should see "Stock Locks" card showing the lock

### Check Visual (V2 Listing Page)
1. Go to: `/v2/listing`
2. Find variation 1248
3. Look for marketplace 1 (Back Market)
4. You should see yellow badge: **[1 Locked]**
5. Click badge to see lock details

### Check Visual (Stock Locks Dashboard)
1. Go to: `/v2/stock-locks?order_id={$order->id}`
2. You should see the lock in the table

---

## Step 3: Complete the Order (Test Stock Reduction)

### Update Order Status to Completed
```php
// In tinker
$order = \App\Models\Order_model::find($orderId);  // Use your order ID
$oldStatus = $order->status;
$order->status = 3;  // Completed
$order->save();

// Get order items
$orderItems = $order->order_items;

// Fire V2 OrderStatusChanged event
event(new \App\Events\V2\OrderStatusChanged($order, $oldStatus, 3, $orderItems));

echo "Order status changed to completed!\n";
echo "V2 OrderStatusChanged event fired!\n";
```

### Verify Stock is Reduced
```php
// In tinker
$marketplaceStock = \App\Models\V2\MarketplaceStockModel::where('variation_id', 1248)->where('marketplace_id', 1)->first();

echo "After Order Completion:\n";
echo "Listed Stock: " . $marketplaceStock->listed_stock . "\n";
echo "Locked Stock: " . $marketplaceStock->locked_stock . "\n";
echo "Available Stock: " . $marketplaceStock->available_stock . "\n";

// Check lock status changed
$lock = \App\Models\V2\MarketplaceStockLock::where('order_id', $order->id)->first();
echo "Lock Status: " . $lock->lock_status . "\n";  // Should be 'consumed'

// Check history
$history = \App\Models\V2\MarketplaceStockHistory::where('order_id', $order->id)->where('change_type', 'order_completed')->first();
if ($history) {
    echo "Completion History: Quantity Change={$history->quantity_change}\n";
}
```

### Check Logs
```bash
tail -f storage/logs/laravel.log | grep "Stock reduced"
```

You should see:
```
V2: Stock reduced for completed order {"order_id":X,"order_reference":"TEST-...","variation_id":1248,"marketplace_id":1,"quantity_reduced":1,"listed_stock_after":9,"available_stock_after":9}
```

---

## Step 4: Test Multiple Orders

### Create Multiple Orders
```php
// In tinker
for ($i = 1; $i <= 3; $i++) {
    $order = \App\Models\Order_model::create([
        'reference_id' => 'TEST-MULTI-' . time() . '-' . $i,
        'customer_id' => $customer->id,
        'marketplace_id' => 1,
        'order_type_id' => 3,
        'status' => 1,
        'currency_id' => $currency->id,
        'country_id' => $country->id,
    ]);
    
    $orderItem = \App\Models\Order_item_model::create([
        'order_id' => $order->id,
        'variation_id' => 1248,
        'quantity' => 1,
        'price' => 500.00,
        'status' => 1,
    ]);
    
    event(new \App\Events\V2\OrderCreated($order, collect([$orderItem])));
    echo "Order {$i} created: {$order->reference_id}\n";
}

// Check total locked
$marketplaceStock = \App\Models\V2\MarketplaceStockModel::where('variation_id', 1248)->where('marketplace_id', 1)->first();
echo "Total Locked: " . $marketplaceStock->locked_stock . "\n";
echo "Available Stock: " . $marketplaceStock->available_stock . "\n";
```

---

## Step 5: Test Buffer Application

### Check Buffer is Applied
```php
// In tinker
$marketplaceStock = \App\Models\V2\MarketplaceStockModel::where('variation_id', 1248)->where('marketplace_id', 1)->first();
$marketplaceStock->listed_stock = 100;
$marketplaceStock->locked_stock = 0;
$marketplaceStock->available_stock = 100;
$marketplaceStock->buffer_percentage = 10.00;
$marketplaceStock->save();

// Get stock with buffer
$stockWithBuffer = $marketplaceStock->getAvailableStockWithBuffer();
echo "Listed Stock: " . $marketplaceStock->listed_stock . "\n";
echo "Available Stock: " . $marketplaceStock->available_stock . "\n";
echo "Stock with Buffer (10%): " . $stockWithBuffer . "\n";  // Should be 90
```

---

## Troubleshooting

### Issue: Stock Not Locking
1. **Check Event is Fired:**
   ```php
   // Add logging in tinker
   \Illuminate\Support\Facades\Log::info("Testing event", ['order_id' => $order->id]);
   ```

2. **Check Listener is Registered:**
   ```php
   // Check EventServiceProvider
   $listeners = \App\Providers\EventServiceProvider::class;
   ```

3. **Check Order Type:**
   ```php
   echo "Order Type ID: " . $order->order_type_id . "\n";  // Must be 3
   ```

4. **Check Order Status:**
   ```php
   echo "Order Status: " . $order->status . "\n";  // Must be 1 or 2
   ```

### Issue: Stock Not Reducing
1. **Check Order Status Changed:**
   ```php
   echo "Old Status: " . $oldStatus . "\n";
   echo "New Status: " . $order->status . "\n";  // Must be 3
   ```

2. **Check Event is Fired:**
   ```bash
   tail -f storage/logs/laravel.log | grep "OrderStatusChanged"
   ```

---

## Quick Test Script

Save this as `test-order-lock.php` in project root:

```php
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$variationId = 1248;
$marketplaceId = 1;

// Get or create customer
$customer = \App\Models\Customer_model::firstOrCreate(
    ['email' => 'test@example.com'],
    ['first_name' => 'Test', 'last_name' => 'Customer']
);

// Get currency and country
$currency = \App\Models\Currency_model::where('code', 'EUR')->first() ?? \App\Models\Currency_model::first();
$country = \App\Models\Country_model::where('code', 'FR')->first() ?? \App\Models\Country_model::first();

// Create order
$order = \App\Models\Order_model::create([
    'reference_id' => 'TEST-' . time(),
    'customer_id' => $customer->id,
    'marketplace_id' => $marketplaceId,
    'order_type_id' => 3,
    'status' => 1,
    'currency_id' => $currency->id,
    'country_id' => $country->id,
]);

// Create order item
$orderItem = \App\Models\Order_item_model::create([
    'order_id' => $order->id,
    'variation_id' => $variationId,
    'quantity' => 1,
    'price' => 500.00,
    'status' => 1,
]);

// Fire event
event(new \App\Events\V2\OrderCreated($order, collect([$orderItem])));

echo "Order created: {$order->reference_id}\n";
echo "Check stock locks at: /v2/stock-locks?order_id={$order->id}\n";
```

Run:
```bash
php test-order-lock.php
```

---

## Summary

✅ **Test Checklist:**
- [ ] Stock exists in `marketplace_stock` table
- [ ] Order created with `order_type_id = 3` and `status = 1`
- [ ] V2 OrderCreated event fired
- [ ] Stock locked in database
- [ ] Lock record created in `marketplace_stock_locks`
- [ ] History record created in `marketplace_stock_history`
- [ ] Visual confirmation in order detail page
- [ ] Visual confirmation in V2 listing page
- [ ] Visual confirmation in stock locks dashboard
- [ ] Order completed (status = 3)
- [ ] V2 OrderStatusChanged event fired
- [ ] Stock reduced in database
- [ ] Lock status changed to 'consumed'
- [ ] Buffer applied correctly

---

## Next Steps

After testing locally:
1. Test with real Back Market API orders
2. Test with multiple marketplaces
3. Test concurrent orders
4. Test order cancellation
5. Test stock sync with 6-hour interval


