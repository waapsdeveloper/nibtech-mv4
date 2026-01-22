# Database Connection Analysis – Last Week Changes

**Date:** January 2026  
**Scope:** Code changes in the last ~7 days that may contribute to "too many database connection requests."

---

## 1. Summary of Recent Changes (Last Week)

| Change | Files | Relevance to DB Connections |
|--------|--------|-----------------------------|
| **Database connection leak fix** | `AppServiceProvider`, Jobs, `DATABASE_CONNECTION_LEAK_FIX.md` | Queue jobs + global `Queue::after` / `Queue::failing` call `DB::disconnect()` after every job. Reduces **leaks** but increases **connect/disconnect churn**. |
| **FunctionsThirty calls Refresh:new** | `FunctionsThirty.php` | Hourly run now **invokes Refresh:new** first. Same process, but both are DB-heavy; amplifies load during that window. |
| **Stock deduction + comparison** | `RefreshNew.php`, `FunctionsThirty.php`, `Stock_deduction_log_model`, `Listing_stock_comparison_model` | New loops: per order → `Variation_model::find`, `MarketplaceStockModel::firstOrNew`/`refresh`/`save`, `Stock_deduction_log_model::create`. Per listing in `createStockComparisons` → `Variation_model::where`, `Order_item_model::whereHas('order')`, `Listing_stock_comparison_model::create`. **Classic N+1 patterns.** |
| **Order page eager-loading fix** | `Order.php` (Livewire) | Replaced unbounded `customer.orders` with `customer.orders` limited to 50. Reduces **query volume** per render but still loads orders for **every** customer on the page. |
| **Ecosystem / scheduler** | `ecosystem.config.js`, `Kernel.php` | Separate **api-requests** queue worker; **~20 scheduled commands** with `runInBackground()`. Each run = **new PHP process = new DB connection**. |
| **Scheduler optimization indexes** | `2026_01_08_094313_add_scheduler_optimization_indexes.php` | Lowers **query cost** (CPU/lock time), not connection count. Helpful for stability, not for "too many connections." |
| **Slack log batching** | `RefreshNew.php`, `SlackLogService` | Reduces Slack API calls, not DB. |
| **Duplicate order protection** | `OrderSyncService`, `RefurbedWebhookController`, migrations | More `Order_model::updateOrCreate` / retries. Slightly more DB work per order, not a major connection driver. |

---

## 2. Root Causes of High DB Connection / Request Volume

### 2.1 Scheduler + `runInBackground()` (Primary)

- **~20 commands** use `runInBackground()`.
- Each invocation spawns a **new `php artisan <command>` process** → **one new DB connection per process**.
- Commands overlap (every 2, 5, 10 min, hourly, etc.). Long-running ones (e.g. `refresh:new`, `functions:thirty`, `v2:marketplace:sync-stock`) can run **concurrently**.
- **Baseline:** 1 scheduler (`schedule:work`) + 2 queue workers = **3 persistent connections**.
- **Peak:** 3 + multiple overlapping background commands → **10–15+ concurrent connections** easily.

**Kernel schedule (simplified):**

- **Every 2 min:** `refresh:new`
- **Every 5 min:** `refresh:latest`, `refresh:orders`, `refurbed:new`, `api-request:process`
- **Every 10 min:** `price:handler`, `refurbed:link-tickets`, `functions:ten`, `support:sync`, `bmpro:orders`
- **Every 30 min:** `queue:retry all`
- **Hourly:** `refurbed:orders`, `functions:thirty` (which also runs `refresh:new`), `backup:email`, `fetch:exchange-rates`
- **2–6 h / daily:** `v2:marketplace:sync-stock`, `v2:sync-orders`, etc.

At :00 or :30 you can have **several** of these starting within the same minute → connection spikes.

---

### 2.2 Queue Workers + `DB::disconnect()` Churn

- **`Queue::after`** and **`Queue::failing`** in `AppServiceProvider` call **`DB::disconnect()`** after **every** job.
- Jobs themselves (`SendApiRequestPayload`, `SyncMarketplaceStockJob`, `ExecuteArtisanCommandJob`, `UpdateOrderInDB`) also disconnect in `finally` / `__destruct`.
- Effect: each job **connects → runs → disconnects**. Next job **reconnects**.  
- So **"connection requests"** (connects) scale with **job throughput**, even if concurrent connections stay low. High job volume (e.g. `api-request:process` dispatching many `SendApiRequestPayload`, or `queue:retry all` re-dispatching failed jobs) → **lots of connect/disconnect cycles**.

