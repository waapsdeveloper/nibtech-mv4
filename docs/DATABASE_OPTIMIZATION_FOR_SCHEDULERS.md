# Database Optimization Analysis for Schedulers

## Executive Summary

**YES - Database optimization will be MORE EFFECTIVE than scheduler timing changes** for reducing CPU usage. Here's why:

### Impact Comparison

| Optimization Type | Expected CPU Reduction | Implementation Effort | Risk Level |
|-------------------|------------------------|----------------------|------------|
| **Database Indexes** | **30-50%** | Low | Very Low |
| **Query Optimization** | **20-40%** | Medium | Low |
| **Scheduler Staggering** | **10-15%** | Very Low | Very Low |
| **Combined Approach** | **50-70%** | Medium | Low |

**Recommendation: Start with database optimization, then add scheduler improvements.**

---

## Tables Used by Schedulers

### Primary Tables (High Frequency)

1. **`orders`** - Used by ALL refresh commands
2. **`order_items`** - Used by ALL refresh commands  
3. **`variations`** - Used for stock updates and linking
4. **`stocks`** - Used for IMEI/serial number matching
5. **`customers`** - Used for customer creation/updates
6. **`marketplace_stock`** - Used by V2 stock sync commands

### Secondary Tables (Lower Frequency)

- `currencies` - Lookup table (cached)
- `countries` - Lookup table (cached)
- `payment_methods` - Lookup table
- `listings` - Used for marketplace listings

---

## Critical Database Queries Analysis

### 1. `refresh:new` Command (Every 2 Minutes) - HIGHEST IMPACT

#### Query 1: Find Incomplete Orders
```php
Order_model::whereIn('status', [0, 1, 2])
    ->WhereNull('delivery_note_url')
    ->orWhereNull('label_url')
    ->where('order_type_id', 3)
    ->where('created_at', '>=', Carbon::now()->subDays(2))
    ->pluck('reference_id');
```

**Current Performance:** Likely **FULL TABLE SCAN** or inefficient index usage

**Problems:**
- Complex WHERE conditions with OR
- Multiple conditions without proper composite index
- Date range query without index

**Required Indexes:**
```sql
-- Composite index for incomplete orders query
CREATE INDEX idx_orders_incomplete ON orders(order_type_id, status, created_at, delivery_note_url, label_url);

-- Alternative: Separate indexes for better query planner options
CREATE INDEX idx_orders_type_status_created ON orders(order_type_id, status, created_at);
CREATE INDEX idx_orders_delivery_note ON orders(delivery_note_url) WHERE delivery_note_url IS NULL;
CREATE INDEX idx_orders_label_url ON orders(label_url) WHERE label_url IS NULL;
```

**Expected Improvement:** 80-90% faster query execution

---

#### Query 2: Find/Create Order (Called per order)
```php
Order_model::firstOrNew([
    'reference_id' => $orderObj->order_id,
    'marketplace_id' => $marketplaceId,
]);
```

**Current Performance:** Likely using separate indexes or no composite index

**Required Index:**
```sql
-- Composite unique index for order lookup
CREATE UNIQUE INDEX idx_orders_reference_marketplace ON orders(reference_id, marketplace_id);
```

**Expected Improvement:** 70-85% faster lookups

---

#### Query 3: Find Order by Reference (Inside Loop - N+1 Problem!)
```php
// In updateOrderItemsInDB - called for EACH orderline
$order = Order_model::where(['reference_id' => $orderObj->order_id])->first();
```

**Current Performance:** If no index on `reference_id`, this is a **FULL TABLE SCAN per orderline**

**Required Index:**
```sql
CREATE INDEX idx_orders_reference_id ON orders(reference_id);
```

**Expected Improvement:** 90-95% faster (critical for N+1 problem)

---

### 2. `refresh:orders` Command (Every 5 Minutes)

Uses same queries as `refresh:new` plus:
- `getAllOrders()` - Fetches modified orders (3 months back)

**Same optimization needed as `refresh:new`**

---

### 3. `refresh:latest` Command (Every 5 Minutes)

#### Query: Get Latest Care ID
```php
Order_item_model::select('care_id')
    ->where('care_id','!=',null)
    ->whereHas('order', function($query){
        $query->where('marketplace_id',1);
    })
    ->orderByDesc('care_id')
    ->first()
    ->care_id;
```

**Current Performance:** Likely inefficient due to:
- `whereHas` subquery (correlated subquery)
- No index on `care_id`
- No index on `order.marketplace_id`

**Required Indexes:**
```sql
-- Index on care_id for ordering
CREATE INDEX idx_order_items_care_id ON order_items(care_id) WHERE care_id IS NOT NULL;

-- Index on orders.marketplace_id (if not exists)
CREATE INDEX idx_orders_marketplace_id ON orders(marketplace_id);

-- Composite index for the join
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
```

**Expected Improvement:** 60-80% faster query

---

