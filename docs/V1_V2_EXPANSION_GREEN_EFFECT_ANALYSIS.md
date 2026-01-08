# V1 vs V2 Listing - Expansion & Green Color Effect Analysis

## Overview
Analysis of how parent card expansion and green color feedback work in V1 vs V2 listing pages.

---

## Part 1: Parent Card Expansion

### V1 Listing (`resources/views/listings.blade.php`)

**Expansion Mechanism:**
- **Type**: Bootstrap Collapse (JavaScript-based)
- **Trigger**: Button with `data-bs-toggle="collapse"` and `onClick` handler
- **Target**: `#details_${variation.id}` div

**Implementation:**
```blade
<!-- Line 1565 -->
<button class="btn btn-link" 
        type="button" 
        data-bs-toggle="collapse" 
        data-bs-target="#details_${variation.id}" 
        aria-expanded="false" 
        aria-controls="details_${variation.id}" 
        onClick="getVariationDetails(${variation.id}, ${eurToGbp}, ${m_min_price}, ${m_price})">
    <i class="fas fa-chevron-down"></i>
</button>

<!-- Line 1626 -->
<div class="card-body p-2 collapse multi_collapse" id="details_${variation.id}">
    <!-- Stocks and Listings tables -->
</div>
```

**Key Features:**
1. **Bootstrap Collapse**: Uses Bootstrap's collapse component
2. **JavaScript Handler**: `onClick="getVariationDetails(...)"` loads data when expanded
3. **Class**: `multi_collapse` - allows "Toggle All" functionality
4. **Data Loading**: Loads listings and stocks via AJAX when expanded

**Function Called on Expand:**
```javascript
// Line 1206
function getVariationDetails(variationId, eurToGbp, m_min_price, m_price, check = 0) {
    getListings(variationId, eurToGbp, m_min_price, m_price, check);
}
```

**Toggle All Feature:**
```blade
<!-- Line 82 -->
<button class="btn btn-link" 
        type="button" 
        data-bs-toggle="collapse" 
        data-bs-target=".multi_collapse" 
        id="open_all_variations">
    Toggle All
</button>
```

---

### V2 Listing (`resources/views/v2/listing/partials/marketplace-bar.blade.php`)

**Expansion Mechanism:**
- **Type**: Bootstrap Collapse (JavaScript-based)
- **Trigger**: Button with `data-bs-toggle="collapse"`
- **Target**: `#marketplace_toggle_{{ $variationId }}_{{ $marketplaceId }}` div

**Implementation:**
```blade
<!-- Line 143 -->
<button class="btn btn-primary btn-sm flex-shrink-0" 
        type="button" 
        data-bs-toggle="collapse" 
        data-bs-target="#marketplace_toggle_{{ $variationId }}_{{ $marketplaceId }}" 
        aria-expanded="false" 
        aria-controls="marketplace_toggle_{{ $variationId }}_{{ $marketplaceId }}">
    <i class="fas fa-chevron-down me-1"></i>
    <span>Listings</span>
</button>

<!-- Line 180 -->
<div class="marketplace-toggle-content collapse" id="marketplace_toggle_{{ $variationId }}_{{ $marketplaceId }}">
    <div class="border-top marketplace-tables-container" data-loaded="false">
        <!-- Loading state -->
    </div>
</div>
```

**Key Features:**
1. **Bootstrap Collapse**: Uses Bootstrap's collapse component
2. **Event-Driven**: Uses Bootstrap collapse event listener
3. **Lazy Loading**: Loads data only when expanded (via `show.bs.collapse` event)
4. **Per Marketplace**: Each marketplace has its own toggle

**Event Listener:**
```javascript
// public/assets/v2/listing/js/listing.js - Line 619
$(document).on('show.bs.collapse', '.marketplace-toggle-content', function() {
    const toggleElement = $(this);
    const container = toggleElement.find('.marketplace-tables-container');
    
    // Check if already loaded
    if (container.data('loaded') === true) {
        return;
    }
    
    // Extract variationId and marketplaceId from toggle ID
    const toggleId = toggleElement.attr('id');
    const matches = toggleId.match(/marketplace_toggle_(\d+)_(\d+)/);
    if (!matches) return;
    
    const variationId = parseInt(matches[1]);
    const marketplaceId = parseInt(matches[2]);
    
    // Load listings for this marketplace
    loadMarketplaceTables(variationId, marketplaceId);
});
```

---

## Comparison: Expansion

