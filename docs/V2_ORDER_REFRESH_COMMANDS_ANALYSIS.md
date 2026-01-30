# V2 Order Refresh Commands Analysis

## Current V1 Commands Overview

### 1. `RefreshLatest.php` (`refresh:latest`)
**Purpose:** Sync latest care/replacement records from marketplace API

**What it does:**
- Gets the latest `care_id` from order items (marketplace_id = 1, BackMarket)
- Fetches care records from BackMarket API since last `care_id`
- Updates order items with `care_id` mapping (care = replacement/return tracking)

**Key Methods:**
- `Order_item_model->get_latest_care($bm)`
- `BackMarketAPIController->getAllCare()`

**Frequency:** Likely runs periodically to track replacements/returns

---

### 2. `RefreshNew.php` (`Refresh:new`)
**Purpose:** Sync new orders and update incomplete orders

**What it does:**
1. **Get New Orders:**
   - Fetches new orders from BackMarket API
   - Validates each orderline (sets state to 2 via API)
   - Updates orders in database

2. **Update Incomplete Orders:**
   - Finds orders from last 2 days that are:
     - Status: 0, 1, or 2 (pending/in-progress)
     - Missing `delivery_note_url` OR `label_url`
     - Order type: 3 (marketplace)
   - Updates these orders from API

**Key Methods:**
- `BackMarketAPIController->getNewOrders()`
- `Order_model->updateOrderInDB()`
- `Order_item_model->updateOrderItemsInDB()`
- `validateOrderlines()` - Sets orderline state to 2

**Frequency:** Likely runs frequently (every few minutes/hours)

---

### 3. `RefreshOrders.php` (`refresh:orders`)
**Purpose:** Sync all modified orders

**What it does:**
1. **Get New Orders:**
   - Fetches new orders (page-size: 50)
   - Validates orderlines (sets state to 2)

2. **Get All Modified Orders:**
   - Fetches all orders modified in last period (default: 3 months)
   - Updates orders and order items in database

**Key Methods:**
- `BackMarketAPIController->getNewOrders(['page-size'=>50])`
- `BackMarketAPIController->getAllOrders(1, ['page-size'=>50], $modification)`
- `Order_model->updateOrderInDB()`
- `Order_item_model->updateOrderItemsInDB()`

**Frequency:** Likely runs less frequently (daily/weekly)

---

## Common Patterns

### Shared Functionality:
1. **All use `BackMarketAPIController`** - Hardcoded to BackMarket
2. **All update orders/order items** - Via `updateOrderInDB()` and `updateOrderItemsInDB()`
3. **RefreshNew & RefreshOrders validate orderlines** - Set state to 2
4. **All fetch from API and sync to database**

### Issues with Current Approach:
1. **Hardcoded to BackMarket** - Not generic for multiple marketplaces
2. **No event firing** - Doesn't fire `OrderCreated` or `OrderStatusChanged` events
3. **Direct database updates** - Bypasses event-driven stock locking system
4. **Code duplication** - Similar logic in multiple commands
5. **No error handling** - Basic echo statements, no proper logging
6. **No progress tracking** - No way to monitor sync progress

---

## V2 Unified Command Design

### Command: `SyncMarketplaceOrders` (`v2:sync-orders`)

**Purpose:** Unified command to sync orders from all marketplaces

**Features:**
- **Generic:** Works with any marketplace (not just BackMarket)
- **Event-driven:** Fires V2 events (`OrderCreated`, `OrderStatusChanged`)
- **Configurable:** Options for different sync types
- **Progress tracking:** Shows progress and logs details
- **Error handling:** Proper error handling and logging

### Command Options:

```bash
# Sync new orders for all marketplaces
php artisan v2:sync-orders --type=new

# Sync modified orders for all marketplaces
php artisan v2:sync-orders --type=modified

# Sync care/replacement records
php artisan v2:sync-orders --type=care

# Sync specific marketplace
php artisan v2:sync-orders --marketplace=1

# Sync all (new + modified + care)
php artisan v2:sync-orders --type=all

# Update incomplete orders (missing labels/delivery notes)
php artisan v2:sync-orders --type=incomplete
```

### Architecture:

```
SyncMarketplaceOrders (Command)
    ↓
MarketplaceOrderSyncService (Service)
    ↓
MarketplaceAPIService (Generic API Service)
    ↓
OrderSyncService (Order-specific logic)
    ↓
Fire Events: OrderCreated, OrderStatusChanged
```

### Key Benefits:

1. **Single command** instead of three separate commands
2. **Generic** - works with any marketplace via `MarketplaceAPIService`
3. **Event-driven** - fires V2 events for stock locking
4. **Maintainable** - centralized logic, easier to update
5. **Testable** - better structure for unit tests
6. **Observable** - proper logging and progress tracking

---

## Implementation Plan

### Phase 1: Create V2 Command Structure
- [ ] Create `app/Console/Commands/V2/SyncMarketplaceOrders.php`
- [ ] Create `app/Services/V2/MarketplaceOrderSyncService.php`
- [ ] Create `app/Services/V2/OrderSyncService.php`

### Phase 2: Implement Sync Types
- [ ] New orders sync
- [ ] Modified orders sync
- [ ] Care/replacement sync
- [ ] Incomplete orders sync

### Phase 3: Event Integration
- [ ] Fire `OrderCreated` event when new order is created
- [ ] Fire `OrderStatusChanged` event when order status changes
- [ ] Ensure stock locking works correctly

### Phase 4: Testing & Migration
- [ ] Test with BackMarket
- [ ] Test with other marketplaces (if applicable)
- [ ] Update scheduler to use V2 command
- [ ] Keep V1 commands for backward compatibility

---

## Notes

- V1 commands should remain functional during transition
- V2 command should be backward compatible with existing data
- Consider adding queue support for large syncs
- Add rate limiting for API calls
- Add retry logic for failed API calls

