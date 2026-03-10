# Dashboard: Total Listed vs Should Be Listed – Discrepancy Analysis

This document explains why **Total Listed Inventory** and **Should Be** can differ in the dashboard widget `inventory-overview-widget` (see `resources/views/livewire/dashboard/inventory-overview-widget.blade.php` and `app/Http/Livewire/Dashboard/InventoryOverviewWidget.php`).

---

## 1. What each number is

| Metric | Source | Meaning |
|--------|--------|--------|
| **Total Listed Inventory** (`$listedInventory`) | `Variation_model::where('listed_stock', '>', 0)->sum('listed_stock')` | Sum of the **denormalized** `listed_stock` column on the `variation` table. This is “how many units the system believes are currently listed” (e.g. on BackMarket), updated by API sync, orders, listing flows, etc. |
| **Should Be** (`$shouldBeListed`) | Formula below | An **independent calculation** from stock + process + orders: “how many units we expect to be listable” given current stock and holds. |

So one number is a **stored counter** (listed), the other is a **derived expectation** (should be listed). They use different data sources and logic, so discrepancies are expected unless everything is perfectly in sync.

---

## 2. Exact formula for “Should Be Listed”

```php
shouldBeListed = max(0,
    A - B - C
)
```

- **A** = Total **stock** count in grades 1–5 (grade_id &lt; 6) from the widget’s “graded inventory”:
  - From `stock` table: `status = 1`, joined to `variation` → `grade`, and `orders` (via `stock.order_id`).
  - Excludes stock that is in **aftersale** (order items where order has `order_type_id = 4` and `status < 3`).
  - Grouped by grade and order status; we take `gradedInventory->where('grade_id', '<', 6)->sum('quantity')`.

- **B** = Count of **process_stock** rows linked to a **process** with `process_type_id = 22` and `status < 3` (topup/listing process not yet closed).  
  So: units currently “in” an active topup (type 22) are not expected to be listed yet.

- **C** = **Number of orders** (not items): `Order_model::where('status', 2)->where('order_type_id', 3)->count()`.  
  So: pending marketplace orders (status 2, type 3). This is **order count**, not sum of order line quantities.

---

## 3. Why discrepancies happen

### 3.1 Different definitions

- **Listed** = “what we/store/API say is listed” (variation-level denormalized total).
- **Should be** = “available stock in grades 1–5 minus stock in active topup (type 22) minus number of pending marketplace orders.”

So listed is **output of listing/API/sync**; should be is **stock-based minus holds**. They are not the same thing by definition.

### 3.2 Denormalization lag

`variation.listed_stock` is updated in many places, including:

- BackMarket API sync (e.g. `ListingController`, `RefreshNew`, `SyncMarketplaceStock`, `FunctionsThirty`, `ReduceStockOnOrderCompleted`, etc.).
- Listing flows, verification, V2 listing controller, marketplace stock formula, Refurbed, order completion listeners.

If any of these fail, run in a different order, or are not run (e.g. sync not run yet), **listed** can be out of date while **should be** is live from `stock` / `process_stock` / `orders`.

### 3.3 Grade scope

- **Should be** only counts stock in **grades 1–5** (`grade_id < 6`). Grade 6 is excluded.
- **Listed** is the sum over **all** variations with `listed_stock > 0`, regardless of grade.

So if you have listed quantity in grade 6 (or different grade logic elsewhere), the two totals are not comparable unless you restrict listed to the same grade set.

### 3.4 Order count vs item count (possible formula bug)

- **C** subtracts the **number of orders** (count of orders with status 2, order_type_id 3).
- Conceptually, “stock we expect to be listed” is reduced by **units** in those orders (order items quantity), not by number of orders.

So if 10 pending orders contain 50 items total, the formula currently subtracts 10, not 50. That can make **should be** **too high** compared to a “units-based” expectation and contribute to listed vs should-be differences. This is a good candidate to fix if you want should-be to reflect “units reserved by pending orders”.

### 3.5 Aftersale exclusion

- **Graded inventory** (and thus A) excludes stock in aftersale (order_type_id 4, status &lt; 3).
- **Listed** has no such filter; it’s a simple sum of `variation.listed_stock`.

So aftersale stock can still contribute to **listed** but not to **should be**, or vice versa if definitions differ elsewhere.

### 3.6 Multiple writers of `listed_stock`

Because `variation.listed_stock` (and in V2, marketplace-level `listed_stock`) is written from many commands, listeners, and controllers, race conditions or missed updates can keep **listed** out of sync with the stock/process/order reality that **should be** is based on.

---

## 4. Summary

- **Listed** = sum of denormalized `variation.listed_stock` (what the system thinks is listed).
- **Should be** = (graded stock count, grade &lt; 6, excluding aftersale) − (stock in active process type 22) − (count of pending marketplace orders).

Discrepancies are expected because:

1. Different data sources (denormalized counter vs live stock/process/orders).
2. Possible lag or errors in updating `listed_stock`.
3. Grade scope (should be only grades 1–5; listed is all grades).
4. **C** uses order **count** instead of pending **item quantity**, which can make should be too high.
5. Aftersale and other filters apply only to the “should be” side.

To **reduce** discrepancy you can:

- Align grade scope (e.g. sum `listed_stock` only for variations with grade &lt; 6 when comparing).
- Change **C** to subtract **sum of order item quantities** for pending marketplace orders (status 2, order_type_id 3) instead of order count.
- Ensure all code paths that change “what is listed” (API sync, orders, listing flows) reliably update `variation.listed_stock` (and any marketplace_stock listed_stock used for the total).
- Optionally add a small doc or comment in the widget pointing to this analysis so future changes keep the two definitions in mind.

---

## 5. References

- Widget: `app/Http/Livewire/Dashboard/InventoryOverviewWidget.php` → `hydrateInventoryOverview()`.
- View: `resources/views/livewire/dashboard/inventory-overview-widget.blade.php` (title shows “Should Be : {{ $shouldBeListed }}”).
- Process type 22: topup (see `docs/LISTINGS_VIEW_DATA_SOURCES.md`).
- Order type 3: marketplace orders (e.g. BackMarket sales).
- `listed_stock` writers: grep `listed_stock\s*=` under `app/` (ListingController, RefreshNew, SyncMarketplaceStock, ReduceStockOnOrderCompleted, V2 ListingController, etc.).
