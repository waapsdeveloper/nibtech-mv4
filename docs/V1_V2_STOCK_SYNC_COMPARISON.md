# V1 vs V2 Stock Sync Pattern Comparison

## Overview
Analysis of how V1 and V2 handle bulk stock updates from BackMarket API, and proposal for an improved unified pattern.

---

## 🔍 V1 Listing Stock Update Sequence

### Current V1 Pattern (Individual API Calls)

#### 1. **On-Demand Stock Fetch (Per Variation)**
**Location:** `app/Models/Variation_model.php::update_qty()`

**Sequence:**
```
User Action (e.g., open listing page)
    ↓
ListingController::getUpdatedQuantity($variationId)
    ↓
Variation_model::update_qty($bm)
    ↓
BackMarketAPIController::getOneListing($reference_id) ← API CALL #1
    ↓
Update variation.listed_stock
```

**Code:**
```php
// Variation_model.php (Line 183-205)
public function update_qty($bm)
{
    $var = $bm->getOneListing($this->reference_id); // Individual API call
    $quantity = isset($var->quantity) ? $var->quantity : $this->listed_stock;
    Variation_model::where('id', $this->id)->update([
        'listed_stock' => $quantity,
        'sku' => $var->sku,
        'state' => $var->publication_state
    ]);
    return $quantity;
}
```

**Used by:**
- `ListingController::getUpdatedQuantity()` - On page load
- `ListingController::add_quantity()` - Before adding stock
- `ListingController::update_quantity()` - When updating stock

**Issue:** ❌ **One API call per variation** - Very inefficient for bulk operations

---

#### 2. **Bulk Listing Sync (Including Stock)**
**Location:** `app/Console/Commands/FunctionsThirty.php::get_listings()`

**Sequence:**
```
Scheduler (Hourly)
    ↓
functions:thirty command
    ↓
BackMarketAPIController::getAllListings() ← BULK API CALL ✅
    ↓
For each country:
    For each listing in response:
        Update variation.listed_stock = $list->quantity
        Update listing prices, buybox, etc.
```

**Code:**
```php
// FunctionsThirty.php (Line 53-118)
public function get_listings(){
    $bm = new BackMarketAPIController();
    $listings = $bm->getAllListings(); // BULK FETCH ✅
    
    foreach($listings as $country => $lists){
        foreach($lists as $list){
            $variation = Variation_model::where(['reference_id'=>...])->first();
            $variation->listed_stock = $list->quantity; // Updates stock
            $variation->save();
            
            $listing = Listing_model::firstOrNew([...]);
            $listing->price = $list->price;
            // ... other fields
            $listing->save();
        }
    }
}
```

**Advantages:**
- ✅ **Single bulk API call** per country (with pagination)
- ✅ **Fetches all listings** including stock quantities
- ✅ **Efficient** - 1 call instead of 1000+ calls

**Limitations:**
- ⚠️ Updates `variation.listed_stock` only (not `marketplace_stock`)
- ⚠️ Runs hourly (not on-demand)
- ⚠️ Also updates prices, buybox, etc. (not just stock)

---

#### 3. **Stock Update When Adding Quantity**
**Location:** `app/Http/Controllers/ListingController.php::add_quantity()`

**Sequence:**
```
User adds stock
    ↓
ListingController::add_quantity($id)
    ↓
Variation_model::update_qty($bm) ← Fetch current stock from API
    ↓
Calculate new_quantity = previous_qty + stock_to_add
    ↓
BackMarketAPIController::updateOneListing() ← Update API
    ↓
Update variation.listed_stock
```

**Code:**
```php
// ListingController.php (Line 835-889)
public function add_quantity($id, $stock = 'no', ...){
    $variation = Variation_model::find($id);
    $bm = new BackMarketAPIController();
    $previous_qty = $variation->update_qty($bm); // Individual API call ❌
    
    // Calculate new quantity
    $new_quantity = $stock + $previous_qty;
    
    // Update via API
    $response = $bm->updateOneListing($variation->reference_id, 
        json_encode(['quantity'=>$new_quantity]), null, true);
    
    // Update database
    if($response && isset($response->quantity)){
        $variation->listed_stock = $response->quantity;
        $variation->save();
    }
}
```

**Issue:** ❌ **Two API calls** per stock update (fetch + update)

---

## 🔍 V2 Listing Stock Update Sequence

### Current V2 Pattern (Individual API Calls)

