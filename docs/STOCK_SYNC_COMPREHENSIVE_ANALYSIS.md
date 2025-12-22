# Comprehensive Stock Sync Analysis

## Executive Summary

This document provides a comprehensive analysis of three critical aspects of the stock synchronization system:

1. **Back Market Stock Sync Analysis** - Current implementation status
2. **Event-Driven Stock Sync Strategy** - Proposed optimization approach
3. **Listed Stock Verification Table** - Audit trail and verification tracking

---

## 1. Back Market Stock Sync Analysis

### Current Implementation Status

#### ✅ Original Listing Page - FULLY IMPLEMENTED

**On Page Load:**
- **Location:** `resources/views/listings.blade.php` (Line 1330)
- **Function:** `fetchUpdatedQuantity(variation.id)` called for each variation
- **Backend:** `listing/get_updated_quantity/{id}` endpoint
- **Flow:**
  1. JavaScript AJAX call on page load
  2. Controller calls `$variation->update_qty($bm)`
  3. Model fetches from Back Market API via `getOneListing()`
  4. Updates `listed_stock`, `sku`, and `state` in database
  5. Returns updated quantity to frontend
  6. Frontend updates quantity input field

**Scheduled Sync:**
- **Command:** `app/Console/Commands/FunctionsThirty.php`
- **Schedule:** Hourly (via `app/Console/Kernel.php`)
- **Method:** `get_listings()` and `get_listingsBi()`
- **Flow:**
  1. Fetches ALL listings from Back Market API
  2. For each listing, finds matching variation by `reference_id` and `sku`
  3. Updates `listed_stock` from API response
  4. Saves variation

#### ❌ V2 Listing Page - NOT IMPLEMENTED

**Missing Functionality:**
- ❌ No automatic stock sync on page load
- ❌ No `fetchUpdatedQuantity` function
- ❌ No `getUpdatedQuantity` endpoint in V2 controller
- ❌ No route for stock sync
- ✅ Relies only on scheduled `FunctionsThirty` command

**Impact:**
- Users see stale stock data between scheduled runs
- No real-time stock updates when opening V2 listing page
- Data accuracy issues for time-sensitive operations

### Comparison Table

| Feature | Original Listing | V2 Listing | Status |
|---------|-----------------|------------|--------|
| Auto-sync on page load | ✅ Yes | ❌ No | **MISSING** |
| Scheduled sync (hourly) | ✅ Yes | ✅ Yes | **WORKING** |
| Backend endpoint | ✅ Yes | ❌ No | **MISSING** |
| Frontend function | ✅ Yes | ❌ No | **MISSING** |
| Updates on demand | ✅ Yes | ❌ No | **MISSING** |

### API Call Overhead

**Current System:**
- **Page Loads:** ~1,000-5,000 API calls/day (10 variations/page × 100-500 page loads)
- **Scheduled Sync:** ~480 API calls/day (20 calls/hour × 24 hours)
- **Total:** ~1,500-5,500 API calls/day

**Issues:**
- High API rate limit usage
- Slow page loads (2-5 seconds waiting for API)
- Synchronous blocking
- No caching mechanism

---

## 2. Event-Driven Stock Sync Strategy

### Proposed Solution

**Core Concept:** Instead of **pulling** stock from Back Market, **track** stock changes locally based on events.

### Architecture

```
Order Created → Event Listener → Stock Tracker → Marketplace Stock Table
Order Cancelled → Event Listener → Stock Tracker → Marketplace Stock Table
Stock Top-up → Event Listener → Stock Tracker → Marketplace Stock Table
```

### Database Schema

#### Existing: `marketplace_stock`
- `variation_id`
- `marketplace_id`
- `listed_stock`
- `last_synced_at` (needs to be added)
- `last_api_quantity` (needs to be added)

#### Proposed: `marketplace_stock_history` (NEW)
- `marketplace_stock_id`
- `variation_id`
- `marketplace_id`
- `quantity_before`
- `quantity_after`
- `quantity_change`
- `change_type` (order_created, order_cancelled, topup, manual, reconciliation, api_sync)
- `order_id` (nullable)
- `order_item_id` (nullable)
- `reference_id` (nullable)
- `admin_id` (nullable)
- `notes` (nullable)
- `created_at`

