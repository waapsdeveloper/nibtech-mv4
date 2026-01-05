# V1 & V2 Listing Bulk Update - EUR Only Analysis

## Summary
**YES, the client is correct.** In V1 listing, when running price and handler updates from the parent card, it **only updates listings in EUR currency** (currency_id = 4, country = 73).

**V2 Implementation:** Updated to match V1 behavior - bulk updates from parent card now only affect EUR listings (currency_id = 4).

## How It Works

### 1. EUR Listings Array Population

The system maintains a `window.eur_listings[variationId]` array that **only contains EUR listings**:

**Location:** `resources/views/listings.blade.php`

#### When Displaying Variations (Line 1386-1388):
```javascript
if (listing.currency_id != 4) {
    // Non-EUR listings: Calculate foreign currency prices
    let rates = exchange_rates_2[currencies_2[listing.currency_id]];
    p_append = 'Fr: '+currency_sign_2[listing.currency_id]+(parseFloat(m_price)*parseFloat(rates)).toFixed(2);
    pm_append = 'Fr: '+currency_sign_2[listing.currency_id]+(parseFloat(m_min_price)*parseFloat(rates)).toFixed(2);
} else {
    // EUR listings ONLY: Add to eur_listings array
    window.eur_listings[variation.id] = window.eur_listings[variation.id] || [];
    window.eur_listings[variation.id].push(listing);
}
```

#### When Loading Listings via AJAX (Line 1078-1080):
```javascript
if (listing.currency_id == 4) {
    // Add EUR listings to eur_listings array when loaded via AJAX
    window.eur_listings[variationId] = window.eur_listings[variationId] || [];
    window.eur_listings[variationId].push(listing);
}
```

### 2. Bulk Handler Updates

**Function:** `submitForm8()` (Line 679-705)

```javascript
function submitForm8(event, variationId, listings, marketplaceId) {
    var form = $('#change_all_handler_' + variationId);
    var min_price = $('#all_min_handler_' + variationId).val();
    var price = $('#all_handler_' + variationId).val();

    // Filter listings by marketplace_id if provided
    var listingsToUpdate = listings || [];
    if (marketplaceId) {
        listingsToUpdate = listings.filter(function(listing) {
            return listing.marketplace_id == marketplaceId;
        });
    }

    applyHandlerChanges(listingsToUpdate, min_price, price, marketplaceId, variationId);
}
```

**Key Point:** The `listings` parameter comes from `window.eur_listings[variationId]` which **only contains EUR listings**.

**Called from:**
- Line 821: `submitForm8(e, variationId, window.eur_listings[variationId] || [], defaultMarketplaceId)`
- Line 905: `submitForm8(event, variationId, window.eur_listings[variationId] || [], defaultMarketplaceId)`

### 3. Bulk Price Updates

**Function:** `submitForm4()` (Line 499-525)

```javascript
function submitForm4(event, variationId, listings, marketplaceId) {
    var form = $('#change_all_price_' + variationId);
    var min_price = $('#all_min_price_' + variationId).val();
    var price = $('#all_price_' + variationId).val();

    // Filter listings by marketplace_id if provided
    var listingsToUpdate = listings || [];
    if (marketplaceId) {
        listingsToUpdate = listings.filter(function(listing) {
            return listing.marketplace_id == marketplaceId;
        });
    }

    applyPriceChanges(listingsToUpdate, min_price, price, marketplaceId, variationId);
}
```

**Key Point:** Same as handlers - uses `window.eur_listings[variationId]` which **only contains EUR listings**.

**Called from:**
- Line 831: `submitForm4(e, variationId, window.eur_listings[variationId] || [], defaultMarketplaceId)`
- Line 952: `submitForm4(event, variationId, window.eur_listings[variationId] || [], marketplaceId)`

### 4. Min Price Calculation

**Location:** Line 1335-1336

```javascript
let m_min_price = Math.min(...variation.listings.filter(listing => listing.country === 73).map(listing => listing.min_price));
let m_price = Math.min(...variation.listings.filter(listing => listing.country === 73).map(listing => listing.price));
```

**Key Point:** Uses `listing.country === 73` (EUR country) to calculate minimum prices.

## Currency and Country IDs

- **EUR Currency ID:** 4
- **EUR Country ID:** 73

## Flow Diagram

```
User clicks "Change" or "Push" in parent card
    ↓
submitForm8() or submitForm4() called
    ↓
Uses window.eur_listings[variationId]
    ↓
This array ONLY contains listings where currency_id == 4 (EUR)
    ↓
Filters by marketplace_id if provided
    ↓
applyHandlerChanges() or applyPriceChanges()
    ↓
Updates ONLY EUR listings
```

## Conclusion

**The client's statement is TRUE:**

> "In V1 listing when we run price and handler updates from parent card, it used to update all prices in euros only"

