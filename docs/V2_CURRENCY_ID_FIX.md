# V2 Currency ID Logic Fix

## Change Made

Updated V2 to use `currency_id == 4` instead of `country.id == 73` to identify EUR listings, matching V1 behavior.

---

## Before (V2 - Wrong)

**Location:** `public/assets/v2/listing/js/listing.js` Line 666-669

```javascript
// Calculate min prices from listings with country 73 (EUR)
const eurListings = data.listings.filter(listing => {
    const country = listing.country_id || (listing.country && countries[listing.country]);
    return country && (country.id === 73 || listing.country === 73);
});
```

**Problem:**
- Uses country-based filtering (`country.id == 73`)
- May include/exclude wrong listings if country and currency don't match
- More complex logic with fallbacks

---

## After (V2 - Fixed)

**Location:** `public/assets/v2/listing/js/listing.js` Line 665-668

```javascript
// Calculate min prices from listings with currency_id 4 (EUR) - matches V1 logic
const eurListings = data.listings.filter(listing => {
    return listing.currency_id == 4;  // 4 = EUR currency
});
```

**Benefits:**
- ✅ Uses currency-based filtering (matches V1)
- ✅ More accurate - directly checks currency, not country
- ✅ Simpler logic - no fallbacks needed
- ✅ Consistent with V1 behavior

---

## V1 Reference

**Location:** `resources/views/listings.blade.php` Line 1071, 1379

```javascript
if (listing.currency_id != 4) {
    // Convert for non-EUR listings
}
```

**Logic:**
- Uses `currency_id == 4` to identify EUR listings
- Direct currency check

---

## Comparison

| Feature | V1 | V2 (Before) | V2 (After) |
|---------|----|-------------|------------|
| **EUR Identification** | `currency_id == 4` | `country.id == 73` | `currency_id == 4` ✅ |
| **Accuracy** | ✅ Direct currency check | ⚠️ Indirect country check | ✅ Direct currency check |
| **Consistency** | ✅ Standard | ❌ Different from V1 | ✅ Matches V1 |

---

## Impact

### Before Fix:
- Could include listings with `country == 73` but `currency_id != 4` (wrong currency)
- Could exclude listings with `currency_id == 4` but `country != 73` (correct currency, wrong country)

### After Fix:
- ✅ Only includes listings with `currency_id == 4` (EUR)
- ✅ Matches V1 behavior exactly
- ✅ More accurate base price calculations

---

## Testing Checklist

- [ ] EUR listings (currency_id == 4) are included in base price calculation
- [ ] Non-EUR listings (currency_id != 4) are excluded from base price calculation
- [ ] Base prices (m_price, m_min_price) are calculated correctly
- [ ] Currency conversion works correctly for non-EUR listings
- [ ] Display shows "Fr: £XX.XX" format correctly

---

## Files Modified

1. ✅ `public/assets/v2/listing/js/listing.js`
   - Line 665-668: Changed from country-based to currency-based filtering

---

## Result

✅ **V2 now matches V1 currency identification logic**
- Uses `currency_id == 4` to identify EUR listings
- More accurate and consistent with V1
- Simpler implementation

