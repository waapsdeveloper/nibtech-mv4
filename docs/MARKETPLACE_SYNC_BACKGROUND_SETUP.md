# Marketplace Sync Background Setup

## How It Works Now

The sync now runs in the **background using Laravel queues**. This means:

✅ **Sync continues even if you close the browser/page**  
✅ **Multiple syncs can run simultaneously**  
✅ **No timeout issues for large syncs**  
✅ **Better performance - doesn't block the web request**

## Queue Configuration

### Current Setup

By default, Laravel uses `sync` queue driver, which means jobs run **synchronously** (immediately, but still in background process).

### Option 1: Use Database Queue (Recommended for Production)

**1. Create jobs table:**
```bash
php artisan queue:table
php artisan migrate
```

**2. Update `.env`:**
```env
QUEUE_CONNECTION=database
```

**3. Run queue worker:**
```bash
php artisan queue:work
```

Or run in background:
```bash
php artisan queue:work > /dev/null 2>&1 &
```

### Option 2: Keep Sync Queue (Current - Works Fine)

If `QUEUE_CONNECTION=sync` in your `.env`, jobs will still run in background but synchronously. This is fine for testing.

**No queue worker needed** - jobs execute immediately when dispatched.

## How to Use

### 1. Start Sync from UI

1. Go to `/v2/marketplace`
2. Click "Sync" button for individual marketplace OR
3. Click "Sync All Marketplaces" button

### 2. What Happens

- Job is dispatched to queue
- Returns immediately with "Sync started in background" message
- Job runs in background (even if you close the page)
- Progress is logged to `storage/logs/laravel.log`

### 3. Check Progress

**Option A: View Logs**
```bash
tail -f storage/logs/laravel.log | grep "SyncMarketplaceStock"
```

**Option B: Check UI**
- Refresh the marketplace page
- "Last Synced" column will update when sync completes
- Status indicators show sync progress

**Option C: Check Database**
```sql
SELECT 
    marketplace_id,
    COUNT(*) as total,
    MAX(last_synced_at) as last_sync
FROM marketplace_stock
GROUP BY marketplace_id;
```

## Queue Worker Commands

### Start Queue Worker
```bash
php artisan queue:work
```

### Start in Background
```bash
nohup php artisan queue:work > storage/logs/queue.log 2>&1 &
```

### Restart Queue Worker (after code changes)
```bash
php artisan queue:restart
```

### Check Queue Status
```bash
php artisan queue:failed  # See failed jobs
php artisan queue:retry all  # Retry failed jobs
```

## Monitoring

### Check if Queue Worker is Running
```bash
ps aux | grep "queue:work"
```

### View Queue Jobs (if using database queue)
```sql
SELECT * FROM jobs ORDER BY id DESC LIMIT 10;
SELECT * FROM failed_jobs ORDER BY id DESC LIMIT 10;
```

## Important Notes

1. **If using `sync` queue**: Jobs run immediately, no worker needed
2. **If using `database` queue**: Must run `php artisan queue:work`
3. **Jobs continue even if browser closes** - they run on the server
4. **Check logs** for detailed progress: `storage/logs/laravel.log`

## Troubleshooting

### Sync Not Running?

1. **Check queue connection:**
   ```bash
   php artisan tinker
   >>> config('queue.default')
   ```

2. **If using database queue, check worker:**
   ```bash
   ps aux | grep queue:work
   ```

3. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. **Manually test job:**
   ```bash
   php artisan tinker
   >>> dispatch(new \App\Jobs\SyncMarketplaceStockJob(1, 1));
   ```

### Jobs Stuck?

1. **Restart queue worker:**
   ```bash
   php artisan queue:restart
   php artisan queue:work
   ```

2. **Clear failed jobs:**
   ```bash
   php artisan queue:flush
   ```

## Summary

- ✅ **No queue worker needed** if using `sync` queue (default)
- ✅ **Sync runs in background** - continues even if you close page
- ✅ **Check logs** for progress: `storage/logs/laravel.log`
- ✅ **UI updates** when you refresh the page

The sync will work immediately - just click the sync button and it will run in the background!