### Event Types

1. **OrderCreated** - Decrease stock when order is created
2. **OrderCancelled** - Increase stock when order is cancelled
3. **StockTopup** - Increase stock when manually added
4. **Reconciliation** - Periodic verification (daily)

### Integration Points

**Files to Modify:**
- `app/Console/Commands/RefreshOrders.php`
- `app/Console/Commands/RefreshNew.php`
- `app/Jobs/UpdateOrderInDB.php`
- `app/Http/Controllers/RefurbedWebhookController.php`
- `app/Http/Controllers/ListingController.php` (add_quantity method)
- `app/Http/Controllers/V2/ListingController.php` (add_quantity method)

### Reconciliation Strategy

**Command:** `app/Console/Commands/ReconcileMarketplaceStock.php`
- **Schedule:** Daily at 2 AM
- **Purpose:** Catch discrepancies from:
  - Manual changes on marketplace
  - API errors
  - System bugs
  - Missed events

### Benefits

**API Call Reduction:**
- **Before:** ~1,500-5,500 calls/day
- **After:** ~1,050-1,200 calls/day
- **Reduction:** 70-80% 🎉

**Performance Improvements:**
- Page load time: <200ms (from 2-5 seconds)
- Instant stock display
- No loading spinners
- Real-time updates via events

**Data Benefits:**
- Historical tracking of stock changes
- Audit trail for compliance
- Analytics on stock movement patterns
- Better data integrity

### Migration Plan

**Phase 1:** Setup (Week 1)
- Create `marketplace_stock_history` table
- Add `last_synced_at` and `last_api_quantity` columns
- Create Event classes
- Create Listener classes

**Phase 2:** Initial Data Population (Week 1)
- Run one-time sync to populate `marketplace_stock`
- Create initial history records

**Phase 3:** Event Integration (Week 2)
- Add event firing to order sync commands
- Add event firing to webhook handlers
- Add event firing to stock top-up functions

**Phase 4:** Frontend Update (Week 2)
- Remove `fetchUpdatedQuantity` from listing page
- Display stock from `marketplace_stock` table
- Add "Last Synced" timestamp display

**Phase 5:** Reconciliation (Week 3)
- Implement reconciliation command
- Schedule daily reconciliation
- Monitor discrepancies

**Phase 6:** Deprecation (Week 4)
- Remove hourly `FunctionsThirty` stock sync
- Monitor for 1 week
- Full rollout

---

## 3. Listed Stock Verification Table Analysis

### Purpose

The `listed_stock_verification` table serves as an **audit trail** for stock changes during topup and verification processes. It tracks every stock modification with detailed context.

### Table Structure

```sql
CREATE TABLE listed_stock_verification (
    id BIGINT PRIMARY KEY,
    process_id BIGINT,           -- Links to Process_model (process_type_id: 21=verification, 22=topup)
    variation_id BIGINT,          -- Variation that was modified
    pending_orders INT,            -- Number of pending orders at time of change
    qty_from INT,                 -- Stock quantity before change
    qty_change INT,               -- Amount of stock added/removed
    qty_to INT,                   -- Stock quantity after change
    admin_id INT,                 -- Admin user who made the change
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP          -- Soft deletes enabled
);
```

### Usage Locations

#### 1. Stock Top-up Operations

**Original Listing Controller:**
- **File:** `app/Http/Controllers/ListingController.php`
- **Method:** `add_quantity()` (Lines 911-919)
- **Method:** `add_quantity_marketplace()` (Lines 1024-1033)
- **Purpose:** Records every stock addition during topup process

**V2 Listing Controller:**
- **File:** `app/Http/Controllers/V2/ListingController.php`
- **Method:** `add_quantity()` (Lines 1039-1047)
- **Purpose:** Same as original - records stock additions

**Flow:**
1. Admin adds stock to variation
2. System calculates new quantity
3. Updates Back Market API
4. Creates `listed_stock_verification` record with:
   - `qty_from`: Previous stock quantity
   - `qty_change`: Amount added
   - `qty_to`: New stock quantity
   - `pending_orders`: Current pending orders count
   - `process_id`: Topup process ID (if part of batch)

