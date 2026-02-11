# Listing Thirty Orders – Sync Record Tables (Analysis & Proposal)

## Goal

- Add a **new independent table** (and child) that keeps a **sync record of exactly what comes from BackMarket**.
- **Parent table**: updated by **functions:thirty** (listing data from BM).
- **Child table**: updated when **refresh:new** or **refresh:orders** have **new orders** (link those orders to the sync record).
- Tables are **independent** of `listings` / `variations` / `orders` for data content, but **reference** `variation_id` and `order_id` from existing tables.
- Client name for the new table: **listing_thirty_orders**.

---

## 1. Current Flow (Summary)

### functions:thirty

1. Runs **refresh:new** (sync new BM orders, deduct stock).
2. **get_listings()**: `$bm->getAllListings()` → per country, per listing:
   - BM payload: `listing_id`, `sku`, `id` (uuid), `quantity`, `publication_state`, `state` (grade), `title`, `price`, `min_price`, `max_price`, `currency`.
   - Writes to: `variations` (reference_id, sku, grade, name, reference_uuid, listed_stock, state), `listings` (country, variation_id, marketplace_id=1, min/max_price, price, reference_uuid, name).
3. **get_listingsBi()**: `$bm->getAllListingsBi()` → per country, per listing (by SKU):
   - BM payload: `sku`, `currency`, `quantity`, `price`, `same_merchant_winner`, `price_for_buybox`.
   - Writes to: same `listings`/`variations` (by SKU match).
4. **createStockComparisons()**: `getAllListings()` again → writes to **listing_stock_comparisons** (api_stock, our_stock, pending counts, etc.). Truncated when oldest record > 3 hours.

### refresh:new

- Gets new (pending) orders from BM → **updateOrderInDB** / **updateOrderItemsInDB** (orders, order_items).
- Validates orderlines (API POST).
- Syncs incomplete orders (missing label/delivery note).
- Deducts listed stock (marketplace_stock, variations.listed_stock).

### refresh:orders

- Gets new orders → same sync + validate.
- Gets modified orders (getAllOrders) → sync to DB.
- Status corrections and SyncShippedOrderToBackMarketJob.

---

## 2. Proposed Table Design

### 2.1 Parent: `listing_thirty_sync` (BM listing snapshot – “the list” from function thirty)

**Purpose**: One row per **BM listing snapshot** per run of functions:thirty. Stores **exactly what came from BM** (numbers + refs). Independent of `listings` table; only FKs are `variation_id` (and optional `country_id` if you use country table).

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| variation_id | unsignedBigInteger, nullable, index | FK to variations.id (our ref) |
| country_code | string(10), nullable, index | BM country code (e.g. from getAllListings key) |
| bm_listing_id | string(255), index | BM listing_id (reference_id) |
| bm_listing_uuid | string(255), nullable | BM id (uuid) |
| sku | string(255), nullable, index | SKU from BM |
| source | string(50) | 'get_listings' \| 'get_listingsBi' |
| quantity | integer default 0 | BM quantity |
| publication_state | tinyInteger nullable | BM publication_state (0–4) |
| state | tinyInteger nullable | BM state (grade-related) |
| title | string(500) nullable | BM title |
| price_amount | decimal(12,2) nullable | Extracted price amount |
| price_currency | string(10) nullable | Currency code |
| min_price | decimal(12,2) nullable | From BM |
| max_price | decimal(12,2) nullable | From BM |
| payload_json | json nullable | Full BM object (optional, for “exactly what came”) |
| synced_at | timestamp | When this row was written (functions:thirty run) |
| created_at, updated_at | timestamps | |

**Unique / index**: `(variation_id, country_code, bm_listing_id, synced_at)` or per-run: e.g. one row per (variation_id, country_code, bm_listing_id) and we **update** on each run, or we **insert** every run (history). Recommendation: **insert every run** (append-only sync log), so we keep history; then “current” view = latest synced_at per (variation_id, country_code, bm_listing_id).

Optional: add `run_id` (e.g. UUID or timestamp of the functions:thirty run) to group all rows of one run.

---

### 2.2 Child: `listing_thirty_orders` (orders linked to the thirty sync)

**Purpose**: When **refresh:new** or **refresh:orders** process a **new order** from BM, we “update the list” by inserting a row here: link that order to the sync context. So this table is the **child** of the thirty sync concept (we can link to `listing_thirty_sync.id` if we want “which snapshot this order was seen in”, or only to variation + order).

**Option A – Link to sync run / listing snapshot**

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| listing_thirty_sync_id | unsignedBigInteger, nullable, index | FK to listing_thirty_sync.id (optional) |
| order_id | unsignedBigInteger, index | FK to orders.id (our order) |
| order_item_id | unsignedBigInteger, nullable, index | FK to order_items.id (optional) |
| variation_id | unsignedBigInteger, index | FK to variations.id (denormalized for quick filter) |
| bm_order_id | string(255), nullable, index | BM order_id (reference_id) |
| source_command | string(50) | 'refresh:new' \| 'refresh:orders' |
| synced_at | timestamp | When we recorded this |
| created_at, updated_at | timestamps | |

