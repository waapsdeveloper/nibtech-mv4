# Listing Stock Comparison - Implementation Summary

## Overview
Created a comprehensive tracking system that compares BackMarket API stock quantities with our system's stock and pending orders. This runs automatically when `functions:thirty` executes (every 30 minutes) to verify listing accuracy.

## What Was Created

### 1. Database Migration
**File**: `database/migrations/2026_01_15_130000_create_listing_stock_comparisons_table.php`

**Table**: `listing_stock_comparisons`

**Fields**:
- `variation_id` - Variation being compared
- `variation_sku` - SKU for quick reference
- `marketplace_id` - Marketplace (default: 1 for BackMarket)
- `country_code` - Country code for the listing
- `api_stock` - Stock quantity from BackMarket API
- `our_stock` - Our listed_stock value
- `pending_orders_count` - Number of pending orders
- `pending_orders_quantity` - Total quantity in pending orders
- `stock_difference` - Difference: our_stock - api_stock
- `available_after_pending` - Available stock after pending: our_stock - pending_orders_quantity
- `api_vs_pending_difference` - API stock vs pending: api_stock - pending_orders_quantity
- `is_perfect` - Whether stock matches perfectly
- `has_discrepancy` - Whether there's a discrepancy
- `has_shortage` - Whether we have less stock than API
- `has_excess` - Whether we have more stock than API
- `compared_at` - Timestamp of comparison

### 2. Model
**File**: `app/Models/Listing_stock_comparison_model.php`

**Features**:
- Relationships: variation, marketplace
- Accessors: `status_badge_class`, `status_label`
- Proper casting for dates and booleans

### 3. Controller
**File**: `app/Http/Controllers/V2/ListingStockComparisonController.php`

**Methods**:
- `index()` - List all comparisons with filtering and statistics
- `show($id)` - View comparison details
- `destroy($id)` - Delete comparison

**Filtering Options**:
- Variation SKU
- Marketplace
- Country Code
- Status (Perfect, Discrepancy, Shortage, Excess)
- Date Range

**Statistics**:
- Total compared
- Perfect matches
- Discrepancies
- Shortages
- Excesses

### 4. Views
**Location**: `resources/views/v2/listing-stock-comparisons/`

**Files**:
- `index.blade.php` - List view with filters, statistics, and comparison table
- `show.blade.php` - Detailed view of a single comparison

**Features**:
- Statistics cards showing comparison summary
- Advanced filtering
- Color-coded status badges
- Detailed comparison metrics
- Links to related variations

### 5. Integration with FunctionsThirty
**File**: `app/Console/Commands/FunctionsThirty.php`

**Changes**:
- Added `createStockComparisons()` method
- Automatically creates comparison records after processing listings
- Logs comparison statistics via SlackLogService

**How It Works**:
1. After `get_listings()` and `get_listingsBi()` complete
2. Fetches all listings from BackMarket API
3. For each listing:
   - Gets API stock from `$list->quantity`
   - Gets our stock from `$variation->listed_stock`
   - Calculates pending orders count and quantity
   - Calculates all differences
   - Determines status flags (perfect, discrepancy, shortage, excess)
   - Creates comparison record
4. Logs summary statistics

### 6. Routes
**File**: `routes/v2.php`

**Routes Added**:
```php
GET    /v2/listing-stock-comparisons              - Index
GET    /v2/listing-stock-comparisons/{id}         - Show
DELETE /v2/listing-stock-comparisons/{id}         - Delete
```

### 7. Menu Integration
**File**: `resources/views/layouts/components/app-sidebar.blade.php`

**Location**: V2 > Extras > Stock Comparisons

## How It Works

### Automatic Comparison
When `functions:thirty` runs (every 30 minutes):
1. Processes listings from BackMarket API
2. Updates our database with latest listings
3. Creates comparison records for each listing
4. Calculates all metrics and flags
5. Logs summary statistics

### Comparison Metrics

**Stock Difference**: `our_stock - api_stock`
- Positive = We have more stock than API
- Zero = Perfect match
- Negative = We have less stock than API

**Available After Pending**: `our_stock - pending_orders_quantity`
- Shows how much stock is actually available after accounting for pending orders

**API vs Pending Difference**: `api_stock - pending_orders_quantity`
- Shows if API stock accounts for pending orders

**Status Flags**:
- `is_perfect`: `our_stock == api_stock`
- `has_discrepancy`: `our_stock != api_stock`
- `has_shortage`: `our_stock < api_stock`
- `has_excess`: `our_stock > api_stock`

## Testing

### Run Migration
```bash
php artisan migrate
```

### Test Automatic Comparison
1. Run `php artisan functions:thirty`
2. Check the Stock Comparisons page
3. Verify comparisons are created for all listings
4. Review statistics and discrepancies

### View Comparisons
1. Navigate to V2 > Extras > Stock Comparisons
2. Use filters to find specific comparisons
3. View statistics (Total, Perfect, Discrepancies, Shortages)
4. Click on any comparison to see details
5. Delete old comparisons if needed

## Benefits

1. **Automatic Verification**: Every 30 minutes, listings are automatically compared
2. **Full Visibility**: See exactly how our stock compares to BackMarket API
3. **Pending Order Tracking**: Understand how pending orders affect stock
4. **Discrepancy Detection**: Quickly identify listings with mismatches
5. **Historical Tracking**: Keep records of all comparisons over time
6. **Easy Analysis**: Filter and search to find specific issues

## Use Cases

1. **Verify Listing Accuracy**: Check if our listed_stock matches BackMarket API
2. **Identify Discrepancies**: Find listings where stock doesn't match
3. **Track Pending Orders**: See how pending orders affect available stock
4. **Monitor Trends**: Track how stock comparisons change over time
5. **Debug Issues**: Investigate why certain listings have discrepancies

## Next Steps

1. Run the migration: `php artisan migrate`
2. Run `functions:thirty` to create initial comparisons
3. Check the Stock Comparisons page
4. Review statistics and identify any discrepancies
5. Use filters to find specific issues
6. Monitor comparisons over time to track improvements

## Notes

- Comparisons are automatically created when `functions:thirty` runs
- Each comparison captures a snapshot at that moment
- Historical comparisons are kept for analysis
- Statistics are calculated for the latest comparison date
- Filters make it easy to find specific issues
