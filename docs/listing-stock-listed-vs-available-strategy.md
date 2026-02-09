# Listing: Total Stock, Listed vs Available – Flow and Strategy

## 1. Where "Total Stock" comes from (listing card)

**File:** `resources/views/v2/listing/partials/variation-card.blade.php` → `total-stock-form.blade.php`

- **Total Stock** = sum over all marketplaces of:
  - **`listed_stock`** (API-synced quantity from Back Market / distribution)
  - **`manual_adjustment`** (local offset, not sent to BM until pushed)
- Formula: `$totalStock = Σ(listed_stock) + Σ(manual_adjustment)`.
- Fallback when no marketplace rows exist: `variation.listed_stock`.
- **Available** (physical) = `$variation->available_stocks->count()` (physical inventory items).

So on the card:
- **Total Stock** = listed (what we show BM / what’s “live” on the listing) + adjustment.
- **Available** = physical stock count.

Mismatch = **Total Stock ≠ Available** (same as “listed ≠ available” when there is no manual adjustment).

---

## 2. What happens when user uses Add/Subtract and pushes

**Form:** `total-stock-form.blade.php`  
**Action:** `POST v2/listings/add_quantity/{variationId}`  
**Payload:** `stock` = delta (e.g. `+1`, `-2`).

**Backend:** `App\Http\Controllers\V2\ListingController::add_quantity()`

### Step-by-step

1. **Current total (before change)**  
   - `current_listed_stock` = Σ(marketplace `listed_stock`) + Σ(marketplace `manual_adjustment`).  
   - Same as “Total Stock” on the card.

2. **Optional: fetch BM**  
   - `$variation->update_qty($bm)` fetches BM listing and updates **only** `variation.listed_stock` (and sku/state).  
   - It does **not** update `marketplace_stock`; add_quantity uses marketplace_stock for the total, so this fetch does not change the “current total” used for the push.

3. **New quantity**  
   - Normal case: `new_quantity = current_listed_stock + stock` (stock = add/subtract delta).  
   - Verification process case: `new_quantity = stock - pending_orders` (stock = new total after pending).

4. **If variation has `reference_id` (BM listing):**
   - **Push to BM API:** `quantityToPushToApi = (BM listed_stock + BM manual_adjustment) + stockChange`, then `updateOneListing(reference_id, quantity)`.
   - **Response:** BM returns new quantity (or we fetch via `update_qty`).
   - **DB update (BM marketplace row):**
     - `listed_stock` = quantity from API response.
     - `manual_adjustment` = 0 (we “used up” the adjustment by pushing).
   - **History:** `marketplace_stock_history` row with `change_type = 'api_sync'`.

5. **If no `reference_id`:**
   - Only local: `manual_adjustment += stockChange` on BM marketplace row (or firstOrCreate).
   - **History:** `marketplace_stock_history` with `change_type = 'manual'`.

6. **After save:**
   - Recompute total: `calculatedTotalStock` = Σ(listed_stock) + Σ(manual_adjustment).
   - **variation.listed_stock** = `calculatedTotalStock` (backward compatibility).
   - **listed_stock_verification** row: qty_from, qty_change, qty_to, process_id, admin_id.

7. **Response to frontend:**  
   JSON with `total_stock`, `marketplace_stocks`, `distribution_preview`, `stock_change`.  
   **JS** (`total-stock-form.js`): updates `#total_stock_{id}` and optional Backmarket badge.

So: one push = one BM API call (when reference_id exists), DB and history updated, Total Stock on card reflects new listed + adjustment.

---

## 3. Why “listed ≠ available” happens

- **Listed** = Total Stock (listed_stock + manual_adjustment) we show and push to BM.
- **Available** = physical `available_stocks` count.

They diverge when:

1. **Physical change without a push**  
   Stock added/removed in warehouse (topup, sale, return) but listing not updated via form or sync.

2. **BM / sync overwrites listed**  
   Another process (e.g. refresh orders, sync job) or BM side change updates `listed_stock` and we don’t align to physical.

3. **Manual adjustment used as buffer**  
   We keep `listed_stock` lower and use `manual_adjustment` to show a different total; if not pushed or if BM sync clears it, listed and available can mismatch.

