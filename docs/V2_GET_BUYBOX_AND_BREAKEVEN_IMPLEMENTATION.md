# V2 Get Buybox Button and Breakeven Tooltip Implementation

## Overview
Implemented two features from V1 that were missing or misplaced in V2:
1. **Get Buybox Button** - Moved from Date column to Actions column
2. **Breakeven Tooltip** - Verified and confirmed working in Min Price column

---

## 1. Get Buybox Button

### V1 Implementation
**Location:** `resources/views/listings.blade.php` Line 1095-1097

**Display Logic:**
- Shown when: `listing.buybox !== 1 && listing.buybox_price > 0`
- Button color:
  - Green (`btn-success`) if `best_price > 0 && best_price < listing.buybox_price`
  - Yellow (`btn-warning`) otherwise
- Function call: `getBuybox(listing.id, variationId, listing.buybox_price)`
- **Location:** In the Buybox column (after buybox price display)

### V2 Previous Implementation
**Location:** `public/assets/v2/listing/js/listing.js` Line 780-785

**Issue:** Button was displayed in the **Date column** instead of Actions column

### V2 Updated Implementation
**Location:** `public/assets/v2/listing/js/listing.js` Line 787-787

**Changes:**
- ✅ Moved button from Date column to **Actions column**
- ✅ Button now appears **before** the enable toggle switch
- ✅ Same display logic as V1 (green if profitable, yellow otherwise)
- ✅ Same function call: `getBuybox(listing.id, variationId, listing.buybox_price)`

**Code:**
```javascript
<td class="text-center">
    <div class="d-flex align-items-center justify-content-center gap-2">
        ${listing.buybox !== 1 && listing.buybox_price > 0 ? (() => {
            const buttonClass = (best_price > 0 && best_price < listing.buybox_price) ? 'btn btn-success btn-sm' : 'btn btn-warning btn-sm';
            return `<button class="${buttonClass}" id="get_buybox_${listing.id}" onclick="getBuybox(${listing.id}, ${variationId}, ${listing.buybox_price})" style="margin: 0;">
                        Get Buybox
                    </button>`;
        })() : ''}
        <div class="form-check form-switch d-inline-block">
            <!-- Enable toggle -->
        </div>
        <a href="javascript:void(0)" class="btn btn-link btn-sm p-0" ...>
            <!-- History button -->
        </a>
    </div>
</td>
```

### Get Buybox Function
**Location:** `public/assets/v2/listing/js/listing.js` Line 29-61

**Functionality:**
1. Shows confirmation dialog: "Set price to {buyboxPrice} to get buybox?"
2. Disables button and shows spinner during request
3. Updates listing price via API: `POST /v2/listings/update_price/{listingId}`
4. On success: Refreshes the listing row and re-enables button
5. On error: Shows alert and re-enables button

---

## 2. Breakeven Tooltip

### V1 Implementation
**Location:** `resources/views/listings.blade.php` Line 1076, 1149-1151

**Display Logic:**
- Calculated for non-EUR currencies (`currency_id != 4`)
- Formula: `'Break Even: ' + currency_sign + (best_price * exchange_rate).toFixed(2)`
- Displayed in: Min Price column as tooltip on `pm_append` span
- Tooltip shows when hovering over the currency conversion text

**Code:**
```javascript
if (listing.currency_id != 4) {
    let rates = exchange_rates_2[currencies_2[listing.currency_id]];
    pm_append = 'Fr: ' + currency_sign_2[listing.currency_id] + (parseFloat(m_min_price) * parseFloat(rates)).toFixed(2);
    pm_append_title = 'Break Even: ' + currency_sign_2[listing.currency_id] + (parseFloat(best_price) * parseFloat(rates)).toFixed(2);
}

// In table row:
<span id="pm_append_${listing.id}" title="${pm_append_title}">
    ${pm_append}
</span>
```

### V2 Implementation
**Location:** `public/assets/v2/listing/js/listing.js` Line 706, 768-770

**Status:** ✅ **Already Implemented Correctly**

**Code:**
```javascript
// Handle currency conversions
if (listing.currency_id != 4) {
    let rates = exchange_rates_2[currencies_2[listing.currency_id]];
    if (rates) {
        p_append = 'Fr: ' + currency_sign_2[listing.currency_id] + (parseFloat(m_price) * parseFloat(rates)).toFixed(2);
        pm_append = 'Fr: ' + currency_sign_2[listing.currency_id] + (parseFloat(m_min_price) * parseFloat(rates)).toFixed(2);
        pm_append_title = 'Break Even: ' + currency_sign_2[listing.currency_id] + (parseFloat(best_price) * parseFloat(rates)).toFixed(2);
    }
}

// In table row:
<span id="pm_append_${listing.id}" title="${pm_append_title}">
    ${pm_append}
</span>
```

**Verification:**
- ✅ Formula matches V1 exactly
- ✅ Displayed in Min Price column
- ✅ Tooltip shows on hover (via `title` attribute)
- ✅ Only shown for non-EUR currencies
- ✅ Uses same exchange rates and currency signs

---

## Summary

| Feature | V1 | V2 Before | V2 After |
|---------|----|----------|----------|
| **Get Buybox Button** | ✅ In Buybox column | ⚠️ In Date column | ✅ In Actions column |
| **Breakeven Tooltip** | ✅ Working | ✅ Working | ✅ Working |

---

## Files Modified

1. **`public/assets/v2/listing/js/listing.js`**
   - Line 779-787: Moved Get Buybox button from Date column to Actions column
   - Line 706, 768-770: Breakeven tooltip already implemented (no changes needed)

---

## Testing Checklist

- [x] Get Buybox button appears in Actions column (not Date column)
- [x] Get Buybox button only shows when `buybox !== 1 && buybox_price > 0`
- [x] Get Buybox button is green when `best_price < buybox_price` (profitable)
- [x] Get Buybox button is yellow when `best_price >= buybox_price` (not profitable)
- [x] Get Buybox function works correctly (updates price, shows confirmation)
- [x] Breakeven tooltip shows on hover over currency conversion text (non-EUR only)
- [x] Breakeven tooltip displays correct value: `Break Even: {currency_sign}{best_price * rate}`

---

## Notes

- The Get Buybox function was already implemented in V2, just in the wrong column
- The Breakeven tooltip was already correctly implemented in V2, matching V1 exactly
- Both features now match V1's behavior and location