#### 1. **Scheduled Stock Sync (Every 6 Hours)**
**Location:** `app/Console/Commands/V2/SyncMarketplaceStock.php`

**Sequence:**
```
Scheduler (Every 6 hours)
    ↓
v2:marketplace:sync-stock --marketplace=1
    ↓
For each marketplace_stock record:
    ↓
    BackMarketAPIController::getOneListing($reference_id) ← API CALL #1 ❌
    ↓
    BackMarketAPIController::getOneListing($reference_id) ← API CALL #2 ❌
    ↓
    ... (1000+ API calls!)
    ↓
    Update marketplace_stock.listed_stock
```

**Code:**
```php
// V2/SyncMarketplaceStock.php (Line 111-146)
foreach ($marketplaceStocks as $marketplaceStock) {
    $apiQuantity = $this->getStockFromMarketplace($variation, $marketplaceId);
    // getStockFromMarketplace() calls getOneListing() ❌
    $marketplaceStock->listed_stock = $apiQuantity;
    $marketplaceStock->save();
}
```

**Issue:** ❌ **One API call per variation** - Very inefficient!

---

#### 2. **Manual Bulk Sync**
**Location:** `app/Console/Commands/V2/SyncAllMarketplaceStockFromAPI.php`

**Sequence:**
```
Manual/Queue Trigger
    ↓
v2:sync-all-marketplace-stock-from-api
    ↓
For each marketplace_stock record:
    ↓
    BackMarketAPIController::getOneListing($reference_id) ← API CALL ❌
    ↓
    Update marketplace_stock.listed_stock
```

**Issue:** ❌ **Same problem** - Individual API calls

---

## 📊 Comparison Table

| Aspect | V1 Pattern | V2 Pattern | Best Practice |
|--------|-----------|------------|---------------|
| **Bulk Fetch** | ✅ `getAllListings()` (in functions:thirty) | ❌ Individual `getOneListing()` | ✅ Use bulk fetch |
| **On-Demand** | ❌ Individual calls | ❌ Individual calls | ⚠️ Acceptable for single items |
| **Scheduled Sync** | ✅ Bulk (hourly) | ❌ Individual (every 6h) | ✅ Use bulk |
| **Updates** | `variation.listed_stock` | `marketplace_stock.listed_stock` | ✅ V2 structure is better |
| **API Calls** | 1 per country (bulk) | 1 per variation | ✅ Bulk is 100x+ more efficient |

---

## 🎯 Key Findings

### ✅ **What V1 Does Well:**
1. **`functions:thirty`** uses `getAllListings()` for bulk fetch
2. Efficient bulk sync (1 API call per country)

### ❌ **What V1 Does Poorly:**
1. **On-demand updates** use individual API calls
2. Updates only `variation.listed_stock` (not marketplace-specific)
3. No separation between marketplaces

### ✅ **What V2 Does Well:**
1. **Better data structure** - `marketplace_stock` table for per-marketplace stock
2. **Separation of concerns** - Each marketplace tracked separately

### ❌ **What V2 Does Poorly:**
1. **Uses individual API calls** instead of bulk fetch
2. **Very inefficient** - 1000+ API calls when 1 bulk call would work
3. **High CPU usage** from many API calls

---

## 💡 Proposed Improved Pattern (V2 Enhanced)

### Unified Bulk Stock Sync Pattern

#### **Principle:**
- Use **bulk fetch** (`getAllListings()`) for scheduled syncs
- Use **individual calls** only for on-demand single-item updates
- Update both `variation.listed_stock` and `marketplace_stock.listed_stock`

#### **Implementation:**

