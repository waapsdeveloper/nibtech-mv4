# Slack Logging Loop Fix - Batch Collection Implementation

**Date:** 2024-12-19  
**Status:** ✅ Complete

---

## Problem

Posting logs to Slack inside loops (like iterating through arrays) was causing Slack to crash due to too many requests. Each iteration was posting immediately, which could result in hundreds of Slack webhook calls in a short time.

---

## Solution

Implemented **batch collection** mechanism in `SlackLogService` to:
1. Collect logs during loops instead of posting immediately
2. Aggregate logs by type and level
3. Post a single summary after the loop completes
4. Always log to file immediately (backup)

---

## Changes Made

### 1. Enhanced SlackLogService

**File:** `app/Services/V2/SlackLogService.php`

**New Methods:**
- `startBatch()` - Start batch mode (logs will be collected instead of posted)
- `collectBatch($logType, $level, $message, $context)` - Collect log for batch posting
- `postBatch()` - Post all collected logs as summaries
- `clearBatch()` - Clear batch buffer without posting

**Modified Method:**
- `post($logType, $level, $message, $context, $allowInLoop = false)` - Now checks if in batch mode and collects instead of posting

**Features:**
- Automatic batch mode detection
- Aggregation by log type and level
- Summary messages with sample logs
- Context aggregation
- Always logs to file immediately (backup)

---

### 2. Fixed Loops in BackMarketAPIController

**File:** `app/Http/Controllers/BackMarketAPIController.php`

#### Fixed Loop 1: `getAllListings()` - While Loop (Line 912-942)

**Before:**
```php
while (($result_next->next) != null) {
    // ... pagination logic ...
    if(!isset($result_next->results)){
        \App\Services\V2\SlackLogService::post('listing_api', 'warning', "Listing API: Missing results", [
            'response' => json_encode($result_next),
            'endpoint' => $end_point_next
        ]);
        break;
    }
    // ...
}
```

**After:**
```php
// Start batch mode to avoid spamming Slack if multiple pages fail
\App\Services\V2\SlackLogService::startBatch();

while (($result_next->next) != null) {
    // ... pagination logic ...
    if(!isset($result_next->results)){
        // Collect log instead of posting immediately (inside loop)
        \App\Services\V2\SlackLogService::collectBatch('listing_api', 'warning', "Listing API: Missing results (page {$page})", [
            'response' => json_encode($result_next),
            'endpoint' => $end_point_next,
            'page' => $page
        ]);
        break;
    }
    // ...
}

// Post batch summary after loop completes (only if there were errors)
\App\Services\V2\SlackLogService::postBatch();
```

#### Fixed Loop 2: `getAllListingsBi()` - Foreach Loop (Line 949-1010)

**Before:**
```php
foreach($country_codes as $id => $code){
    // ... API call ...
    if(!isset($result->results)){
        \App\Services\V2\SlackLogService::post('listing_api', 'warning', "ListingBI API: Missing results", [
            'endpoint' => $end_point,
            'response' => json_encode($result)
        ]);
        // ...
    }
    // ...
}
```

**After:**
```php
// Start batch mode to avoid spamming Slack if multiple countries fail
\App\Services\V2\SlackLogService::startBatch();

foreach($country_codes as $id => $code){
    // ... API call ...
    if(!isset($result->results)){
        // Collect log instead of posting immediately (inside loop)
        \App\Services\V2\SlackLogService::collectBatch('listing_api', 'warning', "ListingBI API: Missing results for country {$code}", [
            'endpoint' => $end_point,
            'response' => json_encode($result),
            'country_code' => $code,
            'country_id' => $id
        ]);
        // ...
    }
    // ...
}

// Post batch summary after loop completes (only if there were errors)
\App\Services\V2\SlackLogService::postBatch();
```

---

## How It Works

### Batch Collection Flow

1. **Start Batch Mode:**
   ```php
   SlackLogService::startBatch();
   ```