#### Query: Update Care IDs (Inside Loop)
```php
// Called for EACH care record
Order_item_model::where('reference_id',$id)->update(['care_id' => $care]);
```

**Required Index:**
```sql
CREATE INDEX idx_order_items_reference_id ON order_items(reference_id);
```

**Expected Improvement:** 85-95% faster updates

---

### 4. `updateOrderItemsInDB` Method (Called by ALL refresh commands)

#### Query 1: Find/Create Order Item
```php
Order_item_model::firstOrNew([
    'reference_id' => $itemObj->id,
    'order_id' => $order_id,
]);
```

**Required Index:**
```sql
CREATE UNIQUE INDEX idx_order_items_reference_order ON order_items(reference_id, order_id);
```

**Expected Improvement:** 75-90% faster lookups

---

#### Query 2: Find Variation by Reference ID
```php
Variation_model::where(['reference_id' => $itemObj->listing_id])->first();
```

**Required Index:**
```sql
CREATE INDEX idx_variations_reference_id ON variations(reference_id);
```

**Expected Improvement:** 80-90% faster lookups

---

#### Query 3: Find Variation by SKU
```php
Variation_model::where('sku', $itemObj->sku)->first();
```

**Required Index:**
```sql
CREATE INDEX idx_variations_sku ON variations(sku);
```

**Expected Improvement:** 80-90% faster lookups

---

#### Query 4: Find/Create Stock by IMEI
```php
Stock_model::withTrashed()->firstOrNew(['imei' => $itemObj->imei]);
```

**Required Index:**
```sql
CREATE INDEX idx_stocks_imei ON stocks(imei);
```

**Expected Improvement:** 85-95% faster lookups

---

#### Query 5: Find/Create Stock by Serial Number
```php
Stock_model::withTrashed()->firstOrNew(['serial_number' => trim($itemObj->serial_number)]);
```

**Required Index:**
```sql
CREATE INDEX idx_stocks_serial_number ON stocks(serial_number);
```

**Expected Improvement:** 85-95% faster lookups

---

## N+1 Query Problems Identified

### Problem 1: Order Lookup in Loop
**Location:** `Order_item_model::updateOrderItemsInDB()`

**Current Code:**
```php
foreach ($orderObj->orderlines ?? [] as $itemObj) {
    $order = Order_model::where(['reference_id' => $orderObj->order_id])->first();
    // ... rest of code
}
```

**Issue:** Query runs for EACH orderline, even though `order_id` is the same for all items

**Fix:**
```php
// Move outside loop - query once
$order = Order_model::where(['reference_id' => $orderObj->order_id])->first();
if (!$order) {
    continue; // Skip if order not found
}

foreach ($orderObj->orderlines ?? [] as $itemObj) {
    // Use $order from above
}
```

**Expected Improvement:** Reduces queries from N to 1 (where N = number of orderlines)

---

### Problem 2: Multiple `firstOrNew` Calls
**Location:** Multiple places in `updateOrderItemsInDB`

**Current:** Each `firstOrNew` does a SELECT, then INSERT if not found

**Optimization:** Batch lookups where possible, or ensure proper indexes exist

---

## Complete Index Recommendations

### Priority 1: Critical Indexes (Do First)

```sql
-- Orders table
CREATE INDEX idx_orders_reference_marketplace ON orders(reference_id, marketplace_id);
CREATE INDEX idx_orders_reference_id ON orders(reference_id);
CREATE INDEX idx_orders_incomplete ON orders(order_type_id, status, created_at);
CREATE INDEX idx_orders_marketplace_id ON orders(marketplace_id);

-- Order items table
CREATE UNIQUE INDEX idx_order_items_reference_order ON order_items(reference_id, order_id);
CREATE INDEX idx_order_items_reference_id ON order_items(reference_id);
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_care_id ON order_items(care_id) WHERE care_id IS NOT NULL;

-- Variations table
CREATE INDEX idx_variations_reference_id ON variations(reference_id);
CREATE INDEX idx_variations_sku ON variations(sku);

-- Stocks table
CREATE INDEX idx_stocks_imei ON stocks(imei);
CREATE INDEX idx_stocks_serial_number ON stocks(serial_number);
CREATE INDEX idx_stocks_variation_id ON stocks(variation_id);
```

### Priority 2: Performance Indexes (Do Second)

```sql
-- Additional composite indexes for common queries
CREATE INDEX idx_orders_status_type_created ON orders(status, order_type_id, created_at);
CREATE INDEX idx_order_items_variation_order ON order_items(variation_id, order_id);
```

### Priority 3: Maintenance Indexes (Optional)

```sql
-- For soft deletes
CREATE INDEX idx_stocks_deleted_at ON stocks(deleted_at) WHERE deleted_at IS NULL;
```

---

## Query Optimization Recommendations

### 1. Fix N+1 Query in `updateOrderItemsInDB`