| Feature | V1 | V2 |
|---------|----|----|
| **Mechanism** | Bootstrap Collapse | Bootstrap Collapse |
| **Trigger** | Button with `onClick` handler | Button with event listener |
| **Data Loading** | `onClick` calls `getVariationDetails()` | `show.bs.collapse` event calls `loadMarketplaceTables()` |
| **Scope** | Entire variation details (stocks + listings) | Per marketplace (listings only) |
| **Toggle All** | Yes (`.multi_collapse` class) | No (per marketplace) |
| **Lazy Loading** | Yes (loads on expand) | Yes (loads on expand) |
| **Loading State** | Shows loading in table | Shows loading spinner |

**Key Difference:**
- **V1**: Single expansion for entire variation (stocks + all listings)
- **V2**: Separate expansion per marketplace (only listings for that marketplace)

---

## Part 2: Green Color Effect on Input Changes

### V1 Listing - Green Color Effect

**Implementation:**
- **Method**: Adds `bg-green` class to input fields
- **Trigger**: After successful AJAX submission
- **Persistence**: Class stays until removed manually

**Code Locations:**

**1. Min Price Update (Line 439):**
```javascript
function submitForm2(event, listingId, marketplaceId, callback) {
    $.ajax({
        success: function(data) {
            $('#min_price_' + listingId).addClass('bg-green');
            checkMinPriceDiff(listingId);
        }
    });
}
```

**2. Price Update (Line 477):**
```javascript
function submitForm3(event, listingId, marketplaceId, callback) {
    $.ajax({
        success: function(data) {
            $('#price_' + listingId).addClass('bg-green');
            checkMinPriceDiff(listingId);
        }
    });
}
```

**3. Handler Limits Update (Lines 607-608):**
```javascript
function submitForm5(event, listingId, marketplaceId, callback) {
    $.ajax({
        success: function(data) {
            $('#min_price_limit_' + listingId).addClass('bg-green');
            $('#price_limit_' + listingId).addClass('bg-green');
            checkMinPriceDiff(listingId);
        }
    });
}
```

**4. Price Validation (Lines 404-419):**
```javascript
function checkMinPriceDiff(listingId){
    let min = $('#min_price_' + listingId);
    let price = $('#price_' + listingId);
    let min_val = min.val();
    let price_val = price.val();
    
    if (min_val > price_val || min_val*1.08 < price_val) {
        min.addClass('bg-red');
        min.removeClass('bg-green');
        price.addClass('bg-red');
        price.removeClass('bg-green');
    } else {
        min.removeClass('bg-red');
        price.removeClass('bg-red');
    }
}
```

