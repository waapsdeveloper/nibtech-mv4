# V2 Listing - Real-time Stock Badge Implementation Plan

## Overview
Add a PostScript badge showing real-time stock from Backmarket API alongside the existing database stock number in the marketplace bar. This will be fetched dynamically when each variation loads.

---

## Requirements

### 1. Client Requirements
- **Display Location**: Marketplace bar (next to existing stock number)
- **Database Stock**: Already showing in marketplace bar (`<span id="stock_{{ $variationId }}_{{ $marketplaceId }}">`)
- **Real-time Badge**: PostScript badge showing Backmarket API stock (only for Backmarket marketplace, ID = 1)
- **Fetch Timing**: On each variation load (when variation card is rendered)
- **Update**: Real-time via AJAX call

### 2. Technical Requirements
- **API Endpoint**: Create V2 API endpoint (`v2/listings/get_updated_quantity/{id}`)
- **Service Layer**: Use `ListingDataService` to reduce controller dependency
- **Backend**: Follow V2 architecture patterns (service-based)
- **Frontend**: Use existing JavaScript structure in `listing.js`

---

## Current Implementation Analysis

### Existing Stock Display
**Location**: `resources/views/v2/listing/partials/marketplace-bar.blade.php` (Line 93)

```php
<span class="text-muted small">(<span id="stock_{{ $variationId }}_{{ $marketplaceId }}">{{ $currentStock }}</span>)</span>
```

**Current Stock Source**:
- From `marketplace_stock` table
- Shows `available_stock` (listed_stock - locked_stock)
- Falls back to `listed_stock` if available_stock is null

### Original V1 Implementation
**Location**: `resources/views/listings.blade.php`

- **Frontend Function**: `fetchUpdatedQuantity(variationId)` (Lines 966-986)
- **Backend Route**: `listing/get_updated_quantity/{id}` (routes/web.php:366)
- **Controller**: `ListingController@getUpdatedQuantity` (Lines 714-721)
- **Model Method**: `Variation_model@update_qty()` (Lines 183-205)
- **API Call**: `BackMarketAPIController@getOneListing()` (Lines 855-859)

---

## Implementation Plan

### Phase 1: Backend - Service Layer

#### 1.1 Extend ListingDataService
**File**: `app/Services/V2/ListingDataService.php`

**Add Method**:
```php
/**
 * Get updated stock quantity from Backmarket API for a variation
 * 
 * @param int $variationId
 * @return array ['quantity' => int, 'sku' => string, 'state' => int, 'updated' => bool]
 */
public function getBackmarketStockQuantity(int $variationId): array
{
    $variation = Variation_model::find($variationId);
    
    if (!$variation || !$variation->reference_id) {
        return [
            'quantity' => 0,
            'sku' => null,
            'state' => null,
            'updated' => false,
            'error' => 'Variation or reference_id not found'
        ];
    }
    
    try {
        $bm = new BackMarketAPIController();
        $apiResponse = $bm->getOneListing($variation->reference_id);
        
        if ($apiResponse && is_object($apiResponse)) {
            $quantity = isset($apiResponse->quantity) ? (int)$apiResponse->quantity : $variation->listed_stock ?? 0;
            $sku = isset($apiResponse->sku) ? $apiResponse->sku : $variation->sku;
            $state = isset($apiResponse->publication_state) ? $apiResponse->publication_state : $variation->state;
            
            // Update database (optional - can be done separately if needed)
            // Variation_model::where('id', $variationId)->update([
            //     'listed_stock' => $quantity,
            //     'sku' => $sku,
            //     'state' => $state
            // ]);
            
            return [
                'quantity' => $quantity,
                'sku' => $sku,
                'state' => $state,
                'updated' => true,
                'error' => null
            ];
        }
        
        return [
            'quantity' => $variation->listed_stock ?? 0,
            'sku' => $variation->sku,
            'state' => $variation->state,
            'updated' => false,
            'error' => 'Invalid API response'
        ];
    } catch (\Exception $e) {
        Log::error("Error fetching Backmarket stock for variation {$variationId}: " . $e->getMessage());
        
        return [
            'quantity' => $variation->listed_stock ?? 0,
            'sku' => $variation->sku,
            'state' => $variation->state,
            'updated' => false,
            'error' => $e->getMessage()
        ];
    }
}
```

