# Use file cache (fix "Connection refused" for cache)

If you see **Connection refused** when running artisan/scheduler and the error mentions `select * from cache`, the app is using **database** for cache and MySQL is unreachable. Switching cache to **file** fixes this immediately; you can optimize with database/redis later.

---

## On the server

1. **Set cache driver to file** in `.env`:

   ```bash
   cd /var/www/sdpos
   # Edit .env and set:
   CACHE_DRIVER=file
   ```

   Or in one line (if you have sed):

   ```bash
   sed -i 's/^CACHE_DRIVER=.*/CACHE_DRIVER=file/' .env
   ```

2. **Clear config cache** so the change is used:

   ```bash
   php artisan config:clear
   ```

3. **Restart the scheduler** (if you use PM2):

   ```bash
   pm2 restart sdpos-scheduler
   ```

After this, cache is stored under `storage/framework/cache/data/` and no longer uses MySQL. You can switch back to `CACHE_DRIVER=database` or `redis` later when DB is stable and you want to optimize.
