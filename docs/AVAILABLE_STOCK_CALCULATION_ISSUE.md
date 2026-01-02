# Available Stock Calculation Issue Analysis

## Problem
**It IS possible for `listed_stock = 82` and `available_stock = 0`** even when `locked_stock = 0`.

This happens because `available_stock` is a **stored field** in the database, not a calculated field. If it's not recalculated when `listed_stock` changes, it can become out of sync.

## Root Cause

### Formula
```
available_stock = max(0, listed_stock - locked_stock)
```

### When Available Stock Should Be Updated
1. When `listed_stock` changes
2. When `locked_stock` changes
3. When stock is distributed to marketplaces
4. When stock is synced from API

### Where Available Stock is NOT Being Recalculated

#### 1. V1 ListingController - Marketplace Stock Update (Line 1052-1054)
**File:** `app/Http/Controllers/ListingController.php`

```php
// Update marketplace stock
$marketplaceStock->listed_stock = $new_quantity;
$marketplaceStock->admin_id = session('user_id');
$marketplaceStock->save();
// ❌ MISSING: available_stock recalculation
```

**Fix Needed:**
```php
$marketplaceStock->listed_stock = $new_quantity;
$marketplaceStock->available_stock = max(0, $new_quantity - ($marketplaceStock->locked_stock ?? 0));
$marketplaceStock->admin_id = session('user_id');
$marketplaceStock->save();
```

#### 2. StockDistributionService - When Creating New Records
**File:** `app/Services/Marketplace/StockDistributionService.php` (Line 78-83)

```php
$marketplaceStock = MarketplaceStockModel::create([
    'variation_id' => $variationId,
    'marketplace_id' => $marketplace->id,
    'listed_stock' => 0,
    'admin_id' => $adminId,
]);
// ❌ MISSING: available_stock initialization
```

**Fix Needed:**
```php
$marketplaceStock = MarketplaceStockModel::create([
    'variation_id' => $variationId,
    'marketplace_id' => $marketplace->id,
    'listed_stock' => 0,
    'locked_stock' => 0,
    'available_stock' => 0,
    'admin_id' => $adminId,
]);
```

#### 3. StockDistributionService - When Updating Listed Stock
**File:** `app/Services/Marketplace/StockDistributionService.php` (Line 98-99)

```php
$marketplaceStock->listed_stock = $newValue;
// ❌ MISSING: available_stock recalculation
```

**Fix Needed:**
```php
$marketplaceStock->listed_stock = $newValue;
$marketplaceStock->available_stock = max(0, $newValue - ($marketplaceStock->locked_stock ?? 0));
```

## Where Available Stock IS Being Recalculated (Correctly)

✅ **V2 ListingController** - `fixStockMismatch()` method (Line 2041)
✅ **SyncMarketplaceStock Commands** - Both V1 and V2 (Line 199, 220, 286)
✅ **Stock Lock Listeners** - `LockStockOnOrderCreated` and `ReduceStockOnOrderCompleted`
✅ **StockDistributionService** - Some places (needs full review)

## Solution

### Option 1: Add Database Trigger (Recommended)
Create a database trigger that automatically recalculates `available_stock` whenever `listed_stock` or `locked_stock` changes.

### Option 2: Use Model Accessors/Mutators
Add a mutator in `MarketplaceStockModel` that automatically recalculates `available_stock` when `listed_stock` or `locked_stock` is set.

### Option 3: Fix All Update Points (Current Approach)
Update all places where `listed_stock` is modified to also recalculate `available_stock`.

## Recommended Fix

Add a method to `MarketplaceStockModel` and use it everywhere:

```php
public function recalculateAvailableStock()
{
    $this->available_stock = max(0, ($this->listed_stock ?? 0) - ($this->locked_stock ?? 0));
    return $this;
}

// Then use it:
$marketplaceStock->listed_stock = $new_quantity;
$marketplaceStock->recalculateAvailableStock();
$marketplaceStock->save();
```

Or use a model observer:

```php
// In MarketplaceStockModel
protected static function booted()
{
    static::saving(function ($marketplaceStock) {
        $marketplaceStock->available_stock = max(0, 
            ($marketplaceStock->listed_stock ?? 0) - ($marketplaceStock->locked_stock ?? 0)
        );
    });
}
```

## Test Case

For SKU `15Pro128Natural-3`:
- **Listed Stock:** 82
- **Locked Stock:** 0
- **Available Stock:** 0 ❌ (Should be 82)
- **Expected:** available_stock = 82 - 0 = 82

This confirms the bug - `available_stock` was not recalculated when `listed_stock` was updated.