#### 2. Stock Verification Process

**Livewire Component:**
- **File:** `app/Http/Livewire/ListedStockVerification.php`
- **Purpose:** Manages stock verification batches (process_type_id = 21)

**Key Methods:**
- `verification_detail($process_id)` - Displays verification details
- `start_listing_verification()` - Starts new verification batch
- Uses `listed_stock_verification` to:
  - Show last 10 changes (Line 184)
  - Display changed vs unchanged stocks (Lines 192-204)
  - Track verification progress

**Topup Component:**
- **File:** `app/Http/Livewire/Topup.php`
- **Purpose:** Manages stock topup batches (process_type_id = 22)
- **Usage:**
  - Checks existing verification records (Line 119)
  - Prevents duplicate stock additions (Lines 122-127)
  - Validates total scanned vs pushed (Lines 158-162)
  - Removes duplicate verification records (Lines 184-199)

#### 3. Verification Record Display

**View Files:**
- `resources/views/livewire/listed_stock_verification.blade.php`
- `resources/views/livewire/listed_stock_verification_detail.blade.php`

**Display Logic:**
- Shows batches grouped by `process_id`
- Displays changed stocks (`qty_from != qty_to`)
- Displays unchanged stocks (`qty_from == qty_to`)
- Shows verification history with admin details

### Data Flow

```
Stock Top-up Process:
1. Admin scans stock items → Process_stock_model records created
2. Admin clicks "Push" → add_quantity() called for each variation
3. Stock updated in database and Back Market API
4. listed_stock_verification record created
5. Process validated: scanned_total == pushed_total
6. Process marked as complete (status = 3)

Stock Verification Process:
1. Admin starts verification batch → Process_model created (type 21)
2. System compares current stock vs expected stock
3. listed_stock_verification records show all changes
4. Admin reviews and approves changes
5. Process marked as verified (status = 3)
```

### Key Relationships

**Process Model:**
- `process_type_id = 21` → Stock Verification
- `process_type_id = 22` → Stock Topup
- Links multiple `listed_stock_verification` records

**Variation Model:**
- Each verification record links to one variation
- Tracks stock changes per variation

**Admin Model:**
- Records which admin made each change
- Enables audit trail

### Use Cases

1. **Audit Trail:**
   - Track who changed stock and when
   - See stock quantity before and after changes
   - Review pending orders at time of change

2. **Verification:**
   - Compare scanned stock vs pushed stock
   - Identify discrepancies in topup batches
   - Validate stock changes are correct

3. **Reconciliation:**
   - Match physical inventory to listed stock
   - Verify stock additions match scanned items
   - Detect duplicate or missing stock entries

4. **Reporting:**
   - Historical stock change analysis
   - Admin activity tracking
   - Process completion validation

### Current Limitations

1. **No Marketplace Context:**
   - Records don't include `marketplace_id`
   - Can't track stock changes per marketplace
   - Limited to total stock changes only

2. **No Change Type:**
   - Doesn't distinguish between:
     - Manual topup
     - Order-driven change
     - Reconciliation
     - API sync

3. **Limited History:**
   - Only tracks changes during processes
   - Doesn't track all stock changes
   - Missing automatic stock sync changes

4. **No Integration with Event System:**
   - Not connected to proposed event-driven system
   - Manual recording only
   - Could be enhanced to auto-record events

### Recommendations

1. **Enhance Schema:**
   - Add `marketplace_id` column (nullable)
   - Add `change_type` enum column
   - Add `order_id` column for order-driven changes
   - Add `notes` column for additional context

2. **Integration with Event System:**
   - Auto-create verification records on events
   - Link to `marketplace_stock_history` table
   - Provide unified audit trail

3. **Reporting Enhancements:**
   - Dashboard for stock change analytics
   - Filter by marketplace, admin, date range
   - Export verification reports

---

## 4. Integration Opportunities

### Connecting All Three Systems

**Current State:**
- Back Market sync: Pull-based, high API usage
- Event-driven strategy: Proposed, not implemented
- Verification table: Manual recording only

**Proposed Integration:**