```php
// New optimized command: V2/SyncMarketplaceStockBulk.php

public function handle()
{
    $marketplaceId = $this->option('marketplace') ?? 1;
    
    // Step 1: Bulk fetch ALL listings from API (1 call per country)
    $bm = new BackMarketAPIController();
    $allListings = $bm->getAllListings(); // BULK FETCH ✅
    
    // Step 2: Create mapping by reference_id for quick lookup
    $listingMap = [];
    foreach($allListings as $countryId => $lists) {
        foreach($lists as $list) {
            $referenceId = $list->listing_id ?? $list->id;
            $listingMap[$referenceId] = [
                'quantity' => $list->quantity,
                'sku' => $list->sku,
                'state' => $list->publication_state,
                'country_id' => $countryId
            ];
        }
    }
    
    // Step 3: Get all variations that need updating
    $variations = Variation_model::whereNotNull('reference_id')
        ->whereHas('marketplaceStocks', function($q) use ($marketplaceId) {
            $q->where('marketplace_id', $marketplaceId);
        })
        ->with('marketplaceStocks')
        ->get();
    
    // Step 4: Update in batch
    $updated = 0;
    foreach($variations as $variation) {
        if(isset($listingMap[$variation->reference_id])) {
            $listingData = $listingMap[$variation->reference_id];
            
            // Update marketplace_stock
            $marketplaceStock = $variation->marketplaceStocks
                ->where('marketplace_id', $marketplaceId)
                ->first();
            
            if($marketplaceStock) {
                $marketplaceStock->listed_stock = $listingData['quantity'];
                $marketplaceStock->available_stock = max(0, 
                    $listingData['quantity'] - $marketplaceStock->locked_stock);
                $marketplaceStock->last_synced_at = now();
                $marketplaceStock->save();
                $updated++;
            }
        }
    }
    
    // Step 5: Update variation.listed_stock (sum of all marketplaces)
    $this->updateVariationListedStock();
    
    $this->info("Updated {$updated} marketplace stock records");
}
```

---

## 🔄 Improved Sequence Flow

### Scheduled Bulk Sync (Every 6 Hours)
```
Scheduler
    ↓
v2:marketplace:sync-stock-bulk --marketplace=1
    ↓
getAllListings() ← 1 BULK API CALL per country ✅
    ↓
Create reference_id → listing data map
    ↓
For each variation:
    Match by reference_id
    Update marketplace_stock.listed_stock
    ↓
Update variation.listed_stock (sum of all marketplaces)
```

**Benefits:**
- ✅ **1 API call per country** instead of 1000+ calls
- ✅ **90-95% reduction** in API calls
- ✅ **Much faster** execution
- ✅ **Lower CPU usage**

---

### On-Demand Single Item Update (Keep Individual)
```
User action (e.g., refresh single item)
    ↓
getOneListing($reference_id) ← 1 API CALL ✅
    ↓
Update marketplace_stock.listed_stock
```

**This is acceptable** - Single item updates should use individual calls.

---

## 📋 Implementation Plan

### Phase 1: Create Optimized Bulk Sync Command
1. Create `V2/SyncMarketplaceStockBulk.php`
2. Use `getAllListings()` for bulk fetch
3. Map by `reference_id` for quick lookup
4. Update `marketplace_stock` records in batch
5. Update `variation.listed_stock` as sum

### Phase 2: Replace Scheduled Command
1. Update `app/Console/Kernel.php` to use new bulk command
2. Keep old command for backward compatibility (deprecated)
3. Test and monitor CPU usage

### Phase 3: Optimize Manual Sync
1. Update `v2:sync-all-marketplace-stock-from-api` to use bulk fetch
2. Maintain logging and error handling
3. Add progress tracking

### Phase 4: Cleanup
1. Deprecate old individual-call commands
2. Update documentation
3. Monitor for any issues

---

## 🎯 Expected Improvements

| Metric | Current (V2) | Improved (V2 Enhanced) | Improvement |
|--------|--------------|------------------------|-------------|
| **API Calls** | 1000+ per sync | 10-20 per sync | **95-98% reduction** |
| **Execution Time** | 10-30 minutes | 1-3 minutes | **80-90% faster** |
| **CPU Usage** | High (many calls) | Low (few calls) | **90%+ reduction** |
| **Rate Limiting** | High risk | Low risk | ✅ Much safer |

---

## 📝 Summary

### V1 Pattern:
- ✅ Uses bulk fetch in `functions:thirty` (good!)
- ❌ Individual calls for on-demand (acceptable)
- ❌ Updates only `variation.listed_stock` (not marketplace-specific)

### V2 Pattern:
- ✅ Better data structure (`marketplace_stock` table)
- ❌ Uses individual calls for bulk sync (inefficient!)
- ❌ High CPU usage from many API calls

### Improved V2 Pattern:
- ✅ Use bulk fetch (`getAllListings()`) for scheduled syncs
- ✅ Keep individual calls for on-demand single items
- ✅ Update both `marketplace_stock` and `variation.listed_stock`
- ✅ 90-95% reduction in API calls
- ✅ Much lower CPU usage

---

**Date:** January 2026  
**Status:** Analysis Complete - Ready for Implementation

