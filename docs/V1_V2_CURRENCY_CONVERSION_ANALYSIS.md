# V1 vs V2 Currency Conversion Analysis for Min/Max Prices

## Overview
Analysis of how currency conversion is handled for `min_price` and `price` (max price) when listing currency is other than EUR.

---

## Key Concepts

### Base Prices (EUR)
- **`m_price`**: Minimum price across all EUR listings for a variation
- **`m_min_price`**: Minimum min_price across all EUR listings for a variation
- These are calculated from EUR listings only (currency_id = 4, country = 73)

### Conversion Logic
- For non-EUR listings, the EUR base prices (`m_price`, `m_min_price`) are converted to the listing's currency
- Conversion formula: `EUR_price * exchange_rate = Local_currency_price`

---

## V1 Implementation

### 1. Calculation of Base Prices (m_price, m_min_price)

**Location:** `resources/views/listings.blade.php` Lines 540-545, 559-565, 577-583, 725-731

**Code:**
```javascript
let m_min_price = 0;
let m_price = 0;
if (window.eur_listings[variationId] && window.eur_listings[variationId].length > 0) {
    m_min_price = Math.min(...window.eur_listings[variationId].map(l => l.min_price || 999999));
    m_price = Math.min(...window.eur_listings[variationId].map(l => l.price || 999999));
}
```

**Logic:**
- Collects all EUR listings for the variation in `window.eur_listings[variationId]` array
- Finds minimum `min_price` across all EUR listings → `m_min_price`
- Finds minimum `price` across all EUR listings → `m_price`
- Uses `999999` as fallback if price is missing

**When Calculated:**
- After price updates (to refresh conversion values)
- When loading listings via AJAX
- When expanding variation details

---

### 2. Currency Conversion for Non-EUR Listings

**Location:** `resources/views/listings.blade.php` Lines 1071-1076, 1379-1384

**Code:**
```javascript
if (listing.currency_id != 4) {  // 4 = EUR
    let rates = exchange_rates_2[currencies_2[listing.currency_id]];
    p_append = 'Fr: '+currency_sign_2[listing.currency_id]+(parseFloat(m_price)*parseFloat(rates)).toFixed(2);
    pm_append = 'Fr: '+currency_sign_2[listing.currency_id]+(parseFloat(m_min_price)*parseFloat(rates)).toFixed(2);
    pm_append_title = 'Break Even: '+currency_sign_2[listing.currency_id]+(parseFloat(best_price)*parseFloat(rates)).toFixed(2);
}
```

**Formula:**
- **Price Conversion:** `m_price * exchange_rate`
- **Min Price Conversion:** `m_min_price * exchange_rate`
- **Best Price Conversion:** `best_price * exchange_rate` (for tooltip)

**Display:**
- `p_append`: Shows converted price next to price input field
- `pm_append`: Shows converted min_price next to min_price input field
- Format: `"Fr: £XX.XX"` (e.g., "Fr: £45.50")

**Example:**
- EUR base price: `m_price = 50.00 EUR`
- Exchange rate for GBP: `rates['GBP'] = 0.85`
- Converted price: `50.00 * 0.85 = 42.50 GBP`
- Display: `"Fr: £42.50"`

---

### 3. Data Sources

**Exchange Rates:**
```php
// app/Http/Controllers/ListingController.php
$exchange_rates = ExchangeRate::pluck('rate','target_currency');
// Example: { 'GBP': 0.85, 'USD': 1.10, 'SEK': 11.50 }
```

**Currency Mapping:**
```php
$currencies = Currency_model::pluck('code','id');
// Example: { 4: 'EUR', 5: 'GBP', 6: 'USD' }
```

**Currency Signs:**
```php
$currency_sign = Currency_model::pluck('sign','id');
// Example: { 4: '€', 5: '£', 6: '$' }
```

---

## V2 Implementation

### 1. Calculation of Base Prices (m_price, m_min_price)

**Location:** `public/assets/v2/listing/js/listing.js` Lines 665-676

