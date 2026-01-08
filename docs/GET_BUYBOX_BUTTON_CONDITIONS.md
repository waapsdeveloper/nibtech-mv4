# Get Buybox Button Display Conditions

## Condition

The "Get Buybox" button is displayed only when **BOTH** conditions are true:

```javascript
listing.buybox !== 1 && listing.buybox_price > 0
```

## Breakdown

### Condition 1: `listing.buybox !== 1`
- **Meaning:** The listing does NOT currently have buybox
- **Values:**
  - `buybox === 1` → Listing HAS buybox → Button **HIDDEN** ✅
  - `buybox === 0` or `buybox === null` → Listing does NOT have buybox → Button **SHOWN** (if condition 2 is met)
  - `buybox === 2` or other values → Listing does NOT have buybox → Button **SHOWN** (if condition 2 is met)

### Condition 2: `listing.buybox_price > 0`
- **Meaning:** There is a valid buybox price available
- **Values:**
  - `buybox_price > 0` → Valid price exists → Button **SHOWN** (if condition 1 is met)
  - `buybox_price === 0` or `buybox_price === null` → No price data → Button **HIDDEN** ❌

## Why This Logic?

1. **If listing already has buybox (`buybox === 1`):**
   - No need to "get" buybox - you already have it!
   - Button is hidden

2. **If `buybox_price === 0` or `null`:**
   - No price data available to set
   - Cannot determine what price to set
   - Button is hidden

3. **If listing doesn't have buybox AND has a valid price:**
   - You can potentially get buybox by setting price to `buybox_price`
   - Button is shown

## Button Colors

When the button IS shown, the color indicates profitability:

- **Green (`btn-success`):** When `best_price > 0 && best_price < buybox_price`
  - Meaning: Your best price is lower than buybox price → Profitable to get buybox
  
- **Yellow (`btn-warning`):** Otherwise
  - Meaning: Your best price is equal to or higher than buybox price → May not be profitable

## Examples

| buybox | buybox_price | Button Shown? | Reason |
|--------|--------------|---------------|--------|
| 1 | 100 | ❌ No | Already has buybox |
| 0 | 100 | ✅ Yes | Doesn't have buybox, price available |
| null | 100 | ✅ Yes | Doesn't have buybox, price available |
| 0 | 0 | ❌ No | No price data |
| 0 | null | ❌ No | No price data |
| 1 | 0 | ❌ No | Already has buybox (and no price) |

## How to Check Your Listings

To see why buttons aren't showing, check your listing data:

```sql
-- Check listings without buybox and with valid prices
SELECT id, buybox, buybox_price, price, min_price 
FROM listings 
WHERE buybox != 1 AND buybox_price > 0;

-- Check listings that should show button but don't
SELECT id, buybox, buybox_price 
FROM listings 
WHERE buybox = 1 OR buybox_price = 0 OR buybox_price IS NULL;
```

## Location in Code

**V2:** `public/assets/v2/listing/js/listing.js` Line 782-787

```javascript
${listing.buybox !== 1 && listing.buybox_price > 0 ? (() => {
    const buttonClass = (best_price > 0 && best_price < listing.buybox_price) ? 'btn btn-success btn-sm' : 'btn btn-warning btn-sm';
    return `<button class="${buttonClass}" id="get_buybox_${listing.id}" onclick="getBuybox(${listing.id}, ${variationId}, ${listing.buybox_price})" style="margin: 0;">
                Get Buybox
            </button>`;
})() : ''}
```

**V1:** `resources/views/listings.blade.php` Line 1094-1098

```javascript
if (listing.buybox !== 1 && listing.buybox_price > 0) {
    buybox_button = `<button ...>Get Buybox</button>`;
}
```