**Dependencies to Add**:
```php
use App\Models\Variation_model;
use App\Http\Controllers\BackMarketAPIController;
use Illuminate\Support\Facades\Log;
```

---

#### 1.2 Create V2 API Endpoint
**File**: `app/Http/Controllers/V2/ListingController.php`

**Add Method**:
```php
/**
 * Get updated stock quantity from Backmarket API (V2 endpoint)
 * Uses ListingDataService for service layer architecture
 * 
 * @param int $variationId
 * @return \Illuminate\Http\JsonResponse
 */
public function getUpdatedQuantity(int $variationId)
{
    try {
        $result = $this->dataService->getBackmarketStockQuantity($variationId);
        
        return response()->json([
            'success' => $result['updated'],
            'quantity' => $result['quantity'],
            'sku' => $result['sku'],
            'state' => $result['state'],
            'error' => $result['error'] ?? null
        ]);
    } catch (\Exception $e) {
        Log::error("V2 getUpdatedQuantity error: " . $e->getMessage(), [
            'variation_id' => $variationId
        ]);
        
        return response()->json([
            'success' => false,
            'quantity' => 0,
            'error' => 'Error fetching stock quantity'
        ], 500);
    }
}
```

---

#### 1.3 Add Route
**File**: `routes/web.php`

**Add Route** (near other V2 listing routes):
```php
Route::get('v2/listings/get_updated_quantity/{id}', [App\Http\Controllers\V2\ListingController::class, 'getUpdatedQuantity'])
    ->name('v2.listing.get_updated_quantity');
```

---

### Phase 2: Frontend - JavaScript Implementation

#### 2.1 Add URL to ListingConfig
**File**: `resources/views/v2/listing/listing.blade.php` (Line 168-190)

**Add to `window.ListingConfig.urls`**:
```javascript
getUpdatedQuantity: "{{ url('v2/listings/get_updated_quantity') }}",
```

---

#### 2.2 Create JavaScript Function
**File**: `public/assets/v2/listing/js/listing.js` (or create new file if needed)

**Add Function**:
```javascript
/**
 * Fetch updated stock quantity from Backmarket API for a variation
 * Only fetches if marketplace is Backmarket (ID = 1)
 * 
 * @param {number} variationId
 * @param {number} marketplaceId
 * @returns {Promise<number|null>} Stock quantity or null if not Backmarket
 */
function fetchBackmarketStockQuantity(variationId, marketplaceId) {
    // Only fetch for Backmarket (marketplace ID = 1)
    if (marketplaceId !== 1) {
        return Promise.resolve(null);
    }
    
    if (!window.ListingConfig || !window.ListingConfig.urls || !window.ListingConfig.urls.getUpdatedQuantity) {
        console.warn('getUpdatedQuantity URL not configured');
        return Promise.resolve(null);
    }
    
    return $.ajax({
        url: window.ListingConfig.urls.getUpdatedQuantity + '/' + variationId,
        type: 'GET',
        dataType: 'json',
        headers: {
            'X-CSRF-TOKEN': window.ListingConfig.csrfToken
        },
        success: function(response) {
            if (response.success && response.quantity !== undefined) {
                return response.quantity;
            }
            return null;
        },
        error: function(xhr, status, error) {
            console.error('Error fetching Backmarket stock quantity:', error);
            return null;
        }
    });
}

/**
 * Update Backmarket stock badge in marketplace bar
 * 
 * @param {number} variationId
 * @param {number} marketplaceId
 * @param {number} quantity
 */
function updateBackmarketStockBadge(variationId, marketplaceId, quantity) {
    const badgeElement = $(`#backmarket_stock_badge_${variationId}_${marketplaceId}`);
    
    if (badgeElement.length) {
        if (quantity !== null && quantity !== undefined) {
            badgeElement.find('.stock-value').text(quantity);
            badgeElement.removeClass('d-none');
        } else {
            badgeElement.addClass('d-none');
        }
    }
}
```

---

#### 2.3 Call Function on Variation Load
**File**: `resources/views/v2/listing/partials/marketplace-bar.blade.php`

**Add Script Section** (at end of file, before closing tag):
```javascript
@once
<script>
    // Fetch Backmarket stock when marketplace bar is rendered
    $(document).ready(function() {
        const variationId = {{ $variationId }};
        const marketplaceId = {{ $marketplaceIdInt }};
        
        // Only fetch for Backmarket (ID = 1)
        if (marketplaceId === 1) {
            fetchBackmarketStockQuantity(variationId, marketplaceId)
                .then(function(quantity) {
                    if (quantity !== null) {
                        updateBackmarketStockBadge(variationId, marketplaceId, quantity);
                    }
                });
        }
    });
