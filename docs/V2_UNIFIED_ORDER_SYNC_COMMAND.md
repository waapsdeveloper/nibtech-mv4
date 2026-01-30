# V2 Unified Order Sync Command

## Overview

The `v2:sync-orders` command is a unified V2 command that replaces the three separate V1 commands:
- `refresh:latest` → Care/replacement records sync
- `refresh:new` → New orders sync
- `refresh:orders` → Modified orders sync

## Key Features

✅ **Generic** - Works with any marketplace (not hardcoded to BackMarket)  
✅ **Event-Driven** - Fires V2 events (`OrderCreated`, `OrderStatusChanged`) for stock locking  
✅ **Unified** - Single command handles all sync types  
✅ **Configurable** - Options for different sync types and marketplaces  
✅ **Error Handling** - Proper logging and error tracking  
✅ **Progress Tracking** - Shows detailed sync progress and summary  

## Architecture

```
SyncMarketplaceOrders (Command)
    ↓
MarketplaceOrderSyncService (Service)
    ↓
OrderSyncService (Order-specific logic)
    ↓
Fire Events: OrderCreated, OrderStatusChanged
    ↓
V2 Listeners: LockStockOnOrderCreated, ReduceStockOnOrderCompleted
```

## Usage

### Basic Usage

```bash
# Sync all types (new + modified + care + incomplete)
php artisan v2:sync-orders

# Sync only new orders
php artisan v2:sync-orders --type=new

# Sync only modified orders
php artisan v2:sync-orders --type=modified

# Sync only care/replacement records
php artisan v2:sync-orders --type=care

# Sync only incomplete orders (missing labels/delivery notes)
php artisan v2:sync-orders --type=incomplete
```

### Advanced Usage

```bash
# Sync specific marketplace
php artisan v2:sync-orders --type=new --marketplace=1

# Custom page size
php artisan v2:sync-orders --type=modified --page-size=100

# Custom days back for incomplete orders
php artisan v2:sync-orders --type=incomplete --days-back=5

# Combine options
php artisan v2:sync-orders --type=all --marketplace=1 --page-size=50
```

## Command Options

| Option | Description | Default | Example |
|--------|-------------|---------|---------|
| `--type` | Sync type: `new`, `modified`, `care`, `incomplete`, or `all` | `all` | `--type=new` |
| `--marketplace` | Specific marketplace ID to sync | All active | `--marketplace=1` |
| `--page-size` | Page size for API requests | `50` | `--page-size=100` |
| `--days-back` | Days back for incomplete orders | `2` | `--days-back=5` |

## Sync Types

### 1. New Orders (`--type=new`)
- Fetches new orders from marketplace API
- Validates orderlines (sets state to 2)
- Creates/updates orders in database
- **Fires `OrderCreated` event** for new orders

### 2. Modified Orders (`--type=modified`)
- Fetches all modified orders (default: last 3 months)
- Updates orders and order items in database
- **Fires `OrderStatusChanged` event** if status changed

### 3. Care Records (`--type=care`)
- Fetches latest care/replacement records
- Updates order items with `care_id`
- Only works with BackMarket for now

### 4. Incomplete Orders (`--type=incomplete`)
- Finds orders missing `delivery_note_url` or `label_url`
- From last N days (default: 2 days)
- Updates orders from API
- Does NOT fire events (to avoid duplicate stock locks)

### 5. All Types (`--type=all`)
- Runs all sync types in sequence:
  1. New orders
  2. Modified orders
  3. Care records
  4. Incomplete orders

## Event Integration

### OrderCreated Event
Fired when a new order is created:
- Triggers `LockStockOnOrderCreated` listener
- Locks stock in `marketplace_stock` table
- Creates lock records in `marketplace_stock_locks`

### OrderStatusChanged Event
Fired when order status changes:
- Triggers `ReduceStockOnOrderCompleted` listener
- Reduces stock when order is completed
- Updates marketplace API with new stock
- Releases/consumes stock locks

## Files Created

### Command
- `app/Console/Commands/V2/SyncMarketplaceOrders.php`

### Services
- `app/Services/V2/MarketplaceOrderSyncService.php` - Generic marketplace sync service
- `app/Services/V2/OrderSyncService.php` - Order-specific sync logic with event firing

## Migration from V1

### V1 Commands (Still Available)
- `Refresh:latest` - Still works, but consider migrating
- `Refresh:new` - Still works, but consider migrating
- `Refresh:orders` - Still works, but consider migrating

### Recommended Migration Path

1. **Phase 1: Parallel Run**
   - Keep V1 commands running
   - Start using V2 command for testing
   - Monitor both for consistency

2. **Phase 2: Gradual Migration**
   - Update scheduler to use V2 command
   - Keep V1 as backup
   - Monitor for issues

3. **Phase 3: Full Migration**
   - Remove V1 commands (or keep as backup)
   - Use only V2 command

## Scheduler Integration

Update `app/Console/Kernel.php`:

```php
// Old V1 commands
// $schedule->command('refresh:new')->everyFiveMinutes();
// $schedule->command('refresh:orders')->hourly();
// $schedule->command('refresh:latest')->hourly();

// New V2 unified command
$schedule->command('v2:sync-orders --type=new')->everyFiveMinutes();
$schedule->command('v2:sync-orders --type=modified')->hourly();
$schedule->command('v2:sync-orders --type=care')->hourly();
$schedule->command('v2:sync-orders --type=incomplete')->everyThirtyMinutes();
```

Or use single command for all:

```php
$schedule->command('v2:sync-orders --type=all')->hourly();
```

## Error Handling

The command includes comprehensive error handling:
- Logs errors to Laravel log
- Continues processing even if individual orders fail
- Shows error count in summary
- Returns exit code 1 on fatal errors

## Logging

All operations are logged:
- Order creation/updates
- Event firing
- API errors
- Sync statistics

Check logs at: `storage/logs/laravel.log`

## Testing

```bash
# Test new orders sync
php artisan v2:sync-orders --type=new --marketplace=1

# Test with verbose output
php artisan v2:sync-orders --type=all -v

# Check logs
tail -f storage/logs/laravel.log | grep "OrderSyncService"
```

## Benefits Over V1

1. **Single Command** - Easier to manage and schedule
2. **Event-Driven** - Proper stock locking via events
3. **Generic** - Works with multiple marketplaces
4. **Better Logging** - Comprehensive error tracking
5. **Progress Tracking** - Real-time sync status
6. **Maintainable** - Clean service layer architecture

## Future Enhancements

- [ ] Queue support for large syncs
- [ ] Rate limiting for API calls
- [ ] Retry logic for failed API calls
- [ ] Support for more marketplaces (Refurbed, etc.)
- [ ] Webhook support for real-time updates
- [ ] Dashboard for sync monitoring

