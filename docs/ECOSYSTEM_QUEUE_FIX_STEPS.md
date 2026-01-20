# Steps to Apply Ecosystem Queue Configuration Fix on Server

## Overview
This document outlines the steps to apply the ecosystem.config.js fix that removes the queue overlap between `sdpos-queue` and `sdpos-queue-api`.

## Changes Made
- **Before:** `sdpos-queue` processed both `api-requests` and `default` queues (overlap with `sdpos-queue-api`)
- **After:** `sdpos-queue` processes only `default` queue, `sdpos-queue-api` processes only `api-requests` queue

## Server Steps

### 1. Navigate to Project Directory
```bash
cd /var/www/sdpos
```

### 2. Pull Latest Changes
```bash
git pull origin main
```

### 3. Verify the Changes
Check that `ecosystem.config.js` has been updated:
```bash
grep -A 2 "sdpos-queue" ecosystem.config.js
```

You should see:
- `sdpos-queue` with `--queue=default` (not `api-requests,default`)
- `sdpos-queue-api` with `--queue=api-requests`

### 4. Stop Current PM2 Processes
```bash
pm2 stop all
```

Or stop specific processes:
```bash
pm2 stop sdpos-queue
pm2 stop sdpos-queue-api
pm2 stop sdpos-scheduler
```

### 5. Delete Old PM2 Processes (Optional but Recommended)
This ensures a clean restart with the new configuration:
```bash
pm2 delete sdpos-queue
pm2 delete sdpos-queue-api
pm2 delete sdpos-scheduler
```

### 6. Start PM2 with Updated Configuration
```bash
pm2 start ecosystem.config.js
```

### 7. Verify Processes are Running
```bash
pm2 list
```

You should see:
- `sdpos-queue` - processing `default` queue only
- `sdpos-queue-api` - processing `api-requests` queue only
- `sdpos-scheduler` - Laravel scheduler

### 8. Check Process Logs
Verify the queue workers are processing the correct queues:
```bash
# Check general queue worker
pm2 logs sdpos-queue --lines 20

# Check API queue worker
pm2 logs sdpos-queue-api --lines 20
```

Look for lines like:
- `sdpos-queue`: `Processing jobs from the [default] queue`
- `sdpos-queue-api`: `Processing jobs from the [api-requests] queue`

### 9. Save PM2 Configuration
Save the current PM2 process list so it restarts on server reboot:
```bash
pm2 save
```

### 10. Verify No Overlap
Check that jobs are being processed correctly:
```bash
# Monitor queue processing
pm2 monit
```

## Verification Checklist

- [ ] `sdpos-queue` is running and processing `default` queue only
- [ ] `sdpos-queue-api` is running and processing `api-requests` queue only
- [ ] No errors in PM2 logs
- [ ] Jobs are being processed correctly
- [ ] PM2 configuration saved

## Troubleshooting

### If processes fail to start:
```bash
# Check PM2 logs
pm2 logs --err

# Check Laravel logs
tail -f storage/logs/laravel.log
```

### If you need to restart a specific process:
```bash
pm2 restart sdpos-queue
pm2 restart sdpos-queue-api
```

### If you need to see detailed process info:
```bash
pm2 show sdpos-queue
pm2 show sdpos-queue-api
```

## Important Notes

1. **Downtime:** There will be a brief downtime (usually a few seconds) when stopping and restarting the queue workers. Jobs in the queue will be picked up once the workers restart.

2. **Queue Backlog:** If there's a backlog of jobs, they will be processed once the workers restart. The `api-requests` queue will be handled by `sdpos-queue-api` (fast processing), and `default` queue will be handled by `sdpos-queue` (conservative processing).

3. **Monitoring:** After applying the changes, monitor the logs for a few minutes to ensure everything is working correctly.

## Rollback (If Needed)

If you need to rollback to the previous configuration:

1. Edit `ecosystem.config.js` and change:
   ```javascript
   args: 'queue:work database --queue=default --sleep=3 --tries=3 --timeout=90 --max-jobs=100 --max-time=3600',
   ```
   Back to:
   ```javascript
   args: 'queue:work database --queue=api-requests,default --sleep=3 --tries=3 --timeout=90 --max-jobs=100 --max-time=3600',
   ```

2. Restart PM2 processes:
   ```bash
   pm2 restart ecosystem.config.js
   pm2 save
   ```
