# Testing `refresh:new` Scheduler Locally

## Overview
The `refresh:new` command syncs new orders from BackMarket API and updates incomplete orders. It's scheduled to run every 2 minutes in production.

## What the Command Does

1. **Fetches New Orders** from BackMarket API
2. **Validates Orderlines** (sets state to 2 via API)
3. **Updates Orders** in database
4. **Updates Incomplete Orders** from last 2 days that are:
   - Status: 0, 1, or 2 (pending/in-progress)
   - Missing `delivery_note_url` OR `label_url`
   - Order type: 3 (marketplace)

## Step-by-Step Testing Guide

### Step 1: Check Prerequisites

```bash
# Navigate to project directory
cd C:\xampp\htdocs\nibritaintech

# Verify PHP and Composer are available
php -v
composer --version

# Check if Laravel is properly set up
php artisan --version
```

### Step 2: Check Database Connection

```bash
# Test database connection
php artisan db:show
# OR
php artisan migrate:status
```

### Step 3: Check BackMarket API Configuration

Ensure your `.env` file has BackMarket API credentials configured:
- `BACKMARKET_API_URL`
- `BACKMARKET_API_KEY` (or similar)
- Any other required BackMarket API settings

### Step 4: Run the Command Manually

```bash
# Run the command directly
php artisan Refresh:new
```

**Note:** The command signature is case-sensitive: `Refresh:new` (capital R)

### Step 5: Monitor Output

The command outputs:
- `1` - After fetching new orders
- `2` - After processing new orders
- `3` - After updating incomplete orders

Watch for:
- Any errors or exceptions
- API response status
- Database update confirmations

### Step 6: Check Logs

```bash
# View Laravel logs in real-time
tail -f storage/logs/laravel.log

# OR on Windows PowerShell
Get-Content storage/logs/laravel.log -Wait -Tail 50
```

Look for:
- API request/response logs
- Order update logs
- Any error messages

### Step 7: Verify Database Changes

Check the database to see if orders were updated:

```sql
-- Check recent orders
SELECT * FROM orders 
WHERE order_type_id = 3 
ORDER BY updated_at DESC 
LIMIT 10;

-- Check incomplete orders that should be updated
SELECT reference_id, status, delivery_note_url, label_url, created_at 
FROM orders 
WHERE status IN (0, 1, 2)
  AND order_type_id = 3
  AND (delivery_note_url IS NULL OR label_url IS NULL)
  AND created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY);
```

### Step 8: Test with Verbose Output (if available)

```bash
# Run with verbose flag (if command supports it)
php artisan Refresh:new -v
# OR
php artisan Refresh:new --verbose
```

### Step 9: Test Scheduler Manually

To test the scheduler itself (not just the command):

```bash
# List all scheduled commands
php artisan schedule:list

# Run the scheduler manually (will execute all due commands)
php artisan schedule:run

# Run scheduler in test mode (shows what would run without executing)
php artisan schedule:test
```

### Step 10: Monitor for Issues

Watch for:
- ✅ API rate limiting
- ✅ Database connection errors
- ✅ Missing API credentials
- ✅ Order validation failures
- ✅ Memory/timeout issues

## Common Issues & Solutions

### Issue 1: Command Not Found
```bash
# Solution: Clear cache and try again
php artisan clear-compiled
php artisan config:clear
php artisan cache:clear
```

### Issue 2: API Authentication Errors
- Check `.env` file for correct API credentials
- Verify API keys are valid
- Check if API endpoint is accessible

### Issue 3: Database Errors
- Verify database connection in `.env`
- Check if database user has proper permissions
- Ensure tables exist and are migrated

### Issue 4: Command Takes Too Long
- The command makes API calls which can be slow
- Check network connectivity
- Consider adding timeout limits

## Testing Checklist

- [ ] Command runs without errors
- [ ] New orders are fetched from API
- [ ] Orderlines are validated (state set to 2)
- [ ] Orders are updated in database
- [ ] Incomplete orders are updated
- [ ] Logs show successful operations
- [ ] No duplicate orders created
- [ ] Database records are correct

## Advanced Testing

### Test with Specific Order ID

If you want to test with a specific order, you can temporarily modify the command or create a test script:

```php
// In tinker or a test script
$bm = new \App\Http\Controllers\BackMarketAPIController();
$orderObj = $bm->getOneOrder('ORDER_ID_HERE');
// Check the response
```

### Monitor API Calls

Enable detailed logging in `BackMarketAPIController` to see all API requests/responses.

### Test Error Handling

Temporarily break API credentials to see how the command handles errors.

## Production vs Local Differences

- **Production:** Runs automatically every 2 minutes via cron
- **Local:** Must run manually or set up local cron/scheduler
- **Production:** Has `withoutOverlapping()` protection
- **Local:** Can run multiple times simultaneously (be careful!)

## Next Steps After Testing

1. If successful, consider setting up local scheduler:
   ```bash
   # Add to crontab (Linux/Mac) or Task Scheduler (Windows)
   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
   ```

2. Monitor logs for a few runs to ensure stability

3. Check database for any unexpected changes

4. Verify order updates are correct

## Related Commands

- `refresh:latest` - Syncs latest care/replacement records
- `refresh:orders` - Syncs all modified orders
- `v2:sync-orders --type=new` - V2 version of this command (newer implementation)
