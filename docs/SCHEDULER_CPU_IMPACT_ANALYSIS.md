# Scheduler CPU Impact Analysis

## Current Scheduler Load Summary

Based on your `app/Console/Kernel.php`, here's the frequency breakdown:

### High-Frequency Commands (Every 2-5 Minutes)
These run most frequently and contribute significantly to CPU load:

| Command | Frequency | CPU Impact | Notes |
|---------|-----------|------------|-------|
| `refresh:new` | **Every 2 minutes** | 🔴 **HIGH** | API calls to BackMarket, DB updates |
| `refresh:latest` | Every 5 minutes | 🟡 Medium | Care/replacement records sync |
| `refresh:orders` | Every 5 minutes | 🟡 Medium | Modified orders sync |
| `refurbed:new` | Every 5 minutes | 🟡 Medium | Refurbed new orders |
| `api-request:process` | Every 5 minutes | 🟡 Medium | Processes queued API requests |

**Total High-Frequency Executions per Hour: ~60-90 commands**

### Medium-Frequency Commands (Every 10 Minutes)
| Command | Frequency | CPU Impact |
|---------|-----------|------------|
| `price:handler` | Every 10 minutes | 🟡 Medium |
| `refurbed:link-tickets` | Every 10 minutes | 🟢 Low |
| `functions:ten` | Every 10 minutes | 🟡 Medium |
| `support:sync` | Every 10 minutes | 🟡 Medium |
| `bmpro:orders` | Every 10 minutes | 🟡 Medium |

**Total Medium-Frequency Executions per Hour: ~30 commands**

### Low-Frequency Commands
- Hourly: `refurbed:orders`, `functions:thirty`, `backup:email`, `fetch:exchange-rates`
- Every 4 hours: `functions:daily`, `v2:sync-orders --type=incomplete`
- Every 6 hours: Stock sync commands
- Daily: `v2:sync-orders --type=modified`, `v2:sync-orders --type=care`

## CPU Impact Factors

### 1. **Command Overlap**
With `refresh:new` running every 2 minutes, you have:
- **30 executions per hour** just for this one command
- If each execution takes 30-60 seconds, there's potential for overlap
- Multiple commands can run simultaneously (even with `withoutOverlapping()`)

### 2. **API Calls**
External API calls are CPU-intensive because they:
- Create network connections
- Wait for responses (blocking I/O)
- Process JSON responses
- Make multiple database writes

**Heavy API Commands:**
- `refresh:new` - Calls BackMarket API for new orders
- `refresh:orders` - Fetches modified orders
- `v2:marketplace:sync-stock-bulk` - Bulk API fetch (runs for ~24 minutes!)

### 3. **Database Operations**
Each command performs:
- Multiple SELECT queries
- UPDATE/INSERT operations
- Transaction handling
- Connection pooling overhead

### 4. **PHP Process Overhead**
- Each command spawns a PHP process
- Laravel bootstrap (autoloading, service providers)
- Memory allocation and garbage collection
- Process management overhead

### 5. **Background Process Management**
Even with `runInBackground()`, the scheduler:
- Checks every minute if commands should run
- Spawns new processes
- Monitors process status
- Handles process cleanup

## Current CPU Usage Analysis (55-85%)

Based on your monitoring graph showing **55-85% CPU utilization**:

### Likely Causes:

1. **Peak Times (85% CPU):**
   - Multiple high-frequency commands running simultaneously
   - `refresh:new` (every 2 min) + `refresh:orders` (every 5 min) + `api-request:process` (every 5 min)
   - API response processing
   - Database write operations

2. **Normal Times (60-75% CPU):**
   - Regular command execution
   - Background processes
   - Web application traffic

3. **Lower Times (55-60% CPU):**
   - Fewer commands running
   - Less web traffic
   - Idle periods between commands

## Recommendations to Reduce CPU Usage

