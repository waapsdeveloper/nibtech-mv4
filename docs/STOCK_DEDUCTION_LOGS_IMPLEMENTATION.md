# Stock Deduction Logs - Implementation Summary

## Overview
Created a comprehensive tracking system for stock deductions that occur when orders arrive via `refresh:new` command. This allows you to track and verify all stock deduction changes in the system.

## What Was Created

### 1. Database Migration
**File**: `database/migrations/2026_01_15_120000_create_stock_deduction_logs_table.php`

**Table**: `stock_deduction_logs`

**Fields**:
- `id` - Primary key
- `variation_id` - Variation that had stock deducted
- `marketplace_id` - Marketplace ID
- `order_id` - Order ID (nullable)
- `order_reference_id` - Order reference from marketplace
- `variation_sku` - SKU for quick reference
- `before_variation_stock` - Stock before deduction
- `before_marketplace_stock` - Marketplace stock before
- `after_variation_stock` - Stock after deduction
- `after_marketplace_stock` - Marketplace stock after
- `deduction_reason` - Reason (new_order_status_1 or status_change_1_to_2)
- `order_status` - Order status at time of deduction
- `is_new_order` - Whether it was a new order
- `old_order_status` - Previous status (if changed)
- `notes` - Additional notes
- `deduction_at` - Timestamp of deduction
- `created_at`, `updated_at` - Standard timestamps

**Indexes**: Optimized for queries by variation_id, order_id, order_reference_id, marketplace_id, deduction_reason, and deduction_at

### 2. Model
**File**: `app/Models/Stock_deduction_log_model.php`

**Features**:
- Relationships: variation, order, marketplace
- Accessors: `order_status_name`, `deduction_reason_label`
- Proper casting for dates and booleans

### 3. Controller
**File**: `app/Http/Controllers/V2/StockDeductionLogController.php`

**Methods**:
- `index()` - List all logs with filtering
- `create()` - Show create form
- `store()` - Save new log
- `show($id)` - View log details
- `edit($id)` - Show edit form
- `update($id)` - Update log
- `destroy($id)` - Delete log

**Filtering Options**:
- Variation SKU
- Order Reference ID
- Deduction Reason
- Marketplace
- Date Range

### 4. Views
**Location**: `resources/views/v2/stock-deduction-logs/`

**Files**:
- `index.blade.php` - List view with filters and statistics
- `create.blade.php` - Create form
- `edit.blade.php` - Edit form
- `show.blade.php` - Detail view

**Features**:
- Statistics cards (Total, Today, This Week)
- Advanced filtering
- Responsive tables
- Links to related records (variations, orders)
- Color-coded badges for status and reasons
- Negative stock highlighting (red)

### 5. Routes
**File**: `routes/v2.php`

**Routes Added**:
```php
GET    /v2/stock-deduction-logs              - Index
GET    /v2/stock-deduction-logs/create       - Create form
POST   /v2/stock-deduction-logs              - Store
GET    /v2/stock-deduction-logs/{id}         - Show
GET    /v2/stock-deduction-logs/{id}/edit   - Edit form
PUT    /v2/stock-deduction-logs/{id}         - Update
DELETE /v2/stock-deduction-logs/{id}         - Delete
```

### 6. Menu Integration
**File**: `resources/views/layouts/components/app-sidebar.blade.php`

**Location**: V2 > Extras > Stock Deduction Logs

### 7. Integration with RefreshNew Command
**File**: `app/Console/Commands/RefreshNew.php`

**Changes**:
- Added `Stock_deduction_log_model` import
- Updated `deductListedStockForOrder()` method to record each deduction
- Logs are automatically created when stock is deducted

## How It Works

### Automatic Logging
When `refresh:new` runs and deducts stock:
1. Stock is deducted from `variations.listed_stock` and `marketplace_stock.listed_stock`
2. A record is automatically created in `stock_deduction_logs` table
3. All details are captured (before/after values, reason, order info, etc.)

### Manual Logging
You can also manually create logs via the UI:
1. Go to V2 > Extras > Stock Deduction Logs
2. Click "Create New"
3. Fill in the details
4. Save

### Viewing Logs
1. Navigate to V2 > Extras > Stock Deduction Logs
2. Use filters to find specific logs
3. View statistics (Total, Today, This Week)
4. Click on any log to see details
5. Edit or delete logs as needed

## Testing

### Run Migration
```bash
php artisan migrate
```

### Test Automatic Logging
1. Set `SYNC_DATA_IN_LOCAL=true` in `.env`
2. Run `php artisan refresh:new`
3. Check the Stock Deduction Logs page
4. Verify logs are created for each deduction

### Test Manual Logging
1. Go to Stock Deduction Logs
2. Click "Create New"
3. Fill in test data
4. Save and verify it appears in the list

## Benefits

1. **Full Traceability**: Every stock deduction is recorded with complete details
2. **Easy Verification**: Quickly see what changed and when
3. **Testing Support**: Perfect for verifying that the deduction logic works correctly
4. **Audit Trail**: Complete history of all stock deductions
5. **Debugging**: Easy to identify issues or verify expected behavior
6. **Reporting**: Can analyze patterns and trends

## Next Steps

1. Run the migration: `php artisan migrate`
2. Test with `SYNC_DATA_IN_LOCAL=true`
3. Run `refresh:new` and check the logs
4. Verify deductions are being recorded correctly
5. Use the UI to view, filter, and manage logs

## Notes

- Logs are automatically created - no manual intervention needed
- All deductions are tracked, even if stock goes negative
- The UI provides full CRUD capabilities for managing logs
- Filters make it easy to find specific deductions
- Links to variations and orders provide quick navigation
