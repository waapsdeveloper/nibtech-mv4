# Database Connection Leak Fix

## Problem
Application was experiencing `SQLSTATE[HY000] [1040] Too many connections` errors due to database connection pool exhaustion.

## Root Causes Identified

1. **Queue Jobs Not Releasing Connections**: Queue jobs (SendApiRequestPayload, SyncMarketplaceStockJob, ExecuteArtisanCommandJob, UpdateOrderInDB) were not explicitly disconnecting from the database after completion.

2. **No Connection Limits**: Database configuration had no proper timeout settings and included invalid 'pool' configuration.

3. **No Worker Restart Policy**: Queue workers in ecosystem.config.js were running indefinitely without restarting, allowing connection leaks to accumulate.

4. **Synchronous Queue Connection**: `.env` had `QUEUE_CONNECTION=sync` which runs jobs synchronously and holds connections longer.

## Fixes Applied

### 1. Database Configuration (`config/database.php`)
- Removed invalid `pool` configuration
- Reduced `PDO::ATTR_TIMEOUT` from 5 to 3 seconds
- Added `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true`
- Kept `PDO::ATTR_PERSISTENT => false` to avoid persistent connections

### 2. Job Connection Cleanup
Added `DB::disconnect()` calls in all queue jobs:

#### SendApiRequestPayload.php
- Added `use Illuminate\Support\Facades\DB`
- Added `finally` block with `DB::disconnect()` after job execution

#### SyncMarketplaceStockJob.php
- Added `finally` block with `DB::disconnect()` after job execution

#### ExecuteArtisanCommandJob.php
- Added `use Illuminate\Support\Facades\DB`
- Added `finally` block with `DB::disconnect()` after job execution

#### UpdateOrderInDB.php
- Added `use Illuminate\Support\Facades\DB`
- Added `__destruct()` method with `DB::disconnect()`

### 3. Global Queue Cleanup (`app/Providers/AppServiceProvider.php`)
Added global queue event listeners to ensure connections are released:
```php
Queue::after(function (JobProcessed $event) {
    DB::disconnect();
});

Queue::failing(function (JobFailed $event) {
    DB::disconnect();
});
```

### 4. Queue Worker Configuration (`ecosystem.config.js`)
Updated worker arguments to include:
- `--max-jobs=100`: Restart worker after processing 100 jobs
- `--max-time=3600`: Restart worker after 1 hour of runtime

This prevents long-running workers from accumulating connection leaks.

## MySQL Configuration Recommendations

### Check Current Max Connections
```sql
SHOW VARIABLES LIKE 'max_connections';
```

### Increase Max Connections (if needed)
Edit MySQL configuration file (`my.ini` or `my.cnf`):
```ini
[mysqld]
max_connections = 500
wait_timeout = 28800
interactive_timeout = 28800
```

Then restart MySQL service:
```bash
# Windows
net stop mysql
net start mysql

# Linux
sudo systemctl restart mysql
```

### Check Current Connections
```sql
SHOW PROCESSLIST;
SELECT COUNT(*) FROM information_schema.PROCESSLIST;
```

## Testing & Verification

### 1. Check Active Connections
```sql
SHOW STATUS WHERE variable_name = 'Threads_connected';
SHOW STATUS WHERE variable_name = 'Max_used_connections';
```

### 2. Monitor Queue Jobs
```bash
# Watch queue worker logs
tail -f storage/logs/pm2-queue.log

# Check PM2 process status
pm2 status
pm2 logs sdpos-queue
```

### 3. Test Job Processing
Dispatch a test job and verify:
- Job completes successfully
- Database connections are released
- No connection leak warnings in logs

## Deployment Steps

1. **Update configuration files** (already done):
   - ✅ config/database.php
   - ✅ ecosystem.config.js
   - ✅ app/Providers/AppServiceProvider.php
   - ✅ All job files

2. **Restart queue workers**:
   ```bash
   pm2 restart sdpos-queue
   pm2 restart sdpos-scheduler
   ```

3. **Monitor connections**:
   ```sql
   -- Monitor every 30 seconds for 5 minutes
   SELECT NOW(), COUNT(*) as total_connections 
   FROM information_schema.PROCESSLIST;
   ```

4. **Change Queue Connection** (if using database queue):
   Update `.env`:
   ```
   QUEUE_CONNECTION=database
   ```
   Then restart:
   ```bash
   pm2 restart all
   php artisan config:clear
   php artisan cache:clear
   ```

## Prevention Best Practices

1. **Always use `DB::disconnect()`** in long-running jobs or jobs that execute artisan commands
2. **Set max-jobs and max-time** for queue workers to force periodic restarts
3. **Monitor connection usage** regularly using MySQL queries
4. **Use `finally` blocks** to ensure cleanup happens even on errors
5. **Avoid persistent connections** (`PDO::ATTR_PERSISTENT => false`)
6. **Keep connection timeouts low** to release idle connections quickly

## Monitoring Queries

```sql
-- Check current connection count
SELECT COUNT(*) as connections FROM information_schema.PROCESSLIST;

-- Check connections by user
SELECT user, COUNT(*) as count 
FROM information_schema.PROCESSLIST 
GROUP BY user;

-- Check long-running queries
SELECT * FROM information_schema.PROCESSLIST 
WHERE time > 60 
ORDER BY time DESC;

-- Check connection usage over time
SHOW STATUS LIKE 'Threads%';
SHOW STATUS LIKE 'Max_used_connections';
```

## Expected Results

- ✅ No more "Too many connections" errors
- ✅ Stable connection count (typically < 50 for this application)
- ✅ Queue workers restart automatically every 100 jobs or 1 hour
- ✅ Jobs complete successfully and release connections
- ✅ Better application stability and performance

## Rollback Plan

If issues occur after deployment:

1. Revert database.php changes
2. Revert ecosystem.config.js changes
3. Revert AppServiceProvider.php changes
4. Restart queue workers: `pm2 restart all`
5. Clear cache: `php artisan config:clear && php artisan cache:clear`
