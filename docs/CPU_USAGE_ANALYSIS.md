# CPU Usage Analysis Report - Console Commands

**Date:** January 2026  
**Issue:** CPU usage reaching ~100% on Digital Ocean droplet  
**Root Cause:** Multiple console commands running frequently with inefficient operations

---

## Executive Summary

The system is experiencing high CPU usage (peaking at 85-90%) due to:
1. **Overlapping command execution** - Multiple commands running simultaneously without proper scheduling
2. **Inefficient database queries** - N+1 query problems and queries inside loops
3. **Synchronous API calls** - Blocking operations without proper rate limiting
4. **Lack of command overlap protection** - Many commands missing `withoutOverlapping()` constraint
5. **Frequent execution intervals** - Some commands running every 2-5 minutes

---

## Command Schedule Analysis

### High-Frequency Commands (Primary CPU Contributors)

| Command | Frequency | Overlap Protection | Background | CPU Impact |
|---------|-----------|-------------------|-------------|------------|
| `refresh:new` | **Every 2 minutes** | ❌ No | ❌ No | 🔴 **CRITICAL** |
| `refresh:latest` | Every 5 minutes | ❌ No | ❌ No | 🔴 **HIGH** |
| `refresh:orders` | Every 5 minutes | ❌ No | ❌ No | 🔴 **HIGH** |
| `price:handler` | Every 10 minutes | ✅ Yes | ✅ Yes | 🟠 **MEDIUM** |
| `functions:ten` | Every 10 minutes | ❌ No | ❌ No | 🟠 **MEDIUM** |
| `api-request:process` | Every 5 minutes | ❌ No | ❌ No | 🟠 **MEDIUM** |
| `refurbed:new` | Every 5 minutes | ❌ No | ❌ No | 🟠 **MEDIUM** |
| `support:sync` | Every 10 minutes | ✅ Yes | ✅ Yes | 🟡 **LOW** |
| `bmpro:orders` | Every 10 minutes | ✅ Yes | ✅ Yes | 🟡 **LOW** |

### Medium-Frequency Commands

| Command | Frequency | Overlap Protection | Background | CPU Impact |
|---------|-----------|-------------------|-------------|------------|
| `functions:thirty` | Hourly | ❌ No | ❌ No | 🟠 **MEDIUM** |
| `refurbed:orders` | Hourly | ❌ No | ❌ No | 🟡 **LOW** |
| `refurbed:link-tickets` | Every 10 minutes | ✅ Yes | ✅ Yes | 🟡 **LOW** |
| `v2:sync-orders --type=new` | Every 2 hours | ✅ Yes | ✅ Yes | 🟡 **LOW** |
| `v2:marketplace:sync-stock` | Every 6 hours | ✅ Yes | ✅ Yes | 🟡 **LOW** |

---

## Detailed Command Analysis

### 1. `refresh:new` (Every 2 Minutes) - 🔴 **CRITICAL ISSUE**

**Location:** `app/Console/Commands/RefreshNew.php`

**Issues:**
- ❌ **Runs every 2 minutes** - Extremely frequent
- ❌ **No overlap protection** - Can spawn multiple instances
- ❌ **No background execution** - Blocks scheduler
- ❌ **Multiple API calls in loops** - `getNewOrders()` + `getOneOrder()` for each order
- ❌ **Database queries in loops** - `updateOrderInDB()` called for each order
- ❌ **No rate limiting** - Can overwhelm API endpoints

**CPU Impact:**
```php
// Makes API call for ALL new orders
$resArray1 = $bm->getNewOrders();

// Then makes ANOTHER API call for EACH order
foreach($orders as $or){
    $this->updateBMOrder($or, $bm, ...); // API call + DB operations
}

// Then queries database and makes MORE API calls
$orders = Order_model::whereIn('status', [0, 1, 2])
    ->WhereNull('delivery_note_url')
    ->orWhereNull('label_url')
    ->where('order_type_id', 3)
    ->where('created_at', '>=', Carbon::now()->subDays(2))
    ->pluck('reference_id');
    
foreach($orders as $order){
    $this->updateBMOrder($order, $bm, ...); // More API calls
}
```

**Recommendations:**
1. Increase interval to **every 5 minutes** (minimum)
2. Add `->withoutOverlapping()` to prevent multiple instances
3. Add `->runInBackground()` to prevent blocking
4. Implement batch processing with chunking
5. Add rate limiting between API calls
6. Cache API responses where possible

---

### 2. `price:handler` (Every 10 Minutes) - 🟠 **MEDIUM-HIGH ISSUE**

**Location:** `app/Console/Commands/PriceHandler.php`