---

### 2.3 N+1 and Heavy Loops in Commands

**RefreshNew (`refresh:new`, every 2 min):**

- Per order: `Order_model::where()->first`, `updateOrderInDB`, `updateOrderItemsInDB`, `deductListedStockForOrder`.
- Inside `deductListedStockForOrder`: `Order_item_model::where()->get`, then per item `Variation_model::find`, `MarketplaceStockModel::firstOrNew` / `refresh` / `save`, `Stock_deduction_log_model::create`.
- So **many queries per order** and **per order line**.

**FunctionsThirty (hourly):**

- Calls **`refresh:new`** then `get_listings` + `get_listingsBi` + `createStockComparisons`.
- `get_listings` / `get_listingsBi`: per listing, `Variation_model::where` / `firstOrNew`, `Currency_model::where`, `Listing_model::firstOrNew` / `save`, etc. **No batching.**
- `createStockComparisons`: per listing, `Variation_model::where()->first`, `Order_item_model::where()->whereHas('order')->get`, `Listing_stock_comparison_model::create`. Again **N+1**.

**UpdateOrderInDB (job):**

- Per orderline: `Order_item_model::firstOrNew`, `Variation_model::where`, sometimes `getOneListing` + variation save, `Stock_model::firstOrNew`, `Order_model::where()->first`. **Heavy per-item work.**

**Api_request_model::push_testing (`api-request:process`):**

- Loads `Admin_model`, `Storage_model`, `Color_model`, `Grade_model` up front, then `chunkById` over `Api_request_model`. Each chunk does more DB work. **Chunking helps**, but the command still runs often (every 5 min) and can dispatch **many** `SendApiRequestPayload` jobs → more queue activity and thus more connect/disconnect.

---

### 2.4 Global View Composer + Session (`dropdown_data`)

- **`view()->composer('*', ...)`** in `AppServiceProvider` runs on **every** view.
- When `!Session::has('dropdown_data')`, it runs **7 `pluck` queries** (products, categories, brands, colors, storages, grades, admins) and stores in session.
- **Every new session** (e.g. new user, new device, session expiry) → **7 extra queries** per first request.  
- **Order Livewire** (and possibly others) sometimes **rebuild** `dropdown_data` (again 7 plucks) and overwrite session → **repeated 7-query bursts** on those flows.

---

### 2.5 Livewire Order Page (`render`)

- **Order** Livewire `render()` runs on **every** request and **every** Livewire update (filters, pagination, etc.).
- It uses `dropdown_data` plus **many** own queries: `Process_model`, `Currency_model`, `Admin_model` (×2), `Order_model` (×3 for counts), `Order_status_model`, `Marketplace_model`, `Listed_stock_verification_model` / `Variation_model` (when `exclude_topup`), and the main **`Order_model::with([...])`** including `customer.orders` (limit 50).
- **Each** Livewire interaction re-renders → **same heavy query set again**. High traffic on Orders → **lots of DB usage**.

---

### 2.6 `queue:retry all` Every 30 Minutes

- **`queue:retry all`** re-dispatches **all** failed jobs.
- If many jobs have failed (e.g. API timeouts, temporary DB issues), **retrying all** causes a **sudden spike** in queued work → **more jobs** → more **connect/disconnect** in workers.

---

## 3. Link to “Too Many Connections” vs “Too Many Connection Requests”

| Symptom | Likely cause |
|---------|---------------|
| **`SQLSTATE[HY000] [1040] Too many connections`** | **Concurrent** connections exceeding `max_connections`: scheduler background processes + queue workers + web requests. |
| **High "connection request" rate** (connects/sec) | **Connect/disconnect churn**: `DB::disconnect()` after every job, plus many short-lived artisan processes. |
| **Both** | Combination of **many overlapping processes** (scheduler) and **frequent connect/disconnect** (queue + short-lived commands). |

---

## 4. Recommended Actions (Prioritised)

### 4.1 High impact, lower effort

1. **Avoid `DB::disconnect()` after every job**  
   - Remove **global** `Queue::after` / `Queue::failing` → `DB::disconnect()` in `AppServiceProvider`.  
   - Rely on **per-job** disconnect only for **long-running or artifact-heavy** jobs (e.g. `ExecuteArtisanCommandJob`, `SyncMarketplaceStockJob`).  
   - **Effect:** Fewer connect/disconnect cycles per job; workers reuse connections across jobs.

