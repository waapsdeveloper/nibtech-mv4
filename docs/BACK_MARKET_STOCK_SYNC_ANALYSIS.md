# Back Market Stock Sync Analysis

## Overview
Analysis of automatic stock quantity synchronization from Back Market (marketplace ID 1) to the listing system.

---

## Client Requirement
- **Trigger:** When opening listing page OR every 30 minutes
- **Action:** Hit Back Market API (marketplace ID 1)
- **Purpose:** Fetch current stock quantity from Back Market
- **Update:** Update `listed_stock` in our database

---

## Analysis Results

### ✅ Original Listing Page - IMPLEMENTED

#### 1. On Page Load (When Opening Listing)
**Location:** `resources/views/listings.blade.php` (Line 1330)

**Implementation:**
```javascript
let listedStock = fetchUpdatedQuantity(variation.id);
```

**Function Definition:** (Lines 962-982)
```javascript
function fetchUpdatedQuantity(variationId, bm) {
    let params = {
        csrf: "{{ csrf_token() }}"
    };
    let queryString = $.param(params);
    return $.ajax({
        url: `{{ url('listing/get_updated_quantity') }}/${variationId}?${queryString}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            $('#quantity_'+variationId).val(response.updatedQuantity);
        },
        error: function(xhr) {
            console.error("Error fetching quantity:", xhr.responseText);
        }
    });
}
```

**Backend Route:** `listing/get_updated_quantity/{id}`
**Controller Method:** `app/Http/Controllers/ListingController.php` (Lines 689-696)
```php
public function getUpdatedQuantity($variationId)
{
    $bm = new BackMarketAPIController();
    $variation = Variation_model::findOrFail($variationId);
    $updatedQuantity = $variation->update_qty($bm);
    return response()->json(['updatedQuantity' => $updatedQuantity]);
}
```

**Model Method:** `app/Models/Variation_model.php` (Lines 173-195)
```php
public function update_qty($bm)
{
    $var = $bm->getOneListing($this->reference_id);
    
    // Check if response is valid and has expected properties
    if ($var && is_object($var)) {
        $quantity = isset($var->quantity) ? $var->quantity : $this->listed_stock;
        $sku = isset($var->sku) ? $var->sku : $this->sku;
        $state = isset($var->publication_state) ? $var->publication_state : $this->state;
    } else {
        // If API call failed, use current database values
        $quantity = $this->listed_stock;
        $sku = $this->sku;
        $state = $this->state;
    }
    
    Variation_model::where('id', $this->id)->update([
        'listed_stock' => $quantity,
        'sku' => $sku,
        'state' => $state
    ]);
    return $quantity;
}
```

**API Call:** `app/Http/Controllers/BackMarketAPIController.php` (Line 820)
```php
public function getOneListing($listing_id) {
    $end_point = 'listings/' . $listing_id;
    $result = $this->apiGet($end_point);
    return $result;
}
```

**Flow:**
1. User opens listing page
2. For each variation displayed, JavaScript calls `fetchUpdatedQuantity(variation.id)`
3. AJAX request to `listing/get_updated_quantity/{id}`
4. Controller calls `$variation->update_qty($bm)`
5. Model calls `$bm->getOneListing($this->reference_id)` to fetch from Back Market API
6. Updates `listed_stock`, `sku`, and `state` in database
7. Returns updated quantity to frontend
8. Frontend updates the quantity input field

---

#### 2. Scheduled Command (Every 30 Minutes / Hourly)

**Command:** `app/Console/Commands/FunctionsThirty.php`
**Schedule:** `app/Console/Kernel.php` (Line 90-91)
```php
$schedule->command('functions:thirty')
    ->hourly();
