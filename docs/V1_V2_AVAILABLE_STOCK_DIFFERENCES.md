# V1 vs V2 Available Stock Calculation Differences

## Summary

Comparing the same variation (`15128Black-2`) between V1 and V2 listings reveals calculation discrepancies.

## Display Values

### V1 Listing Shows:
- **Stock**: 399
- **Pending Order Items**: 32 (BM Orders: 32)
- **Available**: 434
- **Difference**: 402

### V2 Listing Shows:
- **Pending Order Items**: 32 (BM Orders: 32)
- **Available**: 399
- **Difference**: 367
- **Total Stock**: 399
- **Listing Total**: 399

## Key Differences Identified

### 1. Available Stock Calculation

**V1 (Correct):**
```php
// app/Http/Controllers/ListingController.php:330
$availableCount = $variation->available_stocks ? $variation->available_stocks->count() : 0;
```
- Uses **physical inventory count** (`available_stocks` collection count)
- Result: **434** (actual physical stock items)

**V2 (Incorrect):**
```php
// resources/views/v2/listing/partials/variation-card.blade.php:86
$availableCount = $totalAvailableStock; // Sum of marketplace listed_stock
```
- Uses **sum of marketplace listed_stock** values
- Result: **399** (marketplace allocated stock, not physical inventory)

**Issue**: V2 should use physical inventory count like V1, not marketplace listed_stock sum.

---

### 2. Pending Orders Calculation

**V1 (Correct):**
```php
// app/Http/Controllers/ListingController.php:331
$pendingCount = $variation->pending_orders ? $variation->pending_orders->sum('quantity') : 0;
```
- Uses **sum of order quantities** (`sum('quantity')`)
- Result: **32** (total quantity in pending orders)

**V2 (Incorrect):**
```php
// resources/views/v2/listing/partials/variation-card.blade.php:82
$pendingCount = $pendingOrders->count();
```
- Uses **count of pending orders** (number of orders, not quantities)
- Result: **32** (coincidentally same, but wrong calculation method)

**Issue**: V2 should use `sum('quantity')` like V1, not `count()`.

---

### 3. Difference Calculation

**V1:**
```
Difference = Available - Pending
Difference = 434 - 32 = 402 ✓
```

**V2:**
```
Difference = Available - Pending
Difference = 399 - 32 = 367 ✗
```

**Issue**: The difference is wrong because:
1. Available is wrong (399 instead of 434)
2. Pending calculation method is wrong (count vs sum, though result happens to match)

---

## Root Cause

V2 was designed to use **marketplace-allocated stock** (sum of `marketplace_stock.listed_stock`) instead of **physical inventory count** (`available_stocks.count()`). However, V1 uses physical inventory, and the client expects consistency.

Additionally, V2 uses `count()` for pending orders instead of `sum('quantity')`, which could lead to incorrect values when multiple items exist in a single order.

## Fix Required

1. **Change Available Stock**: Use `$physicalAvailableCount` (physical inventory count) instead of `$totalAvailableStock` (marketplace listed_stock sum)
2. **Change Pending Count**: Use `$pendingOrders->sum('quantity')` instead of `$pendingOrders->count()`

This will make V2 match V1's calculation logic and display the correct values.