**Option B – Simpler: only “orders that arrived via thirty context”**

If you don’t need to tie each order to a specific listing_thirty_sync row:

| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| order_id | unsignedBigInteger, unique, index | FK to orders.id |
| order_item_id | unsignedBigInteger, nullable | FK to order_items (if one row per line) |
| variation_id | unsignedBigInteger, index | |
| bm_order_id | string(255), nullable | BM order_id |
| source_command | string(50) | 'refresh:new' \| 'refresh:orders' |
| synced_at | timestamp | |
| created_at, updated_at | timestamps | |

So: **listing_thirty_orders** = “orders that we learned about via refresh:new/refresh:orders” (the “list” of orders tied to the thirty flow). Parent = listing snapshot from BM; child = order refs.

**Naming**: You said the new table will be called **listing_thirty_orders**. So we can have:

- **listing_thirty_sync** = parent (listing snapshot from function thirty), **or**  
- **listing_thirty_orders** = the table that stores **order refs** (child). Then the “listing” snapshot table could be **listing_thirty** or **listing_thirty_listings**.

If you prefer a single name “listing_thirty_orders” for the main table, then:

- **listing_thirty_orders** = parent = one row per BM listing snapshot (what came from BM in function thirty).
- **listing_thirty_order_refs** (or **listing_thirty_order_lines**) = child = one row per order (and optionally order_item) we add when refresh:new / refresh:orders see a new order.

---

## 3. Integration Points

### 3.1 FunctionsThirty

- In **get_listings()**, after resolving `$variation` and before/after updating `Listing_model` / `Variation_model`:
  - Insert (or upsert) into **listing_thirty_sync** (or **listing_thirty_orders** if that’s the listing table name):
    - variation_id, country_code, bm_listing_id, bm_listing_uuid, sku, source = 'get_listings', quantity, publication_state, state, title, price/min/max, currency, payload_json (optional), synced_at = now().
- In **get_listingsBi()**, same idea:
  - Insert row with source = 'get_listingsBi', same kind of fields from `$list`.

Keep this **additive**: no updates to existing rows in other tables; only insert into the new table(s). Optionally add a `run_id` at the start of handle() and set it on every insert so you can query “all listing snapshots from this run”.

### 3.2 RefreshNew

- After **updateOrderInDB** / **updateOrderItemsInDB** for each new order (or after the loop over `$resArray1`):
  - For each order that was **new** (created or just synced from BM):
    - Insert into **listing_thirty_orders** (child): order_id, variation_id (from order items), bm_order_id = order_id from BM, source_command = 'refresh:new', synced_at = now().
  - If you use Option A (link to snapshot), you can set listing_thirty_sync_id to the **latest** listing_thirty_sync row for that variation_id (or leave null if not needed).

### 3.3 RefreshOrders

- Same as RefreshNew for the **new orders** block (getNewOrders): when we sync a new order into DB, insert one row into **listing_thirty_orders** with source_command = 'refresh:orders'.
- For the **getAllOrders** (modified orders) block: optionally also insert a row when we update an existing order (so you have a log of “this order was touched by refresh:orders”), or only insert for orders that were **new** in that run. Recommendation: only for **new** orders to keep “the list” as “orders that appeared via thirty-related commands”.

---

## 4. Summary

| Item | Proposal |
|------|----------|
| **Parent table** | **listing_thirty_sync** (or **listing_thirty_orders** if you want that name for the listing snapshot). Stores one row per BM listing snapshot from functions:thirty (get_listings / get_listingsBi). Columns: variation_id, country_code, bm_listing_id, sku, source, quantity, publication_state, price fields, payload_json, synced_at. |
| **Child table** | **listing_thirty_orders** (or **listing_thirty_order_refs**). Stores one row per order (and optionally order_item) that we sync from refresh:new or refresh:orders. Columns: order_id, variation_id, bm_order_id, source_command, synced_at; optionally listing_thirty_sync_id. |
| **functions:thirty** | In get_listings and get_listingsBi, after processing each BM listing, insert into parent table (exact BM numbers + variation_id, country, source). |
| **refresh:new** | After syncing each new order to DB, insert into child table (order_id, variation_id, bm_order_id, source_command = 'refresh:new'). |
| **refresh:orders** | Same for new orders: insert into child with source_command = 'refresh:orders'. |
| **Independence** | New tables do not replace listings/orders; they only store sync records and reference variation_id / order_id. |

---

## 5. Next Steps

1. Confirm names: parent = **listing_thirty_sync** vs **listing_thirty_orders**; child = **listing_thirty_orders** vs **listing_thirty_order_refs**.
2. Add migrations for the two tables (and optional run_id / indexes).
3. Implement inserts in FunctionsThirty (get_listings, get_listingsBi).
4. Implement inserts in RefreshNew and RefreshOrders for new orders.
5. Optionally: add a small service or trait to avoid duplicating insert logic (e.g. `ListingThirtySyncService::recordListingSnapshot()` and `::recordOrderFromRefresh()`).

If you confirm the naming and whether you want payload_json / run_id, the next step is to add the migrations and the insert calls in the three commands.
