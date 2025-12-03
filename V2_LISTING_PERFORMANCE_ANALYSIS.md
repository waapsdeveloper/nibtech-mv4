# V2 Listing Page - Performance Analysis & Optimization Suggestions

## Current Loading Flow

### Comparison with Main Branch Listing

**Main Branch Approach:**
- Loads variations with ALL listings data via eager loading in initial query
- Sales data: Loads immediately via jQuery `.load()` for each variation (10 variations = 10 calls)
- Stocks: Loads immediately via AJAX `getStocks()` for each variation (10 variations = 10 calls)  
- Listings: Already included in variation data (no separate API call needed)
- Details section: Only loads when user clicks to expand (via `getVariationDetails()`)
- **No auto-expand** - sections remain collapsed until user interaction

**V2 Current Approach:**
- Loads variation IDs first (optimized)
- Then loads each variation via Livewire component lazy loading
- Sales data: Loads immediately for each variation (10 variations = 10 calls)
- Marketplace data: Loads immediately via API for each variation (10 variations = 10 calls)
- Auto-expands all marketplace sections automatically
- Creates multiple Livewire components (one per variation + one per marketplace)

**Key Difference:** Main branch loads listings upfront in one query, V2 tries to load everything separately and immediately.

---

### Initial Page Load
1. **Controller (`ListingController@index`)**:
   - Loads reference data (storages, colors, grades, currencies, countries, marketplaces) via `ListingDataService`
   - Loads exchange rate data
   - Renders main view with search form

### Variation List Loading
2. **Fetch Variations (`getVariations`)**:
   - Builds query with filters
   - Returns only variation IDs (pagination)
   - ✅ **Already optimized** - returns IDs only

3. **Render Listing Items (`renderListingItems`)**:
   - Loads reference data again (duplicate)
   - Mounts Livewire `ListingItems` component
   - Creates multiple `ListingItem` components (one per variation)

### Per Variation Loading (`wire:init="loadRow"`)
4. **Each ListingItem Component**:
   - **Immediate operations (blocking)**:
     - Loads variation with relationships: `listings`, `listings.country_id`, `listings.currency`, `listings.marketplace`, `product`, `available_stocks`, `pending_orders`, `storage_id`, `color_id`, `grade_id`
     - Calculates stats (available stocks, pending orders, stock difference)
     - Calculates pricing info
     - Calculates average cost
     - Sets `detailsExpanded = true` (auto-expands marketplace section)

5. **JavaScript Operations (after component ready)**:
   - **Sales data loading** (per variation): Calls `listing/get_sales/{id}` immediately
   - **Marketplace data loading** (per variation): Calls `/v2/listings/marketplaces/{id}` via AJAX
   - **Auto-expand logic**: Expands all marketplace sections sequentially
   - Multiple timeouts and polling checks

### Marketplace Accordion Components
6. **Each MarketplaceAccordion**:
   - Currently making Livewire calls (user wants to remove this)
   - Loads listings, stocks, order summaries, header metrics

---

## Performance Bottlenecks Identified

### 🔴 Critical Issues

1. **Multiple Sales Data API Calls (10 variations = 10 calls)**
   - **Location**: `listing-item.blade.php` line 34
   - **Current**: Each variation immediately calls `listing/get_sales/{id}` on load
   - **Impact**: Heavy database queries per variation (today, yesterday, 7 days, 30 days averages)
   - **Priority**: HIGH - Can be deferred
   - **Main Branch**: Also loads immediately (same issue - both need optimization)

2. **Auto-Expanding All Marketplace Sections**
   - **Location**: `listing-item.blade.php` lines 48-158
   - **Current**: Automatically expands all marketplace accordions sequentially
   - **Impact**: Triggers multiple UI operations, potentially causing layout shifts
   - **Priority**: MEDIUM - Not critical for initial interaction
   - **Main Branch**: ✅ **Does NOT auto-expand** - sections stay collapsed until user clicks (BETTER approach)

3. **Marketplace Data API Calls**
   - **Location**: JavaScript in `listing-item.blade.php`
   - **Current**: Calls `/v2/listings/marketplaces/{id}` immediately after component ready
   - **Impact**: Heavy operation - compiles all marketplace data per variation
   - **Priority**: HIGH - Can be deferred until user expands
   - **Main Branch**: Loads listings with variation data upfront (eager loading) - no separate API call needed, BUT loads everything even if not used

