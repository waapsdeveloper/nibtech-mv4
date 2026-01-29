# Analysis: Intensive / Redundant DB Queries in RefreshNew and FunctionsThirty

This document lists database-heavy or redundant patterns in `Refresh:new` and `functions:thirty`, especially around log-like entries and per-item queries.

---

## 1. RefreshNew (`app/Console/Commands/RefreshNew.php`)

### 1.1 Redundant: Order fetched twice per order

- **Where:** `updateBMOrder()` fetches the order to check if new/status; then `deductListedStockForOrder()` fetches the same order again.
- **Flow:**
  - `updateBMOrder()`: `Order_model::where('reference_id', $orderObj->order_id)->where('marketplace_id', $marketplaceId)->first()` → `$existingOrder`.
  - Then `updateOrderInDB()` / `updateOrderItemsInDB()` run (order is updated in DB).
  - Then `deductListedStockForOrder($orderObj, ...)` runs.
  - Inside `deductListedStockForOrder()`: `Order_model::where('reference_id', $orderObj->order_id)->where('marketplace_id', $marketplaceId)->first()` again to get `$order`.
- **Impact:** One extra `SELECT` per order. Avoidable by passing the already-fetched order (or its id) into `deductListedStockForOrder()` after `updateOrderInDB()` so the command can reuse the same row.

### 1.2 N+1: Variation and marketplace_stock per order item

- **Where:** `deductListedStockForOrder()` loop over `$orderItems`.
- **Per item:**
  - `Variation_model::find($item->variation_id)` → 1 query per item.
  - `$variation->save()` → 1 UPDATE per item.
  - `MarketplaceStockModel::firstOrNew([...])` → 1 SELECT or INSERT per item.
  - `$marketplaceStock->refresh()` when exists → 1 extra SELECT per item.
  - `$marketplaceStock->save()` → 1 UPDATE per item.
- **Impact:** For O order items: O variation selects + O variation updates + O marketplace_stock select/insert + O refresh selects + O marketplace_stock updates. Variation lookups can be batched (e.g. one `Variation_model::whereIn('id', $variationIds)->get()->keyBy('id')`), and stock updates could be considered for batching if needed.

### 1.3 Log-like / non-critical DB (already addressed)

- Stock deduction **logging** was moved from `stock_deduction_logs` table to `storage/logs/stock_deduction.log`. No DB log rows from this command anymore.

---

## 2. FunctionsThirty (`app/Console/Commands/FunctionsThirty.php`)

### 2.1 Redundant: SlackLogService → LogSetting DB on every post

- **Where:** Every `SlackLogService::post(...)` call.
- **What happens:** `SlackLogService::post()` calls:
  - `LogSetting::getActiveForType($logType, $level)` → `where(...)->get()->filter(...)->first()` (loads all matching rows from `log_settings`).
  - Sometimes `LogSetting::getActiveForKeywords($message, $level)` → same pattern (another full `get()` on `log_settings`).
- **Impact:** In FunctionsThirty there are **8** `SlackLogService::post()` calls. Each can trigger 1–2 queries to `log_settings`. So up to ~8–16 queries per run just to decide *where* to log (file/Slack). These are redundant for “log to file only” and can be reduced by **caching** log settings for the command (e.g. load once at start, reuse for all posts).

### 2.2 createStockComparisons(): One INSERT per listing (log-like table)

- **Where:** `createStockComparisons()` loop.
- **Per listing (non-archived):**
  - `Variation_model::where(['reference_id' => ..., 'sku' => ...])->first()` → 1 SELECT.
  - `Order_item_model::where('variation_id', $variation->id)->whereHas('order', ...)->get()` → 1 SELECT + join to orders.
  - `Listing_stock_comparison_model::create([...])` → 1 INSERT.
- **Impact:** For N listings: N variation lookups + N pending-order queries + **N inserts** into `listing_stock_comparisons`. This table is comparison/audit data (like a log). If the only consumer is “recent comparison report”, the same data could be written to a **dedicated log file** (e.g. `storage/logs/listing_stock_comparison.log`) instead of the DB to cut N inserts and table growth.

### 2.3 createStockComparisons(): N+1 variation and N+1 pending-orders