2. **Stagger scheduler commands**  
   - Use **`->at(':15')`**, **`->at(':35')`**, etc. so that multiple heavy commands **don’t all start at :00 / :05 / :10**.  
   - **Effect:** Fewer simultaneous background processes → lower concurrent connection peaks.

3. **Review `queue:retry all`**  
   - Replace **`queue:retry all`** every 30 min with **retry only recent failures** (e.g. `queue:retry` for specific jobs or a **bounded** subset).  
   - **Effect:** Avoids sudden job (and connection) spikes when many jobs have failed.

### 4.2 High impact, more effort

4. **Reduce `runInBackground()` usage where possible**  
   - For **quick** commands, run **inside** the scheduler process (no `runInBackground`) so they share the scheduler’s DB connection.  
   - Reserve `runInBackground()` for **long-running** commands only.  
   - **Effect:** Fewer one-off PHP processes → fewer one-off connections.

5. **Fix N+1 in RefreshNew, FunctionsThirty, CreateStockComparisons**  
   - **Batch** order/orderline processing; use **`whereIn`** + **`keyBy`** for variations, marketplace_stock, etc.  
   - **Eager-load** relations needed in loops; avoid **per-item** `find` / `firstOrNew` where avoidable.  
   - **Effect:** Fewer queries per run → less DB load and shorter connection hold time.

6. **Cache `dropdown_data`**  
   - Move **dropdown_data** to **Cache** (e.g. Redis) with a TTL (e.g. 5–15 min) instead of (or in addition to) session.  
   - Ensure **view composer** and **Order** (and any rebuild logic) use **same** cache key.  
   - **Effect:** Fewer repeated 7-query bursts on new sessions or rebuilds.

### 4.3 Medium impact

7. **Optimise Order Livewire `render()`**  
   - Cache **counts** (e.g. `pending_orders_count`, `missing_charge_count`) and **dropdown_data**; avoid recalculating on **every** render.  
   - **Effect:** Fewer queries per Order page interaction.

8. **Add `withoutOverlapping()` / `onOneServer()` where missing**  
   - Ensure **long-running** scheduled commands use **`withoutOverlapping`** (and **`onOneServer`** if multi-instance) so the same command doesn’t stack.  
   - **Effect:** Fewer overlapping runs → lower connection peaks.

9. **MySQL tuning**  
   - Increase **`max_connections`** **only** if you’ve first reduced connection churn and overlap (otherwise you hide the problem).  
   - Adjust **`wait_timeout`** / **`interactive_timeout`** so idle connections are released sooner.  
   - **Effect:** More headroom and faster release of idle connections.

---

## 5. Quick Checks

```sql
-- Current connection count
SELECT COUNT(*) AS connections FROM information_schema.PROCESSLIST;

-- By user
SELECT user, COUNT(*) AS cnt FROM information_schema.PROCESSLIST GROUP BY user;

-- Long-running queries
SELECT id, user, host, db, command, time, state, LEFT(info, 80) AS info
FROM information_schema.PROCESSLIST
WHERE time > 60
ORDER BY time DESC;
```

```bash
# Scheduler log
tail -f storage/logs/pm2-scheduler*.log

# Queue workers
tail -f storage/logs/pm2-queue*.log
pm2 status
```

---

## 6. Conclusion

The **recent changes** (connection leak fix, FunctionsThirty → Refresh:new, stock deduction/comparison, Order eager-loading tweak, ecosystem/scheduler setup) **both**:

- **Reduce** long-lived connection **leaks** (good), and  
- **Increase** connection **churn** (global `DB::disconnect` per job) and **concurrency** (many `runInBackground` commands, N+1 in heavy loops).

**Most impactful levers:**

1. **Scheduler:** Stagger and reduce `runInBackground` usage; avoid many overlapping DB-heavy processes.  
2. **Queue:** Stop disconnecting after **every** job globally; reuse connections across jobs.  
3. **Commands:** Remove N+1 in `refresh:new`, `functions:thirty`, and `createStockComparisons`; batch and eager-load.  
4. **App:** Cache `dropdown_data`; optimise Order Livewire `render()`.

Applying **§4.1** first should already reduce both **connection requests** and **concurrent connection** spikes noticeably.
