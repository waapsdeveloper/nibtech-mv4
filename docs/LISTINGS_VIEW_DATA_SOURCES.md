# Listings View – Data Sources and Calculations

This document records where the data shown on the Listings page comes from and how it is built (backend → frontend). Reference view: `resources/views/listings.blade.php`.

---

## 1. Card block: Pending orders, Available stock, Difference (lines 1640–1651)

### 1.1 Data flow

| Step | Location | Description |
|------|----------|-------------|
| Frontend | `fetchVariations(page)` (~line 1317) | Calls `GET listing/get_variations?…` |
| Backend | `routes/web.php` | `ListingController::get_variations` |
| Backend | `ListingController::buildVariationQuery($request)->paginate($perPage)` | Builds variation query with relations |
| Response | Paginated JSON | `variations.data[]` = array of variation objects |

Each variation object is used in the template; the block uses `variation.*` (e.g. `variation.sku`, `variation.pending_orders_count`, `variation.available_stocks`, etc.).

### 1.2 Variation base (identity and links)

| Displayed | Source | DB table | DB columns |
|-----------|--------|----------|------------|
| Variation id | `variation.id` | `variation` | `id` |
| SKU (order link) | `variation.sku` | `variation` | `sku` |
| Product (model) | `variation.product` | `products` | via `variation.product_id` → `products.id`, `products.model` |
| Inventory link params | `variation.product_id`, `variation.storage`, `variation.color`, `variation.grade` | `variation` | `product_id`, `storage`, `color`, `grade` |

### 1.3 Pending order items and BM orders

- **Pending Order Items (X)**  
  - From `variation.pending_orders_count` when the listing is filtered by “available stock” (controller uses `withCount('pending_orders')`).  
  - Otherwise from `variation.pending_orders`: sum of each item’s `quantity`.
- **BM Orders (Y)**  
  - From `variation.pending_bm_orders`: count of items (same as pending but only `marketplace_id = 1`).

**Relations (Variation_model):**

- `pending_orders`: `hasMany(Order_item_model, 'variation_id', 'id')` with order `order_type_id = 3`, `status = 2`.
- `pending_bm_orders`: same, plus `orders.marketplace_id = 1`.

**Root DB columns:**

| Concept | Table | Columns |
|---------|--------|---------|
| Pending items / sum | `order_items` | `variation_id`, `quantity` |
| Which orders count | `orders` | `id`, `order_type_id`, `status` |
| BM-only | `orders` | `marketplace_id` |

### 1.4 Available stock count

- **Available (Z)**  
  - From `variation.available_stocks_count` when filtering by available stock, else length of `variation.available_stocks`.

**Relation:** `available_stocks` = stocks where `variation_id`, `status = 1`, and `whereHas('active_order')` and `whereHas('latest_listing_or_topup')`.

**Root DB columns:**

| Concept | Table | Columns / logic |
|---------|--------|------------------|
| Stock per variation | `stock` | `variation_id`, `status` (= 1) |
| “Active” purchase | `stock` | `order_id` → `orders.id`; filter `orders.status = 3` |
| Listing/topup | `process_stock`, `process` | `process_stock.stock_id`, `process_id`; `process.process_type_id` in (21, 22) |

### 1.5 Difference

- **Difference** = Available − Pending (same sources as above).

---

## 2. Details section: Stocks table, Listings table, Average cost, Best price (lines 1714–1752)

This is the collapsible block `#details_${variation.id}` containing:

1. **Stocks table** – No, IMEI/Serial, Cost (with **Average cost** in header).
2. **Listings table** – Country, Min Hndlr, Price Hndlr, BuyBox, Min (€**best_price**), Price, Date.

### 2.1 When data is loaded

- **On initial render:** For each variation, `getStocks(variation.id)` is called (~line 1449). It loads the stocks table (and sets average cost / best price) via AJAX. The listings table is initially built from `variation.listings` in the same `displayVariations` loop (`listingsTable` from `variation.listings`).
- **On expand:** User clicks the collapse trigger → `getVariationDetails(variationId, eurToGbp, m_min_price, m_price)` (~line 1653). That calls `getListings(...)`, which fetches `GET listing/get_competitors/{variationId}/{check}` and replaces `#listings_${variationId}` with the result.

So:

