# Server steps for refresh:new (after deploy)

Follow these steps on the server after pulling the latest code (logging + lowercase command names).

---

## 1. Pull the updates

```bash
cd /var/www/sdpos
# or your app path, e.g. /var/www/nibritaintech
git pull origin main
```

---

## 2. Confirm commands are registered (lowercase)

```bash
php artisan list | grep -i refresh
```

You should see:

- `refresh:new`
- `refresh:orders`
- `refresh:latest`

(All lowercase.)

---

## 3. Confirm schedule includes refresh:new

```bash
php artisan schedule:list
```

Check that `refresh:new` appears and runs every 2 minutes.

---

## 4. Restart the scheduler (PM2)

So the scheduler process loads the new code and schedule:

```bash
pm2 restart sdpos-scheduler
```

(Use your actual PM2 app name if different, e.g. `nibritaintech-scheduler`.)

---

## 5. Verify refresh:new runs

- Wait for the next even minute (e.g. 12:10, 12:12) or run the scheduler once:

  ```bash
  php artisan schedule:run
  ```

- Tail scheduler logs:

  ```bash
  pm2 logs sdpos-scheduler --lines 30
  ```

You should see a line like:

`Running ['artisan' refresh:new] in background ... DONE`

---

## 6. Check log file (start/end entries)

Start/end are written to the order_sync log file (no Slack):

```bash
tail -f storage/logs/slack-order_sync.log
```

After a run you should see lines like:

- `[YYYY-MM-DD HH:MM:SS] local.INFO: 🔄 refresh:new command started {...}`
- `[YYYY-MM-DD HH:MM:SS] local.INFO: ✅ refresh:new command completed ... | Duration: Xs {...}`

---

## 7. Optional: run refresh:new once by hand

```bash
php artisan refresh:new
```

Use lowercase. Then check `storage/logs/slack-order_sync.log` for the start and completed entries.

---

## Quick reference

| Action              | Command / path |
|---------------------|----------------|
| List refresh commands | `php artisan list \| grep refresh` |
| List schedule        | `php artisan schedule:list` |
| Restart scheduler    | `pm2 restart sdpos-scheduler` |
| Scheduler logs       | `pm2 logs sdpos-scheduler` |
| refresh:new log file | `storage/logs/slack-order_sync.log` |
| Run refresh:new      | `php artisan refresh:new` |