**Issues:**
- ⚠️ **Processes ALL listings** matching criteria in single execution
- ⚠️ **API call for EACH listing** - `getListingCompetitors()` called per listing
- ⚠️ **Sleep(1) between calls** - Still inefficient for large datasets
- ⚠️ **Database queries in loops** - Multiple queries per listing
- ⚠️ **No chunking** - Loads all listings into memory at once
- ⚠️ **No pagination** - Processes all at once

**CPU Impact:**
```php
// Loads ALL listings into memory
$listings = Listing_model::whereIn('handler_status', [1,3])
    ->where('marketplace_id', 1)
    ->where('buybox', '!=', 1)
    ->where('min_price_limit', '>', 0)
    ->whereColumn('min_price_limit', '<=', 'buybox_price')
    ->whereColumn('min_price_limit', '<=', 'min_price')
    ->get(); // Could be thousands of records

// API call for EACH listing
foreach ($references as $reference) {
    $responses = $bm->getListingCompetitors($reference);
    sleep(1); // Still inefficient
    // Process response...
}
```

**Recommendations:**
1. Implement **chunking** - Process 50-100 listings at a time
2. Add **rate limiting** - Respect API rate limits properly
3. Use **queue jobs** - Process listings asynchronously
4. Add **caching** - Cache competitor data for short periods
5. Add **progress tracking** - Resume from last processed position
6. Consider **staggered execution** - Process different subsets at different times

---

### 3. `functions:thirty` (Hourly) - 🟠 **MEDIUM ISSUE**

**Location:** `app/Console/Commands/FunctionsThirty.php`

**Issues:**
- ⚠️ **Fetches ALL listings** from API - `getAllListings()` and `getAllListingsBi()`
- ⚠️ **No pagination/chunking** - Processes entire dataset
- ⚠️ **Database operations in loops** - `firstOrNew()` called for each listing
- ⚠️ **No overlap protection** - Can run multiple times
- ⚠️ **Complex nested loops** - Country → Listing → Processing

**CPU Impact:**
```php
// Fetches ALL listings from API (could be thousands)
$listings = $bm->getAllListings();

// Nested loops processing each listing
foreach($listings as $country => $lists){
    foreach($lists as $list){
        // Database query for EACH listing
        $variation = Variation_model::where(['reference_id'=>...])->first();
        $listing = Listing_model::firstOrNew([...]);
        // Multiple saves per iteration
    }
}
```

**Recommendations:**
1. Implement **pagination** - Process listings in batches
2. Add **overlap protection** - `->withoutOverlapping()`
3. Use **bulk insert/update** - Reduce individual queries
4. Add **background execution** - `->runInBackground()`
5. Cache **country/currency lookups** - Reduce repeated queries
6. Consider **incremental sync** - Only process changed listings

---

### 4. `refresh:orders` (Every 5 Minutes) - 🔴 **HIGH ISSUE**

**Location:** `app/Console/Commands/RefreshOrders.php`

**Issues:**
- ❌ **No overlap protection** - Multiple instances can run
- ❌ **Fetches ALL orders** - `getAllOrders(1, ['page-size'=>50])` with pagination
- ❌ **API calls in loops** - `validateOrderlines()` makes API POST for each orderline
- ❌ **Database operations per order** - `updateOrderInDB()` and `updateOrderItemsInDB()`

**CPU Impact:**
```php
// Fetches new orders
$resArray1 = $bm->getNewOrders(['page-size'=>50]);

// API call for EACH orderline
foreach ($resArray1 as $orderObj) {
    foreach($orderObj->orderlines as $orderline){
        $this->validateOrderlines($orderObj->order_id, $orderline->listing, $bm);
        // Makes API POST request
    }
}

// Fetches ALL modified orders
$resArray = $bm->getAllOrders(1, ['page-size'=>50], $modification);

// Processes each order
foreach ($resArray as $orderObj) {
    $order_model->updateOrderInDB($orderObj, ...);
    $order_item_model->updateOrderItemsInDB($orderObj, ...);
}
```

**Recommendations:**
1. Add `->withoutOverlapping()` - Prevent concurrent execution
2. Add `->runInBackground()` - Non-blocking execution
3. Implement **batch processing** - Process orders in chunks
4. Reduce **API call frequency** - Batch validate orderlines
5. Use **queue jobs** - Process orders asynchronously
6. Add **rate limiting** - Respect API limits

---

### 5. `functions:ten` (Every 10 Minutes) - 🟠 **MEDIUM ISSUE**

**Location:** `app/Console/Commands/Functions.php`

