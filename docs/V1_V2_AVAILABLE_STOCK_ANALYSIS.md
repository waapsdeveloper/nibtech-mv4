# V1 vs V2 Available Stock Calculation Analysis

## Problem Statement
Client noticed discrepancy: Inventory page shows 400 available items, but V1 Listing page shows 300. Need to analyze the calculation difference and ensure V2 matches V1 logic.

---

## V1 Listing Page Calculation

### Location
- **View**: `resources/views/listings.blade.php` (line 1559)
- **Display**: `${variation.available_stocks.length || 0}`
- **Relationship**: `Variation_model::available_stocks()`

### Relationship Definition
**File**: `app/Models/Variation_model.php` (lines 149-152)

```php
public function available_stocks()
{
    return $this->hasMany(Stock_model::class, 'variation_id', 'id')
        ->where('status', 1)
        ->whereHas('active_order')
        ->whereHas('latest_listing_or_topup');
}
```

### Requirements for V1 Listing "Available" Stock:
1. ✅ **`status = 1`** - Stock must be available (not sold)
2. ✅ **`whereHas('active_order')`** - Must have a completed purchase order
   - `active_order` relationship: `Order_model` where `status = 3` (completed)
3. ✅ **`whereHas('latest_listing_or_topup')`** - Must have been through listing/topup process
   - `latest_listing_or_topup` relationship: `Process_stock_model` where `process_type_id IN (21, 22)`
   - Process types: 21 = Listing, 22 = Topup

### What V1 Listing Shows:
- **Only stocks that are "ready to list"** - items that have been through the listing/topup process
- **Count**: 300 items (example)

---

## Inventory Page Calculation

### Location
- **Controller**: `app/Http/Livewire/Inventory.php` (lines 221-300)
- **Query**: Direct `Stock_model` query with filters

### Query Logic
```php
$data['stocks'] = Stock_model::
    ->where('stock.status', 1)  // Only available stocks
    ->whereHas('order', function ($q) {
        $q->where('orders.status', 3);  // Completed purchase orders
    })
    // ... additional filters for product/storage/color/grade ...
```

### Requirements for Inventory "Available" Stock:
1. ✅ **`status = 1`** - Stock must be available (not sold)
2. ✅ **`whereHas('order')` with `status = 3`** - Must have completed purchase order
3. ❌ **NO requirement for `latest_listing_or_topup`** - Shows ALL available stocks

### What Inventory Shows:
- **All available stocks** - regardless of whether they've been through listing/topup
- **Count**: 400 items (example)

---

## The Difference Explained

### Why Inventory (400) > V1 Listing (300)?

**The 100-item difference represents:**
- Items that are available (`status = 1`)
- Items that have been purchased (`has active_order`)
- **BUT items that haven't been through listing/topup process yet**

These are items that:
- Are physically in inventory
- Have been received from purchase orders
- **Have NOT yet been added to a listing or topup process**

### Business Logic:
- **Inventory Page**: Shows all physical inventory ready for sale
- **Listing Page**: Shows only inventory that has been processed through listing/topup and is "ready to list"

---

## V2 Listing Page Current Implementation

### Location
- **View**: `resources/views/v2/listing/partials/variation-card.blade.php` (lines 77-87)

### Current Code:
```php
// Use physical stock count (matching V1 behavior)
$availableStocks = $variation->available_stocks ?? collect();
$pendingOrders = $variation->pending_orders ?? collect();
$pendingBmOrders = $variation->pending_bm_orders ?? collect();
$physicalAvailableCount = $availableStocks->count(); // Physical stock items count
$pendingCount = $pendingOrders->sum('quantity'); // Sum of quantities (matching V1 behavior)
$pendingBmCount = $pendingBmOrders->count();

// Use physical inventory count for display (matching V1)
$availableCount = $physicalAvailableCount;
```

