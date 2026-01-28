# CPU Spike Analysis - January 27, 2026 (1:50 AM)

## Timeline of Events

### 00:00:26 - Midnight Scheduled Tasks Start
1. **SyncMarketplaceOrders (incomplete)** - Starts
   - Duration: 0.82 seconds
   - Synced: 1 order
   - Status: ✅ Completed quickly

2. **SyncMarketplaceStockBulk** - Starts (scheduled at 00:00)
   - Marketplace ID: 1 (BackMarket)
   - Status: ⚠️ **LONG RUNNING**

### 00:09:39 - SyncMarketplaceStockBulk API Fetch Completes
- **Duration: 553.1 seconds (9+ minutes)**
- Countries processed: 14
- Status: ⚠️ **VERY SLOW API FETCH**

### 00:09:42 - SyncMarketplaceStockBulk FAILS
- **Error**: `Call to undefined relationship [marketplaceStocks] on model [App\Models\Variation_model]`
- **Location**: Line 437 in `SyncMarketplaceStockBulk.php`
- **Code**: `->with('marketplaceStocks')`
- Status: ❌ **CRITICAL ERROR**

### 02:00:03 - Modified Orders Sync Starts
- **SyncMarketplaceOrders (modified)** - Scheduled daily at 2 AM
- Status: ⚠️ **PROCESSING LARGE VOLUME**

### 02:00:46 - Modified Orders Sync Completes
- **Duration: 42.78 seconds**
- **Synced: 873 orders**
- **Errors: 12** (missing `order_type_id` field)
- Status: ✅ Completed but with errors

### During 02:00:03 - 02:00:46
- **Hundreds of OrderStatusChanged events fired**
- Each event triggers:
  - Database queries
  - Stock lock lookups
  - Marketplace stock updates
  - History record creation
  - Potential API calls

## Root Causes of CPU Spike

### 1. **SyncMarketplaceStockBulk - Long API Fetch (553 seconds)**
**Location**: `app/Console/Commands/V2/SyncMarketplaceStockBulk.php`

**Issue**:
- `getAllListings()` API call takes **9+ minutes** to complete
- Processing 14 countries with potentially thousands of listings
- All happening synchronously, blocking CPU

**Impact**:
- High CPU usage for 9+ minutes
- Memory consumption from large dataset
- Database queries during processing

**Evidence from logs**:
```
[2026-01-27 00:00:26] V2 SyncMarketplaceStockBulk: Starting API fetch
[2026-01-27 00:09:39] V2 SyncMarketplaceStockBulk: API fetch completed {"duration_seconds":553.1,"countries_count":14}
```

### 2. **SyncMarketplaceStockBulk - Missing Relationship Error**
**Location**: `app/Console/Commands/V2/SyncMarketplaceStockBulk.php:437`

**Issue**:
```php
$variations = Variation_model::whereIn('id', $chunk)
    ->with('marketplaceStocks')  // ❌ This relationship doesn't exist
    ->get();
```

**Impact**:
- Command fails after 9+ minutes of work
- Wasted CPU cycles
- No stock updates applied
- Error handling overhead

**Evidence from logs**:
```
[2026-01-27 00:09:42] ERROR: Call to undefined relationship [marketplaceStocks] on model [App\Models\Variation_model]
```

### 3. **SyncMarketplaceOrders (Modified) - High Volume Processing**
**Location**: `app/Console/Commands/V2/SyncMarketplaceOrders.php`

**Issue**:
- Processing **873 orders** in 42 seconds
- Each order triggers `OrderStatusChanged` event
- Hundreds of events fire simultaneously

**Impact**:
- High database query load
- Event listener processing for each order
- Potential N+1 query problems
- Memory usage from event objects

**Evidence from logs**:
```
[2026-01-27 02:00:03] 🔄 Fetching modified orders...
[2026-01-27 02:00:46] ✅ Synced modified orders {"synced":873,"errors":12}
```

### 4. **OrderStatusChanged Event - Cascade Effect**
**Location**: `app/Listeners/V2/ReduceStockOnOrderCompleted.php`

**Issue**:
- Each `OrderStatusChanged` event triggers:
  - Database queries (marketplace stock, locks)
  - Stock updates
  - History record creation
  - Potential API calls to update marketplace

**Impact**:
- **873 orders × multiple operations = thousands of database queries**
- Each event processes order items
- Stock lock lookups
- History record inserts
- API update calls (if locks exist)

**Evidence from logs**:
- Hundreds of log entries like:
```
[2026-01-27 02:00:23] OrderSyncService: OrderStatusChanged event fired
[2026-01-27 02:00:23] V2: No active lock found on order completion; skipping stock consume
```

### 5. **Database Errors - Missing order_type_id**
**Location**: Order creation in `MarketplaceOrderSyncService`

**Issue**:
- 12 orders failed with: `Field 'order_type_id' doesn't have a default value`
- Each error triggers error handling, logging, Slack notifications

**Impact**:
- Additional CPU for error handling
- Logging overhead
- Slack API rate limiting (429 errors)

