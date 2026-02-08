# Close verification vs close topup – code comparison

Comparison of **Listed Stock Verification** (9xxx) close and **Topup** (4xxxx) close to spot what runs and what can cause calculation mismatches.  
Topup UI: [topup?status=2](https://sdpos.nibritaintech.com/topup?status=2)

---

## 1. Start new process

| | Listed Stock Verification (9xxx) | Topup (4xxxx) |
|---|----------------------------------|---------------|
| **Function** | `ListedStockVerification::start_listing_verification()` | `Topup::start_topup()` |
| **Process type** | 21 | 22 |
| **Reference ID** | Last 9xxx + 1 (or 9000) | Last + 1 (or 40001) |
| **Status** | 1 (active) | 1 (active) |

---

## 2. Close / push flow

### Listed Stock Verification – `close_verification($process_id)`

```php
// Process_stocks: status = 1 only (scanned, not yet verified)
$variation_qty = Process_stock_model::where('process_id', $process_id)->where('status', 1)
    ->groupBy('variation_id')->selectRaw('variation_id, Count(*) as total')->get();

foreach($variation_qty as $variation){
    $listingController->add_quantity($variation->variation_id, $variation->total, $process->id);
}
```

- Always calls **V1** `ListingController::add_quantity(variation_id, total, process_id)`.
- No check for existing `Listed_stock_verification`; V1 handles it (reuses zero-record for process_type 21 or creates new).
- **Quantity passed** = scan count per variation (`total`).

### Topup – `close_topup($process_id)` when `push=1`

```php
// Process_stocks: status < 3; optionally only status = 2 when not "all"
$variation_qty = Process_stock_model::where('process_id', $process_id)->where('status', '<', 3)
    ->when(!request('all'), fn($q) => $q->where('status', 2))
    ->groupBy('variation_id')
    ->selectRaw('variation_id, COUNT(*) as total, GROUP_CONCAT(id) as ps_ids')
    ->get();

foreach($variation_qty as $variation){
    $listed_stock = Listed_stock_verification_model::where('process_id', $process->id)
        ->where('variation_id', $variation->variation_id)->first();

    if($listed_stock == null){
        $listingController->add_quantity($variation->variation_id, $variation->total, $process->id);
    } elseif($listed_stock->qty_change < $variation->total){
        $new_qty = $variation->total - $listed_stock->qty_change;
        $listingController->add_quantity($variation->variation_id, $new_qty, $process->id);  // was variation_id1 – FIXED
    }
    // Mark process_stocks status = 3
}
```

- Also uses **V1** `ListingController::add_quantity`.
- **Difference:** Topup checks for an existing `Listed_stock_verification` for this process+variation:
  - If none: push full `total`.
  - If exists and `qty_change < total`: push only the **delta** `total - qty_change`.
- **Bug (fixed):** The delta branch used `$variation->variation_id1` (typo). It must be `$variation->variation_id`. Using the wrong property could pass `null`/wrong id and push to the wrong variation → **calculation mismatch**.

---

## 3. V1 `add_quantity($id, $stock, $process_id)` behaviour

| Process type | Behaviour |
|--------------|-----------|
| **21 (verification)** | `check_active_verification` is set. `new_quantity = $stock - pending_orders`. Finds existing row with same process_id, variation_id, `qty_to` null; updates it and sets `qty_from = previous_qty` (fix), `qty_change = $stock`, `qty_to = response`. |
| **22 (topup)** | `check_active_verification` is null. `new_quantity = previous_qty + $stock`. Always creates a **new** `Listed_stock_verification` with `qty_from = previous_qty`, `qty_change = $stock`, `qty_to = response`. |

So:

- **Verification (21):** One row per process+variation (reused after zero); `qty_change` = scan count; listed = scan − pending.
- **Topup (22):** Can create **multiple** rows per process+variation if Topup calls `add_quantity` more than once for the same variation (e.g. first full push, then delta). History then shows multiple rows for same Topup ref; `sum(qty_change)` is what `recheck_closed_topup` compares to `scanned_total`.

---

## 4. What can cause calculation mismatch

1. **Topup typo (fixed):** `variation_id1` → `variation_id` in the delta branch. Wrong variation id would push to the wrong variation and skew listed/verification totals.
2. **Verification (9xxx):** “Qty Before” was “before zero” instead of “before this push” – fixed in V1 by setting `qty_from = previous_qty` when updating the zero-record.
3. **Different status filters:** Verification uses `status = 1`; Topup uses `status < 3` and optionally `status = 2`. If process_stocks are in different statuses, counts can differ between “scanned” and “pushed”.
4. **Topup delta logic:** When `listed_stock` exists and `qty_change < total`, only the delta is pushed. That creates a second `Listed_stock_verification` row for the same process+variation. Totals are still consistent if the typo is fixed, but history shows two lines for that ref+variation.

---

## 5. Fix applied

- **Topup.php** `close_topup`: `$variation->variation_id1` changed to `$variation->variation_id` when calling `add_quantity` for the delta (existing `listed_stock` and `qty_change < total`).