### 1. **Stagger High-Frequency Commands** ⭐ RECOMMENDED
Instead of all commands starting at the same time, offset them:

```php
// Current: All start at :00, :02, :05, :10
$schedule->command('refresh:new')
    ->everyTwoMinutes()
    ->at(':00'); // Start at :00, :02, :04, etc.

$schedule->command('refresh:orders')
    ->everyFiveMinutes()
    ->at(':01'); // Start at :01, :06, :11, etc.

$schedule->command('api-request:process')
    ->everyFiveMinutes()
    ->at(':03'); // Start at :03, :08, :13, etc.
```

### 2. **Increase Intervals for Non-Critical Commands**
```php
// Change from every 2 minutes to every 3-4 minutes
$schedule->command('refresh:new')
    ->everyThreeMinutes(); // Reduces from 30 to 20 executions/hour
```

### 3. **Use Time Windows for Heavy Commands**
Run heavy commands during off-peak hours:
```php
$schedule->command('refresh:orders')
    ->everyFiveMinutes()
    ->between('22:00', '06:00'); // Only during low-traffic hours
```

### 4. **Optimize Database Queries**
- Add indexes on frequently queried columns
- Use eager loading to reduce N+1 queries
- Batch database operations
- Use database connection pooling

### 5. **Monitor Command Execution Times**
Add logging to identify slow commands:
```php
// In each command's handle() method
$startTime = microtime(true);
// ... command logic ...
Log::info('Command execution time', [
    'command' => $this->signature,
    'duration' => microtime(true) - $startTime
]);
```

### 6. **Consider Queue Workers**
Move heavy operations to queue workers:
- Reduces immediate CPU spikes
- Better resource management
- Can scale horizontally

### 7. **Reduce API Call Frequency**
- Cache API responses when possible
- Batch API requests
- Use webhooks instead of polling (if API supports)

## Immediate Actions

### Quick Win: Stagger Command Start Times
```php
$schedule->command('refresh:new')
    ->everyTwoMinutes()
    ->at(':00');

$schedule->command('refresh:latest')
    ->everyFiveMinutes()
    ->at(':01'); // Offset by 1 minute

$schedule->command('refresh:orders')
    ->everyFiveMinutes()
    ->at(':02'); // Offset by 2 minutes

$schedule->command('refurbed:new')
    ->everyFiveMinutes()
    ->at(':03'); // Offset by 3 minutes

$schedule->command('api-request:process')
    ->everyFiveMinutes()
    ->at(':04'); // Offset by 4 minutes
```

This prevents all commands from starting at the same time, reducing CPU spikes.

## Monitoring Recommendations

1. **Track Command Execution Times:**
   ```bash
   # Check Laravel logs for command durations
   tail -f storage/logs/laravel.log | grep "execution time"
   ```

2. **Monitor Process Count:**
   ```bash
   # Count running PHP processes
   ps aux | grep php | wc -l
   ```

3. **Database Connection Monitoring:**
   ```sql
   SHOW PROCESSLIST; -- See active database connections
   ```

4. **CPU Usage by Process:**
   ```bash
   top -p $(pgrep -f "php artisan")
   ```

## Expected Impact

After implementing recommendations:
- **Current:** 55-85% CPU (peaks at 85%)
- **Expected:** 40-70% CPU (peaks at 70%)
- **Reduction:** ~15-20% average CPU reduction

## Conclusion

Your current CPU usage (55-85%) is **moderate to high** but manageable. The main contributors are:
1. **High-frequency commands** (especially `refresh:new` every 2 minutes)
2. **Command overlap** (multiple commands running simultaneously)
3. **API call processing** (external API latency)
4. **Database operations** (writes and queries)

**Priority Actions:**
1. ✅ Stagger command start times (quick win)
2. ✅ Monitor command execution times
3. ✅ Consider increasing `refresh:new` interval if 2 minutes is not critical
4. ✅ Optimize database queries in heavy commands

