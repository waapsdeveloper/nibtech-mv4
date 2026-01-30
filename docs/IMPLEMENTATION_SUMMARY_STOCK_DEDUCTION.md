# Implementation Summary: Listed Stock Deduction on Order Arrival

## ✅ Implementation Complete

The feature to deduct `listed_stock` when orders arrive via `refresh:new` has been implemented.

## Implementation Details

### File Modified
- `app/Console/Commands/RefreshNew.php`

### New Method Added
- `deductListedStockForOrder($orderObj, $isNewOrder, $oldStatus)`

### Modified Method
- `updateBMOrder()` - Now tracks order state and calls deduction logic

## Business Rules Implemented

### ✅ Deduct Stock When:
1. **New order with status 1 (Pending)**: Deduct 1 from `listed_stock`
2. **Existing order status changes from 1 → 2**: Deduct 1 from `listed_stock`

### ❌ Do NOT Deduct When:
1. **New order with status 2 (Accepted)**: Remain as is (no deduction)
2. **Order status 3+ (Shipped, Cancelled, etc.)**: No deduction
3. **Order already processed**: No double deduction

### Additional Rules:
- ✅ Always deduct **1** (not by quantity, even if order item quantity > 1)
- ✅ Update **both** `variations.listed_stock` AND `marketplace_stock.listed_stock`
- ✅ Allow **negative stock** values (no max(0, ...) prevention)
- ✅ Comprehensive logging via SlackLogService

## How It Works

1. **Order Processing Flow**:
   ```
   refresh:new → updateBMOrder() → 
   - Check if order exists (isNewOrder flag)
   - Get old status if exists
   - Update order in DB
   - Update order items in DB
   - Call deductListedStockForOrder()
   ```

2. **Deduction Logic**:
   ```
   deductListedStockForOrder() →
   - Check if order_type_id = 3 (marketplace orders)
   - Check if should deduct (new order status 1 OR status change 1→2)
   - For each order item:
     - Deduct 1 from variations.listed_stock
     - Deduct 1 from marketplace_stock.listed_stock (for order's marketplace)
     - Log the deduction
   ```

## Testing

### Safe Testing with SYNC_DATA_IN_LOCAL

1. Set `SYNC_DATA_IN_LOCAL=true` in `.env`
2. Run `php artisan refresh:new`
3. Check database for stock deductions
4. Review logs via SlackLogService

### Test Scenarios

- ✅ New order with status 1 → Should deduct
- ✅ New order with status 2 → Should NOT deduct
- ✅ Status change 1→2 → Should deduct
- ✅ Multiple items → Each variation deducted by 1
- ✅ Quantity > 1 → Still deduct only 1
- ✅ Negative stock → Allowed (no prevention)

## Logging

All deductions are logged via SlackLogService with:
- Order reference ID
- Variation SKU
- Old and new stock values (both variation and marketplace)
- Deduction reason (new_order_status_1 or status_change_1_to_2)
- Order status information

## Files Changed

1. `app/Console/Commands/RefreshNew.php` - Implementation
2. `docs/STOCK_DEDUCTION_ANALYSIS.md` - Analysis document
3. `docs/TESTING_LISTED_STOCK_DEDUCTION.md` - Testing guide
4. `docs/IMPLEMENTATION_SUMMARY_STOCK_DEDUCTION.md` - This file

## Next Steps

1. ✅ Code implemented
2. ⏳ Test with `SYNC_DATA_IN_LOCAL=true`
3. ⏳ Review test results
4. ⏳ Deploy to production
5. ⏳ Monitor logs and stock values

## Rollback Plan

If issues occur, the deduction logic can be temporarily disabled by:
- Commenting out the `$this->deductListedStockForOrder()` call in `updateBMOrder()`
- Or adding a feature flag check

Manual stock restoration (if needed):
```sql
UPDATE variations 
SET listed_stock = listed_stock + 1 
WHERE id IN (
    SELECT variation_id 
    FROM order_items 
    WHERE order_id = (SELECT id FROM orders WHERE reference_id = 'ORDER_REFERENCE_ID')
);
```