The system is designed to:
1. Only populate `window.eur_listings` with EUR listings (currency_id == 4)
2. Use this array for all bulk updates from the parent card
3. Filter by marketplace_id if provided, but still only within EUR listings
4. Calculate min prices only from EUR listings (country == 73)

This ensures that bulk updates from the parent card **never affect non-EUR listings** (GBP, USD, etc.).

---

## V2 Implementation (Updated to Match V1)

### Changes Made

**File:** `app/Http/Controllers/V2/ListingController.php`

#### 1. `update_marketplace_handlers()` Method (Line 1708-1713)

**Before:**
```php
// Get all listings for this variation and marketplace
$listings = Listing_model::where('variation_id', $variationId)
    ->where('marketplace_id', $marketplaceId)
    ->get();
```

**After:**
```php
// Get all listings for this variation and marketplace
// V1 Pattern: Only update EUR listings (currency_id = 4, country = 73)
$listings = Listing_model::where('variation_id', $variationId)
    ->where('marketplace_id', $marketplaceId)
    ->where('currency_id', 4) // EUR currency only (matches V1 behavior)
    ->get();
```

#### 2. `update_marketplace_prices()` Method (Line 1800-1805)

**Before:**
```php
// Get all listings for this variation and marketplace
$listings = Listing_model::where('variation_id', $variationId)
    ->where('marketplace_id', $marketplaceId)
    ->with(['currency', 'country_id'])
    ->get();
```

**After:**
```php
// Get all listings for this variation and marketplace
// V1 Pattern: Only update EUR listings (currency_id = 4, country = 73)
$listings = Listing_model::where('variation_id', $variationId)
    ->where('marketplace_id', $marketplaceId)
    ->where('currency_id', 4) // EUR currency only (matches V1 behavior)
    ->with(['currency', 'country_id'])
    ->get();
```

### V2 Flow Diagram

```
User clicks "Change" or "Push" in V2 marketplace bar
    ↓
update_marketplace_handlers() or update_marketplace_prices()
    ↓
Query filters: variation_id + marketplace_id + currency_id = 4 (EUR)
    ↓
Only EUR listings are retrieved
    ↓
Updates applied ONLY to EUR listings
    ↓
Non-EUR listings (GBP, USD, etc.) are NOT affected
```

### Consistency

Both V1 and V2 now follow the same pattern:
- ✅ Bulk handler updates only affect EUR listings
- ✅ Bulk price updates only affect EUR listings
- ✅ Non-EUR listings remain unchanged
- ✅ Maintains data integrity across currencies

---

## Bulk Update Modal (Target Prices)

### V1/V2 Shared Endpoint: `get_target_variations()`

**File:** `app/Http/Controllers/ListingController.php` (Line 490-493)

**Already Filters by EUR:**
```php
->join('listings', function($join){
    $join->on('variation.id', '=', 'listings.variation_id')
    ->where('listings.country', 73); // EUR country filter
})
```

**Status:** ✅ Already filters by country 73 (EUR)

### V1/V2 Shared Endpoint: `update_target()`

**File:** `app/Http/Controllers/ListingController.php` (Line 1418-1427)

**Updated with Safety Check:**
```php
public function update_target($id){
    $listing = Listing_model::find($id);
    
    if (!$listing) {
        return response()->json(['error' => 'Listing not found'], 404);
    }
    
    // V1/V2 Pattern: Only update EUR listings (currency_id = 4, country = 73)
    // Safety check: Ensure we're only updating EUR listings
    if ($listing->currency_id != 4) {
        return response()->json([
            'error' => 'Target updates are only allowed for EUR listings (currency_id = 4)',
            'currency_id' => $listing->currency_id
        ], 400);
    }
    
    $listing->target_price = request('target');
    $listing->target_percentage = request('percent');
    $listing->save();
    
    return $listing;
}
```

**Status:** ✅ Added safety check to prevent non-EUR updates

### Flow

```
V2 Bulk Update Modal Opens
    ↓
loadTargetVariations() calls get_target_variations()
    ↓
get_target_variations() filters by country = 73 (EUR)
    ↓
Returns only EUR listing IDs
    ↓
User updates target price/percentage
    ↓
submitBulkTarget() calls update_target() for each listing ID
    ↓
update_target() verifies currency_id = 4 (safety check)
    ↓
Only EUR listings are updated
```

## Summary of All EUR-Only Updates

| Feature | V1 | V2 | Status |
|---------|----|----|--------|
| Bulk Handler Updates (Parent Card) | ✅ EUR only | ✅ EUR only | ✅ Consistent |
| Bulk Price Updates (Parent Card) | ✅ EUR only | ✅ EUR only | ✅ Consistent |
| Bulk Target Price Updates (Modal) | ✅ EUR only | ✅ EUR only | ✅ Consistent |
| Individual Listing Updates | ❌ All currencies | ❌ All currencies | ✅ As intended |

**Note:** Individual listing updates (single row edits) are intentionally allowed for all currencies, as users may need to update specific listings in different currencies. Only bulk operations from parent cards/modals are restricted to EUR.

