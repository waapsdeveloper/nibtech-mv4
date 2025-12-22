# Stock Lock Unfreeze Logic

## Overview
The "Unfreeze" action releases locked stock back to available stock without deleting the lock record (for audit trail).

## Logic Flow

### 1. **Validation**
- ✅ Only unlock locks with status `locked` (not `consumed` or `cancelled`)
- ✅ Verify lock exists and belongs to the order/variation
- ✅ Check if order is still in pending/processing status (optional check)

### 2. **Stock Updates**
- ✅ Reduce `locked_stock` in `marketplace_stock` table by lock quantity
- ✅ Increase `available_stock` in `marketplace_stock` table (recalculate)
- ✅ **DO NOT** change `listed_stock` (that's the total physical stock)

### 3. **Lock Record Update**
- ✅ Change `lock_status` from `locked` → `cancelled` (or `released`)
- ✅ Set `released_at` timestamp
- ✅ **DO NOT** delete the lock record (keep for audit trail)

### 4. **History Record**
- ✅ Create entry in `marketplace_stock_history`:
   - `change_type`: `unlock` or `release`
   - Record before/after values
   - Reference to lock and order
   - Admin ID (who performed the action)

### 5. **Marketplace API Update**
- ✅ Update marketplace API with new available stock (with buffer)
- ✅ Use `MarketplaceAPIService` to ensure buffer is applied

## Use Cases

### Scenario 1: Order Cancelled
- Order status changes to cancelled (status 4)
- All locks for that order should be released
- Stock becomes available again

### Scenario 2: Manual Unfreeze (Admin Action)
- Admin manually unfreezes a lock
- Stock becomes available immediately
- Order may still be pending (admin decision)

### Scenario 3: Order Item Removed
- Order item is deleted from order
- Lock for that item should be released
- Stock becomes available

## Implementation

### Service Method
```php
public function releaseLock($lockId, $adminId = null, $reason = null)
{
    // 1. Find lock
    // 2. Validate (status = locked)
    // 3. Update marketplace_stock
    // 4. Update lock record
    // 5. Create history
    // 6. Update API
}
```

### Controller Action
```php
public function releaseLock(Request $request, $lockId)
{
    // Validate permission
    // Call service
    // Return response
}
```

## Important Notes

⚠️ **DO NOT:**
- Delete lock records (keep for audit)
- Change `listed_stock` (only `locked_stock` and `available_stock`)
- Release already consumed/cancelled locks

✅ **DO:**
- Update marketplace API after release
- Log all actions
- Create history records
- Validate before releasing