### Analysis:
✅ **V2 IS CORRECT** - It uses `$variation->available_stocks` which is the same relationship as V1:
- Uses `Variation_model::available_stocks()` relationship
- This relationship includes all three requirements:
  1. `status = 1`
  2. `whereHas('active_order')`
  3. `whereHas('latest_listing_or_topup')`

---

## Marketplace Consideration

### Important: Physical Stock vs Marketplace Stock

**Physical Stock (`stock` table)**:
- ❌ **NO `marketplace_id` field** - Physical stock items are NOT marketplace-specific
- Physical stock items are just inventory units (IMEI/Serial numbers)
- They belong to a `variation_id` only
- Inventory page shows ALL physical stock for a variation (400 items)

**Marketplace Stock (`marketplace_stock` table)**:
- ✅ **HAS `marketplace_id` field** - Stock distribution across marketplaces
- This is where stock quantities are DISTRIBUTED to different marketplaces
- Example: 300 physical items might be distributed as:
  - Marketplace 1 (BackMarket): 150 units
  - Marketplace 2 (Refurbed): 100 units
  - Marketplace 3 (Other): 50 units

### How "Available" Stock Works:

**Inventory Page**:
- Shows **physical stock count** (variation-level, NOT marketplace-specific)
- No marketplace filter - shows ALL physical items
- Count: 400 items (all physical stock for that variation)

**Listing Page (V1 & V2)**:
- Shows **physical stock count** that has been through listing/topup
- Also variation-level, NOT marketplace-specific
- Count: 300 items (physical stock ready to list)

**Marketplace Bar (V2)**:
- Shows **marketplace-specific stock** from `marketplace_stock` table
- This is the DISTRIBUTED quantity for that specific marketplace
- Example: "Listed: 150" (for Marketplace 1), "Listed: 100" (for Marketplace 2)

### Key Insight:

The "Available" count in listing pages (300) is **NOT marketplace-specific** - it's the total physical stock count for the variation. The marketplace-specific quantities are shown separately in the marketplace bars.

**Why Inventory (400) > Listing (300)?**
- Inventory: All physical stock (400 items)
- Listing: Only physical stock that has been through listing/topup (300 items)
- Difference: 100 items not yet processed

**Marketplace Distribution:**
- The 300 items ready to list are then DISTRIBUTED across marketplaces
- Distribution happens in `marketplace_stock` table
- Each marketplace gets a portion based on formulas

## Conclusion

### V2 Implementation Status: ✅ CORRECT

V2 listing page **already matches V1 logic**:
- Both use `Variation_model::available_stocks()` relationship
- Both require: `status=1`, `active_order`, and `latest_listing_or_topup`
- Both show variation-level physical stock count (NOT marketplace-specific)
- Both show the same count (300 in example)

### The Discrepancy is Expected:
- **Inventory (400)**: Shows ALL available physical inventory (variation-level)
- **Listing V1/V2 (300)**: Shows only inventory that has been through listing/topup (variation-level)
- **Difference (100)**: Items not yet processed through listing/topup

### Marketplace Context:
- **Physical Stock**: Variation-level (no marketplace_id) - shown in "Available" count
- **Marketplace Stock**: Marketplace-specific distribution - shown in marketplace bars
- Inventory page does NOT filter by marketplace (and shouldn't - physical stock has no marketplace_id)

### No Action Required:
V2 is already correctly implementing the same logic as V1. The difference between inventory and listing pages is intentional and represents the business logic:
- Inventory = All physical stock (variation-level)
- Listing = Only stock ready to list (variation-level)
- Marketplace Bars = Marketplace-specific distributed quantities

---

## Summary Table

| Page | Count | Requirements | Explanation |
|------|-------|-------------|-------------|
| **Inventory** | 400 | `status=1` + `active_order` | All available physical inventory |
| **V1 Listing** | 300 | `status=1` + `active_order` + `latest_listing_or_topup` | Only inventory ready to list |
| **V2 Listing** | 300 | `status=1` + `active_order` + `latest_listing_or_topup` | Only inventory ready to list (matches V1) |

**Difference**: 100 items that haven't been through listing/topup process yet.