**Code:**
```javascript
// Calculate min prices from listings with country 73 (EUR)
const eurListings = data.listings.filter(listing => {
    const country = listing.country_id || (listing.country && countries[listing.country]);
    return country && (country.id === 73 || listing.country === 73);
});

if (eurListings.length > 0) {
    const minPrices = eurListings.map(l => parseFloat(l.min_price) || 0).filter(p => p > 0);
    const prices = eurListings.map(l => parseFloat(l.price) || 0).filter(p => p > 0);
    m_min_price = minPrices.length > 0 ? Math.min(...minPrices) : 0;
    m_price = prices.length > 0 ? Math.min(...prices) : 0;
}
```

**Logic:**
- Filters listings by country ID 73 (EUR country)
- Finds minimum `min_price` across EUR listings → `m_min_price`
- Finds minimum `price` across EUR listings → `m_price`
- Uses `0` as fallback if no prices found

**When Calculated:**
- When loading marketplace tables via `loadMarketplaceTables()`
- Calculated per marketplace (not globally per variation)

**Difference from V1:**
- V1: Uses `window.eur_listings` array (global per variation)
- V2: Filters from `data.listings` directly (per marketplace load)

---

### 2. Currency Conversion for Non-EUR Listings

**Location:** `public/assets/v2/listing/js/listing.js` Lines 702-708

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
```

**Formula:**
- **Price Conversion:** `m_price * exchange_rate` ✅ Same as V1
- **Min Price Conversion:** `m_min_price * exchange_rate` ✅ Same as V1
- **Best Price Conversion:** `best_price * exchange_rate` ✅ Same as V1

**Display:**
- `p_append`: Shows converted price next to price input field (Line 778)
- `pm_append`: Shows converted min_price next to min_price input field (Line 770)
- Format: `"Fr: £XX.XX"` ✅ Same as V1

**Example:**
- EUR base price: `m_price = 50.00 EUR`
- Exchange rate for GBP: `rates['GBP'] = 0.85`
- Converted price: `50.00 * 0.85 = 42.50 GBP`
- Display: `"Fr: £42.50"` ✅ Same as V1

---

### 3. Data Sources

**Exchange Rates:**
```php
// app/Http/Controllers/V2/ListingController.php Line 89
$data['exchange_rates'] = ExchangeRate::pluck('rate','target_currency');
```

**Passed to View:**
```javascript
// resources/views/v2/listing/listing.blade.php Line 79
window.exchange_rates = {!! json_encode($exchange_rates ?? []) !!};
```

**Currency Mapping:**
```php
// app/Http/Controllers/V2/ListingController.php
$data['currencies'] = Currency_model::pluck('code','id');
```

**Passed to View:**
```javascript
// resources/views/v2/listing/listing.blade.php Line 80
window.currencies = {!! json_encode($currencies ?? []) !!};
```

**Currency Signs:**
```php
// app/Http/Controllers/V2/ListingController.php Line 91
$data['currency_sign'] = Currency_model::pluck('sign','id');
```

**Passed to View:**
```javascript
// resources/views/v2/listing/listing.blade.php Line 81
window.currency_sign = {!! json_encode($currency_sign ?? []) !!};
```

---

## Comparison: V1 vs V2

| Feature | V1 | V2 | Status |
|---------|----|----|--------|
| **Base Price Calculation** | From `window.eur_listings` array | From filtered `data.listings` | ⚠️ **Different Method** |
| **EUR Filter** | `currency_id == 4` | `country.id == 73` | ⚠️ **Different Filter** |
| **Conversion Formula** | `m_price * rate` | `m_price * rate` | ✅ **Match** |
| **Display Format** | `"Fr: £XX.XX"` | `"Fr: £XX.XX"` | ✅ **Match** |
| **Display Location** | Next to input fields | Next to input fields | ✅ **Match** |
| **Exchange Rate Source** | `ExchangeRate` table | `ExchangeRate` table | ✅ **Match** |
| **Currency Sign Source** | `Currency_model` | `Currency_model` | ✅ **Match** |

---

## Key Differences

### 1. Base Price Calculation Method

**V1:**
- Uses global `window.eur_listings[variationId]` array
- Array is populated when EUR listings are loaded
- Calculated once per variation (shared across all marketplaces)

**V2:**
- Filters from `data.listings` directly in `loadMarketplaceTables()`
- Calculated per marketplace load (not shared globally)
- Uses country ID 73 instead of currency_id 4

**Impact:**
- V1: Base prices are consistent across all marketplaces for a variation
- V2: Base prices are recalculated per marketplace (may differ if EUR listings vary by marketplace)

---

### 2. EUR Listing Identification

**V1:**
- Uses `currency_id == 4` (currency-based)
- More direct currency identification

**V2:**
- Uses `country.id == 73` (country-based)
- Assumes country 73 = EUR (may not always be true)

**Potential Issue:**
- If a listing has `currency_id != 4` but `country == 73`, V2 might include it incorrectly
- If a listing has `currency_id == 4` but `country != 73`, V2 might exclude it incorrectly

---

## Conversion Flow Diagram

### V1 Flow:
```
1. Load EUR listings → Store in window.eur_listings[variationId]
2. Calculate m_price = min(EUR prices)
3. Calculate m_min_price = min(EUR min_prices)
4. For each non-EUR listing:
   - Get exchange_rate for listing.currency_id
   - Convert: m_price * rate → p_append
   - Convert: m_min_price * rate → pm_append
   - Display: "Fr: £XX.XX"
