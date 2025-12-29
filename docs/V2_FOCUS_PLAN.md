# V2 Focus Plan - Keep V1 Working, Update V2

## Strategy

**Keep V1 working** - Don't break existing functionality  
**Focus on V2** - All new updates go to V2 structure

---

## ✅ V1 - Keep As Is (No Changes Needed)

These will continue working with existing code:

- ✅ `ListingController.php` (V1) - Keep existing buffer logic (if any)
- ✅ `RefreshOrders.php` - Keep existing order sync (no events needed)
- ✅ `UpdateOrderInDB.php` - Keep existing order updates (no events needed)
- ✅ `Kernel.php` - Keep existing schedule (V1 sync commands)

**Reason:** V1 code is production-ready and working. We don't want to break it.

---

## 🎯 V2 - Updates Needed

### 1. ✅ Already Completed
- ✅ `V2/ListingController.php` - Uses MarketplaceAPIService
- ✅ `V2/SyncMarketplaceStock.php` - V2 sync command
- ✅ `V2/OrderCreated` & `V2/OrderStatusChanged` - V2 events
- ✅ `V2/LockStockOnOrderCreated` & `V2/ReduceStockOnOrderCompleted` - V2 listeners
- ✅ `MarketplaceAPIService` - Generic service
- ✅ V2 Models - All in V2 namespace

### 2. ⏳ Remaining V2 Updates

#### A. Update Kernel.php Schedule (V2)
**File:** `app/Console/Kernel.php`

**Action:** Add V2 sync command to schedule
```php
// Add to schedule() method
$schedule->command('v2:marketplace:sync-stock')
    ->everySixHours()
    ->withoutOverlapping()
    ->runInBackground();
```

**Note:** Keep V1 sync commands running in parallel if needed.

#### B. Fire V2 Events in Order Sync Commands
**Files:** 
- `app/Console/Commands/RefreshOrders.php`
- `app/Jobs/UpdateOrderInDB.php` (or wherever orders are synced)

**Action:** Fire V2 events when orders are created/updated
```php
// When order is created
use App\Events\V2\OrderCreated;
event(new OrderCreated($order, $order->order_items));

// When order status changes
use App\Events\V2\OrderStatusChanged;
event(new OrderStatusChanged($order, $oldStatus, $newStatus, $order->order_items));
```

**Note:** V1 events can stay for backward compatibility, but V2 events will handle stock locking.

---

## 📊 Visual Stock Lock Display

### Where to Show Stock Locks:

1. **Order Detail Page** (`/order` or order detail view)
   - Show locked stock per order item
   - Show lock status (locked/consumed/cancelled)

2. **V2 Listing Page** (`/v2/listing`)
   - Show locked stock in marketplace stock section
   - Show pending locks per variation

3. **New Stock Locks Page** (Optional)
   - Dedicated page to view all active locks
   - Filter by marketplace, variation, order

### Implementation Plan:

#### Option 1: Add to Order Detail Page (Recommended)
- Show stock lock badge/indicator on order items
- Display lock quantity and status
- Show when lock was created

#### Option 2: Add to V2 Listing Page
- Show locked stock in marketplace stock section
- Display active locks count
- Link to order details

#### Option 3: Create Stock Locks Dashboard
- New page: `/v2/stock-locks`
- List all active locks
- Filter and search capabilities

---

## Summary

### V1 Status: ✅ Keep Working (No Changes)
- All V1 code remains functional
- No breaking changes

### V2 Status: ⏳ 2 Updates Remaining
1. Add V2 sync to Kernel.php schedule
2. Fire V2 events in order sync commands

### Visual Display: 📊 To Be Implemented
- Add stock lock display to order detail page
- Add stock lock display to V2 listing page
- Optional: Create stock locks dashboard

---

## Next Steps

1. **Update Kernel.php** - Add V2 sync schedule
2. **Update Order Sync** - Fire V2 events
3. **Add Visual Display** - Show stock locks in UI
4. **Test** - Verify V2 events fire correctly

