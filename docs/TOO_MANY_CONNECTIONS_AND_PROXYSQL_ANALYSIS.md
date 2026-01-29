# Too Many Connections – Log Pattern Analysis & ProxySQL Suggestion

**Date:** 2026  
**Context:** Log excerpt 52452–52917 (`laravel.log`) – "Too many connections" during V2 SyncMarketplaceStock run.

---

## 1. What the Log Pattern Shows

### 1.1 Timeline (simplified)

| Approx. time   | What’s happening |
|---------------|-------------------|
| 11:39:17–11:40:47 | **V2 SyncMarketplaceStock** runs and logs `Stock updated` for **hundreds** of variations (marketplace_id=1). One log line per variation → one long-running Artisan process. |
| 11:39:27, 11:39:47, 11:40:07, 11:40:27, 11:40:47 | **SendApiRequestPayload** jobs hit timeouts to `ykpos.nibritaintech.com` → **queue worker(s)** are active and using DB. |
| 11:40:01–11:40:11 | **Refresh:new** runs (start + complete) **while sync is still running** → another Artisan process with its own DB connection. |
| 11:40:48       | A **web request** hits the app; **AuthorizeMiddleware** does `Admin::find(22)` → **SQLSTATE[1040] Too many connections**. |

So at 11:40:48 you have:

- At least **1** connection held by **V2 SyncMarketplaceStock** (long-running).
- At least **1** connection used by **Refresh:new** (or other scheduler tasks in that window).
- **Scheduler** process (`schedule:work`) and any other **runInBackground** commands that started in that minute.
- **Queue worker(s)** (each typically 1 connection).
- **Web/PHP-FPM** requests (each request often 1 connection until release).

Sum of these can exceed MySQL `max_connections` (e.g. 151). The **next** connection attempt (the web request in AuthorizeMiddleware) then fails with “Too many connections”.

### 1.2 Root Cause in One Sentence

**During an update sync we have one long-running process (SyncMarketplaceStock) holding a DB connection, plus overlapping scheduler tasks and queue workers and normal web traffic, and the total number of concurrent MySQL connections exceeds the server limit.**

So the “too many connections” is not a bug in the sync logic itself; it’s **concurrent usage**: sync + scheduler + queue + web at the same time.

---

## 2. Why This Fits “In Between an Update Sync”

- The sync runs for **many minutes** (hundreds of variations, ~1 update per second in the log).
- Scheduler is **staggered** but still runs other commands every few minutes; **Refresh:new** is every 2 minutes.
- So it’s **normal** that in the middle of a long sync you get:
  - Other cron jobs starting (each new process = new connection).
  - Queue jobs running (workers keep connections).
  - Users opening the app (each request = connection).

So “we are in between an update sync and suddenly too many connection requests” is exactly this: **peak concurrency** during a long sync.

---

## 3. ProxySQL: What It Is and How It Helps

**ProxySQL** is a proxy that sits **between your application (Laravel) and MySQL**:

```
  Laravel (web + queue + scheduler)  →  ProxySQL  →  MySQL
         many “client” connections         pool      fewer “backend” connections
```

- **Connection pooling:**  
  Many short-lived or concurrent “client” connections from PHP (web, queue, artisan) are multiplexed onto a **smaller pool** of persistent connections to MySQL.  
  Example: 100 app connections might use only 20–30 real MySQL connections.

- **Effect on “Too many connections”:**  
  MySQL only sees the **backend** connections (the pool size), not every single app connection. So you can have more concurrent web + scheduler + queue activity without hitting `max_connections` on MySQL.

- **Other benefits:**  
  Query routing, read/write splitting, caching, and better handling of connection churn (connect/disconnect) are possible with ProxySQL, but the main gain for your symptom is **pooling**.

So the suggestion to put **ProxySQL between the database and the connections** is about **reducing the number of actual connections to MySQL** by pooling them, which directly addresses “too many connections” during heavy sync + scheduler + web load.

---

## 4. Recommendation Order

### 4.1 Already Aligned With Your Docs

From `DB_CONNECTION_ANALYSIS_LAST_WEEK.md` you already:

- Removed global `DB::disconnect()` after every job.
- Staggered the scheduler (cron minutes spread out).
- Use a single queue worker for api-requests + default.
- Use `withoutOverlapping()` / `onOneServer()` for long-running commands.

So application-side mitigation is partly in place. The log still shows that **under load** (long sync + overlapping tasks + web), you can exceed `max_connections`.

### 4.2 Application / Ops (Do First or in Parallel)

1. **Limit concurrency of heavy commands**  
   - Ensure **V2 SyncMarketplaceStock** (and similar) never run in parallel with themselves (e.g. `withoutOverlapping(120)` or similar so a 6-hour sync doesn’t stack).  
   - Optionally **exclude** the sync from running at the same time as the busiest scheduler window (e.g. when Refresh:new runs every 2 min), if you can schedule it at a quieter time.

2. **Reduce connection hold time in sync**  
   - The sync command already uses one connection for the whole run. You can’t “release” it mid-run without major refactors, but you can **shorten** the run by batching (e.g. bulk API calls, bulk DB updates) so the process (and its connection) lives for a shorter time.

3. **MySQL tuning (short-term)**  
   - Slightly increase `max_connections` **only** after checking current usage (e.g. `PROCESSLIST`), and lower `wait_timeout` / `interactive_timeout` so idle connections are closed sooner.  
   - This gives a bit of headroom but doesn’t fix the structural issue (many processes + one connection each).

4. **Monitor**  
   - Log or graph connection count over time (e.g. from `PROCESSLIST`) and correlate with scheduler and sync runs to confirm the pattern.

### 4.3 ProxySQL (Infrastructure Fix)

- **When:** After (or in parallel with) the above, if you still see “Too many connections” during sync + scheduler + web, or if you want a single infrastructure lever to cap MySQL connections.
- **What:** Introduce ProxySQL in front of MySQL; point Laravel’s `DB_HOST` (and port if needed) to ProxySQL; configure a **connection pool** (e.g. `max_connections` in ProxySQL for the backend, and a larger frontend limit).
- **Result:** Fewer actual connections to MySQL; same app behaviour, but pooled. This directly addresses “too many connections” during the “in between an update sync” window.

---

## 5. Summary

| Question | Answer |
|----------|--------|
| **What’s the pattern?** | Long V2 SyncMarketplaceStock run + overlapping scheduler (e.g. Refresh:new) + queue workers + web traffic → total MySQL connections exceed `max_connections`. |
| **Why “in between” sync?** | Sync holds one connection for a long time; other processes and requests add more; the failure happens when the next connection (e.g. web request) is needed and the limit is already hit. |
| **Will ProxySQL help?** | Yes. ProxySQL pools connections so that many app connections use fewer MySQL connections, reducing the chance of 1040 during peaks. |
| **What to do first?** | Keep and tighten app/scheduler discipline (no stacking sync, stagger, reduce hold time where possible); then add ProxySQL for a robust cap on MySQL connections. |

If you want, next step can be a short “ProxySQL checklist” (install, config snippet, Laravel `.env` change, and how to verify pooling).
