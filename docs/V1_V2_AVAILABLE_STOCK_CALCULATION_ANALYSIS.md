# V1 vs V2 Available Stock Calculation Analysis

## Summary
**V1 and V2 calculate "Available Stock" differently:**

- **V1**: Counts **physical inventory items** (actual stock records with IMEI/Serial numbers)
- **V2**: Sums **marketplace allocated stock** (sum of marketplace_stock.available_stock values)

## V1 Available Stock Calculation

### Location
**File:** `resources/views/listings.blade.php` (Line 1559)

### Code
```javascript
Available: ${variation.available_stocks.length || 0}
```

### Data Source
**Relationship:** `Variation_model::available_stocks()` (Line 149-151)

```php
public function available_stocks()
{
    return $this->hasMany(Stock_model::class, 'variation_id', 'id')
        ->where('status', 1)
        ->whereHas('active_order')
        ->whereHas('latest_listing_or_topup');
}
```

### What It Represents
**Physical inventory count** - The number of actual stock items (records in `stocks` table) that:
- Have `status = 1` (Available, not sold)
- Have an `active_order` (Order with status = 3, meaning completed/delivered purchase order)
- Have a `latest_listing_or_topup` (Has been through a listing or topup process - process_type_id 21 or 22)

### Example
If you have 10 physical iPhone units in inventory:
- Each unit has a record in `stocks` table with IMEI/Serial
- All 10 have `status = 1`, `active_order`, and `latest_listing_or_topup`
- **V1 Available Stock = 10**

---

## V2 Available Stock Calculation

### Location
**File:** `resources/views/v2/listing/partials/variation-card.blade.php` (Lines 48-88)

### Code
```php
// Calculate available stock from marketplace_stock table
$totalAvailableStock = 0;
if(isset($marketplaces) && count($marketplaces) > 0) {
    foreach($marketplaces as $mpId => $mp) {
        $marketplaceIdInt = (int)$mpId;
        $marketplaceStock = \App\Models\MarketplaceStockModel::where('variation_id', $variationId)
            ->where('marketplace_id', $marketplaceIdInt)
            ->first();
        if($marketplaceStock) {
            // Calculate available stock for this marketplace (listed - locked)
            $availableStock = $marketplaceStock->available_stock !== null 
                ? (int)$marketplaceStock->available_stock 
                : max(0, $listedStock - (int)($marketplaceStock->locked_stock ?? 0));
            $totalAvailableStock += $availableStock;
        }
    }
}

// Ensure available stock never exceeds total stock (safety check)
$totalAvailableStock = min($totalAvailableStock, $totalStock);

// Use marketplace available stock for display (not physical count)
$availableCount = $totalAvailableStock;
```

### Data Source
**Table:** `marketplace_stock` table

**Calculation per marketplace:**
- `available_stock = listed_stock - locked_stock`
- If `available_stock` is NULL, calculates: `max(0, listed_stock - locked_stock)`

**Total:** Sum of all marketplace `available_stock` values

### What It Represents
**Marketplace allocated stock** - The sum of stock quantities allocated to marketplaces:
- **Listed Stock**: How many units are listed on each marketplace
- **Locked Stock**: How many units are reserved/pending on each marketplace
- **Available Stock**: Listed - Locked (available for new orders)

### Example
If you have 10 physical iPhone units:
- 8 units allocated to BackMarket (marketplace_id = 1): `listed_stock = 8`, `locked_stock = 2`, `available_stock = 6`
- 2 units allocated to Refurbed (marketplace_id = 2): `listed_stock = 2`, `locked_stock = 0`, `available_stock = 2`
- **V2 Available Stock = 6 + 2 = 8**

---

## Key Differences

| Aspect | V1 | V2 |
|--------|----|----|
| **Data Source** | `stocks` table (physical inventory) | `marketplace_stock` table (allocations) |
| **Calculation** | Count of stock records | Sum of marketplace available_stock |
| **Represents** | Physical inventory items | Marketplace allocated stock |
| **Includes** | All physical items with status=1, active_order, latest_listing_or_topup | Only stock allocated to marketplaces (listed - locked) |
| **Excludes** | Items not yet allocated to marketplaces | Physical items not allocated to any marketplace |

## Why They Differ

### V1 Logic
- **Purpose**: Shows how many physical items are available in inventory
- **Use Case**: Inventory management - "How many units do we physically have?"
- **Limitation**: Doesn't account for marketplace allocations or locked stock

### V2 Logic
- **Purpose**: Shows how many units are available for sale on marketplaces
- **Use Case**: Marketplace management - "How many units can we sell right now?"
- **Advantage**: Accounts for marketplace allocations and locked/reserved stock
- **Limitation**: May not match physical inventory if not all stock is allocated

## Scenarios Where Numbers Differ

### Scenario 1: Unallocated Stock
- **Physical Inventory**: 10 units
- **Allocated to Marketplaces**: 8 units
- **V1 Available**: 10 (all physical items)
- **V2 Available**: 8 (only allocated stock)

### Scenario 2: Locked Stock
- **Physical Inventory**: 10 units
- **Allocated to BackMarket**: 10 units
- **Locked/Reserved**: 3 units
- **V1 Available**: 10 (all physical items)
- **V2 Available**: 7 (10 - 3 locked)

### Scenario 3: Multiple Marketplaces
- **Physical Inventory**: 10 units
- **BackMarket**: listed=6, locked=1, available=5
- **Refurbed**: listed=4, locked=0, available=4
- **V1 Available**: 10 (all physical items)
- **V2 Available**: 9 (5 + 4 from marketplaces)

## Recommendation

**V2's approach is more accurate for marketplace management** because:
1. ✅ Accounts for marketplace allocations
2. ✅ Accounts for locked/reserved stock
3. ✅ Shows actual sellable quantity per marketplace
4. ✅ Prevents overselling

**However**, if you want V2 to match V1's behavior (show physical inventory count), you would need to:
- Change V2 to use `$variation->available_stocks->count()` instead of `$totalAvailableStock`
- This would show physical inventory count, but lose marketplace allocation visibility

## Current V2 Implementation Notes

**File:** `resources/views/v2/listing/partials/variation-card.blade.php`

The code currently:
1. Calculates `$totalAvailableStock` from marketplace_stock table (Lines 48-77)
2. Keeps `$physicalAvailableCount` for reference (Line 83) but doesn't use it for display
3. Uses `$availableCount = $totalAvailableStock` for display (Line 88)

**Comment in code (Line 87):**
```php
// Use marketplace available stock for display (not physical count)
$availableCount = $totalAvailableStock;
```

This confirms V2 intentionally uses marketplace allocations, not physical inventory count.

