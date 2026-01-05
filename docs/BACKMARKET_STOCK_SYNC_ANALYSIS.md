# BackMarket Stock Sync Analysis

## Overview
Analysis of how BackMarket stock is fetched and updated in the system, identifying bulk vs individual API calls.

---

## 🔍 Stock Fetching Methods

### 1. **Bulk Fetch (getAllListings)** ✅ **EFFICIENT**

**Location:** `app/Http/Controllers/BackMarketAPIController.php::getAllListings()`

**How it works:**
- Fetches **ALL listings** from BackMarket API in bulk
- Uses pagination (50 items per page)
- Loops through all countries
- Returns listings with `quantity` field for each listing

**Code:**
```php
public function getAllListings ($publication_state = null, $param = array()) {
    $country_codes = Country_model::where('market_code','!=',null)->pluck('market_code','id')->toArray();
    foreach($country_codes as $id => $code){
        $end_point = 'listings?publication_state=$publication_state&page-size=50';
        $result = $this->apiGet($end_point, $code);
        // Paginates through all pages
        while (($result_next->next) != null) {
            $page++;
            $result_next = $this->apiGet($end_point_next, $code);
            // Accumulates all listings
        }
    }
    return $result_array; // Returns all listings with quantity
}
```

**Used by:**
- ✅ `FunctionsThirty::get_listings()` - Hourly command
- ✅ `FunctionsThirty::get_listingsBi()` - Hourly command

**Stock Update:**
```php
// Line 101 in FunctionsThirty.php
$variation->listed_stock = $list->quantity; // Updates from bulk fetch
```

**Advantages:**
- ✅ **Single API call per country** (with pagination)
- ✅ **Fetches all listings at once**
- ✅ **Much more efficient** than individual calls
- ✅ **Includes stock quantity** in response

---

### 2. **Individual Fetch (getOneListing)** ❌ **INEFFICIENT FOR BULK**

**Location:** `app/Http/Controllers/BackMarketAPIController.php::getOneListing()`

**How it works:**
- Fetches **ONE listing** at a time
- Makes separate API call for each variation
- Returns single listing with `quantity` field

**Code:**
```php
public function getOneListing($listing_id) {
    $end_point = 'listings/' . $listing_id;
    $result = $this->apiGet($end_point);
    return $result; // Single listing with quantity
}
```

**Used by:**
- ❌ `v2:sync-all-marketplace-stock-from-api` - **BULK SYNC COMMAND** (inefficient!)
- ❌ `v2:marketplace:sync-stock` - Scheduled every 6 hours
- ❌ `SyncMarketplaceStock` - V1 command
- ❌ `V2/SyncMarketplaceStock::getBackMarketStock()` - Individual sync

**Stock Update:**
```php
// Example from SyncAllMarketplaceStockFromAPI.php (line 133)
$apiListing = $bm->getOneListing($variation->reference_id);
$apiQuantity = (int) $apiListing->quantity;
$marketplaceStock->listed_stock = $apiQuantity;
```

**Disadvantages:**
- ❌ **One API call per variation** (could be hundreds/thousands)
- ❌ **Very slow** for bulk operations
- ❌ **High CPU usage** from many API calls
- ❌ **Rate limiting issues** possible

---

## 📅 Scheduled Stock Updates

### Current Schedule (from `app/Console/Kernel.php`)

#### 1. **V2 Marketplace Stock Sync** (Every 6 Hours)
```php
$schedule->command('v2:marketplace:sync-stock --marketplace=1')
    ->everySixHours()
    ->at('00:00') // Back Market at midnight
```

**Command:** `app/Console/Commands/V2/SyncMarketplaceStock.php`
- Uses `getOneListing()` for each variation ❌
- Processes all marketplace_stock records
- Checks if sync needed (6-hour interval)
- Updates `marketplace_stock.listed_stock` from API

**Issue:** Uses individual API calls instead of bulk fetch

---

#### 2. **Functions:Thirty** (Hourly)
```php
$schedule->command('functions:thirty')
    ->hourly();
```

**Command:** `app/Console/Commands/FunctionsThirty.php`
- Uses `getAllListings()` ✅ **BULK FETCH**
- Updates `variation.listed_stock` from bulk response
- Also updates listing prices, buybox info, etc.

**Note:** This is the **ONLY command using bulk fetch** for stock!

---

#### 3. **Sync All Marketplace Stock From API** (Manual/Queue)
```php
// Not scheduled, but can be triggered manually or via queue
php artisan v2:sync-all-marketplace-stock-from-api --marketplace=1
```