**Issues:**
- ⚠️ **Multiple heavy operations** - Refund currency, check linked orders, duplicate cleanup, merge transactions
- ⚠️ **Database queries in loops** - Multiple queries per iteration
- ⚠️ **No overlap protection** - Can run multiple times
- ⚠️ **Complex queries** - Window functions, subqueries
- ⚠️ **Large dataset processing** - Processes up to 1000 orders at once

**CPU Impact:**
```php
// Multiple operations running sequentially
$this->refund_currency();        // Queries + loops
$this->check_linked_orders();    // Nested queries
$this->duplicate_orders();       // Complex window functions
$this->misc();                   // More queries
$this->merge_order_transactions(); // Processes 1000 orders

// Example: merge_order_transactions processes 1000 orders
Order_model::query()->where('order_type_id', 3)
    ->with(['transactions', 'order_charges.charge'])
    ->whereHas('transactions', ...)
    ->orderByDesc('id')
    ->limit(1000) // Still a large batch
    ->get()
    ->each(function ($order) {
        // Multiple operations per order
    });
```

**Recommendations:**
1. Add `->withoutOverlapping()` - Prevent concurrent runs
2. Split into **separate commands** - Run different operations at different intervals
3. Implement **chunking** - Process smaller batches
4. Add **indexes** - Optimize database queries
5. Use **bulk operations** - Reduce individual queries
6. Add **background execution** - `->runInBackground()`

---

### 6. `api-request:process` (Every 5 Minutes) - 🟠 **MEDIUM ISSUE**

**Location:** `app/Console/Commands/ProcessApiRequests.php`

**Issues:**
- ⚠️ **No overlap protection** - Multiple instances possible
- ⚠️ **Processes queued API requests** - Could be many requests
- ⚠️ **Default chunk size 100** - Could process 100 requests per run

**Recommendations:**
1. Add `->withoutOverlapping()` - Prevent concurrent execution
2. Add `->runInBackground()` - Non-blocking
3. Monitor **queue size** - Adjust frequency based on load
4. Implement **rate limiting** - Prevent API overload

---

## Database Query Issues

### N+1 Query Problems

**Found in multiple commands:**
- `RefreshNew.php` - Queries for each order
- `PriceHandler.php` - Queries for each listing
- `FunctionsThirty.php` - Queries for each listing/variation
- `Functions.php` - Queries for each order/item

**Example from PriceHandler:**
```php
// Loads all listings
$listings = Listing_model::whereIn('handler_status', [1,3])->get();

// Then queries for EACH listing's variation
foreach ($references as $reference) {
    // This could be optimized with eager loading
    $variationId = $this->resolveVariationIdForReference($reference);
    // Multiple queries per iteration
}
```

**Recommendations:**
1. Use **eager loading** - `with(['variation', 'country', 'currency'])`
2. **Batch queries** - Use `whereIn()` instead of individual queries
3. **Cache lookups** - Cache country/currency/variation mappings
4. Use **database indexes** - Optimize frequently queried columns

---

## API Call Issues

### Synchronous Blocking Calls

**Found in:**
- `BackMarketAPIController::apiGet()` - Uses cURL with 60s timeout
- `BackMarketAPIController::getAllListings()` - Makes calls per country
- `BackMarketAPIController::getListingCompetitors()` - Called per listing
- `RefreshNew::updateBMOrder()` - API call per order

**Issues:**
- ❌ **No connection pooling** - New connection per request
- ❌ **No request queuing** - All requests execute immediately
- ❌ **No rate limiting** - Can overwhelm API
- ❌ **Blocking execution** - Waits for each response

**Recommendations:**
1. Implement **HTTP connection pooling** - Reuse connections
2. Add **rate limiting** - Respect API rate limits (e.g., 100 req/min)
3. Use **async/queue** - Process API calls asynchronously
4. Implement **retry logic with backoff** - Handle rate limits gracefully
5. Add **caching layer** - Cache API responses for short periods

---

## Memory Usage Issues

### Large Dataset Loading

**Found in:**
- `PriceHandler` - Loads all listings into memory
- `FunctionsThirty` - Loads all listings from API
- `Functions::merge_order_transactions()` - Loads 1000 orders

**Issues:**
- ⚠️ **No chunking** - Loads entire dataset
- ⚠️ **Memory intensive** - Can cause memory exhaustion
- ⚠️ **No pagination** - Processes all at once

**Recommendations:**
1. Implement **chunking** - Process in batches of 50-100
2. Use **cursor()** - Process one record at a time
3. Add **memory limits** - Set `ini_set('memory_limit', '512M')`
4. Use **generators** - Lazy load data

---

## Scheduling Conflicts

### Overlapping Execution Windows

