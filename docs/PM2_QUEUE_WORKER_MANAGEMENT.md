# PM2 Queue Worker Management Guide

## Overview

PM2 is used to manage the Laravel queue worker (`php artisan queue:work`) as a background process. This ensures the queue worker runs continuously and automatically restarts if it crashes.

**Current Process Name:** `sdpos-queue`

---

## Quick Reference Commands

### Check PM2 Status
```bash
npx pm2 list
# or
pm2 list
```

### View PM2 Logs
```bash
npx pm2 logs
# View last 50 lines
npx pm2 logs --lines 50
# View specific process logs
npx pm2 logs sdpos-queue
```

### Stop Process
```bash
# Stop gracefully (waits for current job to finish)
npx pm2 stop sdpos-queue

# Stop all processes
npx pm2 stop all
```

### Kill Process (Force Stop)
```bash
# Kill immediately (force stop)
npx pm2 delete sdpos-queue

# Kill all processes
npx pm2 delete all
```

### Restart Process
```bash
# Restart gracefully
npx pm2 restart sdpos-queue

# Restart all processes
npx pm2 restart all
```

### Reload Process (Zero Downtime)
```bash
# Reload with zero downtime (recommended)
npx pm2 reload sdpos-queue
```

---

## Complete Stop and Restart Procedure

### Step 1: Navigate to Project Directory
```bash
cd /var/www/sdpos
```

### Step 2: Check Current PM2 Status
```bash
npx pm2 list
```

**Expected Output:**
```
┌────┬────────────────────┬──────────┬──────┬───────────┬──────────┬──────────┐
│ id │ name               │ mode     │ ↺    │ status    │ cpu      │ memory   │
├────┼────────────────────┼──────────┼──────┼───────────┼──────────┼──────────┤
│ 0  │ sdpos-queue        │ fork     │ 0    │ online    │ 0%       │ 74.3mb   │
└────┴────────────────────┴──────────┴──────┴───────────┴──────────┴──────────┘
```

### Step 3: Stop the Process

**Option A: Graceful Stop (Recommended)**
```bash
npx pm2 stop sdpos-queue
```
- Waits for current job to finish
- Clean shutdown
- Use this when possible

**Option B: Force Kill (If process is stuck)**
```bash
npx pm2 delete sdpos-queue
```
- Immediate termination
- Use only if graceful stop doesn't work

### Step 4: Verify Process is Stopped
```bash
npx pm2 list
```

**Expected Output:**
```
┌────┬────────────────────┬──────────┬──────┬───────────┬──────────┬──────────┐
│ id │ name               │ mode     │ ↺    │ status    │ cpu      │ memory   │
├────┼────────────────────┼──────────┼──────┼───────────┴──────────┴──────────┤
│ 0  │ sdpos-queue        │ fork     │ 0    │ stopped   │ 0%       │ 0b       │
└────┴────────────────────┴──────────┴──────┴───────────┴──────────┴──────────┘
```

Or if deleted:
```
┌────┬────────────────────┬──────────┬──────┬───────────┬──────────┬──────────┐
│ id │ name               │ mode     │ ↺    │ status    │ cpu      │ memory   │
└────┴────────────────────┴──────────┴──────┴───────────┴──────────┴──────────┘
```

### Step 5: Start PM2 Queue Worker

**From Project Root Directory (`/var/www/sdpos`):**

```bash
npx pm2 start "php artisan queue:work --tries=3 --timeout=300" --name sdpos-queue
```

**Or if you have a PM2 ecosystem file:**
```bash
npx pm2 start ecosystem.config.js
```

### Step 6: Save PM2 Configuration (Important!)
```bash
npx pm2 save
```

This saves the current PM2 process list so it will automatically restart on server reboot.

### Step 7: Setup PM2 Startup Script (One-time setup)
```bash
npx pm2 startup
```

Follow the instructions shown. This will configure PM2 to start automatically when the server boots.

### Step 8: Verify Process is Running
```bash
npx pm2 list
npx pm2 logs sdpos-queue --lines 20
```

---

## Alternative: Restart Without Stopping

If you just need to restart the queue worker (e.g., after code changes):

### Method 1: PM2 Restart (Recommended)
```bash
cd /var/www/sdpos
npx pm2 restart sdpos-queue
```