4. **Reference Data Loaded Twice**
   - **Location**: `ListingController@index` and `ListingController@renderListingItems`
   - **Current**: Loads reference data in controller AND when rendering items
   - **Impact**: Redundant database queries
   - **Priority**: LOW - Already cached, but still redundant

### 🟡 Medium Priority Issues

5. **Eager Loading in `loadRow()`**
   - **Location**: `ListingItem.php` line 79-90
   - **Current**: Loads ALL relationships immediately (listings, stocks, orders, etc.)
   - **Impact**: Large data sets loaded even if not immediately needed
   - **Priority**: MEDIUM - Could be optimized

6. **Sequential Marketplace Expansion**
   - **Location**: `listing-item.blade.php` lines 81-136
   - **Current**: Expands marketplaces one by one with delays (100-300ms between each)
   - **Impact**: If 5 marketplaces, takes 500-1500ms just for expansion animation
   - **Priority**: LOW - Visual only

7. **Multiple setTimeout/polling**
   - **Location**: Throughout JavaScript in listing-item.blade.php
   - **Current**: Multiple polling intervals checking for element existence
   - **Impact**: Unnecessary CPU cycles, could be optimized
   - **Priority**: LOW

### 🟢 Low Priority (Nice to Have)

8. **Sales Data Query Complexity**
   - Multiple date range queries per variation
   - Could benefit from batch processing or caching

9. **Marketplace Data Compilation**
   - Complex calculations per marketplace
   - Could use background jobs or caching

---

## Optimization Suggestions

### 🚀 Immediate Actions (High Impact, Low Risk)

#### 1. **Defer Sales Data Loading**
**Current**: Loads immediately when variation component is ready  
**Suggestion**: 
- Load sales data only when:
  - User scrolls variation into viewport (Intersection Observer)
  - User clicks to expand marketplace section
  - Or after 2-3 seconds delay (low priority queue)
  
**Impact**: Reduces 10 API calls on initial page load (if 10 variations)

#### 2. **Defer Marketplace Data Loading**
**Current**: Loads immediately after ListingItem is ready  
**Suggestion**:
- Load marketplace data ONLY when user clicks to expand the marketplace accordion
- Show placeholder/loading state until data is fetched
- Use intersection observer for items in viewport

**Impact**: Eliminates heavy API calls for variations user doesn't interact with

#### 3. **Remove Auto-Expand**
**Current**: Automatically expands all marketplace sections  
**Suggestion**:
- Don't auto-expand marketplace sections
- Let users expand manually when needed
- OR: Add a setting/preference for auto-expand behavior

**Impact**: Faster initial render, less UI manipulation

#### 4. **Lazy Load Marketplace Accordions**
**Current**: All marketplace accordion components mounted immediately  
**Suggestion**:
- Render marketplace sections only when main accordion is expanded
- Use placeholder/skeleton until user expands

**Impact**: Reduces initial DOM size and Livewire component initialization

---

### ⚡ Secondary Optimizations

#### 5. **Batch Sales Data Loading**
**Current**: Individual API calls per variation  
**Suggestion**:
- Create batch endpoint: `/v2/listings/sales/batch`
- Accept array of variation IDs
- Return sales data for all variations in one call
- Use when variations come into viewport

**Impact**: 1 API call instead of 10

#### 6. **Virtual Scrolling / Progressive Loading**
**Suggestion**:
- Only render variations currently in viewport
- Load next batch as user scrolls
- Use libraries like `vue-virtual-scroll-list` or similar

**Impact**: Reduces initial DOM size and memory usage

#### 7. **Debounce Marketplace Expansion**
**Current**: Sequential expansion with fixed delays  
**Suggestion**:
- Expand all simultaneously if data is pre-loaded
- Use CSS transitions instead of JavaScript delays

**Impact**: Faster visual feedback

#### 8. **Cache Reference Data**
**Current**: Already using cache (ListingDataService)  
**Suggestion**: 
- Verify cache is working properly
- Consider longer cache TTL (currently 1 hour)
- Store in browser localStorage for even faster access

**Impact**: Faster page loads

---

### 🔧 Code-Level Optimizations

#### 9. **Optimize Eager Loading**
**Suggestion**:
- Load only essential relationships initially
- Load detailed data (listings, stocks) when marketplace section expands
- Use `loadMissing()` for on-demand loading

