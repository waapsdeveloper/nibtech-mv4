# Optimal solution: same table read/write by jobs + used by testers (order page)

**Problem:** One table (e.g. `orders`) is written by background processes (queues, refresh:new, schedulers) and read by the frontend (order page / testers). Contention causes slowness or blocking.

**Goal:** Writers and readers can both use the table without hurting each other; testers get a responsive page.

---

## Recommended approach (in order of impact)

### 1. Read/write split (best for same table, different load)

**Idea:** Writes go to the **primary** DB; reads (order page, testers) go to a **replica**. Same schema, same table — no lock contention between writers and readers.

- **Writers:** Queues, schedulers, refresh:new, any `INSERT/UPDATE/DELETE` → primary.
- **Readers:** Order page, list/count queries → replica(s).
- **Trade-off:** Replication lag (usually 1–5 seconds). Testers may see data a few seconds behind; for an order list that’s usually acceptable.

**Laravel:** Use one `mysql` connection with `read` and `write` hosts. Laravel sends read-only queries to `read`, writes to `write`.

**Config (e.g. in `config/database.php` under `connections.mysql`):**

```php
'mysql' => [
    'driver' => 'mysql',
    'read' => [
        'host' => [
            env('DB_READ_HOST', env('DB_HOST', '127.0.0.1')),
        ],
    ],
    'write' => [
        'host' => [
            env('DB_WRITE_HOST', env('DB_HOST', '127.0.0.1')),
        ],
    ],
    'sticky' => true, // optional: after a write in the request, use write for subsequent reads in same request
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
],
```

**.env (single server: same host for both until you add a replica):**

```env
DB_HOST=127.0.0.1
DB_READ_HOST=127.0.0.1
DB_WRITE_HOST=127.0.0.1
```

When you have a replica:

```env
DB_WRITE_HOST=primary.db.example.com
DB_READ_HOST=replica.db.example.com
```

So: **one table, writers on primary, readers (testers) on replica = optimal separation of load without changing app logic.**

---

### 2. Cache heavy reads (order page)

**Idea:** Don’t hit the DB for the same list/counts on every request. Cache them for a short TTL (e.g. 30–60 seconds). Writers still write to the table; testers usually read from cache.

- Cache: order list query result (or key parts), the three `Order_model::count()` (pending, missing_charge, missing_processed_at), and optionally dropdown data.
- TTL: 30–60 seconds so testers see “good enough” freshness and writers don’t block readers.
- Invalidate on write (optional): when a job updates an order, invalidate cache for that list/count so next load is fresh (or rely on short TTL).

**Effect:** Same table still read/write; testers mostly hit cache instead of DB, so writers and readers contend less.

---

### 3. Keep writers fast (short transactions)

**Idea:** Jobs that write to the table should hold locks for the minimum time.

- Open transaction → do only the necessary updates for that job → commit. No long work inside the transaction.
- Avoid: large loops, external API calls, or heavy computation inside a transaction that touches the same table testers read.

**Effect:** Readers (order page) block less when writers run.

---

### 4. Don’t mix heavy reads and N× writes on page load

**Idea:** Order page should be “read-only” for the table where possible. Any “recheck” or update per row should not run synchronously for every row on the same request that does the big SELECT.

- Today: with `missing=processed_at` or multiple `order_id`, the order page calls `recheck($ref)` for each ref → N writes (and API) on the same request that does the big read.
- Better: load the list from DB (or cache) and show it; run recheck in background (queue) or via a separate “Refresh” action so the initial page load is just a read (or cache read).

**Effect:** Same table stays read/write, but the tester’s “list” request doesn’t compete with itself (one big read + many writes in one request).

---

### 5. Connection pooling (optional)

**Idea:** Limit and reuse DB connections so many workers (web + queue) don’t exhaust the DB.

- Use ProxySQL (or similar) in front of MySQL; point Laravel to the proxy. Web and queue workers share a pool.
- Helps both writers and readers when connection count is the bottleneck; doesn’t remove lock contention but avoids “connection refused” and balances load.

---

## Summary: one table, read/write + testers

| Measure | What it does |
|--------|----------------|
| **Read/write split** | Writers → primary, readers (order page, testers) → replica. Same table; no lock contention between them. Best single change. |
| **Cache heavy reads** | Cache order list/counts 30–60 s so testers often don’t hit the table at all. |
| **Short transactions** | Jobs commit quickly so readers block less. |
| **No N× recheck on list load** | List load = read (or cache); recheck in queue or on demand. |
| **Connection pooling** | Reuse connections so writers and readers don’t exhaust the DB. |

**Optimal combo:** Read/write split (1) + cache for the order page (2) + short transactions (3) + no synchronous N× recheck on list load (4). That way the same table can be written by jobs and read by testers with minimal contention and a responsive page.