- **Stocks table + Average cost + Best price** → from **getStocks** (and its backend).
- **Listings table** → initially from **get_variations** response (`variation.listings`); when the user expands, from **getListings** → **get_competitors**.

### 2.2 Stocks table and Cost / Average cost / Best price

**Frontend:** `getStocks(variationId, page)` (~line 999).

**API:** `GET listing/get_variation_available_stocks/{id}?page=…&per_page=50`

**Backend:** `ListingController::get_variation_available_stocks($id)` (~line 570).

**Logic:**

- **Stocks (rows):**  
  - Query: `Stock_model::where('variation_id', $id)->where('status', 1)->whereHas('latest_closed_listing_or_topup')`  
  - Ordered by `id` desc, paginated (default 50 per page).  
  - So table shows: **stock** rows for this variation that are available and have a closed listing or topup.

- **Cost per row:**  
  - From `Order_item_model::whereHas('order', order_type_id = 1)->whereIn('stock_id', $stockIds)->pluck('price', 'stock_id')`.  
  - So cost = **order_items.price** for the purchase order item linked to that stock (order type 1 = purchase).

- **Vendor / PO reference:**  
  - Vendors: `Customer_model` (e.g. `last_name`) keyed by customer id.  
  - PO: `Order_model::where('order_type_id', 1)->pluck('customer_id', 'id')`.  
  - Reference: `Order_model::where('order_type_id', 1)->pluck('reference_id', 'id')`.  
  - Topup reference: `Process_model::whereIn('process_type_id', [21, 22])->pluck('reference_id', 'id')`; `Process_stock_model` used to map stock → process.

- **Average cost:**  
  - From **all** available stocks for this variation (not only current page):  
    `Order_item_model::whereHas('order', order_type_id = 1)->whereIn('stock_id', $allStockIds)->pluck('price')`  
  - `average_cost = $all_stock_costs->average()` (or 0 if empty).

- **Breakeven (Best price):**  
  - If `average_cost > 0`: `breakeven_price = (average_cost + 20) / 0.88`; optionally saved on `variation.breakeven_price`.  
  - Frontend shows it in `#best_price_${variationId}`; if server does not send `breakeven_price`, fallback is the same formula in JS.

**Root DB columns (stocks section):**

| Concept | Table | Columns |
|---------|--------|---------|
| Stock rows | `stock` | `id`, `variation_id`, `status`, `imei`, `serial_number`, `order_id` |
| Cost per stock | `order_items` | `stock_id`, `price`; linked via `orders.id` |
| Purchase orders | `orders` | `id`, `order_type_id` (= 1), `customer_id`, `reference_id` |
| Vendor | `customers` (or equivalent) | via `orders.customer_id` |
| Listing/topup | `process_stock`, `process` | `process_stock.stock_id`, `process_id`; `process.process_type_id` (21, 22) |

### 2.3 Listings table

**Two sources:**

1. **Initial render**  
   - From `variation.listings` in the `get_variations` response.  
   - Each listing is from `Listing_model` (eager-loaded with `listings` on variation).  
   - Columns shown: Country, Min Hndlr, Price Hndlr, BuyBox, Min (€best_price), Price, Date, etc.  
   - Root: **listing** table (+ relations: `country_id`, `marketplace`, `currency`).

2. **After expand (getListings)**  
   - **API:** `GET listing/get_competitors/{variationId}/{check}`  
   - **Backend:** `ListingController::getCompetitors($id, $no_check)` (~line 809).  
   - **Logic:**  
     - Gets variation’s BM listing `reference_uuid` (from variation’s listings, marketplace_id = 1).  
     - Calls BackMarket API: `$bm->getListingCompetitors($reference)`.  
     - For each competitor: finds or creates `Listing_model` by `variation_id`, `country` (from `Country_model` by market code), `marketplace_id = 1`; updates `reference_uuid_2`, `price`, `min_price`, `buybox`, `buybox_price`, `buybox_winner_price`, `currency_id` from API.  
     - Returns `Listing_model::with('marketplace')->where('variation_id', $id)->get()`.

So the listings table is always **listing** rows for this variation; when the user expands, data are refreshed/synced from BackMarket and then read from the **listing** table.

**Root DB columns (listings section):**