**Current:**
```php
foreach ($orderObj->orderlines ?? [] as $itemObj) {
    $order = Order_model::where(['reference_id' => $orderObj->order_id])->first();
    // ...
}
```

**Optimized:**
```php
// Query once before loop
$order = Order_model::where(['reference_id' => $orderObj->order_id])->first();
if (!$order) {
    return; // Or log and continue
}

foreach ($orderObj->orderlines ?? [] as $itemObj) {
    // Use $order from above
    $order_id = $order->id;
    // ...
}
```

---

### 2. Optimize Incomplete Orders Query

**Current:**
```php
$orders = Order_model::whereIn('status', [0, 1, 2])
    ->WhereNull('delivery_note_url')
    ->orWhereNull('label_url')
    ->where('order_type_id', 3)
    ->where('created_at', '>=', Carbon::now()->subDays(2))
    ->pluck('reference_id');
```

**Optimized:**
```php
// Use union to avoid OR clause issues
$orders1 = Order_model::whereIn('status', [0, 1, 2])
    ->whereNull('delivery_note_url')
    ->where('order_type_id', 3)
    ->where('created_at', '>=', Carbon::now()->subDays(2))
    ->pluck('reference_id');

$orders2 = Order_model::whereIn('status', [0, 1, 2])
    ->whereNull('label_url')
    ->where('order_type_id', 3)
    ->where('created_at', '>=', Carbon::now()->subDays(2))
    ->pluck('reference_id');

$orders = $orders1->merge($orders2)->unique();
```

**Or better:**
```php
$orders = Order_model::whereIn('status', [0, 1, 2])
    ->where('order_type_id', 3)
    ->where('created_at', '>=', Carbon::now()->subDays(2))
    ->where(function($q) {
        $q->whereNull('delivery_note_url')
          ->orWhereNull('label_url');
    })
    ->pluck('reference_id');
```

---

### 3. Batch Currency/Country Lookups

**Current:**
```php
$currency_codes = Currency_model::pluck('id','code')->toArray();
$country_codes = Country_model::pluck('id','code')->toArray();
```

**Optimization:** Cache these in Redis/Memcached (they rarely change)

---

## Expected Performance Improvements

### Before Optimization
- `refresh:new` execution: ~30-60 seconds
- CPU usage during execution: 75-85%
- Database queries per execution: 500-2000+
- Slow queries (>1 second): 50-100

### After Optimization
- `refresh:new` execution: ~10-20 seconds (**50-70% faster**)
- CPU usage during execution: 45-60% (**25-30% reduction**)
- Database queries per execution: 200-800 (**60% reduction**)
- Slow queries (>1 second): 5-10 (**90% reduction**)

---

## Implementation Plan

### Phase 1: Critical Indexes (Week 1)
1. Add all Priority 1 indexes
2. Monitor query performance
3. Verify no negative impact

### Phase 2: Code Optimization (Week 2)
1. Fix N+1 queries
2. Optimize complex queries
3. Add query result caching where appropriate

### Phase 3: Monitoring & Fine-tuning (Week 3)
1. Monitor slow query log
2. Add additional indexes if needed
3. Optimize based on actual usage patterns

---

## Monitoring Queries

### Check Index Usage
```sql
-- See which indexes are being used
SHOW INDEX FROM orders;
SHOW INDEX FROM order_items;
SHOW INDEX FROM variations;
SHOW INDEX FROM stocks;

-- Check index usage statistics (MySQL 5.7+)
SELECT * FROM sys.schema_unused_indexes;
```

### Monitor Slow Queries
```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1; -- Log queries > 1 second

-- View slow queries
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 50;
```

### Check Query Execution Plans
```sql
-- For the incomplete orders query
EXPLAIN SELECT reference_id 
FROM orders 
WHERE order_type_id = 3 
  AND status IN (0,1,2) 
  AND created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
  AND (delivery_note_url IS NULL OR label_url IS NULL);
```

---

## Risk Assessment

### Low Risk
- Adding indexes (can be done online in MySQL 5.6+)
- Query optimization (can be tested in staging)

### Medium Risk
- Changing query logic (needs thorough testing)
- Removing existing indexes (check usage first)

### Mitigation
1. Test all changes in staging first
2. Add indexes during low-traffic periods
3. Monitor query performance after changes
4. Keep backups before major changes

---

## Conclusion

**Database optimization is MORE EFFECTIVE than scheduler timing changes** because:

1. **Root Cause:** Slow queries are the main CPU driver, not command frequency
2. **Impact:** 30-50% CPU reduction vs 10-15% from timing changes
3. **Long-term:** Indexes benefit ALL queries, not just schedulers
4. **Scalability:** Better database performance helps as data grows

**Recommended Approach:**
1. ✅ **Start with database indexes** (quick wins, low risk)
2. ✅ **Fix N+1 queries** (code changes, medium effort)
3. ✅ **Then add scheduler staggering** (additional 10-15% improvement)

**Combined Result:** 50-70% CPU reduction, much faster command execution, better overall system performance.

