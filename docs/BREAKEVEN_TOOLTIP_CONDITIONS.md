# Breakeven Tooltip Conditions Analysis

## Current Implementation

### Location
**File:** `public/assets/v2/listing/js/listing.js`  
**Lines:** 700-707 (calculation), 768-770 (display)

### Conditions for Tooltip to Show

The breakeven tooltip is displayed when **ALL** of the following conditions are met:

1. **Non-EUR Currency:** `listing.currency_id != 4`
   - Tooltip only shows for non-EUR currencies (GBP, USD, etc.)
   - EUR listings (`currency_id == 4`) do NOT show the tooltip

2. **Exchange Rate Available:** `rates` exists and is valid
   - Must have exchange rate data: `exchange_rates_2[currencies_2[listing.currency_id]]`
   - If rate is missing, tooltip won't be set

3. **Best Price Calculated:** `best_price` must be available
   - Best price is calculated from stocks: `(average_cost + 20) / 0.88`
   - If stocks haven't loaded yet, `best_price` will be 0 or empty
   - Tooltip will show "Break Even: £0.00" if best_price is 0

4. **pm_append Span Exists:** The span must be rendered in the table
   - Span ID: `pm_append_${listing.id}`
   - If span is empty or not rendered, tooltip won't be visible

### Code Flow

```javascript
// Step 1: Initialize variables
let pm_append = '';
let pm_append_title = '';

// Step 2: Check conditions
if (listing.currency_id != 4) {  // Non-EUR only
    let rates = exchange_rates_2[currencies_2[listing.currency_id]];
    if (rates) {  // Exchange rate must exist
        // Calculate currency conversion
        pm_append = 'Fr: ' + currency_sign_2[listing.currency_id] + (parseFloat(m_min_price) * parseFloat(rates)).toFixed(2);
        
        // Calculate breakeven tooltip
        pm_append_title = 'Break Even: ' + currency_sign_2[listing.currency_id] + (parseFloat(best_price) * parseFloat(rates)).toFixed(2);
    }
}

// Step 3: Display in table
<span id="pm_append_${listing.id}" title="${pm_append_title}">
    ${pm_append}
</span>
```

### Why Tooltip Might Not Show

1. **EUR Listings:** If `currency_id == 4`, tooltip is intentionally not set (matches V1 behavior)

2. **Missing Exchange Rate:** If `exchange_rates_2[currencies_2[listing.currency_id]]` is undefined or null

3. **Best Price Not Loaded:** 
   - Best price is loaded asynchronously via `loadStocksForBestPrice()`
   - If stocks haven't loaded, `best_price` will be 0
   - Tooltip will show "Break Even: £0.00" (not very useful)

4. **Empty pm_append:** 
   - If `pm_append` is empty, the span might not be visible
   - Hovering over empty space won't show tooltip

5. **Timing Issue:**
   - Best price is calculated AFTER stocks load
   - Tooltip is set when table row is rendered
   - If stocks load later, tooltip won't update

### V1 vs V2 Comparison

**V1:** `resources/views/listings.blade.php` Line 1076
```javascript
if (listing.currency_id != 4) {
    let rates = exchange_rates_2[currencies_2[listing.currency_id]];
    pm_append = 'Fr: '+currency_sign_2[listing.currency_id]+(parseFloat(m_min_price)*parseFloat(rates)).toFixed(2);
    pm_append_title = 'Break Even: '+currency_sign_2[listing.currency_id]+(parseFloat(best_price)*parseFloat(rates)).toFixed(2);
}
```

**V2:** `public/assets/v2/listing/js/listing.js` Line 706
```javascript
if (listing.currency_id != 4) {
    let rates = exchange_rates_2[currencies_2[listing.currency_id]];
    if (rates) {
        pm_append = 'Fr: ' + currency_sign_2[listing.currency_id] + (parseFloat(m_min_price) * parseFloat(rates)).toFixed(2);
        pm_append_title = 'Break Even: ' + currency_sign_2[listing.currency_id] + (parseFloat(best_price) * parseFloat(rates)).toFixed(2);
    }
}
```

**Difference:** V2 has an extra `if (rates)` check, which is good for safety but shouldn't prevent tooltip from showing if rates exist.

## Recommendations

### To Debug Why Tooltip Isn't Showing:

1. **Check Currency ID:**
   ```javascript
   console.log('Currency ID:', listing.currency_id);
   // Should be != 4 for tooltip to show
   ```

2. **Check Exchange Rates:**
   ```javascript
   console.log('Exchange rates:', exchange_rates_2);
   console.log('Currency mapping:', currencies_2[listing.currency_id]);
   console.log('Rate for currency:', exchange_rates_2[currencies_2[listing.currency_id]]);
   ```

3. **Check Best Price:**
   ```javascript
   console.log('Best price:', best_price);
   // Should be > 0 for meaningful tooltip
   ```

4. **Check pm_append:**
   ```javascript
   console.log('pm_append:', pm_append);
   console.log('pm_append_title:', pm_append_title);
   // Both should have values for non-EUR listings
   ```

5. **Inspect HTML:**
   - Check if `<span id="pm_append_${listing.id}">` exists in DOM
   - Check if `title` attribute is set
   - Hover over the span to see if tooltip appears

### Potential Fixes:

1. **Update Tooltip After Best Price Loads:**
   - After `loadStocksForBestPrice()` completes, update the tooltip
   - Use jQuery: `$('#pm_append_' + listingId).attr('title', newTooltipText)`

2. **Show Tooltip Even for EUR (if needed):**
   - Remove `currency_id != 4` condition
   - But this would change V1 behavior

3. **Ensure pm_append is Always Visible:**
   - Add a non-breaking space if empty: `${pm_append || '&nbsp;'}`