### Method 2: Laravel Queue Restart (Graceful)
```bash
cd /var/www/sdpos
php artisan queue:restart
npx pm2 restart sdpos-queue
```

The `queue:restart` command tells the queue worker to finish current jobs and stop gracefully. Then PM2 will automatically restart it.

---

## Complete Restart Script

Create a script for easy restart:

```bash
#!/bin/bash
# File: /var/www/sdpos/restart-queue.sh

cd /var/www/sdpos

echo "Stopping PM2 queue worker..."
npx pm2 stop sdpos-queue

echo "Waiting 2 seconds..."
sleep 2

echo "Starting PM2 queue worker..."
npx pm2 start "php artisan queue:work --tries=3 --timeout=300" --name sdpos-queue

echo "Saving PM2 configuration..."
npx pm2 save

echo "Queue worker restarted successfully!"
npx pm2 list
```

Make it executable:
```bash
chmod +x /var/www/sdpos/restart-queue.sh
```

Run it:
```bash
/var/www/sdpos/restart-queue.sh
```

---

## PM2 Ecosystem Configuration File (Recommended)

Create `/var/www/sdpos/ecosystem.config.js`:

```javascript
module.exports = {
  apps: [{
    name: 'sdpos-queue',
    script: 'artisan',
    args: 'queue:work --tries=3 --timeout=300',
    interpreter: 'php',
    cwd: '/var/www/sdpos',
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: '500M',
    error_file: './storage/logs/pm2-error.log',
    out_file: './storage/logs/pm2-out.log',
    log_file: './storage/logs/pm2-combined.log',
    time: true,
    env: {
      APP_ENV: 'production'
    }
  }]
};
```

Then use:
```bash
# Start
npx pm2 start ecosystem.config.js

# Stop
npx pm2 stop ecosystem.config.js

# Restart
npx pm2 restart ecosystem.config.js

# Delete
npx pm2 delete ecosystem.config.js
```

---

## Troubleshooting

### Process Won't Stop
```bash
# Force kill
npx pm2 delete sdpos-queue

# If still stuck, kill by PID
npx pm2 list  # Find the PID
kill -9 <PID>
```

### Process Keeps Crashing
```bash
# Check logs
npx pm2 logs sdpos-queue --lines 100

# Check Laravel logs
tail -f /var/www/sdpos/storage/logs/laravel.log

# Verify queue connection in .env
cat /var/www/sdpos/.env | grep QUEUE_CONNECTION
```

### Process Not Starting
```bash
# Check if PHP is available
which php
php -v

# Check if artisan exists
ls -la /var/www/sdpos/artisan

# Test queue:work manually
cd /var/www/sdpos
php artisan queue:work --once
```

### Clear PM2 Completely
```bash
# Stop all
npx pm2 stop all

# Delete all
npx pm2 delete all

# Kill PM2 daemon
npx pm2 kill

# Restart PM2
npx pm2 resurrect
```

---

## Monitoring Commands

### Real-time Monitoring
```bash
npx pm2 monit
```

### Process Information
```bash
npx pm2 show sdpos-queue
```

### Process Statistics
```bash
npx pm2 status
```

### View All Logs
```bash
npx pm2 logs
```

### View Error Logs Only
```bash
npx pm2 logs sdpos-queue --err
```

### View Output Logs Only
```bash
npx pm2 logs sdpos-queue --out
```

---

## Important Notes

1. **Always run commands from project root** (`/var/www/sdpos`)
2. **Save PM2 configuration** after making changes: `npx pm2 save`
3. **Use `queue:restart`** before PM2 restart for graceful shutdown
4. **Check logs** if process keeps crashing
5. **Verify `.env`** has correct `QUEUE_CONNECTION` setting
6. **PM2 auto-restart** is enabled by default, so crashed processes will restart automatically

---

## Quick Command Reference

```bash
# Status
npx pm2 list

# Stop
npx pm2 stop sdpos-queue

# Kill
npx pm2 delete sdpos-queue

# Start
cd /var/www/sdpos
npx pm2 start "php artisan queue:work --tries=3 --timeout=300" --name sdpos-queue

# Restart
npx pm2 restart sdpos-queue

# Logs
npx pm2 logs sdpos-queue

# Save config
npx pm2 save
```

