# Stock Verification vs Available Stock – Logic Cross-Check

This document clarifies how **listed stock verification** and **available stock** are calculated and where they are shown, so the client’s “history vs available unequal / non-realistic” report can be checked consistently.

---

## 1. Definitions

| Term | Meaning | Source of truth |
|------|--------|------------------|
| **Listed stock** | Quantity we tell the marketplace (e.g. Backmarket). Can be split per marketplace. | `marketplace_stock.listed_stock` (+ `manual_adjustment`). Total ≈ `variation.listed_stock`. |
| **Available stock** | Physical inventory: units in warehouse that can be sold. | `variation->available_stocks` (stock table, status 1 or 3). **Same for all marketplaces.** |
| **Pending orders** | Units already in orders (e.g. status pending). | `variation->pending_orders` (order_items quantity sum). |

**Important:** Available and listed are **not** the same. Example: 10 available, 5 listed is valid (we list only part of stock).

---

## 2. Where each number is shown

### Variation card (V2 listing page)

- **Listed total** (e.g. “Listing Total” / total stock):  
  Sum of `marketplace_stock.listed_stock` + `marketplace_stock.manual_adjustment` for the variation.  
  Fallback: `variation.listed_stock` if no marketplace rows.

- **AV (Available):**  
  `variation->available_stocks->count()` (physical inventory).  
  Same value for the whole card (variation-level).

- **PO (Pending orders):**  
  `variation->pending_orders->sum('quantity')`.

- **DF (Difference):**  
  `availableCount - pendingCount` (available minus pending).

File: `resources/views/v2/listing/partials/variation-card.blade.php` (e.g. `$totalStock`, `$availableCount`, `$physicalAvailableCount`).

### Marketplace bar (per marketplace)

- **LS (Listed stock):**  
  `marketplace_stock.listed_stock` for that marketplace (no manual_adjustment in the bar label; total stock on card includes it).

- **Available:**  
  Same as card: passed `availableCount` = `variation->available_stocks->count()` (inventory). So “available” is **not** per-marketplace; it’s the same for all bars.

File: `resources/views/v2/listing/partials/marketplace-bar.blade.php`.

### Listing History modal

- **Content:**  
  `ListingMarketplaceHistory`: **field changes** (price, min_handler, buybox, etc.).  
- **Does not contain:**  
  Listed stock or available stock history.

So “history of stocks” is **not** in this modal. Stock changes are in **listed stock verification** (and optionally in `marketplace_stock_history` / logs).

Endpoint: `GET v2/listings/get_listing_history/{id}` → `V2\ListingController::get_listing_history()`.

### Listed stock verification

- **Purpose:**  
  Record when we **push** listed quantity (e.g. during a verification batch): before/after and change.

- **Fields (per variation per process):**  
  - `qty_from` = listed total **before** the push (V2: sum of `marketplace_stock.listed_stock` + `manual_adjustment` before update).  
  - `qty_change` = value added (e.g. +3).  
  - `qty_to` = listed total **after** the push (V2: same sum after update).

- **Where created:**  
  - **V2 listing flow:** `V2\ListingController::add_quantity()` → uses marketplace_stock sum for `qty_from` and `qty_to` → consistent with variation card.  
  - **Verification close (batch):** `ListedStockVerification::close_verification()` → uses **V1** `ListingController::add_quantity()` → V1 uses API response for `variation.listed_stock` and `qty_to`, and distributes to `marketplace_stock` via `stockDistributionService`. So after close, variation card (which uses marketplace_stock sum) and verification `qty_to` can match only if distribution and API agree.

Files:  
- `app/Http/Livewire/ListedStockVerification.php` (e.g. `close_verification`).  
- `app/Http/Controllers/V2/ListingController.php` (`add_quantity` – V2 verification record).  
- `app/Http/Controllers/ListingController.php` (`add_quantity` – V1, used on close).

---

## 3. Possible causes of “unequal / non-realistic”

1. **Comparing available vs listed**  
   They are different by design (inventory vs marketplace quantity). They only match by coincidence.

2. **Expecting stock history in “Listing History” modal**  
   That modal is for listing **field** history (price, buybox, etc.), not stock. Stock history is in listed stock verification batches and (if used) marketplace_stock_history.

3. **Verification `qty_to` vs current card total**  
   - V2 push: verification and card both use marketplace_stock sum → should align.  
   - Verification **close** uses V1 `add_quantity`: `qty_to` = API response; card = sum of marketplace_stock. If distribution or API delay differs, they can diverge until next sync.

4. **Available from wrong source**  
   Anywhere that uses `marketplace_stock.available_stock` for “available” instead of `variation->available_stocks->count()` will disagree with the variation card. The card and marketplace bar intentionally use inventory count only.

---

## 4. Consistency checklist

- [x] Variation card “AV” = `variation->available_stocks->count()`.  
- [x] Variation card total stock = sum of `marketplace_stock.listed_stock` + `manual_adjustment`.  
- [x] Marketplace bar “LS” = `marketplace_stock.listed_stock`; “available” = same as card (inventory).  
- [x] V2 `add_quantity` verification record: `qty_from` / `qty_to` from marketplace_stock sum.  
- [ ] Stock comparison API / modal: “available” should be inventory count (see fix below), not `marketplace_stock.available_stock`.  
- [ ] Listing history modal: no stock columns; clarify in UI or docs that it’s “listing field history”, not “stock history”.

---

## 5. Variation History table (9xxx calculation difference)

The **History** modal on the variation card shows **listed_stock_verifications**: Topup Ref, Pending Orders, Qty Before (`qty_from`), Qty Added (`qty_change`), Qty After (`qty_to`).

- **Normal topup:** Qty Before + Qty Added = Qty After (delta push).
- **9xxx (full verification):** Zero step creates a row with `qty_from` = listed before zero (e.g. 198), `qty_to` = null. On **close**, that row is reused: `qty_change` = scanned count (283), `qty_to` = API response (281 = scan - pending). So the row showed 198, 283, 281 because "Qty Before" stayed "before zero" instead of "before this push" (0). **Fix:** When updating the zero-record on close, set `qty_from = previous_qty` (0) so new 9xxx rows show 0, 283, 281; 283 - 281 = pending (2).

---

## 6. Fixes applied

- **getMarketplaceStockComparison** (`V2\ListingController`):  
  “Available” is now taken from **inventory** (`variation->available_stocks->count()`), not from `marketplace_stock.available_stock`.  
  - **V1 add_quantity** (`ListingController`): When updating existing zero-record on verification close, set `qty_from = previous_qty` so "Qty Before" = listed at push time (0) for 9xxx.