</script>
@endonce
```

**Note**: If using Livewire or component-based loading, trigger this when the marketplace bar component mounts.

---

### Phase 3: Frontend - UI Badge Display

#### 3.1 Add Badge to Marketplace Bar
**File**: `resources/views/v2/listing/partials/marketplace-bar.blade.php` (Line 93, after stock display)

**Add Badge** (right after the stock span):
```php
{{-- Database Stock (existing) --}}
<span class="text-muted small">(<span id="stock_{{ $variationId }}_{{ $marketplaceId }}">{{ $currentStock }}</span>)</span>

{{-- Real-time Backmarket Stock Badge (only for Backmarket) --}}
@if($marketplaceIdInt === 1)
    <span id="backmarket_stock_badge_{{ $variationId }}_{{ $marketplaceId }}" class="badge bg-info text-white ms-2 d-none" style="font-size: 0.75rem;">
        <i class="fas fa-sync-alt me-1"></i>
        <span class="stock-value">0</span>
        <small class="ms-1">(API)</small>
    </span>
@endif
```

**Alternative Design** (PostScript style badge):
```php
@if($marketplaceIdInt === 1)
    <span id="backmarket_stock_badge_{{ $variationId }}_{{ $marketplaceId }}" class="badge bg-primary text-white ms-2 d-none" style="font-size: 0.7rem; font-weight: 600; letter-spacing: 0.5px;">
        API: <span class="stock-value">0</span>
    </span>
@endif
```

---

#### 3.2 Add Loading State
**Initial State** (while fetching):
```php
@if($marketplaceIdInt === 1)
    <span id="backmarket_stock_badge_{{ $variationId }}_{{ $marketplaceId }}" class="badge bg-secondary text-white ms-2" style="font-size: 0.7rem;">
        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
        <span>Loading...</span>
    </span>