**Impact**: Faster initial queries

#### 10. **Streaming/SSE for Real-time Updates**
**Suggestion**:
- Use Server-Sent Events (SSE) for sales data updates
- Push updates from server instead of polling

**Impact**: Real-time updates without polling overhead

---

## Lessons from Main Branch Implementation

### ✅ What Main Branch Does Better

1. **No Auto-Expand**
   - Main branch keeps sections collapsed until user clicks
   - V2 auto-expands all marketplace sections (should remove)

2. **Listings Loaded with Variations**
   - Main branch loads listings via eager loading in initial query
   - V2 loads marketplace data separately (could optimize by loading with variation)

3. **Details Load Only on Expand**
   - Main branch calls `getVariationDetails()` only when user clicks to expand
   - V2 loads marketplace data immediately

### ⚠️ What Main Branch Also Needs to Optimize

1. **Sales Data Loading**
   - Both main branch and V2 load sales data immediately for each variation
   - Should be deferred in both

2. **Stocks Loading**
   - Main branch calls `getStocks()` immediately for each variation
   - V2 loads stocks with marketplace data
   - Both could defer

### 🎯 What V2 Does Better

1. **Lazy Loading Architecture**
   - V2 loads variation IDs first, then components
   - More scalable approach

2. **Single Marketplace API Endpoint**
   - V2 has dedicated endpoint for marketplace data
   - Better separation of concerns

3. **Marketplace Info Cards**
   - V2 shows summary cards (new feature)
   - Better UX for overview

---

## Recommended Implementation Order

