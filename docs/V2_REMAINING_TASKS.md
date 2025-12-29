# V2 Remaining Tasks & Stock Lock Visualization

## ✅ V1 Status: Keep Working (No Changes Needed)

**Strategy:** Keep all V1 code working. Don't modify:
- ❌ `ListingController.php` (V1) - Keep as is
- ❌ `RefreshOrders.php` - Keep existing sync
- ❌ `UpdateOrderInDB.php` - Keep existing updates
- ❌ V1 sync commands in Kernel.php - Keep running

**Reason:** V1 is production-ready. We work on V2 in parallel.

---

## ⏳ V2 Remaining Tasks

### 1. ✅ Kernel.php Schedule (COMPLETED)
**File:** `app/Console/Kernel.php`

**Status:** ✅ Added V2 sync schedule
- Back Market sync every 6 hours at 00:00
- Refurbed sync every 6 hours at 03:00 (staggered)
- All marketplaces sync every 6 hours at 06:00

### 2. ⏳ Fire V2 Events in Order Sync Commands

#### A. RefreshOrders.php
**File:** `app/Console/Commands/RefreshOrders.php`

**Action:** Fire V2 `OrderCreated` event when new orders are created
```php
use App\Events\V2\OrderCreated;

// After creating/updating order
event(new OrderCreated($order, $order->order_items));
```

#### B. UpdateOrderInDB.php (or similar)
**File:** `app/Jobs/UpdateOrderInDB.php` or wherever order status is updated

**Action:** Fire V2 `OrderStatusChanged` event when order status changes
```php
use App\Events\V2\OrderStatusChanged;

// When order status changes
$oldStatus = $order->getOriginal('status');
$order->status = $newStatus;
$order->save();

event(new OrderStatusChanged($order, $oldStatus, $newStatus, $order->order_items));
```

---

## 📊 Stock Lock Visualization

### Where to See Stock Locks:

#### 1. ✅ Created: Stock Locks Component
**Component:** `app/Http/Livewire/V2/StockLocks.php`  
**View:** `resources/views/livewire/v2/stock-locks.blade.php`

**Features:**
- Shows all stock locks (locked/consumed/cancelled)
- Filter by order, variation, or marketplace
- Summary statistics (total locked, consumed, cancelled)
- Duration calculation
- Links to order details

#### 2. Add to Order Detail Page

**File:** `resources/views/livewire/order.blade.php`

**Add after order items section:**
```blade
@if($order->order_type_id == 3) {{-- Marketplace orders only --}}
    @livewire('v2.stock-locks', ['orderId' => $order->id])
@endif
```

**Location:** Around line 900-1000 (after order items table)

#### 3. Add to V2 Listing Page

**File:** `resources/views/v2/listing/partials/marketplace-stocks-section.blade.php`

**Add stock lock indicator:**
```blade
@php
    $activeLocks = \App\Http\Livewire\V2\StockLocks::getLocksForVariation($variationId, $marketplaceIdInt);
    $totalLocked = $activeLocks->sum('quantity_locked');
@endphp

@if($totalLocked > 0)
    <div class="alert alert-warning alert-sm mb-2">
        <i class="fe fe-lock me-1"></i>
        <strong>{{ $totalLocked }}</strong> units locked for pending orders
        <a href="#" onclick="showStockLocks({{ $variationId }}, {{ $marketplaceIdInt }})" class="ms-2">
            View Details
        </a>
    </div>
@endif
```

#### 4. Create Stock Locks Dashboard (Optional)

**Route:** Add to `routes/v2.php`
```php
Route::get('stock-locks', [\App\Http\Livewire\V2\StockLocks::class, 'index'])->name('v2.stock-locks');
```

**Usage:**
- View all active locks: `/v2/stock-locks`
- Filter by order: `/v2/stock-locks?order_id=123`
- Filter by variation: `/v2/stock-locks?variation_id=456`
- Filter by marketplace: `/v2/stock-locks?marketplace_id=1`

---

## Quick Reference: Where to See Stock Locks

### Option 1: Order Detail Page (Recommended)
**URL:** `/order?order_id=ORDER_REFERENCE`

**What you'll see:**
- Stock locks for that specific order
- Lock status (locked/consumed/cancelled)
- Quantity locked per item
- Lock duration

### Option 2: V2 Listing Page
**URL:** `/v2/listing`

**What you'll see:**
- Locked stock indicator in marketplace stock section
- Total locked quantity per variation/marketplace
- Link to view lock details

### Option 3: Stock Locks Dashboard (To Be Created)
**URL:** `/v2/stock-locks`

**What you'll see:**
- All active stock locks
- Filter by order, variation, marketplace
- Summary statistics
- Export capabilities

---

## Implementation Steps

### Step 1: Add to Order Detail Page
1. Open `resources/views/livewire/order.blade.php`
2. Find order items section (around line 900)
3. Add stock locks component after order items
4. Test with a marketplace order

### Step 2: Add to V2 Listing Page
1. Open `resources/views/v2/listing/partials/marketplace-stocks-section.blade.php`
2. Add stock lock indicator in marketplace stock display
3. Add modal/component to show lock details
4. Test with variations that have locked stock

### Step 3: Fire V2 Events
1. Find where orders are created (`RefreshOrders.php`)
2. Add V2 `OrderCreated` event
3. Find where order status changes (`UpdateOrderInDB.php`)
4. Add V2 `OrderStatusChanged` event
5. Test order creation → stock lock
6. Test order completion → stock reduction

---

## Testing Checklist

- [ ] Order created → Stock locked (check `marketplace_stock_locks` table)
- [ ] Order completed → Stock reduced (check `marketplace_stock` table)
- [ ] Stock locks visible in order detail page
- [ ] Stock locks visible in V2 listing page
- [ ] V2 sync command runs on schedule
- [ ] Buffer applied when updating stock
- [ ] Events fire correctly (check logs)

---

## Summary

### V1: ✅ Keep Working (No Changes)
- All V1 code remains functional
- No breaking changes

### V2: ⏳ 2 Tasks Remaining
1. ✅ Kernel.php schedule - **COMPLETED**
2. ⏳ Fire V2 events in order sync commands

### Stock Locks: ✅ Component Created
- ✅ Stock locks component ready
- ⏳ Add to order detail page
- ⏳ Add to V2 listing page
- ⏳ Optional: Create dashboard