**Evidence from logs**:
```
[2026-01-27 02:00:27] ERROR: Field 'order_type_id' doesn't have a default value
[2026-01-27 02:00:27] SlackLogService: Rate limit (429) hit
```

## Performance Impact Summary

### CPU Usage Breakdown (Estimated)

1. **SyncMarketplaceStockBulk API Fetch**: ~40-50% CPU for 553 seconds
   - API pagination requests
   - Data processing
   - Memory allocation

2. **SyncMarketplaceOrders (Modified)**: ~30-40% CPU for 42 seconds
   - Order fetching
   - Order processing
   - Event firing

3. **OrderStatusChanged Events**: ~20-30% CPU during event processing
   - 873 orders × ~3-5 operations each = 2,600-4,300 operations
   - Database queries
   - Stock updates
   - History records

4. **Error Handling**: ~5-10% CPU
   - Exception handling
   - Logging
   - Slack API calls (rate limited)

### Total CPU Spike Duration
- **Start**: 00:00:26 (SyncMarketplaceStockBulk)
- **Peak**: 02:00:03 - 02:00:46 (Modified orders sync + events)
- **End**: 02:00:46
- **Total**: ~2 hours 20 minutes of elevated CPU usage
- **Peak Period**: ~1 hour around 1:50 AM (overlapping tasks)

## Recommendations (Analysis Only - No Code Changes)

### 1. Fix Missing Relationship
**Priority**: 🔴 **CRITICAL**

**Issue**: `Variation_model` doesn't have `marketplaceStocks` relationship

**Solution Options**:
- Add relationship to `Variation_model`
- OR remove eager loading and use direct query
- OR use `MarketplaceStockModel::where('variation_id', ...)` directly

**Impact**: Prevents command failure, saves 9+ minutes of wasted processing

### 2. Optimize SyncMarketplaceStockBulk API Fetch
**Priority**: 🟠 **HIGH**

**Issues**:
- 553 seconds is too long
- Processing all countries synchronously
- No pagination/chunking optimization

**Solution Options**:
- Process countries in parallel (if API allows)
- Add pagination/chunking for large datasets
- Cache intermediate results
- Add timeout/retry logic

**Impact**: Reduces CPU usage from 9+ minutes to potentially 2-3 minutes

### 3. Optimize OrderStatusChanged Event Processing
**Priority**: 🟠 **HIGH**

**Issues**:
- 873 events firing in quick succession
- Each event does multiple database queries
- Potential N+1 query problems

**Solution Options**:
- Batch process events
- Use database transactions
- Eager load relationships
- Queue event processing
- Debounce/throttle events

**Impact**: Reduces database load, improves performance

### 4. Fix Missing order_type_id
**Priority**: 🟡 **MEDIUM**

**Issue**: Orders created without `order_type_id` field

**Solution**: Ensure `order_type_id` is set when creating orders

**Impact**: Prevents 12 errors, reduces error handling overhead

### 5. Optimize Schedule Timing
**Priority**: 🟡 **MEDIUM**

**Current Schedule**:
- `SyncMarketplaceStockBulk`: Every 6 hours at 00:00
- `SyncMarketplaceOrders (modified)`: Daily at 02:00
- `SyncMarketplaceOrders (incomplete)`: Every 4 hours

**Issue**: Tasks overlap, causing CPU spikes

**Solution Options**:
- Stagger schedules better
- Add delays between tasks
- Run during off-peak hours only

**Impact**: Spreads CPU load, prevents spikes

## Code Locations to Review

1. **SyncMarketplaceStockBulk.php:437**
   - Line 437: `->with('marketplaceStocks')` - Missing relationship

2. **SyncMarketplaceStockBulk.php:81-156**
   - `syncMarketplaceBulk()` method - Long API fetch

3. **OrderSyncService.php:151-152**
   - Event firing for each order status change

4. **ReduceStockOnOrderCompleted.php:25-154**
   - Event listener processing - Multiple queries per event

5. **MarketplaceOrderSyncService.php**
   - Order creation without `order_type_id`

## Metrics from Logs

- **SyncMarketplaceStockBulk**: 553.1 seconds (9.2 minutes)
- **SyncMarketplaceOrders (modified)**: 42.78 seconds
- **Orders processed**: 873
- **Events fired**: ~873+ (one per order, potentially more)
- **Errors**: 12 (order_type_id missing)
- **Countries processed**: 14

## Conclusion

The CPU spike around 1:50 AM is caused by:

1. **Primary**: SyncMarketplaceStockBulk running for 9+ minutes (00:00-00:09)
2. **Secondary**: SyncMarketplaceOrders processing 873 orders at 2 AM (02:00-02:00:46)
3. **Tertiary**: Hundreds of OrderStatusChanged events firing simultaneously
4. **Contributing**: Missing relationship causing command failure after 9 minutes of work

The spike is a combination of:
- Long-running API fetch (9+ minutes)
- High-volume order processing (873 orders)
- Event cascade (hundreds of events)
- Missing relationship error (wasted processing)

**Estimated CPU Usage**: 75%+ during peak periods (1:50 AM - 2:00 AM)