**Characteristics:**
- ✅ Applied to **individual input fields**
- ✅ Class **persists** (doesn't auto-remove)
- ✅ Also uses **validation** (red for invalid, green for valid)
- ✅ Applied to: `min_price_`, `price_`, `min_price_limit_`, `price_limit_`, `target_`, `percent_`

---

### V2 Listing - Green Color Effect

**Implementation:**
- **Method**: Adds `table-success` class to entire table row
- **Trigger**: After successful AJAX submission
- **Persistence**: Auto-removed after 2 seconds

**Code Locations:**

**1. Price Update (Lines 1638-1641):**
```javascript
$(document).on('submit', '[id^="change_min_price_"], [id^="change_price_"]', function(e) {
    $.ajax({
        success: function(response) {
            if (response.success) {
                const row = input.closest('tr');
                row.addClass('table-success');
                setTimeout(function() {
                    row.removeClass('table-success');
                }, 2000);
            }
        }
    });
});
```

**2. Handler Limits Update (Lines 1694-1697):**
```javascript
$(document).on('submit', '[id^="change_limit_"]', function(e) {
    $.ajax({
        success: function(response) {
            if (response.success) {
                const row = minLimitInput.closest('tr');
                row.addClass('table-success');
                setTimeout(function() {
                    row.removeClass('table-success');
                }, 2000);
            }
        }
    });
});
```

**3. Price Validation (public/assets/v2/listing/js/price-validation.js):**
```javascript
window.checkMinPriceDiff = function(listingId) {
    const minPriceInput = document.getElementById('min_price_' + listingId);
    const priceInput = document.getElementById('price_' + listingId);
    
    // Remove previous classes
    minPriceInput.classList.remove('bg-red', 'bg-green');
    priceInput.classList.remove('bg-red', 'bg-green');
    
    // Validation logic
    if (minVal > priceVal || (minVal > 0 && priceVal > 0 && minVal * 1.08 < priceVal)) {
        minPriceInput.classList.add('bg-red');
        priceInput.classList.add('bg-red');
    } else if (minVal > 0 && priceVal > 0) {
        minPriceInput.classList.add('bg-green');
        priceInput.classList.add('bg-green');
    }
};
```

**Characteristics:**
- ✅ Applied to **entire table row** (`table-success`)
- ✅ **Auto-removed** after 2 seconds
- ✅ Also has **validation** (red/green on inputs via `checkMinPriceDiff`)
- ✅ More **subtle** feedback (row highlight vs input highlight)

---

## Comparison: Green Color Effect

| Feature | V1 | V2 |
|---------|----|----|
| **Target Element** | Individual input fields | Entire table row |
| **CSS Class** | `bg-green` | `table-success` |
| **Persistence** | Permanent (until manually removed) | Temporary (2 seconds) |
| **Visual Feedback** | Input background turns green | Row background turns green |
| **Validation** | Yes (`checkMinPriceDiff` - red/green) | Yes (`checkMinPriceDiff` - red/green) |
| **Scope** | Single input | Entire row (all inputs in row) |
| **User Experience** | More persistent feedback | Subtle, temporary feedback |

**Key Differences:**

1. **V1 Approach:**
   - Highlights **individual input** that was changed
   - Green color **stays** until next change
   - More **obvious** feedback
   - User can see which specific field was updated

2. **V2 Approach:**
   - Highlights **entire row** containing the changed input
   - Green color **fades** after 2 seconds
   - More **subtle** feedback
   - User sees the row was updated, but effect is temporary

---

## Detailed Code Analysis

### V1 Green Effect Flow

```
User changes input → Press Enter/Submit
    ↓
AJAX call to update_price/update_limit
    ↓
Success response
    ↓
addClass('bg-green') on input field
    ↓
checkMinPriceDiff() validates
    ↓
If valid: bg-green stays
If invalid: bg-red replaces bg-green
```

**Example - Min Price Update:**
```javascript
// Line 421-457
function submitForm2(event, listingId, marketplaceId, callback) {
    $.ajax({
        url: "{{ url('listing/update_price') }}/" + listingId,
        success: function(data) {
            $('#min_price_' + listingId).addClass('bg-green'); // ✅ Green effect
            checkMinPriceDiff(listingId); // ✅ Validation
        }
    });
}
```

---

### V2 Green Effect Flow

```
User changes input → Press Enter/Submit
    ↓
AJAX call to update_price/update_limit
    ↓
Success response
    ↓
addClass('table-success') on row
    ↓
setTimeout removes class after 2 seconds
    ↓
(Optional) checkMinPriceDiff() for validation
```

**Example - Price Update:**
```javascript
// Line 1597-1654
$(document).on('submit', '[id^="change_min_price_"], [id^="change_price_"]', function(e) {
    $.ajax({
        success: function(response) {
            if (response.success) {
                const row = input.closest('tr');
                row.addClass('table-success'); // ✅ Green effect on row
                setTimeout(function() {
                    row.removeClass('table-success'); // ✅ Auto-remove after 2s
                }, 2000);
            }
        }
    });
});
```

---

## Visual Comparison

### V1 Green Effect
```
┌─────────────────────────────────┐
│ [Input Field] ← bg-green class  │ ← Individual input highlighted
│ (Green background)               │
└─────────────────────────────────┘
```

### V2 Green Effect
```
┌─────────────────────────────────┐
│ [Row with table-success class]  │ ← Entire row highlighted
│ [Input 1] [Input 2] [Input 3]   │
│ (Green row background)           │
└─────────────────────────────────┘
     ↓ (after 2 seconds)
┌─────────────────────────────────┐
│ [Row - normal background]        │ ← Returns to normal
│ [Input 1] [Input 2] [Input 3]   │
└─────────────────────────────────┘
```

---

## Recommendations

### For Consistency

**Option 1: Keep V2 Approach (Recommended)**
- More modern (temporary feedback)
- Less visual clutter
- Better UX (doesn't persist)

**Option 2: Hybrid Approach**
- Row highlight (temporary) for success feedback
- Input highlight (persistent) for validation state
- Best of both worlds

**Option 3: Match V1**
- If users prefer persistent feedback
- More obvious which fields were changed
- Easier to see update history

---

## Files Referenced

**V1:**
- `resources/views/listings.blade.php` - Lines 404-640, 1565, 1626

**V2:**
- `resources/views/v2/listing/partials/marketplace-bar.blade.php` - Lines 143, 180
- `public/assets/v2/listing/js/listing.js` - Lines 619-643, 1638-1641, 1694-1697
- `public/assets/v2/listing/js/price-validation.js` - Lines 13-38

---

## Summary

**Expansion:**
- Both use Bootstrap Collapse
- V1: Single expansion for entire variation
- V2: Separate expansion per marketplace

**Green Effect:**
- V1: Individual input fields, permanent
- V2: Entire table row, temporary (2 seconds)
- Both have validation (red/green based on price rules)