1. **Event-Driven System + Verification Table:**
   - Auto-create `listed_stock_verification` records on events
   - Link to `marketplace_stock_history` for complete audit trail
   - Track both manual and automatic changes

2. **Event-Driven System + Back Market Sync:**
   - Replace pull-based sync with event-driven tracking
   - Use reconciliation for accuracy verification
   - Reduce API calls by 70-80%

3. **Verification Table + Marketplace Stock:**
   - Enhance verification records with marketplace context
   - Track stock per marketplace in verification
   - Provide marketplace-specific audit trail

### Unified Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Stock Change Event                        │
│  (Order Created, Cancelled, Topup, Manual, Reconciliation)  │
└───────────────────────┬─────────────────────────────────────┘
                        │
        ┌───────────────┼───────────────┐
        │               │               │
        ▼               ▼               ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│ Marketplace  │ │ Marketplace  │ │ Listed Stock │
│ Stock Table  │ │ Stock History│ │ Verification │
│              │ │              │ │              │
│ - Current    │ │ - Historical │ │ - Audit      │
│   Stock      │ │ - Change Log │ │ - Process    │
│ - Per MP     │ │ - Analytics  │ │   Tracking   │
└──────────────┘ └──────────────┘ └──────────────┘
```

---

## 5. Recommendations Summary

### Immediate Actions

1. **Implement V2 Stock Sync:**
   - Add `getUpdatedQuantity()` method to V2 ListingController
   - Add route for stock sync endpoint
   - Add `fetchUpdatedQuantity()` JavaScript function
   - Match original listing page functionality

2. **Enhance Verification Table:**
   - Add `marketplace_id` column
   - Add `change_type` enum column
   - Add `order_id` column
   - Update existing code to use new columns

### Short-term (1-2 weeks)

3. **Implement Event-Driven System:**
   - Create database tables and migrations
   - Create Event and Listener classes
   - Integrate with order sync commands
   - Integrate with stock top-up functions

4. **Implement Reconciliation:**
   - Create reconciliation command
   - Schedule daily reconciliation
   - Add discrepancy alerting

### Medium-term (2-4 weeks)

5. **Frontend Updates:**
   - Remove `fetchUpdatedQuantity` from listing pages
   - Display stock from `marketplace_stock` table
   - Add "Last Synced" timestamp
   - Add manual sync button (optional)

6. **Deprecation:**
   - Remove hourly `FunctionsThirty` stock sync
   - Monitor for 1 week
   - Full rollout

### Long-term (1-2 months)

7. **Analytics & Reporting:**
   - Stock movement dashboard
   - Historical analysis
   - Marketplace performance metrics
   - Admin activity reports

8. **Optimization:**
   - Fine-tune event logic
   - Optimize reconciliation process
   - Add caching where appropriate
   - Performance monitoring

---

## 6. Success Metrics

### Performance
- ✅ Page load time: <200ms (from 2-5 seconds)
- ✅ API calls/day: <1,500 (from 5,500+)
- ✅ API call reduction: >70%

### Accuracy
- ✅ Stock accuracy: >99.5%
- ✅ Reconciliation discrepancies: <1% of variations
- ✅ Event processing success: >99.9%

### User Experience
- ✅ Page load complaints: 0
- ✅ Stock accuracy complaints: <1/month
- ✅ System reliability: >99.9% uptime

---

## Conclusion

The current stock sync system has three distinct components:

1. **Back Market Sync:** Working in original, missing in V2, high API usage
2. **Event-Driven Strategy:** Proposed solution to reduce API calls by 70-80%
3. **Verification Table:** Audit trail for manual stock changes, needs enhancement

**Key Findings:**
- V2 listing page lacks stock sync functionality
- Current system makes excessive API calls
- Verification table provides good audit trail but needs marketplace context
- Event-driven system can significantly improve performance

**Recommended Approach:**
1. First: Implement V2 stock sync (quick win)
2. Second: Enhance verification table schema
3. Third: Implement event-driven system (long-term solution)
4. Fourth: Integrate all three systems for unified architecture

---

**Document Version:** 1.0  
**Created:** [Current Date]  
**Status:** Ready for Review  
**Next Steps:** Review recommendations and prioritize implementation