| Concept | Table | Columns (main) |
|---------|--------|-----------------|
| Listing row | `listing` | `id`, `variation_id`, `country`, `marketplace_id`, `min_price_limit`, `price_limit`, `min_price`, `price`, `buybox`, `buybox_price`, `buybox_winner_price`, `handler_status`, `currency_id`, `reference_uuid`, `reference_uuid_2`, `updated_at` |
| Country | `countries` | via `listing.country` |
| Marketplace | `marketplaces` | via `listing.marketplace_id` |
| Currency | `currencies` | via `listing.currency_id` |

### 2.4 Summary: Details section data flow

| UI element | Trigger | API / source | Main DB tables |
|------------|---------|--------------|----------------|
| Stocks table (No, IMEI/Serial, Cost) | Page load + pagination | `GET listing/get_variation_available_stocks/{id}` | `stock`, `order_items`, `orders`, `process`, `process_stock` |
| Average cost | Same as stocks | Same response `average_cost` | Same (order_items.price for purchase orders) |
| Best price (Min €) | Same as stocks | Same response `breakeven_price` or client formula | Same (derived from average cost) |
| Listings table | Initial: from variations; Expand: getListings | Initial: `get_variations` → `variation.listings`; Expand: `GET listing/get_competitors/{id}/{check}` | `listing`, `countries`, `marketplaces`, `currencies` |

---

## 4. Available vs stocks table count mismatch (discrepancy)

### 4.1 Observed behaviour

- **Available** (line 1647): shows `variation.available_stocks_count` or `variation.available_stocks.length` (e.g. 3).
- **Stocks table** (line 1054): filled by `getStocks()` → `GET listing/get_variation_available_stocks/{id}`; can show 50+ rows for the same variation.

So for some variations the card shows a small “Available” count while the details stocks table shows a much larger number.

### 4.2 Root cause

The two numbers come from **different query definitions**:

| Source | Criteria |
|--------|----------|
| **Available (card)** | Variation’s `available_stocks` relation: `status = 1` **and** `whereHas('active_order')` **and** `whereHas('latest_listing_or_topup')`. So: stock has a **completed purchase order** (`order_id` → `orders.status = 3`) and has **any** listing or topup process (type 21 or 22, any status). |
| **Stocks table** | `get_variation_available_stocks`: `status = 1` **and** `whereHas('latest_closed_listing_or_topup')`. So: stock has a **closed** listing (process type 21, status 2) or **closed** topup (process type 22, status 3). **No** requirement for `active_order`. |

So the stocks table includes every stock that has a **closed** listing/topup, while the card’s “Available” only includes stocks that also have an **active_order** (and a listing/topup in any state). Stocks that have closed listing/topup but no active_order (e.g. purchase order not status 3, or missing `order_id`) appear in the details table but not in the Available count → **Available 3, stocks 50+**.

### 4.3 Recommended fix

**Align the stocks table with the card** so both use the same set of “available” stocks:

- In `ListingController::get_variation_available_stocks($id)`, change the stocks query from:
  - `whereHas('latest_closed_listing_or_topup')`
- to the same scope as `available_stocks`:
  - `whereHas('active_order')->whereHas('latest_listing_or_topup')`.

That way the stocks table and the “Available” count will match. The comment in the controller (“Remove restrictive whereHas filter…”) was intentionally broadening the table; reverting to the card’s definition removes the discrepancy. If you need to show “all closed listing/topup” stocks in addition, that can be a separate filter or view.

### 4.4 Discrepancy tracking (table + CRUD)

A script runs over all variations and compares:

- **available_count** = count from Variation’s `available_stocks` (same as card).
- **stocks_table_count** = count from the current `get_variation_available_stocks` query (latest_closed_listing_or_topup).

Where these differ, a row is stored in **`listing_available_stock_discrepancies`** (variation_id, available_count, stocks_table_count, difference, variation_sku, detected_at). A simple CRUD list under **V2 → Extras** allows viewing and managing these records. See the controller, command, and views for implementation.

---

## 3. Quick reference – Root tables by block

| Block | Root tables |
|-------|-------------|
| Card: Pending / Available / Difference | `variation`, `products`, `order_items`, `orders`, `stock`, `process`, `process_stock` |
| Details: Stocks + Cost + Average + Best price | `stock`, `order_items`, `orders`, `process`, `process_stock`, customers (vendors) |
| Details: Listings table | `listing`, `countries`, `marketplaces`, `currencies` |