- **Variation:** Each listing does `Variation_model::where([...])->first()`. Variations could be **cached** by `(reference_id, sku)` for the current run (or prefetched in bulk if the API response allows).
- **Pending orders:** Each listing does `Order_item_model::where('variation_id', $v->id)->whereHas('order', ...)->get()`. So M listings → M such queries. This can be **batched**: e.g. get all variation ids from the current listings, then one query like `Order_item_model::with('order')->whereIn('variation_id', $variationIds)->whereHas('order', ...)->get()` and group by `variation_id` in PHP. That replaces M queries with 1 (or a few) batched queries.

### 2.4 get_listings(): Per-listing Currency and Variation lookups

- **Where:** Inner loop over `$lists` in `get_listings()`.
- **Per listing:**
  - `Variation_model::where(['reference_id'=>..., 'sku'=>...])->first()`.
  - `Currency_model::where('code', $curr)->first()`.
- **Impact:** For L listings: L variation queries + L currency queries. Currency codes are a small set (e.g. EUR, GBP); **caching** by `code` (e.g. `Currency_model::pluck('id','code')` or a small in-memory map) removes repeated currency queries. Variation lookups can be **cached** by `(reference_id, sku)` for the run to avoid repeated selects for the same listing.

### 2.5 get_listingsBi(): Same pattern

- **Where:** Inner loop in `get_listingsBi()`.
- **Per listing:** `Variation_model::where('sku', $list->sku)->first()`, `Currency_model::where('code', $list->currency)->first()`, then `Listing_model::firstOrNew(...)` and saves.
- **Impact:** Same idea as get_listings: **cache** currency by code and variation by sku (or by whatever key is unique in that loop) to avoid redundant queries.

### 2.6 autoTruncateStockComparisons(): One-time maintenance

- **Where:** Start of `createStockComparisons()`.
- **Queries:** `orderBy('compared_at')->first()`, then `count()`, then `truncate()`. This is **3 queries once per run**, not per-item. Reasonable; only worth optimizing if you move comparison data to file and no longer use this table.

---

## 3. Summary: “Log-like” / redundant DB usage

| Location | Type | Suggestion |
|----------|------|------------|
| RefreshNew | Duplicate order fetch | Pass order (or id) into `deductListedStockForOrder()` after update; remove second `Order_model::where(...)->first()`. |
| RefreshNew | N+1 variation in deduction loop | Load variations in one query by `whereIn('id', $variationIds)` and use a map keyed by id. |
| RefreshNew | Per-item marketplace_stock refresh + save | firstOrNew + refresh + save is 2–3 queries per item; consider batching or avoiding refresh when not needed. |
| FunctionsThirty | SlackLogService → LogSetting | Cache log settings at command start; reuse for all 8 posts to avoid 8–16 `log_settings` queries. |
| FunctionsThirty | createStockComparisons | **Listing_stock_comparison_model::create** per listing = log-like table; consider writing to a dedicated **log file** instead of DB. |
| FunctionsThirty | createStockComparisons | Batch pending orders: one (or few) `Order_item_model::whereIn('variation_id', ...)->whereHas('order', ...)->get()` and group by variation_id. |
| FunctionsThirty | createStockComparisons | Cache variation by (reference_id, sku) for the run. |
| FunctionsThirty | get_listings / get_listingsBi | Cache `Currency_model` by code (e.g. one pluck or array at start). Cache variation by (reference_id, sku) or sku for the run. |

---g

## 4. Quick wins (in order of impact)

1. **RefreshNew:** Remove duplicate order fetch in `deductListedStockForOrder()` by passing the order from `updateBMOrder()`.
2. **FunctionsThirty:** Cache LogSetting (or “listing_sync” log config) once at start of `handle()` and pass or use it so each `SlackLogService::post()` does not hit the DB.
3. **FunctionsThirty:** In `createStockComparisons()`, either (a) write comparison rows to a **log file** instead of `listing_stock_comparisons`, or (b) batch pending orders (one query per batch of variation_ids) and cache variations.
4. **RefreshNew:** Batch load variations in `deductListedStockForOrder()` with `whereIn('id', $variationIds)`.
5. **FunctionsThirty:** In `get_listings()` and `get_listingsBi()`, cache currencies by code and variations by (reference_id, sku) or sku.

This keeps business logic the same while reducing redundant and log-style DB usage.