### Phase 1: Quick Wins (No Code Structure Changes)
1. ✅ Remove auto-expand of marketplace sections (learn from main branch)
2. ✅ Defer sales data loading (2-3 second delay or on scroll)
3. ✅ Defer marketplace data loading until user expands (like main branch's `getVariationDetails`)

### Phase 2: API Optimization
4. ✅ Create batch sales data endpoint (optimize both main and V2)
5. ✅ Optimize marketplace data endpoint (already exists)

### Phase 3: Consider Loading Listings with Variations
6. ✅ Option: Load listings with variation data (like main branch) to reduce API calls
7. ✅ Option: Keep separate marketplace endpoint but only call on expand

### Phase 4: Advanced Optimizations
8. ✅ Virtual scrolling
9. ✅ Intersection Observer for lazy loading
10. ✅ Progressive enhancement

---

## Estimated Performance Gains

### Current Load Time (Estimated)
- Initial page: ~500ms
- Variation IDs fetch: ~200ms
- Render 10 ListingItem components: ~1000ms
- Load sales data (10 calls): ~2000ms
- Load marketplace data (10 calls): ~3000ms
- Auto-expand animations: ~1500ms
- **Total: ~8200ms (8.2 seconds)**

### Main Branch Load Time (Estimated) - For Comparison
- Initial page: ~500ms
- Load variations with listings (eager loading): ~2000ms
- Load sales data (10 calls): ~2000ms
- Load stocks (10 calls): ~1000ms
- No auto-expand: 0ms
- **Total: ~5500ms (5.5 seconds)** - Better than V2, but still has optimization opportunities

### After Phase 1 Optimizations (Estimated)
- Initial page: ~500ms
- Variation IDs fetch: ~200ms
- Render 10 ListingItem components: ~1000ms
- Defer sales data: 0ms (deferred)
- Defer marketplace data: 0ms (deferred)
- No auto-expand: 0ms
- **Total: ~1700ms (1.7 seconds) - 79% faster than current, 69% faster than main branch**

### After All Optimizations (Estimated)
- Initial page: ~500ms
- Variation IDs fetch: ~200ms
- Render 5 visible components (virtual scroll): ~500ms
- **Total: ~1200ms (1.2 seconds) - 85% faster than current, 78% faster than main branch**

---

## Non-Urgent Operations That Can Be Deferred

### ✅ Safe to Defer (User doesn't need immediately)

1. **Sales Data** (`listing/get_sales/{id}`)
   - Shows: Today/Yesterday/7d/30d averages
   - Defer: Until user scrolls to variation OR 3-second delay
   - Priority: Low - informative but not critical

2. **Marketplace Data** (`/v2/listings/marketplaces/{id}`)
   - Shows: Marketplace-specific listings, stocks, order summaries
   - Defer: Until user clicks to expand marketplace section
   - Priority: Medium - needed for interaction but not for overview

3. **Marketplace Section Expansion**
   - Defer: Let user manually expand when needed
   - Priority: Low - auto-expand is convenience feature

4. **Marketplace Info Cards Data**
   - Currently shown in main area
   - Could show skeleton/placeholder until marketplace data loads
   - Priority: Low

### ❌ Keep Immediate (User needs for interaction)

1. **Variation Basic Info** (SKU, product name, color, storage, grade)
   - Required: Immediately
   - Already: ✅ Optimized

2. **Basic Stats** (Pending orders, Available stocks, Stock difference)
   - Required: Immediately
   - Already: ✅ Optimized

3. **Main Variation Card Header**
   - Required: Immediately
   - Already: ✅ Optimized

---

## Loading Priority Matrix

| Operation | Current | Suggested | Priority |
|-----------|---------|-----------|----------|
| Page HTML | Immediate | Immediate | Critical |
| Reference Data | Immediate | Immediate | Critical |
| Variation IDs | Immediate | Immediate | Critical |
| Basic Variation Info | Immediate | Immediate | Critical |
| Basic Stats | Immediate | Immediate | Critical |
| Sales Data | Immediate | Deferred (scroll/click/3s delay) | Low |
| Marketplace Data | Immediate | Deferred (on expand) | Medium |
| Auto-expand | Immediate | Removed/Manual | Low |
| Marketplace Cards | Immediate | Deferred (when marketplace data loads) | Low |

---

## Implementation Notes

### Intersection Observer Pattern
```javascript
// Example: Load sales data when variation comes into viewport
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            loadSalesData(entry.target.dataset.variationId);
            observer.unobserve(entry.target);
        }
    });
}, { rootMargin: '50px' });
```

### Batch API Pattern
```javascript
// Instead of 10 separate calls:
// GET /listing/get_sales/1
// GET /listing/get_sales/2
// ...
// Do:
// POST /v2/listings/sales/batch
// Body: { variation_ids: [1,2,3,4,5,6,7,8,9,10] }
```

### Lazy Loading Pattern
```javascript
// Load marketplace data only when section is expanded
accordion.addEventListener('show.bs.collapse', function() {
    if (!dataLoaded) {
        loadMarketplaceData(variationId, marketplaceId);
    }
});
```

---

## Summary

**Main Issues:**
1. Too many API calls on initial load (sales data + marketplace data)
2. Auto-expanding all sections (unnecessary UI operations) - **Main branch doesn't do this**
3. Loading data that users don't immediately need

**Key Insights from Main Branch Comparison:**
1. ✅ **Don't auto-expand** - Main branch keeps sections collapsed until user clicks (better UX)
2. ✅ **Load details only on expand** - Main branch calls `getVariationDetails()` only when needed
3. ⚠️ **Both need optimization** - Main branch also loads sales/stocks immediately (same issue)
4. 💡 **Consider eager loading** - Main branch loads listings with variations (one query vs multiple)

**Quick Wins:**
1. Defer sales data loading (save ~2000ms) - **Apply to both main and V2**
2. Defer marketplace data loading (save ~3000ms) - **V2 specific**
3. Remove auto-expand (save ~1500ms) - **Learn from main branch**

**Total Potential Savings: ~6.5 seconds on initial load**

The page should feel much snappier with just Phase 1 optimizations!

---

## Action Items Based on Main Branch Comparison

### Immediate Actions for V2:
1. ✅ Remove auto-expand functionality (main branch doesn't have it)
2. ✅ Load marketplace data only on expand (like main branch's `getVariationDetails`)
3. ✅ Defer sales data loading (both branches need this)

### Consider for Future:
4. 💡 Option: Load listings with variation data (like main branch eager loading)
   - Pro: One query instead of separate marketplace API calls
   - Con: Loads all listings even if marketplace sections not expanded
   - **Recommendation**: Keep separate endpoint but only call on expand

5. 💡 Option: Batch sales data endpoint (both branches)
   - One API call instead of 10 separate calls
   - Better performance for both main and V2

6. 💡 Option: Batch stocks data endpoint (main branch specific)
   - Currently main branch makes 10 separate `getStocks()` calls
   - Could be optimized similar to V2's approach
