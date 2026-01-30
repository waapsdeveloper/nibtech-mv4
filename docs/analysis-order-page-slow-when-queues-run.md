# Analysis: Order page slow when “running all queues”

**Route:** `GET /order` → Livewire `Order` component (`app/Http/Livewire/Order.php`).  
**Complaint:** After “running all queues”, the order page becomes much slower.  
**Scope:** Analysis only; no code changes.

---

## 1. What “running all queues” does

- **Jobs UI “Process all”** (`JobsController::processAll()`): one HTTP request runs `Artisan::call('queue:work', ['--once' => true, ...])` in a loop (up to 100 times). Each iteration processes one job.
- **PM2 queue workers** (e.g. `sdpos-api-queue`, `sdpos-default-queue`): long‑lived processes that run `queue:work` and process jobs continuously.

So “running all queues” means either:

1. A user clicked “Process all” in the Jobs UI (one long‑running web request doing many jobs), and/or  
2. PM2 workers are busy processing many jobs.

---

## 2. Why the order page is heavy (even without queues)

The order page is already query‑heavy. On every load, `Order::render()` does:

### 2.1 Session / dropdown data

- Uses `session('dropdown_data')` (storages, colors, grades).
- If not set, `AppServiceProvider` view composer runs **6 pluck queries** (products, categories, brands, colors, storages, grades, admins) on **every view** until session is populated.

### 2.2 Counts and lookups (multiple queries)

- `Process_model::where(...)->pluck(...)`
- `Currency_model::pluck(...)`
- `Admin_model::pluck(...)` (twice)
- **Three `Order_model::count()`** (pending_orders_count, missing_charge_count, missing_processed_at_count)
- `Order_status_model::pluck(...)`
- `Marketplace_model::pluck(...)`
- `buildRefurbedShippingDefaults()` (can hit DB/API)

### 2.3 Optional “exclude topup” logic

- When `request('exclude_topup')` is set: `Listed_stock_verification_model::whereIn(...)->get()`, then `Variation_model::whereIn(...)->get()`, then a loop over variations and a clone of the orders query with `whereHas` and `whereNotIn('orders.id', $ids)`.

### 2.4 Main orders query

- **Large `Order_model::with([...])`** including:
  - `customer` → with `orders` (constrained: order_type_id 3, last 50, ordered by created_at)
  - `order_items`
  - `order_items.variation`, `order_items.variation.product`, `order_items.variation.grade_id`
  - `order_items.stock`, `order_items.replacement`
  - `transactions`, `order_charges`
- Many `->when(...)` filters (marketplace, type, items, start/end date, status, adm, care, missing, transaction, order_id, sku, imei, currency, tracking_number, with_stock, sort, adm).
- Optional join for sort by product model (join order_items, variation, products).
- Then **clone + paginate** via `buildOrdersPaginator($orders, $per_page)`.

### 2.5 Extra work after pagination

- **`tryFetchMissingRefurbedOrders()`**: can run and then **rebuild the paginator** (main query run again).
- **When `request('missing') == 'processed_at'`**: for **each** `reference_id` on the current page, **`$this->recheck($ref)`** is called. Each `recheck()` does BackMarket API + DB updates. So N orders on the page ⇒ N rechecks.
- **When `request('order_id')` has multiple refs** (space‑separated) and marketplace is not Refurbed: **`$this->recheck($or)`** for each ref. Again, many API + DB calls.

So the order page:

- Runs many queries (counts, plucks, one big eager‑loaded query, optional clone/filter, paginate, sometimes same query again).
- In some filter combinations, adds **N × recheck()** (API + DB) per page load.

---

## 3. Why “running all queues” makes it worse

### 3.1 Shared MySQL: connections and contention

- **Connections:** Each web request and each queue worker uses a DB connection. MySQL has a finite `max_connections`. When “Process all” runs, one web request holds a connection for a long time (loop of `queue:work --once`). PM2 workers each hold a connection. So total connections = web requests + scheduler + queue workers. When the pool is full, **new requests (e.g. order page) wait or fail**.
- **Lock / I/O contention:** Jobs like `UpdateOrderInDB` read and write `orders`, `order_items`, `customers`, and related tables. The order page runs big SELECTs (and sometimes updates via `recheck`) on the **same tables**. So:
  - Row/table locks from jobs can **block** or delay the order page’s queries.
  - Even without locks, **CPU and disk I/O** are shared; heavy job activity slows down the order page’s heavy query.

### 3.2 “Process all” in the web process

- **JobsController::processAll()** runs **inside a single HTTP request**. That request runs `queue:work --once` repeatedly (up to 100 times). So:
  - One PHP/Web worker is busy for a long time (e.g. 100 × job duration).
  - If you use PHP‑FPM (or similar), **fewer workers are free** for other requests. The order page request may **queue** until a worker is free, so the user sees long wait before the page even starts.
- So “running all queues” from the UI can both **hold a DB connection** and **reduce capacity** for the order page at the same time.

### 3.3 What queue jobs do (same resources as the order page)

- **UpdateOrderInDB:**  
  - Plucks: Currency_model, Country_model.  
  - Order_model::firstOrNew, updateCustomerInDB, mapStateToStatus, getOrderLabel (API), order_model->updateOrderInDB, order_item_model->updateOrderItemsInDB, etc.  
  So each job does a lot of **reads/writes on orders, order_items, customers** — the same data the order page reads.

Other jobs (e.g. API requests, syncs) also use the DB. So when “all queues” are running:

- More connections and more read/write load on the same tables the order page uses.
- The order page’s big SELECT (and optional rechecks) competes for connections, locks, CPU, and I/O.

---

## 4. Summary: cause of slowness

| Factor | Effect |
|--------|--------|
| Order page is already heavy | Many queries, one big eager‑loaded orders query, optional second run of the same query, and in some cases N× recheck() (API + DB) per load. |
| Queues hold DB connections | “Process all” (long‑lived request) + PM2 workers use several connections; pool can be exhausted or nearly full. |
| Same tables | Jobs read/write orders, order_items, customers; order page reads (and sometimes updates) the same tables → lock and I/O contention. |
| “Process all” in web process | One HTTP request runs many jobs; one worker busy for a long time → fewer workers for the order page → request may wait in queue. |

So the order page goes slow when queues run because:

1. **Resource contention:** DB connections, CPU, and I/O are shared; heavy queue activity slows the order page’s heavy query and optional rechecks.  
2. **Connection pressure:** Many connections in use (web + workers + “Process all”) can make new connections slow or fail.  
3. **Worker starvation (when using “Process all”):** One long‑running web request ties up a worker, so the order page request may wait before it even starts.

---

## 5. What to change later (not in this analysis)

- **Order page:** Reduce queries (e.g. cache dropdown/counts, avoid N× recheck on page load, consider deferred table load like OrdersTable/OrderTableQuery if applicable).  
- **Queues:** Run queue processing in background (e.g. only PM2 workers), not via “Process all” in a web request; optionally limit concurrency or prioritize queues.  
- **DB:** Tune `max_connections`, connection pooling (e.g. ProxySQL), and indexes/query shape for the orders query and job updates.  
- **Cache:** Use file/Redis for cache so cache access doesn’t add more DB load when queues are busy.

This document is analysis only; no code has been changed.