```

**Note:** Runs **hourly** (not every 30 minutes, but close to client requirement)

**Implementation:** (Lines 53-117)
```php
public function get_listings(){
    $bm = new BackMarketAPIController();
    $listings = $bm->getAllListings();
    
    foreach($listings as $country => $lists){
        foreach($lists as $list){
            $variation = Variation_model::where(['reference_id'=>trim($list->listing_id), 'sku' => trim($list->sku)])->first();
            
            // ... other updates ...
            
            // Update stock quantity from Back Market
            $variation->listed_stock = $list->quantity;  // Line 101
            
            // ... save variation ...
        }
    }
}
```

**Flow:**
1. Scheduled command runs hourly
2. Calls `$bm->getAllListings()` to fetch ALL listings from Back Market
3. For each listing, finds matching variation by `reference_id` and `sku`
4. Updates `$variation->listed_stock = $list->quantity`
5. Saves variation

**Additional Method:** `get_listingsBi()` (Lines 119-147)
- Similar functionality but uses `getAllListingsBi()` method
- Also updates `listed_stock` from Back Market data (Line 135)

---

### ❌ V2 Listing Page - NOT IMPLEMENTED

#### Missing Functionality

**1. No Automatic Stock Sync on Page Load**
- **Location Checked:** `resources/views/v2/listing/listing.blade.php`
- **Result:** No `fetchUpdatedQuantity` or similar function call
- **Location Checked:** `app/Http/Controllers/V2/ListingController.php`
- **Result:** No `getUpdatedQuantity` method exists
- **Impact:** Stock quantities are NOT automatically synced when opening V2 listing page

**2. No Route for Stock Sync**
- **Location Checked:** `routes/web.php`
- **Result:** No route like `v2/listing/get_updated_quantity/{id}` exists
- **Impact:** Even if frontend tried to call it, there's no endpoint

**3. Relies Only on Scheduled Command**
- V2 listing page only gets updated stock from the scheduled `FunctionsThirty` command
- If user opens page between scheduled runs, they see stale stock data

---

## Comparison Table

| Feature | Original Listing | V2 Listing | Status |
|---------|-----------------|------------|--------|
| **Auto-sync on page load** | ✅ Yes (via `fetchUpdatedQuantity`) | ❌ No | **MISSING** |
| **Scheduled sync (hourly)** | ✅ Yes (`FunctionsThirty`) | ✅ Yes (same command) | **WORKING** |
| **Backend endpoint** | ✅ `listing/get_updated_quantity/{id}` | ❌ Not exists | **MISSING** |
| **Frontend function** | ✅ `fetchUpdatedQuantity()` | ❌ Not exists | **MISSING** |
| **Updates on demand** | ✅ Yes | ❌ No | **MISSING** |

---

## Technical Details

### Back Market API Integration

**Endpoint Used:**
- Single listing: `GET /ws/listings/{listing_id}`
- All listings: `GET /ws/listings?publication_state={state}&page-size=50`

**Data Retrieved:**
- `quantity` → `variation.listed_stock`
- `sku` → `variation.sku`
- `publication_state` → `variation.state`

**API Controller:** `app/Http/Controllers/BackMarketAPIController.php`
- `getOneListing($listing_id)` - Fetches single listing
- `getAllListings($publication_state, $param)` - Fetches all listings

---

## Impact Assessment

### Current V2 Behavior
1. User opens V2 listing page
2. Variations display with stock quantities from database
3. **Stock quantities may be stale** (last updated by scheduled command)
4. No automatic refresh happens

### Expected Behavior (Based on Original)
1. User opens listing page
2. Variations display with stock quantities from database
3. **JavaScript automatically calls Back Market API** for each variation
4. Stock quantities update in real-time
5. User sees current stock levels

---

## Recommendations

### Option 1: Implement Same Functionality in V2
1. **Add Backend Endpoint:**
   - Create `getUpdatedQuantity($variationId)` method in `app/Http/Controllers/V2/ListingController.php`
   - Add route: `v2/listing/get_updated_quantity/{id}`

2. **Add Frontend Function:**
   - Create `fetchUpdatedQuantity()` function in V2 listing JavaScript
   - Call it for each variation when page loads (similar to original)

3. **Update Variation Card:**
   - Add JavaScript to call `fetchUpdatedQuantity()` when variation card is rendered
   - Update quantity display after API response

### Option 2: Batch Sync on Page Load
- Instead of individual API calls per variation, fetch all at once
- More efficient but requires different implementation

### Option 3: Background Sync
- Use AJAX to sync stock in background without blocking UI
- Show loading indicator while syncing

---

## Files to Modify (If Implementing)

1. **Backend:**
   - `app/Http/Controllers/V2/ListingController.php` - Add `getUpdatedQuantity()` method
   - `routes/web.php` - Add route for stock sync

2. **Frontend:**
   - `resources/views/v2/listing/listing.blade.php` - Add JavaScript function
   - `public/assets/v2/listing/js/listing.js` - Add `fetchUpdatedQuantity()` function
   - `resources/views/v2/listing/partials/variation-card.blade.php` - Trigger sync on load

3. **Configuration:**
   - `app/Services/V2/ListingConfig.php` - Add URL for stock sync endpoint (if using config)

---

## Conclusion

**Status:** ❌ **NOT WORKING in V2**

The V2 listing page does **NOT** automatically sync stock quantities from Back Market when opening the page. It only relies on the scheduled `FunctionsThirty` command which runs hourly.

**Gap:** The original listing page automatically fetches and updates stock quantities for each variation when the page loads, ensuring users always see current stock levels. This functionality is completely missing in V2.

**Priority:** **HIGH** - This affects data accuracy and user experience. Users may make decisions based on stale stock data.

---

## Next Steps

1. ✅ **Analysis Complete** - This document
2. ⏳ **Implementation** - Add stock sync functionality to V2
3. ⏳ **Testing** - Verify stock sync works correctly
4. ⏳ **Documentation** - Update V2 documentation

---

**Generated:** [Current Date]
**Analyst:** Code Analysis
**Status:** Ready for Implementation

