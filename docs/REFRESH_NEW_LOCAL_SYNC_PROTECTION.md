# Refresh:new Local Sync Protection

## Overview
The `refresh:new` command has been updated to prevent live data updates to BackMarket when `SYNC_DATA_IN_LOCAL=true` is set in the `.env` file.

## Live Update Found

### Location: `validateOrderlines()` method
**File:** `app/Console/Commands/RefreshNew.php` (lines 94-120)

**What it does:**
- Makes a POST request to BackMarket API endpoint: `orders/{order_id}`
- Updates order state to `2` (validated) in BackMarket's live system
- This is a **live update** that modifies data on BackMarket's servers

**API Call:**
```php
$end_point = 'orders/' . $order_id;
$request = ['order_id' => $order_id, 'new_state' => 2, 'sku' => $sku];
$result = $bm->apiPost($end_point, $request_JSON);
```

## Protection Added

When `SYNC_DATA_IN_LOCAL=true` in `.env`:
- ✅ The `validateOrderlines()` API call is **skipped**
- ✅ A log entry is created indicating the skip
- ✅ Console output shows a warning message
- ✅ No data is sent to BackMarket's live API

## Code Changes

### Modified Method: `validateOrderlines()`

**Before:**
```php
private function validateOrderlines($order_id, $sku, $bm)
{
    $end_point = 'orders/' . $order_id;
    $new_state = 2;
    $request = ['order_id' => $order_id, 'new_state' => $new_state, 'sku' => $sku];
    $request_JSON = json_encode($request);
    $result = $bm->apiPost($end_point, $request_JSON);
    return $result;
}
```

**After:**
```php
private function validateOrderlines($order_id, $sku, $bm)
{
    // Check if local sync mode is enabled - prevent live data updates to BackMarket
    $syncDataInLocal = env('SYNC_DATA_IN_LOCAL', false);
    
    if ($syncDataInLocal) {
        // Skip live API update when in local testing mode
        Log::info("RefreshNew: Skipping validateOrderlines API call (SYNC_DATA_IN_LOCAL=true)", [
            'order_id' => $order_id,
            'sku' => $sku,
            'would_set_state' => 2
        ]);
        $this->info("⚠️  Local Mode: Skipping orderline validation for order {$order_id}, SKU {$sku} (would set state to 2)");
        return null;
    }
    
    // ... rest of the code (unchanged)
}
```

## Other Operations (Read-Only - No Protection Needed)

The following operations in `refresh:new` are **read-only** and do NOT update live BackMarket data:

1. **`$bm->getNewOrders()`** - GET request (fetches new orders)
2. **`$bm->getOneOrder($order_id)`** - GET request (fetches single order)
3. **`$order_model->updateOrderInDB()`** - Only updates local database
4. **`$order_item_model->updateOrderItemsInDB()`** - Only updates local database
5. **`$bm->getOrderLabel()`** - GET request (fetches label URL)
6. **`$bm->getOneListing()`** - GET request (fetches listing details)

## Usage

### Enable Local Mode
Add to your `.env` file:
```env
SYNC_DATA_IN_LOCAL=true
```

### Run Command
```bash
php artisan Refresh:new
```

### Expected Behavior

**With `SYNC_DATA_IN_LOCAL=true`:**
- ✅ Fetches new orders from BackMarket (read-only)
- ✅ Updates local database with order data
- ✅ Updates incomplete orders in local database
- ❌ **Skips** validating orderlines (no live state update to BackMarket)
- 📝 Logs skipped operations

**Without the flag (production mode):**
- ✅ All operations run normally
- ✅ Orderlines are validated (state set to 2 in BackMarket)

## Log Output

When running in local mode, you'll see:
- Console: `⚠️  Local Mode: Skipping orderline validation for order {order_id}, SKU {sku} (would set state to 2)`
- Log file: `RefreshNew: Skipping validateOrderlines API call (SYNC_DATA_IN_LOCAL=true)`

## Testing Checklist

- [ ] Set `SYNC_DATA_IN_LOCAL=true` in `.env`
- [ ] Run `php artisan Refresh:new`
- [ ] Verify console shows skip warnings
- [ ] Check logs for skip entries
- [ ] Verify no order state updates occurred in BackMarket
- [ ] Verify local database is still updated correctly
- [ ] Test with flag set to `false` to ensure normal operation

## Important Notes

1. **Read Operations Still Work**: The command will still fetch data from BackMarket API (GET requests)
2. **Local Database Updates**: Local database updates continue normally
3. **Only POST Requests Blocked**: Only the live update (POST) to set order state is blocked
4. **Production Safety**: Always ensure `SYNC_DATA_IN_LOCAL=false` or unset in production

## Related Files

- `app/Console/Commands/RefreshNew.php` - Main command file
- `app/Http/Controllers/BackMarketAPIController.php` - API controller with `apiPost()` method
- `.env` - Environment configuration file
