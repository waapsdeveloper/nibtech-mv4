# V2 8% Price Validation Implementation

## Overview
Re-enabled the 8% price difference validation formula in V2 to match V1 behavior.

---

## Implementation Details

### Formula Logic
**Rule:** Price cannot exceed min_price by more than 8%

**Formula:**
```javascript
if (minVal > priceVal || (minVal > 0 && priceVal > 0 && minVal * 1.08 < priceVal)) {
    // Invalid: Both inputs turn red
} else {
    // Valid: Remove red classes
}
```

**Conditions:**
1. `min_price` must be <= `price` (min cannot exceed price)
2. `price` must be <= `min_price * 1.08` (price cannot exceed min by more than 8%)

---

## Code Changes

### 1. Re-enabled checkMinPriceDiff Call

**File:** `public/assets/v2/listing/js/listing.js`

**Price Update Handler (Line ~1637):**
```javascript
success: function(response) {
    if (response.success) {
        // Show success feedback - individual input green, persistent
        input.addClass('bg-green');
        
        // Run 8% validation formula (like V1)
        if (typeof window.checkMinPriceDiff === 'function') {
            window.checkMinPriceDiff(listingId);
        }
    }
    input.prop('disabled', false);
}
```

**Handler Limits Update (Line ~1706):**
```javascript
success: function(response) {
    if (response.success) {
        // Show success feedback - individual inputs green, persistent
        minLimitInput.addClass('bg-green');
        priceLimitInput.addClass('bg-green');
        
        // Run 8% validation formula (like V1)
        if (typeof window.checkMinPriceDiff === 'function') {
            window.checkMinPriceDiff(listingId);
        }
    }
    minLimitInput.prop('disabled', false);
    priceLimitInput.prop('disabled', false);
}
```

---

### 2. Updated checkMinPriceDiff Function

**File:** `public/assets/v2/listing/js/price-validation.js`

**Before:**
- Removed all classes (bg-red, bg-green) then re-added based on validation
- Added bg-green to both inputs if valid

**After:**
- Only removes/adds red classes for validation
- Preserves success green from update (if validation passes)
- Matches V1 behavior: validation only removes red, doesn't add green

**Code:**
```javascript
window.checkMinPriceDiff = function(listingId) {
    const minVal = parseFloat(minPriceInput.value) || 0;
    const priceVal = parseFloat(priceInput.value) || 0;

    // Validation: min_price should be <= price AND price should be <= min_price * 1.08
    if (minVal > priceVal || (minVal > 0 && priceVal > 0 && minVal * 1.08 < priceVal)) {
        // Invalid: highlight both in red (validation overrides success green)
        minPriceInput.classList.remove('bg-green');
        minPriceInput.classList.add('bg-red');
        priceInput.classList.remove('bg-green');
        priceInput.classList.add('bg-red');
    } else {
        // Valid: remove red classes (success green from update will remain if present)
        minPriceInput.classList.remove('bg-red');
        priceInput.classList.remove('bg-red');
        // Note: We don't add bg-green here - that's handled by success feedback
    }
};
```

---

## Behavior Flow

### Scenario 1: Valid Price Update
1. User changes min_price → Press Enter
2. Success: min_price input turns green ✅
3. Validation runs: Both inputs valid → No red classes
4. Result: min_price stays green (success feedback)

### Scenario 2: Invalid Price Update (Price exceeds min by >8%)
1. User changes price → Press Enter
2. Success: price input turns green ✅
3. Validation runs: Price exceeds min by >8% → Both inputs turn red ❌
4. Result: Both inputs turn red (validation overrides success)

### Scenario 3: Invalid Price Update (Min exceeds price)
1. User changes min_price → Press Enter
2. Success: min_price input turns green ✅
3. Validation runs: Min exceeds price → Both inputs turn red ❌
4. Result: Both inputs turn red (validation overrides success)

---

## Comparison with V1

| Feature | V1 | V2 | Status |
|---------|----|----|--------|
| **8% Formula** | ✅ `min_val*1.08 < price_val` | ✅ `minVal * 1.08 < priceVal` | ✅ Match |
| **Min > Price Check** | ✅ `min_val > price_val` | ✅ `minVal > priceVal` | ✅ Match |
| **Called After Update** | ✅ Yes | ✅ Yes | ✅ Match |
| **Red on Invalid** | ✅ Both inputs | ✅ Both inputs | ✅ Match |
| **Green on Valid** | ✅ Removes red only | ✅ Removes red only | ✅ Match |

---

## Testing Checklist

- [ ] Change min_price to valid value → Press Enter → min_price turns green
- [ ] Change price to valid value → Press Enter → price turns green
- [ ] Change price to exceed min by >8% → Press Enter → Both inputs turn red
- [ ] Change min_price to exceed price → Press Enter → Both inputs turn red
- [ ] Change price to valid (within 8%) → Press Enter → Both inputs green (no red)
- [ ] Validation runs after handler limits update
- [ ] Validation only runs on Enter key (not blur/change)

---

## Files Modified

1. ✅ `public/assets/v2/listing/js/listing.js`
   - Line ~1637: Added checkMinPriceDiff call after price update
   - Line ~1706: Added checkMinPriceDiff call after handler limits update

2. ✅ `public/assets/v2/listing/js/price-validation.js`
   - Lines 13-38: Updated checkMinPriceDiff to match V1 behavior
   - Preserves success green, only adds/removes red for validation

---

## Result

✅ **8% validation formula is now active in V2**
- Matches V1 behavior exactly
- Validates on Enter key press
- Shows red if invalid (price exceeds min by >8% or min exceeds price)
- Preserves success green if validation passes

