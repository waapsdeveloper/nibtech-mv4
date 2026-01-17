# Testing Plan: Listed Stock Deduction on Order Arrival

## Overview
This document outlines how to test the new feature that deducts `listed_stock` when orders arrive via `refresh:new` command.

## Feature Requirements

When `refresh:new` processes orders, we need to:
1. **New Order with Status 1 (Pending)**: ✅ Deduct -1 from `listed_stock` for each order item's variation
2. **New Order with Status 2 (Accepted/Validated)**: ❌ NO deduction (remain as is)
3. **Existing Order transitioning from Status 1 → 2**: ✅ Deduct -1 from `listed_stock` for each order item's variation

**Additional Rules**:
- Always deduct **1** (not by quantity, even if order item quantity > 1)
- Update **both** `variations.listed_stock` AND `marketplace_stock.listed_stock`
- Allow **negative stock** values (don't prevent with max(0, ...))

## Order Status Mapping

Based on `Order_model::mapStateToStatus()`:
- **Status 1** = "To Be Treated" (Pending) - when order state is 0 or 1
- **Status 2** = "Awaiting Shipment" (Accepted/Validated) - when order state is 3 and orderlines state is 2
- **Status 3** = "Shipped"
- **Status 4** = "Cancelled"
- **Status 5** = "Refunded Before Delivery"
- **Status 6** = "Reimbursed After Delivery"

## Current Flow in `refresh:new`

1. Command starts → logs to SlackLogService
2. Fetches new orders via `$bm->getNewOrders()`
3. For each order:
   - Validates orderlines (calls `validateOrderlines()` - **protected by SYNC_DATA_IN_LOCAL**)
   - Updates order in DB via `updateBMOrder()` → calls `Order_model::updateOrderInDB()`
   - Updates order items via `Order_item_model::updateOrderItemsInDB()`
4. Fetches incomplete orders and updates them

## Testing Strategy Using `SYNC_DATA_IN_LOCAL`

### Step 1: Enable Local Mode
```bash
# In .env file
SYNC_DATA_IN_LOCAL=true
```

This ensures:
- ✅ No POST/PUT calls to live BackMarket API
- ✅ All data fetching still works (GET operations)
- ✅ Database updates still happen (we can test stock deduction)
- ✅ Logging still works via SlackLogService

### Step 2: Prepare Test Data

#### A. Check Current Listed Stock
```sql
-- Find variations with listed_stock > 0
SELECT id, sku, listed_stock, name 
FROM variations 
WHERE listed_stock > 0 
LIMIT 10;

-- Note down a few variation IDs and their current listed_stock values
```

#### B. Check Existing Orders
```sql
-- Check recent orders from BackMarket (order_type_id = 3, marketplace_id = 1)
SELECT id, reference_id, status, created_at, updated_at
FROM orders
WHERE order_type_id = 3 
  AND marketplace_id = 1
ORDER BY created_at DESC
LIMIT 10;

-- Note down reference_ids to avoid testing with orders that already exist
```

### Step 3: Run `refresh:new` in Test Mode

```bash
php artisan Refresh:new
```

### Step 4: Monitor What Happens

#### A. Check Logs
```bash
# Check SlackLogService logs (or Laravel logs)
tail -f storage/logs/laravel.log | grep -i "refresh:new\|listed_stock"
```

#### B. Check Database Changes

**Before running:**
```sql
-- Record initial state
SELECT 
    v.id as variation_id,
    v.sku,
    v.listed_stock as initial_listed_stock,
    o.id as order_id,
    o.reference_id,
    o.status as order_status,
    oi.id as order_item_id,
    oi.variation_id,
    oi.quantity
FROM variations v
LEFT JOIN order_items oi ON oi.variation_id = v.id
LEFT JOIN orders o ON o.id = oi.order_id
WHERE v.sku IN ('YOUR_TEST_SKU_1', 'YOUR_TEST_SKU_2')
ORDER BY o.created_at DESC;
```

**After running:**
```sql
-- Check if listed_stock was reduced
SELECT 
    v.id as variation_id,
    v.sku,
    v.listed_stock as current_listed_stock,
    o.id as order_id,
    o.reference_id,
    o.status as order_status,
    o.created_at as order_created,
    o.updated_at as order_updated,
    oi.quantity
FROM variations v
JOIN order_items oi ON oi.variation_id = v.id
JOIN orders o ON o.id = oi.order_id
WHERE o.order_type_id = 3
  AND o.marketplace_id = 1
  AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY o.created_at DESC;
```

### Step 5: Test Scenarios

#### Scenario 1: New Order with Status 1 (Pending)
**Expected:**
- Order is created with status = 1
- `listed_stock` is reduced by 1 for each order item's variation
- Log entry shows stock deduction

**Verification:**
```sql
-- Find the order
SELECT * FROM orders WHERE reference_id = 'ORDER_REFERENCE_ID';

-- Check if listed_stock was reduced
SELECT 
    v.id,
    v.sku,
    v.listed_stock,
    oi.quantity,
    o.status
FROM variations v
JOIN order_items oi ON oi.variation_id = v.id
JOIN orders o ON o.id = oi.order_id
WHERE o.reference_id = 'ORDER_REFERENCE_ID';
```

#### Scenario 2: New Order with Status 2 (Accepted)
**Expected:**
- Order is created with status = 2
- ❌ **NO deduction** - `listed_stock` remains unchanged
- No log entry for stock deduction

#### Scenario 3: Existing Order Status Change (1 → 2)
**Expected:**
- Order status changes from 1 to 2
- `listed_stock` is reduced by 1 for each order item's variation
- Log entry shows stock deduction

**Verification:**
```sql
-- Check order status history (if you have a history table)
-- Or check order updated_at timestamp

-- Before update
SELECT id, reference_id, status, updated_at FROM orders WHERE reference_id = 'EXISTING_ORDER_ID';

-- Run refresh:new

-- After update
SELECT id, reference_id, status, updated_at FROM orders WHERE reference_id = 'EXISTING_ORDER_ID';

-- Check if listed_stock was reduced
SELECT 
    v.id,
    v.sku,
    v.listed_stock,
    o.status,
    o.updated_at
FROM variations v
JOIN order_items oi ON oi.variation_id = v.id
JOIN orders o ON o.id = oi.order_id
WHERE o.reference_id = 'EXISTING_ORDER_ID';
```

### Step 6: Edge Cases to Test

1. **Order with multiple items**: Verify each variation's `listed_stock` is reduced by 1
2. **Order with quantity > 1**: Verify `listed_stock` is reduced by **1** (not by quantity)
3. **Order already processed**: Verify no double deduction
4. **Order status 3+ (Shipped, Cancelled, etc.)**: Verify no deduction
5. **Variation with listed_stock = 0**: Verify it goes to -1 (allow negative)
6. **Marketplace stock**: Verify both `variations.listed_stock` AND `marketplace_stock.listed_stock` are updated
7. **New order with status 2**: Verify NO deduction

### Step 7: Rollback Plan

If something goes wrong:
```sql
-- Manually restore listed_stock (if needed)
UPDATE variations 
SET listed_stock = listed_stock + 1 
WHERE id IN (
    SELECT variation_id 
    FROM order_items 
    WHERE order_id = (SELECT id FROM orders WHERE reference_id = 'ORDER_REFERENCE_ID')
);
```

## Implementation Notes

### ✅ Implementation Complete

The stock deduction logic has been implemented in `RefreshNew.php`:

**Location**: `app/Console/Commands/RefreshNew.php`
- Method: `deductListedStockForOrder($orderObj, $isNewOrder, $oldStatus)`
- Called from: `updateBMOrder()` after order and items are saved

### Key Implementation Details

1. ✅ **Check if order is new**: Tracks `$isNewOrder` flag before calling `updateOrderInDB()`
2. ✅ **Check status change**: Compares `$oldStatus` vs new status
3. ✅ **Only deduct for specific cases**: 
   - New order with status 1
   - Status change from 1 → 2
   - NOT for new order with status 2
4. ✅ **Prevent double deduction**: Uses order existence check and status tracking
5. ✅ **Always deduct 1**: Not by quantity (even if quantity > 1)
6. ✅ **Update both tables**: `variations.listed_stock` AND `marketplace_stock.listed_stock`
7. ✅ **Allow negative**: No max(0, ...) - allows negative stock values
8. ✅ **Comprehensive logging**: Uses SlackLogService to log all deductions

## Testing Checklist

- [ ] Set `SYNC_DATA_IN_LOCAL=true` in `.env`
- [ ] Note current `listed_stock` values for test variations
- [ ] Run `php artisan Refresh:new`
- [ ] Verify new orders are created in database
- [ ] Verify `listed_stock` is reduced for status 1 orders
- [ ] Verify `listed_stock` is reduced for status 2 orders
- [ ] Verify `listed_stock` is reduced when status changes 1→2
- [ ] Verify no deduction for status 3+ orders
- [ ] Verify no double deduction for same order
- [ ] Check logs for stock deduction entries
- [ ] Test with orders containing multiple items
- [ ] Test with orders containing quantity > 1 (should still deduct only 1)
- [ ] Verify edge cases (zero stock goes to -1, negative stock allowed)
- [ ] Verify marketplace_stock.listed_stock is also updated
- [ ] Verify new order with status 2 does NOT deduct

## Next Steps

After testing is complete:
1. Review test results
2. Implement the actual code
3. Test again with `SYNC_DATA_IN_LOCAL=true`
4. Once confirmed, set `SYNC_DATA_IN_LOCAL=false` for production testing
5. Monitor production logs closely
