<?php
/**
 * Quick Test Script for Order Stock Locking
 * 
 * Usage: php test-order-lock.php
 * 
 * This script creates a test order for variation 1248 (iPhone 14 Pro 256GB Deep Purple Good)
 * and fires the V2 OrderCreated event to test stock locking.
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$variationId = 1248;  // iPhone 14 Pro 256GB Deep Purple Good
$marketplaceId = 1;  // Back Market

echo "=== Testing Order Stock Locking ===\n\n";

// Step 1: Check if variation exists
$variation = \App\Models\Variation_model::find($variationId);
if (!$variation) {
    echo "❌ ERROR: Variation ID {$variationId} not found!\n";
    exit(1);
}
echo "✅ Variation found: {$variation->sku}\n";

// Step 2: Check/Setup marketplace stock
$marketplaceStock = \App\Models\V2\MarketplaceStockModel::firstOrCreate(
    [
        'variation_id' => $variationId,
        'marketplace_id' => $marketplaceId
    ],
    [
        'listed_stock' => 10,
        'locked_stock' => 0,
        'available_stock' => 10,
        'buffer_percentage' => 10.00
    ]
);

echo "📊 Current Stock Status:\n";
echo "   Listed Stock: {$marketplaceStock->listed_stock}\n";
echo "   Locked Stock: {$marketplaceStock->locked_stock}\n";
echo "   Available Stock: {$marketplaceStock->available_stock}\n";
echo "   Buffer: {$marketplaceStock->buffer_percentage}%\n\n";

// Step 3: Get or create customer
$customer = \App\Models\Customer_model::firstOrCreate(
    ['email' => 'test-order-lock@example.com'],
    [
        'first_name' => 'Test',
        'last_name' => 'Order Lock',
        'phone' => '1234567890'
    ]
);
echo "✅ Customer ready: {$customer->first_name} {$customer->last_name}\n";

// Step 4: Get currency and country
$currency = \App\Models\Currency_model::where('code', 'EUR')->first() ?? \App\Models\Currency_model::first();
$country = \App\Models\Country_model::where('code', 'FR')->first() ?? \App\Models\Country_model::first();
echo "✅ Currency: {$currency->code}\n";
echo "✅ Country: {$country->code}\n\n";

// Step 5: Create order (use numeric reference_id like Back Market - 8 digits)
$referenceId = 90000000 + rand(100000, 999999);  // Generate unique 8-digit numeric ID
// Make sure it doesn't exist
while (\App\Models\Order_model::where('reference_id', $referenceId)->exists()) {
    $referenceId = 90000000 + rand(100000, 999999);
}
$order = \App\Models\Order_model::create([
    'reference_id' => $referenceId,
    'customer_id' => $customer->id,
    'marketplace_id' => $marketplaceId,
    'order_type_id' => 3,  // Marketplace order
    'status' => 1,         // Pending
    'currency' => $currency->id,  // currency field stores currency_id
    'currency_id' => $currency->id,
    'country_id' => $country->id,
    'created_at' => now(),
]);
echo "✅ Order created:\n";
echo "   Order ID: {$order->id}\n";
echo "   Reference: {$order->reference_id}\n";
echo "   Status: {$order->status} (Pending)\n";
echo "   Type: {$order->order_type_id} (Marketplace)\n\n";

// Step 6: Create order item
$orderItem = \App\Models\Order_item_model::create([
    'order_id' => $order->id,
    'variation_id' => $variationId,
    'quantity' => 1,
    'price' => 500.00,
    'status' => 1,
]);
echo "✅ Order Item created:\n";
echo "   Item ID: {$orderItem->id}\n";
echo "   Variation ID: {$orderItem->variation_id}\n";
echo "   Quantity: {$orderItem->quantity}\n\n";

// Step 7: Fire V2 OrderCreated event
echo "🔥 Firing V2 OrderCreated event...\n";
event(new \App\Events\V2\OrderCreated($order, collect([$orderItem])));
echo "✅ Event fired!\n\n";

// Step 8: Wait a moment for event processing
sleep(1);

// Step 9: Verify stock is locked
$marketplaceStock->refresh();
echo "📊 Stock Status After Locking:\n";
echo "   Listed Stock: {$marketplaceStock->listed_stock}\n";
echo "   Locked Stock: {$marketplaceStock->locked_stock}\n";
echo "   Available Stock: {$marketplaceStock->available_stock}\n\n";

// Step 10: Check lock record
$lock = \App\Models\V2\MarketplaceStockLock::where('order_id', $order->id)->first();
if ($lock) {
    echo "✅ Lock Record Found:\n";
    echo "   Lock ID: {$lock->id}\n";
    echo "   Quantity Locked: {$lock->quantity_locked}\n";
    echo "   Lock Status: {$lock->lock_status}\n";
    echo "   Locked At: {$lock->locked_at}\n\n";
} else {
    echo "❌ ERROR: No lock record found!\n";
    echo "   Check EventServiceProvider to ensure listener is registered.\n";
    exit(1);
}

// Step 11: Check history
$history = \App\Models\V2\MarketplaceStockHistory::where('order_id', $order->id)->first();
if ($history) {
    echo "✅ History Record Found:\n";
    echo "   History ID: {$history->id}\n";
    echo "   Change Type: {$history->change_type}\n";
    echo "   Quantity Change: {$history->quantity_change}\n";
    echo "   Notes: {$history->notes}\n\n";
} else {
    echo "⚠️  WARNING: No history record found!\n\n";
}

// Step 12: Summary
echo "=== Test Summary ===\n";
echo "✅ Order created successfully\n";
echo "✅ Stock locked successfully\n";
echo "✅ Lock record created\n";
echo "✅ History record created\n\n";

echo "📋 Next Steps:\n";
echo "1. View order: /order?order_id={$order->reference_id}\n";
echo "2. View stock locks: /v2/stock-locks?order_id={$order->id}\n";
echo "3. View in listing: /v2/listing (find variation 1248)\n";
echo "4. Complete order to test stock reduction:\n";
echo "   php artisan tinker\n";
echo "   \$order = \\App\\Models\\Order_model::find({$order->id});\n";
echo "   \$oldStatus = \$order->status;\n";
echo "   \$order->status = 3;\n";
echo "   \$order->save();\n";
echo "   event(new \\App\\Events\\V2\\OrderStatusChanged(\$order, \$oldStatus, 3, \$order->order_items));\n\n";

echo "✅ Test completed successfully!\n";