@endif
```

**Update JavaScript** to handle loading state:
```javascript
function updateBackmarketStockBadge(variationId, marketplaceId, quantity) {
    const badgeElement = $(`#backmarket_stock_badge_${variationId}_${marketplaceId}`);
    
    if (badgeElement.length) {
        if (quantity !== null && quantity !== undefined) {
            // Remove loading spinner, show quantity
            badgeElement
                .removeClass('bg-secondary d-none')
                .addClass('bg-primary')
                .html(`<span class="stock-value">${quantity}</span> <small class="ms-1">(API)</small>`);
        } else {
            // Hide badge if fetch failed
            badgeElement.addClass('d-none');
        }
    }
}
```

---

### Phase 4: Error Handling & Edge Cases

#### 4.1 Handle Missing Reference ID
- If `variation->reference_id` is null, return database stock
- Show badge with warning icon or hide badge

#### 4.2 Handle API Failures
- On error, show database stock (existing behavior)
- Optionally show error indicator on badge
- Log errors for debugging

#### 4.3 Handle Non-Backmarket Marketplaces
- Only show badge for marketplace ID = 1
- No API call for other marketplaces

#### 4.4 Performance Considerations
- Fetch only when marketplace bar is visible
- Cache results if needed (optional)
- Debounce if multiple variations load simultaneously

---

## File Structure Summary

### New/Modified Files

1. **Backend**:
   - `app/Services/V2/ListingDataService.php` - Add `getBackmarketStockQuantity()` method
   - `app/Http/Controllers/V2/ListingController.php` - Add `getUpdatedQuantity()` method
   - `routes/web.php` - Add V2 route

2. **Frontend**:
   - `resources/views/v2/listing/listing.blade.php` - Add URL to config
   - `resources/views/v2/listing/partials/marketplace-bar.blade.php` - Add badge HTML and fetch script
   - `public/assets/v2/listing/js/listing.js` - Add fetch functions (or create new file)

---

## Testing Checklist

### Backend Tests
- [ ] `ListingDataService::getBackmarketStockQuantity()` returns correct data
- [ ] Handles missing `reference_id` gracefully
- [ ] Handles API failures gracefully
- [ ] Returns proper error messages
- [ ] V2 endpoint returns JSON correctly

### Frontend Tests
- [ ] Badge appears only for Backmarket (ID = 1)
- [ ] Badge shows loading state initially
- [ ] Badge updates with API quantity
- [ ] Badge handles errors gracefully
- [ ] Badge doesn't break existing functionality
- [ ] Multiple variations load correctly

### Integration Tests
- [ ] Badge fetches on variation load
- [ ] Badge updates correctly
- [ ] No performance degradation
- [ ] Works with marketplace toggle
- [ ] Works with pagination

---

## Implementation Order

1. **Backend Service** (Phase 1)
   - Extend `ListingDataService`
   - Add controller method
   - Add route
   - Test with Postman/curl

2. **Frontend Badge** (Phase 3)
   - Add badge HTML to marketplace bar
   - Style badge appropriately

3. **Frontend JavaScript** (Phase 2)
   - Add fetch function
   - Add update function
   - Wire up to variation load

4. **Error Handling** (Phase 4)
   - Add error handling
   - Add loading states
   - Test edge cases

---

## Design Considerations

### Badge Styling
- **Color**: Use distinct color (e.g., `bg-primary` or `bg-info`) to differentiate from database stock
- **Size**: Slightly smaller than main stock number
- **Icon**: Optional sync icon to indicate real-time data
- **Label**: "API" or "(API)" to indicate source

### Placement
- **Location**: Next to database stock number in marketplace bar header
- **Alignment**: Inline with marketplace name and stock count

### User Experience
- **Loading State**: Show spinner while fetching
- **Error State**: Hide badge or show error indicator
- **Update Frequency**: Fetch once per variation load (not continuously)

---

## Future Enhancements (Optional)

1. **Auto-refresh**: Add button to manually refresh stock
2. **Comparison**: Highlight if API stock differs from database stock
3. **Caching**: Cache API results for short period (e.g., 5 minutes)
4. **Batch Fetching**: Fetch multiple variations in one request
5. **Webhook Integration**: Update badge when stock changes via webhook

---

## Notes

- **Service Layer**: Using `ListingDataService` keeps business logic separate from controller
- **V2 Architecture**: Follows existing V2 patterns (service-based, clean separation)
- **Backward Compatibility**: Doesn't affect existing functionality
- **Performance**: Minimal impact (one API call per Backmarket variation on load)

---

## Dependencies

- `BackMarketAPIController` - Already exists
- `Variation_model` - Already exists
- `ListingDataService` - Already exists (needs extension)
- jQuery - Already loaded
- Bootstrap - Already loaded (for badges)

---

## Estimated Implementation Time

- **Backend Service**: 1-2 hours
- **Frontend Badge**: 1 hour
- **Frontend JavaScript**: 1-2 hours
- **Testing & Debugging**: 1-2 hours
- **Total**: 4-7 hours

---

## Questions to Clarify

1. Should the badge update the database stock, or just display?
2. Should there be a visual indicator if API stock differs from database stock?
3. Should the badge be clickable (e.g., to refresh or see details)?
4. Should we cache API results to reduce calls?
5. What should happen if API call fails? (Hide badge, show error, show database stock?)

---

## Approval

- [ ] Backend approach approved
- [ ] Frontend design approved
- [ ] Service layer approach approved
- [ ] Ready to implement

