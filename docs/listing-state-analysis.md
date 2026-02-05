# Listing state analysis: "Offline" in app vs ad running on Backmarket

## Where the status comes from (data chain)

### 1. **Display (what the client sees)**

- **File:** `resources/views/v2/listing/partials/marketplace-bar.blade.php` (lines 105–113)
- **Source:** `$variation->state` — single integer from the `variations` table.
- **Meaning:** 0=Missing price/comment, 1=Pending validation, 2=Online, 3=Offline, 4=Deactivated.

So the label ("Offline", "Online", etc.) is the **variation-level** `state` column, not per marketplace or per country.

### 2. **Where `$variation` comes from**

- **Controller:** `App\Http\Controllers\V2\ListingController::index()`
- **Query:** `Variation_model::with(['listings', ...])` via `buildVariationQuery()`.
- **Result:** Each row is a `Variation_model` instance; `state` is read from `variations.state`.

So the value is whatever was last written to `variations.state` by any sync or update path.

### 3. **Who updates `variation->state` (and how)**

| Location | When | How state is set |
|----------|------|-------------------|
| **FunctionsThirty.php** (`get_listings`) | Scheduled/artisan sync | `getAllListings()` returns listings **per country**. For each listing: find variation by `reference_id|sku`, then `$variation->state = $list->publication_state`. Same variation can be updated **many times** (once per country). **Last processed listing wins.** |
| **Variation_model::update_qty()** | Manual/other sync | `getOneListing($this->reference_id)` (no country). One listing from API → `state` from that response. |
| **Order_item_model** / **UpdateOrderInDB** | Order sync | `getOneListing($itemObj->listing_id)` → set `$variation->state = $list->publication_state`. |
| **ListingDataService::getBackmarketStockQuantity()** | Per-variation stock refresh | Reads `publication_state` from API and **returns** it in the array; **does not save** to `variations.state`. |
| **SyncMarketplaceStockBulk (V2)** | Bulk stock sync | Builds a `listingMap` that includes `state` from API; **never writes** to `variations.state`. |

So the only places that **persist** `variation->state` from Backmarket are:

- FunctionsThirty (bulk, per country, last write wins),
- Variation_model::update_qty (single listing, unknown country),
- Order_item_model / UpdateOrderInDB (single listing when processing orders).

### 4. **Backmarket API behaviour**

- **getAllListings()**  
  - Called as `getAllListings()` (no `publication_state` filter).  
  - Fetches listings **per country** (`Country_model::where('market_code','!=',null)->pluck('market_code','id')`).  
  - Returns structure: `[ country_id => [ listing1, listing2, ... ], ... ]`.  
  - Same product can appear in several countries with **different** `publication_state` (e.g. Online in DE, Offline in FR).

- **getOneListing($listing_id)**  
  - Single endpoint `listings/{listing_id}`; no country parameter.  
  - Returns one listing (typically one market); which country is undefined in our code.

So Backmarket state is **per listing (per country)**, while we store **one state per variation**. That mismatch is the root of the bug.

---

## Why the client sees "Offline" while the ad is running on Backmarket

- The **variation** has one row; **Backmarket** has one listing per country, each with its own `publication_state`.
- In **FunctionsThirty**, we loop over countries and then over listings, and every time we do `$variation->state = $list->publication_state`. So for a given variation we keep overwriting with the **last** listing we process (last country in iteration order).
- If that last listing is Offline (3) and another country’s listing is Online (2), we save 3 and show "Offline" even though the ad is live in another market.
- Other updaters (e.g. `update_qty`, order sync) only see one listing and can also set state to Offline if that single response is offline.

So the client is correct: the ad can be running (e.g. in one country) while our app shows Offline because we store a single state and it was set from another country or an earlier sync.

---

## Recommended fix: resolve state so "Online if any"

- When syncing from Backmarket (e.g. in **FunctionsThirty**), for each variation do **not** overwrite with the last listing. Instead:
  - Collect all `publication_state` values for that variation (across all countries).
  - Set `variation->state` using a rule like: **if any listing is Online (2), set 2; else if any is Offline (3), set 3; else keep existing or use a default.**
- Optionally:
  - In **ListingDataService::getBackmarketStockQuantity()**, after a successful API call, update `variation->state` from the response so single-listing refresh also keeps state in sync.
  - In **SyncMarketplaceStockBulk**, when building the listing map we have `state`; we could also update `variation->state` using the same "any Online => Online" logic if we have multiple countries in the map.

This keeps a single `variations.state` but makes it represent "at least one marketplace is online", which matches the client’s expectation that "the ad is running" means we show Online.

---

## Files to change (summary)

1. **FunctionsThirty.php** – When updating variation state from `getAllListings()`, resolve state across all listings for that variation (e.g. "Online if any listing is Online").
2. **Optionally:** **ListingDataService::getBackmarketStockQuantity()** – Persist `publication_state` to `variation->state` when we get a successful API response.
3. **Optionally:** **SyncMarketplaceStockBulk** – When we have multiple listings per variation (e.g. from multiple countries), compute resolved state and update `variation->state`.

After that, the status shown in `marketplace-bar.blade.php` (from `$variation->state`) will still come from the same place, but the value will be correct for "ad is running on Backmarket" (any country).