**Problem:** Multiple commands scheduled at similar times without overlap protection:

```
00:00 - refresh:new (runs every 2 min)
00:02 - refresh:new (another instance starts)
00:05 - refresh:orders + refresh:latest + api-request:process (all start together)
00:10 - price:handler + functions:ten + support:sync + bmpro:orders (all start together)
```

**Impact:** CPU spikes when multiple commands run simultaneously.

**Recommendations:**
1. **Stagger execution times** - Offset start times
2. Add `->withoutOverlapping()` to ALL commands
3. Use `->onOneServer()` for multi-server deployments
4. Monitor **command execution times** - Adjust intervals accordingly

---

## Priority Recommendations

### 🔴 **CRITICAL (Implement Immediately)**

1. **Add overlap protection to `refresh:new`**
   ```php
   $schedule->command('refresh:new')
       ->everyFiveMinutes()  // Increase from 2 minutes
       ->withoutOverlapping()
       ->runInBackground();
   ```

2. **Add overlap protection to `refresh:orders`**
   ```php
   $schedule->command('refresh:orders')
       ->everyFiveMinutes()
       ->withoutOverlapping()
       ->runInBackground();
   ```

3. **Add overlap protection to `refresh:latest`**
   ```php
   $schedule->command('refresh:latest')
       ->everyFiveMinutes()
       ->withoutOverlapping()
       ->runInBackground();
   ```

### 🟠 **HIGH PRIORITY (Implement Soon)**

4. **Implement chunking in `price:handler`**
   - Process listings in batches of 50-100
   - Add progress tracking
   - Use queue jobs for processing

5. **Optimize `functions:thirty`**
   - Add overlap protection
   - Implement pagination
   - Use bulk operations

6. **Add overlap protection to `functions:ten`**
   ```php
   $schedule->command('functions:ten')
       ->everyTenMinutes()
       ->withoutOverlapping()
       ->runInBackground();
   ```

### 🟡 **MEDIUM PRIORITY (Plan for Next Sprint)**

7. **Implement rate limiting for API calls**
   - Add rate limiter middleware
   - Queue API requests
   - Respect API rate limits

8. **Optimize database queries**
   - Add eager loading
   - Use batch queries
   - Add indexes

9. **Implement caching**
   - Cache API responses
   - Cache lookup tables
   - Use Redis for shared cache

### 🟢 **LOW PRIORITY (Nice to Have)**

10. **Monitor and alerting**
    - Add command execution monitoring
    - Alert on long-running commands
    - Track CPU/memory usage per command

11. **Refactor to queue jobs**
    - Move heavy operations to queue
    - Process asynchronously
    - Better resource management

---

## Expected Impact

### After Implementing Critical Fixes

**Current State:**
- CPU: 40-90% (peaks at 85-90%)
- Multiple overlapping commands
- High memory usage
- Slow response times

**Expected State:**
- CPU: 20-50% (peaks at 60-70%)
- No overlapping commands
- Reduced memory usage
- Faster execution

**Estimated CPU Reduction:**
- **Immediate (Critical fixes):** 30-40% reduction
- **After High Priority:** 50-60% reduction
- **After Medium Priority:** 60-70% reduction

---

## Implementation Checklist

### Phase 1: Critical Fixes (Week 1)
- [ ] Add `withoutOverlapping()` to `refresh:new`
- [ ] Add `withoutOverlapping()` to `refresh:orders`
- [ ] Add `withoutOverlapping()` to `refresh:latest`
- [ ] Increase `refresh:new` interval to 5 minutes
- [ ] Add `runInBackground()` to all high-frequency commands

### Phase 2: High Priority (Week 2)
- [ ] Implement chunking in `price:handler`
- [ ] Add overlap protection to `functions:ten`
- [ ] Optimize `functions:thirty` with pagination
- [ ] Add overlap protection to `api-request:process`

### Phase 3: Medium Priority (Week 3-4)
- [ ] Implement rate limiting for API calls
- [ ] Optimize database queries with eager loading
- [ ] Add database indexes
- [ ] Implement caching layer

### Phase 4: Monitoring (Ongoing)
- [ ] Add command execution logging
- [ ] Monitor CPU/memory usage
- [ ] Track command execution times
- [ ] Set up alerts for long-running commands

---

## Notes

- **Test changes in staging** before deploying to production
- **Monitor CPU usage** after each phase
- **Adjust intervals** based on actual execution times
- **Consider server upgrade** if issues persist after optimizations
- **Document command dependencies** - Some commands may need to run sequentially

---

**Report Generated:** January 2026  
**Next Review:** After Phase 1 implementation

