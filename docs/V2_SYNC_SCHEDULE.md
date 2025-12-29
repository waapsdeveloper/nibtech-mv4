# V2 Order Sync Schedule

## Daily Schedule Configuration

The V2 unified order sync command is scheduled to run automatically on a daily basis with the following schedule:

### Schedule Overview

| Sync Type | Frequency | Time | Notes |
|-----------|-----------|------|-------|
| **New Orders** | Every 2 hours | 6 AM - 10 PM | Business hours only |
| **Modified Orders** | Daily | 2:00 AM | Full sync of all modified orders |
| **Care Records** | Daily | 4:00 AM | Replacement/return tracking |
| **Incomplete Orders** | Every 4 hours | All day | Missing labels/delivery notes |

### Detailed Schedule

#### 1. New Orders Sync (`--type=new`)
```php
$schedule->command('v2:sync-orders --type=new')
    ->everyTwoHours()
    ->between('06:00', '22:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
```

**Runs at:** 6 AM, 8 AM, 10 AM, 12 PM, 2 PM, 4 PM, 6 PM, 8 PM, 10 PM

**Purpose:** 
- Fetches new orders from marketplace APIs
- Validates orderlines
- Creates orders in database
- **Fires `OrderCreated` event** for stock locking

#### 2. Modified Orders Sync (`--type=modified`)
```php
$schedule->command('v2:sync-orders --type=modified')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
```

**Runs at:** 2:00 AM daily

**Purpose:**
- Fetches all modified orders (last 3 months)
- Updates orders and order items
- **Fires `OrderStatusChanged` event** if status changed

#### 3. Care Records Sync (`--type=care`)
```php
$schedule->command('v2:sync-orders --type=care')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
```

**Runs at:** 4:00 AM daily

**Purpose:**
- Fetches latest care/replacement records
- Updates order items with `care_id`
- Tracks replacements and returns

#### 4. Incomplete Orders Sync (`--type=incomplete`)
```php
$schedule->command('v2:sync-orders --type=incomplete')
    ->everyFourHours()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
```

**Runs at:** Every 4 hours (12 AM, 4 AM, 8 AM, 12 PM, 4 PM, 8 PM)

**Purpose:**
- Finds orders missing `delivery_note_url` or `label_url`
- Updates from API
- Does NOT fire events (to avoid duplicate stock locks)

## Schedule Features

All scheduled commands include:
- ✅ **`withoutOverlapping()`** - Prevents multiple instances running simultaneously
- ✅ **`onOneServer()`** - Ensures only one server runs the command (for multi-server setups)
- ✅ **`runInBackground()`** - Runs in background to avoid blocking

## Monitoring

### Check Schedule Status
```bash
php artisan schedule:list
```

### Run Schedule Manually
```bash
php artisan schedule:run
```

### View Logs
```bash
tail -f storage/logs/laravel.log | grep "OrderSyncService"
```

## Migration from V1

### Old V1 Schedule (Still Active)
- `refresh:latest` - Every 5 minutes
- `refresh:new` - Every 2 minutes  
- `refresh:orders` - Every 5 minutes

### New V2 Schedule
- More efficient with fewer API calls
- Better error handling
- Event-driven stock locking
- Staggered to avoid API rate limits

### Recommendation
1. **Phase 1:** Run both V1 and V2 in parallel for 1-2 weeks
2. **Phase 2:** Monitor V2 performance and accuracy
3. **Phase 3:** Disable V1 schedules once V2 is proven stable

## Customization

To modify the schedule, edit `app/Console/Kernel.php`:

```php
// Example: Run new orders sync every hour instead of every 2 hours
$schedule->command('v2:sync-orders --type=new')
    ->hourly()
    ->between('06:00', '22:00');
```

## Troubleshooting

### Command Not Running
1. Check if cron is set up: `crontab -l`
2. Verify schedule: `php artisan schedule:list`
3. Check logs: `storage/logs/laravel.log`

### Overlapping Commands
- Commands have `withoutOverlapping()` protection
- If a command is still running, the next run will be skipped
- Check for stuck processes: `ps aux | grep artisan`

### API Rate Limits
- Schedules are staggered to avoid rate limits
- New orders: Every 2 hours (not every minute)
- Modified orders: Once daily (not every 5 minutes)
- If issues occur, increase intervals