**Command:** `app/Console/Commands/V2/SyncAllMarketplaceStockFromAPI.php`
- Uses `getOneListing()` for each variation ❌
- Has 30-minute cooldown
- Updates `marketplace_stock` table
- Creates log entries

**Issue:** Uses individual API calls - very inefficient for bulk sync!

---

## 🔄 Stock Update Flow

### Current Flow (Inefficient)

```
Scheduler (Every 6 hours)
    ↓
v2:marketplace:sync-stock
    ↓
For each marketplace_stock record:
    ↓
    getOneListing(reference_id) ← API CALL #1
    ↓
    getOneListing(reference_id) ← API CALL #2
    ↓
    getOneListing(reference_id) ← API CALL #3
    ...
    (Could be 1000+ API calls!)
```

**Problem:** If you have 1000 variations, that's **1000 API calls**!

---

### Optimal Flow (Using Bulk Fetch)

```
Scheduler (Every 6 hours)
    ↓
v2:marketplace:sync-stock (OPTIMIZED)
    ↓
getAllListings() ← SINGLE API CALL (with pagination)
    ↓
For each listing in response:
    ↓
    Match by reference_id
    ↓
    Update marketplace_stock.listed_stock
```

**Benefit:** **1 API call per country** instead of 1000+ calls!

---

## 📊 Comparison

| Method | API Calls | Speed | CPU Usage | Used By |
|--------|-----------|-------|-----------|---------|
| **getAllListings()** | 1 per country | Fast | Low | `functions:thirty` ✅ |
| **getOneListing()** | 1 per variation | Slow | High | `v2:sync-all-marketplace-stock-from-api` ❌ |
| **getOneListing()** | 1 per variation | Slow | High | `v2:marketplace:sync-stock` ❌ |

---

## 🎯 Key Findings

### ✅ **What's Working Well:**
1. **`functions:thirty`** uses `getAllListings()` - efficient bulk fetch
2. Updates `variation.listed_stock` from bulk response
3. Runs hourly

### ❌ **What Needs Optimization:**
1. **`v2:sync-all-marketplace-stock-from-api`** - Uses individual calls instead of bulk
2. **`v2:marketplace:sync-stock`** - Uses individual calls instead of bulk
3. Both commands make **hundreds/thousands of API calls** when they could use **1 bulk call**

---

## 💡 Recommendations

### Option 1: Optimize `v2:marketplace:sync-stock` to use bulk fetch
- Replace `getOneListing()` loop with `getAllListings()`
- Match listings by `reference_id` or `reference_uuid`
- Update `marketplace_stock` records in bulk
- **Expected improvement:** 90-95% reduction in API calls

### Option 2: Optimize `v2:sync-all-marketplace-stock-from-api` to use bulk fetch
- Same approach as Option 1
- Use bulk fetch instead of individual calls
- **Expected improvement:** 90-95% reduction in API calls

### Option 3: Create unified bulk sync command
- Single command that uses `getAllListings()`
- Updates both `variation.listed_stock` and `marketplace_stock.listed_stock`
- Replace both commands with optimized version

---

## 📝 Current Stock Update Locations

### Where Stock is Updated:

1. **`functions:thirty`** (Hourly)
   - Uses: `getAllListings()` ✅
   - Updates: `variation.listed_stock`
   - Location: `app/Console/Commands/FunctionsThirty.php:101`

2. **`v2:marketplace:sync-stock`** (Every 6 hours)
   - Uses: `getOneListing()` ❌
   - Updates: `marketplace_stock.listed_stock`
   - Location: `app/Console/Commands/V2/SyncMarketplaceStock.php:272`

3. **`v2:sync-all-marketplace-stock-from-api`** (Manual/Queue)
   - Uses: `getOneListing()` ❌
   - Updates: `marketplace_stock.listed_stock`
   - Location: `app/Console/Commands/V2/SyncAllMarketplaceStockFromAPI.php:133`

---

## 🔧 Implementation Plan

### Phase 1: Optimize `v2:marketplace:sync-stock`
1. Replace individual `getOneListing()` calls with `getAllListings()`
2. Create mapping by `reference_id` or `reference_uuid`
3. Update `marketplace_stock` records in batch
4. Test and monitor CPU usage

### Phase 2: Optimize `v2:sync-all-marketplace-stock-from-api`
1. Same optimization as Phase 1
2. Use bulk fetch instead of individual calls
3. Maintain logging and error handling

### Phase 3: Consider Consolidation
1. Evaluate if both commands are needed
2. Consider merging into single optimized command
3. Update scheduler accordingly

---

**Date:** January 2026  
**Status:** Analysis Complete - Ready for Optimization

