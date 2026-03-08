# Orders In Between vs Expected (from qty) – Why They Differ

## What we compare

- **Orders In Between (left)**  
  Count of **distinct marketplace orders** (order_type_id = 3) for this variation in the period from **this verification** up to **the next verification** (or "now" for the latest).  
  From DB: `listed_stock_verification.orders_in_between` (job every 6h) or computed as `orders_arrived_between`.

- **Expected (from qty) (right)**  
  **Expected = Qty After (this row) − Qty Before (next row)**  
  So it's the drop in listed stock between this verification and the next, assuming only sales (no manual adjustments, no sync overwrites).

- **Consistent?** ✓ when Orders In Between = Expected; ✗ when they differ (cell highlighted).

---

## What's been fixed (implemented)

| # | Point | Status | Implementation |
|---|--------|--------|----------------|
| 1 | **BackMarket: no double-reduce on order completed** | ✅ Solved | When an order is marked completed, we no longer reduce `listed_stock` locally and then push to BackMarket. For **BackMarket (marketplace_id = 1)** we now **sync FROM the BackMarket API** (fetch current quantity, set `marketplace_stock.listed_stock` and `variation.listed_stock`). BackMarket is the single source of truth. See `App\Listeners\V2\ReduceStockOnOrderCompleted` → `syncBackMarketStockOnOrderCompleted()`. |
| 2 | **refresh:new and refresh:orders trigger the same flow** | ✅ Solved | Both commands now fire `OrderStatusChanged` when an order **becomes** completed (status 3). So when they sync an order that is or transitions to completed, `ReduceStockOnOrderCompleted` runs and (for BackMarket) syncs stock from API. No separate deduction in those commands. |
| 3 | **V2 sync commands (v2:sync-orders)** | ✅ Verified | They do **not** perform any stock deduction; they only call `OrderSyncService::syncOrder(..., true)`, which fires `OrderStatusChanged` when status changes. The listener handles stock; for BackMarket it syncs from API. No wrong deduction. |

**Still open (recommendations):** Reduce "no sync" gaps before next verification (e.g. sync or fetch from API before topup); avoid duplicate verification rows; interpretation notes for manual reduction vs orders.

---

## Root causes of mismatches

### 1. BackMarket auto-reduces; we don't always sync before the next verification

**What happens**

- When a sale happens on BackMarket, **BackMarket decreases quantity automatically**.
- Our **orders** table gets the order; we count it in "Orders In Between".
- Our **listed stock** in the DB is only updated when we **sync from the BackMarket API**.

**Where we sync from BackMarket**

- **Scheduled / console:** `SyncMarketplaceStock`, `SyncAllMarketplaceStockFromAPI` (set `listed_stock = API quantity`); `FunctionsThirty` (hourly) updates `variation.listed_stock`.
- **V2 add_quantity:** At start we call `update_qty($bm)` but V2 uses **marketplace_stock** for `current_listed_stock`; after a push we set `listed_stock = API response`.
- **V2 getUpdatedQuantity (ListingDataService):** Only updates DB **if API quantity ≥ current** – we do **not** pull a **lower** stock from API on page load.

So: **If no sync ran between verification A and B**, at B we still have old listed stock (e.g. 257). We record `qty_from(B) = 257`, so for row A **Expected = Qty_After(A) − Qty_Before(B) = 257 − 257 = 0**, but **Orders In Between = 8** → ✗.

**If a sync had run before B**, we'd have pulled the lower API quantity and `qty_from(B)` would reflect the 8 sales; then Expected = 8 → ✓.

**Conclusion:** BackMarket does reduce stock automatically. Mismatches often mean we didn't sync that reduced value before the next verification.

---

### 2. Duplicate verification rows (same time / process)

**What happens**

- History is loaded **newest first** by `id`. "Next" = array index i−1.
- **Job** uses **time only:** period = (previous verification's `created_at`, this verification's `created_at`]. Two rows with the **same** `created_at` get the **same** previous and the **same** order count.
- **Expected** uses the **next row in the list**. For the **second** of two same-time rows, "next" is the **first** row (same moment), not the next chronological event.

**Example (your data)**

- Two rows both 40951, 05/03 9:52:18, Rohit, 251 → 257 (+6).
- **First 9:52 row:** Next = 11:01 row → Expected = 257 − 257 = **0**. Orders In Between = **8** → 8 vs 0 ✗.
- **Second 9:52 row:** Next = first 9:52 row → Expected = 257 − 251 = **6**. Orders In Between = **8** → 8 vs 6 ✗.

V2 `add_quantity` always does `new Listed_stock_verification_model()` and `save()`, so **double submit or two pushes** can create duplicates. Preventing duplicates (e.g. one row per process_id + variation_id per action) would remove this class of mismatch.

---

### 3. Manual stock reduction (negative push)

**What happens**

- User does a **manual reduction** (e.g. −6). We record Qty Before 257, Qty After 251.
- **Orders In Between** = 0 (no orders).
- **Expected** for the **previous** row = 257 − 251 = **6**.

So we show **0** vs **exp: 6** ✗. Expected is the **total drop** (sales + manual); Orders In Between counts **only orders**.

---

### 4. Other (sync overwriting, multi-source)

- Scheduled sync overwrites `listed_stock` with API; the **next** verification's `qty_from` can reflect that. If overwrite timing or source (e.g. Refurbed vs BackMarket) differs from order creation, Expected can differ from Orders In Between.
- Mixed use of `variation.listed_stock` (V1/some commands) and `marketplace_stock` (V2) can make "current" at verification time inconsistent.

---

## Summary table

| Cause | Orders In Between | Expected | Typical pattern |
|-------|--------------------|----------|------------------|
| BackMarket auto-reduced, no sync before next verification | Correct (e.g. 8) | Too low (e.g. 0) | 8 vs exp: 0 ✗ |
| Duplicate verification rows (same time) | Same for both | Wrong for 2nd row | e.g. 8 vs exp: 6 ✗ |
| Manual reduction (e.g. −6) | 0 | Positive (e.g. 6) | 0 vs exp: 6 ✗ |
| Sync ran before next verification, no manual change | Matches | Matches | ✓ |

---

## Recommendations

1. **Reduce "no sync" gaps:** Run a BackMarket sync **before** topups/verifications (or more frequently) so `qty_from` at the next verification reflects post-sale quantity. Optionally in V2 `add_quantity`, fetch current quantity from BackMarket and update `marketplace_stock` before writing the verification.
2. **Avoid duplicate verification rows:** In V2 `add_quantity`, avoid creating a new verification row when one for the same process_id + variation_id already exists for this action (update existing or enforce one per process/variation per push).
3. **Interpretation:** Orders In Between > Expected → often "Qty Before" at next row was **stale** (BackMarket sold, we didn't sync) or "next" is wrong (duplicate). Orders In Between < Expected → often **manual reduction** or other non-order stock change.
4. **BackMarket:** BackMarket **does** reduce quantity automatically on sale. Numbers align when we **sync** that reduced quantity before the next verification.

---

**Related:** `CalculateOrdersInBetweenJob`, `get_variation_history` (Expected formula), V2 `add_quantity`, `SyncMarketplaceStock`, `ListingDataService::getBackmarketStockQuantity`.
