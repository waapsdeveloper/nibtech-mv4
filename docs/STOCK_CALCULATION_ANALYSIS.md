# Stock Calculation Analysis - Pending & Available Numbers

## Overview
This document explains how the **Pending** and **Available** stock numbers are calculated in the V2 listing page variation cards.

## Location
**File**: `resources/views/v2/listing/partials/variation-card.blade.php` (lines 64-70, 101-115)

## Formula Breakdown

### 1. **Available Count** (`$availableCount`)
```php
$availableStocks = $variation->available_stocks ?? collect();
$availableCount = $availableStocks->count();
```

**Definition**: Count of physical stock items that are:
- **Status = 1** (Available, not sold)
- **Has an active_order** (Order with status = 3, meaning completed/delivered purchase order)
- **Has a latest_listing_or_topup** (Has been through a listing or topup process - process_type_id 21 or 22)

**Relationship**: `Variation_model::available_stocks()`
```php
return $this->hasMany(Stock_model::class, 'variation_id', 'id')
    ->where('status', 1)
    ->whereHas('active_order')
    ->whereHas('latest_listing_or_topup');
```

**What it represents**: 
- Physical inventory items (IMEI/Serial numbers) that are:
  - Available for sale (status = 1)
  - Have been purchased and received (active_order exists)
  - Have been listed or topped up to marketplaces (latest_listing_or_topup exists)

---

### 2. **Pending Count** (`$pendingCount`)
```php
$pendingOrders = $variation->pending_orders ?? collect();
$pendingCount = $pendingOrders->count();
```

**Definition**: Count of order items from **sales orders** (order_type_id = 3) that are:
- **Status = 2** (Pending/In Progress)
- **Not yet fulfilled**

**Relationship**: `Variation_model::pending_orders()`
```php
return $this->hasMany(Order_item_model::class, 'variation_id', 'id')
    ->whereHas('order', function($q){
        $q->where('order_type_id', 3)  // Sales orders
          ->where('status', 2);       // Pending status
    });
```

**What it represents**:
- Customer orders that have been placed but not yet fulfilled
- These are sales orders waiting to be shipped/processed

---

### 3. **Pending BM Count** (`$pendingBmCount`)
```php
$pendingBmOrders = $variation->pending_bm_orders ?? collect();
$pendingBmCount = $pendingBmOrders->count();
```

**Definition**: Subset of pending orders specifically from **BackMarket** (marketplace_id = 1)

**Relationship**: `Variation_model::pending_bm_orders()`
```php
return $this->hasMany(Order_item_model::class, 'variation_id', 'id')
    ->whereHas('order', function($q){
        $q->where('order_type_id', 3)      // Sales orders
          ->where('status', 2)             // Pending status
          ->where('marketplace_id', 1);    // BackMarket only
    });
```

**What it represents**:
- Pending orders specifically from BackMarket marketplace

---

### 4. **Difference** (`$difference`)
```php
$difference = $availableCount - $pendingCount;
```

**Formula**: `Available Stock - Pending Orders`

**What it represents**:
- **Positive value**: More available stock than pending orders (surplus)
- **Zero**: Available stock exactly matches pending orders (balanced)
- **Negative value**: More pending orders than available stock (shortage/backorder situation)

**Business Logic**:
- This indicates whether you have enough physical inventory to fulfill all pending customer orders
- A negative difference means you're short on stock and may need to restock

---

## Visual Display

In the variation card, these values are displayed as:

```
Pending Order Items: {pendingCount} (BM Orders: {pendingBmCount})
Available: {availableCount}
Difference: {difference}
```

## Key Database Tables Involved

1. **`stock`** table
   - Physical inventory items (IMEI/Serial numbers)
   - `status` field: 1 = Available, 2 = Sold

2. **`order`** table
   - Orders (purchases, sales, returns, etc.)
   - `order_type_id`: 1 = Purchase, 3 = Sale, etc.
   - `status`: 2 = Pending, 3 = Completed

3. **`order_item`** table
   - Individual items within orders
   - Links stock to orders

4. **`process_stock`** table
   - Tracks stock through various processes
   - `process_type_id`: 21 = Listing, 22 = Topup

## Important Notes

### Available Stock vs Listed Stock
- **Available Stock** (`$availableCount`): Physical inventory count
- **Listed Stock** (`$totalStock`): Quantity listed on marketplaces (can be different from available)

These are **separate concepts**:
- You can have 100 physical items (available) but only list 50 on marketplaces
- You can list 50 items but only have 30 physically available (if some are sold but not yet removed from listings)

### Calculation Method
- These are **NOT calculated from marketplace stock tables**
- They come directly from:
  - Physical stock records (`stock` table)
  - Order records (`order` and `order_item` tables)
- The counts are **real-time** based on current database state

## Example Scenario

**Scenario**: Variation has 50 physical items available, 20 pending customer orders

- **Available**: 50 (physical stock items)
- **Pending**: 20 (customer orders waiting)
- **Difference**: 30 (surplus - can fulfill all orders with 30 items left over)

**If pending increases to 60**:
- **Available**: 50
- **Pending**: 60
- **Difference**: -10 (shortage - need 10 more items to fulfill all orders)

---

## Summary

| Metric | Source | Calculation | Meaning |
|--------|--------|-------------|---------|
| **Available** | `stock` table | Count of items with status=1, active_order, and latest_listing_or_topup | Physical inventory ready for sale |
| **Pending** | `order_item` + `order` tables | Count of sales orders (type=3) with status=2 | Customer orders waiting to be fulfilled |
| **Difference** | Calculated | Available - Pending | Stock surplus or shortage |

These numbers help track inventory availability and fulfillment capacity in real-time.