```

### V2 Flow:
```
1. Load listings for marketplace → data.listings
2. Filter EUR listings (country == 73)
3. Calculate m_price = min(EUR prices)
4. Calculate m_min_price = min(EUR min_prices)
5. For each non-EUR listing:
   - Get exchange_rate for listing.currency_id
   - Convert: m_price * rate → p_append
   - Convert: m_min_price * rate → pm_append
   - Display: "Fr: £XX.XX"
```

---

## Example Calculation

### Scenario:
- Variation has 3 EUR listings:
  - Listing 1: min_price = 45.00, price = 50.00
  - Listing 2: min_price = 40.00, price = 48.00
  - Listing 3: min_price = 42.00, price = 52.00

### Base Prices:
- `m_min_price = min(45, 40, 42) = 40.00 EUR`
- `m_price = min(50, 48, 52) = 48.00 EUR`

### For GBP Listing (rate = 0.85):
- Converted min_price: `40.00 * 0.85 = 34.00 GBP`
- Converted price: `48.00 * 0.85 = 40.80 GBP`
- Display: `"Fr: £34.00"` (min_price) and `"Fr: £40.80"` (price)

### For USD Listing (rate = 1.10):
- Converted min_price: `40.00 * 1.10 = 44.00 USD`
- Converted price: `48.00 * 1.10 = 52.80 USD`
- Display: `"Fr: $44.00"` (min_price) and `"Fr: $52.80"` (price)

---

## Potential Issues

### Issue 1: V2 Uses Country Instead of Currency
- **Problem:** V2 filters by `country.id == 73` instead of `currency_id == 4`
- **Impact:** May include/exclude wrong listings if country and currency don't match
- **Recommendation:** Consider using `currency_id == 4` like V1 for consistency

### Issue 2: V2 Recalculates Per Marketplace
- **Problem:** Base prices are recalculated per marketplace, not globally
- **Impact:** Base prices may differ between marketplaces if EUR listings vary
- **Recommendation:** Consider using global `window.eur_listings` array like V1

---

## Recommendations

1. **Standardize EUR Identification:**
   - Use `currency_id == 4` in V2 (like V1) instead of `country.id == 73`
   - More accurate and consistent

2. **Use Global Base Prices:**
   - Consider using `window.eur_listings` array in V2 (like V1)
   - Ensures consistent base prices across all marketplaces

3. **Add Validation:**
   - Verify exchange rates exist before conversion
   - Handle missing rates gracefully (V2 already checks `if (rates)`)

---

## Summary

✅ **Conversion Formula:** Both V1 and V2 use the same formula: `EUR_price * exchange_rate`

✅ **Display Format:** Both show `"Fr: £XX.XX"` format

⚠️ **Base Price Calculation:** V1 uses global array, V2 calculates per marketplace

⚠️ **EUR Filter:** V1 uses `currency_id == 4`, V2 uses `country.id == 73`

**Overall:** Conversion logic is functionally the same, but implementation details differ. V2 may have edge cases with country vs currency filtering.

