# Analysis: Listed Stock Deduction on Order Arrival

## Current System Analysis

### 1. RefreshNew Command Flow

**File**: `app/Console/Commands/RefreshNew.php`

**Current Process**:
1. Fetches new orders from BackMarket API via `$bm->getNewOrders()`
2. For each order:
   - Validates orderlines (calls `validateOrderlines()` - **protected by SYNC_DATA_IN_LOCAL**)
   - Updates order in DB via `updateBMOrder()` → `Order_model::updateOrderInDB()`
   - Updates order items via `Order_item_model::updateOrderItemsInDB()`
3. Fetches incomplete orders and updates them

**SYNC_DATA_IN_LOCAL Protection**:
- ✅ `validateOrderlines()` method checks flag and skips API POST if `true`
- ✅ Logs skipped operations via SlackLogService

### 2. Order Status Mapping

**File**: `app/Models/Order_model.php` → `mapStateToStatus()`

**Status Values**:
- **Status 1** = "To Be Treated" (Pending)
  - Triggered when: `order->state == 0 || order->state == 1`
- **Status 2** = "Awaiting Shipment" (Accepted/Validated)
  - Triggered when: `order->state == 3` AND `orderlines[].state == 2`
- **Status 3** = "Shipped"
- **Status 4** = "Cancelled"
- **Status 5** = "Refunded Before Delivery"
- **Status 6** = "Reimbursed After Delivery"

### 3. Listed Stock Storage

**Location**: `variations` table → `listed_stock` column

**Current Usage**:
- Updated by `functions:thirty` command when syncing listings from BackMarket API
- Used in various listing views and calculations
- Also stored per-marketplace in `marketplace_stocks` table

### 4. Order Processing

**Order Creation**:
- Uses `Order_model::firstOrNew()` based on `reference_id` and `marketplace_id`
- `firstOrNew()` returns existing order if found, or creates new one
- Can check if order is new using `$order->wasRecentlyCreated` or `$order->exists` before save

**Order Items**:
- Created via `Order_item_model::updateOrderItemsInDB()`
- Each item has `variation_id` linking to the variation
- Each item has `quantity` field

## Requirements Analysis

### Client Requirements

1. **When order arrives (new order)**:
   - If status = 1 (Pending) → -1 from `listed_stock`
   - If status = 2 (Accepted) → -1 from `listed_stock`

2. **When existing order status changes**:
   - If status changes from 1 → 2 → -1 from `listed_stock`

### Implementation Points

**Where to Add Logic**:
- Best place: After order and order items are saved in `RefreshNew::updateBMOrder()`
- Need to check:
  1. Is order new? (`$order->wasRecentlyCreated` or check if `$order->exists` before save)
  2. What is the order status? (1 or 2)
  3. Did status change from 1 to 2? (compare old vs new status)

**Key Considerations**:
1. **Prevent Double Deduction**: Track which orders have been processed
2. **Handle Quantity**: If order item has `quantity > 1`, deduct by quantity or by 1?
3. **Multiple Items**: Deduct for each order item's variation
4. **Edge Cases**: 
   - Order status 3+ (should not deduct)
   - Order already processed (should not deduct again)
   - Variation with `listed_stock = 0` (prevent negative or handle gracefully)

## Testing Strategy

### Using SYNC_DATA_IN_LOCAL Flag

**Advantages**:
- ✅ No live API modifications (POST/PUT blocked)
- ✅ Data fetching still works (GET operations)
- ✅ Database updates still happen (can test stock deduction)
- ✅ Safe to test without affecting production

**Testing Steps**:
1. Set `SYNC_DATA_IN_LOCAL=true` in `.env`
2. Note current `listed_stock` values
3. Run `php artisan refresh:new`
4. Check database for:
   - New orders created
   - `listed_stock` values reduced
   - Log entries
5. Verify edge cases
6. Rollback if needed

### Test Scenarios

1. **New Order Status 1**: Verify deduction
2. **New Order Status 2**: Verify deduction
3. **Status Change 1→2**: Verify deduction
4. **Status 3+**: Verify no deduction
5. **Multiple Items**: Verify each variation deducted
6. **Quantity > 1**: Verify correct deduction amount
7. **Already Processed**: Verify no double deduction

## Implementation Plan

### ✅ Implementation Complete

The deduction logic has been implemented in `RefreshNew.php`:

**Key Features**:
- ✅ Deducts **1** (not by quantity) for each order item
- ✅ Updates **both** `variations.listed_stock` and `marketplace_stock.listed_stock`
- ✅ Allows **negative stock** values
- ✅ Only deducts for:
  - New order with status 1 (Pending)
  - Existing order status change from 1 → 2 (Pending → Accepted)
- ✅ Does NOT deduct for new order with status 2 directly
- ✅ Comprehensive logging via SlackLogService

**Implementation Details**:
- Method: `deductListedStockForOrder($orderObj, $isNewOrder, $oldStatus)`
- Called from: `updateBMOrder()` after order and items are saved
- Tracks: New orders vs existing orders, status changes
- Logs: Individual deductions and summary for multi-item orders

## Clarifications (Confirmed)

1. **Quantity Handling**: ✅ Always deduct **1** (not by quantity)
2. **Double Deduction Prevention**: ✅ Track using `isNewOrder` flag and status change detection
3. **Marketplace Stock**: ✅ Update **both** `marketplace_stocks.listed_stock` AND `variations.listed_stock`
4. **Negative Stock**: ✅ **Allow negative** (don't use max(0, ...)) so we know the deficit
5. **Status 2 on First Arrival**: ✅ **NO deduction** if order arrives with status 2 directly - only deduct when status changes from 1→2

## Next Steps

1. ✅ Review this analysis
2. ✅ Clarify questions with client
3. ✅ Create test plan (see `TESTING_LISTED_STOCK_DEDUCTION.md`)
4. ✅ Implement code
5. ⏳ Test with `SYNC_DATA_IN_LOCAL=true`
6. ⏳ Review test results
7. ⏳ Deploy to production