2. **Collect Logs in Loop:**
   ```php
   foreach ($items as $item) {
       // If error occurs, collect instead of posting
       SlackLogService::collectBatch('log_type', 'warning', "Error message", ['context' => 'data']);
       // Log is immediately written to file (backup)
   }
   ```

3. **Post Summary After Loop:**
   ```php
   SlackLogService::postBatch();
   // Posts aggregated summary to Slack (if any errors collected)
   ```

### Summary Format

Instead of posting multiple individual messages like:
```
⚠️ [warning] Listing API: Missing results (page 1)
⚠️ [warning] Listing API: Missing results (page 2)
⚠️ [warning] Listing API: Missing results (page 3)
...
```

It now posts a single aggregated summary:
```
⚠️ [warning] Batch Summary: 3 occurrence(s) of listing_api (warning)

Sample messages (showing 3 of 3):
• Listing API: Missing results (page 1)
• Listing API: Missing results (page 2)
• Listing API: Missing results (page 3)

Context:
{
  "total_occurrences": 3,
  "batch_size": 3,
  "page": [1, 2, 3],
  "endpoint": ["listings?page=1", "listings?page=2", "listings?page=3"]
}

Channel: #listing-api-logs
```

---

## Benefits

1. **Prevents Slack Overload**: No more hundreds of requests in short time
2. **Reduces Noise**: Single summary instead of multiple duplicate messages
3. **Better Context**: Aggregated context shows patterns across all errors
4. **File Backup**: Always logs to file immediately for debugging
5. **Smart Aggregation**: Groups by log type and level automatically

---

## Usage Examples

### Example 1: Simple Loop Fix

**Before:**
```php
foreach ($items as $item) {
    if ($error) {
        SlackLogService::post('item_processing', 'error', "Failed: {$item->id}");
    }
}
```

**After:**
```php
SlackLogService::startBatch();

foreach ($items as $item) {
    if ($error) {
        SlackLogService::collectBatch('item_processing', 'error', "Failed: {$item->id}", ['item_id' => $item->id]);
    }
}

SlackLogService::postBatch();
```

### Example 2: Nested Loops

```php
SlackLogService::startBatch();

foreach ($markets as $market) {
    foreach ($listings as $listing) {
        if ($error) {
            SlackLogService::collectBatch('listing_sync', 'warning', "Error syncing listing", [
                'market_id' => $market->id,
                'listing_id' => $listing->id
            ]);
        }
    }
}

SlackLogService::postBatch();
```

### Example 3: Manual Posting (Outside Loop)

If you need to post immediately (not in a loop), use `$allowInLoop = true`:

```php
// This will post immediately even if batch mode is active
SlackLogService::post('critical', 'error', 'Critical error!', [], true);
```

---

## Important Notes

1. **Always call `postBatch()` after loop completes** - Otherwise collected logs won't be posted
2. **Logs are always written to file** - Even when collecting for batch, they're logged to file immediately
3. **Batch mode is per-request** - Resets after `postBatch()` or `clearBatch()`
4. **Automatic detection** - Regular `post()` calls automatically collect when in batch mode (unless `$allowInLoop = true`)

---

## Testing Checklist

- [x] Batch collection works inside while loops
- [x] Batch collection works inside foreach loops
- [x] Batch collection works inside nested loops
- [x] Summary is posted after loop completes
- [x] Logs are written to file immediately
- [x] Empty batch doesn't post (no errors)
- [x] Multiple log types are grouped separately
- [x] Context is aggregated correctly

---

## Future Enhancements

Potential improvements:
- [ ] Rate limiting per channel (max 1 post per minute)
- [ ] Batching timeout (post after X seconds even if loop continues)
- [ ] Configurable batch size limits
- [ ] Summary templates (customizable format)
- [ ] Alert thresholds (only alert after X occurrences)

---

## Files Modified

- `app/Services/V2/SlackLogService.php` - Added batch collection methods
- `app/Http/Controllers/BackMarketAPIController.php` - Fixed 2 loops with batch collection

---

**End of Documentation**