4. **Timing**  
   Orders, cancellations, or returns change physical stock before or after we push to BM.

So the “listed vs available” strategy is really: when and how we change **listed** (and optionally **manual_adjustment**) so it matches **available**, and how we keep the BM API and our DB in sync.

---

## 4. Suggested handling (strategy)

### A. Keep single source of truth for “display total”

- **Card Total Stock** = Σ(marketplace `listed_stock`) + Σ(marketplace `manual_adjustment`) (already the case).
- **Don’t** use `variation.listed_stock` for the card total when marketplace_stock rows exist; use it only as fallback.  
  (Already done in variation-card and add_quantity.)

### B. When user pushes from the form (current behaviour – keep it)

- Use **current total from marketplace_stock** (listed + manual).
- Push to BM = **new total** (current + delta), then set BM row to API response and clear BM manual_adjustment.
- Always write **marketplace_stock_history** and **listed_stock_verification** so every change is auditable.

No change needed here; just keep this as the standard “user-driven” path.

### C. When listed ≠ available (mismatch)

You already have:

- **listing:stock-mismatch-report**  
  Finds variations where (listed + manual) ≠ available, with history and suggested adjustment.
- **listing:stock-mismatch-report --apply**  
  For each mismatch: set BM `listed_stock` (and optional manual) so **total displayed = available**, and insert **marketplace_stock_history** + **listed_stock_verification** so the change is in “history”.

Recommendation:

- Run **report** regularly (e.g. cron) to monitor mismatches.
- Run **--apply** when you want to bulk-align listed to available (e.g. after stock takes or after reviewing the report).  
  Optionally restrict --apply to cases where e.g. `available > 0` and you have tracking/label so you don’t set listed for truly unshipped items.

### D. Optional: “Match to available” from the listing UI

Add a small control on the listing card (e.g. “Set listed = available”) that:

1. Computes **delta** = available - current_total (same as mismatch report).
2. Calls the same backend logic as one “add_quantity” push with that delta (or a dedicated endpoint that does the same: update BM, update marketplace_stock, write history + listed_stock_verification).
3. Refreshes Total Stock and badge (same as current form success handler).

That way users can fix a single variation without running the artisan command.

### E. Sync / refresh orders and listing

- **refresh:orders** (and similar) should not overwrite **marketplace_stock** blindly; they should either:
  - Only update order-related state, or
  - If they do touch listing quantity, do it via the same rules (e.g. set listed from BM response and keep manual_adjustment where appropriate).
- Prefer: listing quantity is updated only by:
  - User push (add_quantity),
  - Scheduled sync from BM (e.g. SyncMarketplaceStock) that writes to marketplace_stock + history,
  - Or explicit “reconcile to available” (mismatch report --apply or UI “set listed = available”).

### F. Summary table

| Action                    | Who/What              | Effect on listed                         | History |
|---------------------------|-----------------------|-----------------------------------------|--------|
| Add/Subtract + Push       | User (total-stock-form) | BM row: listed = API response, manual = 0 | Yes (api_sync/manual + verification) |
| Stock mismatch --apply    | Artisan               | Set listed (and optional manual) so total = available | Yes (reconciliation + verification) |
| Set listed = available (UI) | User (future button)  | Same as one push with delta             | Yes (same as add_quantity) |
| BM sync / refresh         | Jobs / cron           | Should update marketplace_stock + history only via defined flows | Yes (reconciliation/api_sync) |

---

## 5. Technical summary

- **Total Stock on listing** = Σ(marketplace `listed_stock`) + Σ(marketplace `manual_adjustment`).  
  **Available** = physical `available_stocks` count.
- **Form push** = POST to `v2/listings/add_quantity/{id}` with delta → BM API update → DB (listed/manual) + marketplace_stock_history + listed_stock_verification → frontend updates Total Stock and badge.
- **Handling listed ≠ available:** use **listing:stock-mismatch-report** (and **--apply**) to align listed with available and keep all changes in history; optionally add a per-variation “Set listed = available” in the listing UI that reuses the same backend behaviour.

This keeps the “total = listed + adjustment” model, makes every change auditable, and gives a clear path to align listed with available when they diverge.
