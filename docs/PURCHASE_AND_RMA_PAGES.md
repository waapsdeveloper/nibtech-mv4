# Purchase and RMA Pages – What They Use and How It Matches Our Approach

This document describes the two legacy pages the client asked about, the models they use, and how the current **parts purchase order series** approach (slug in `orders.reference`) fits with them.

---

## 1. Purchase page

**URLs (examples):**
- `http://syntora.local/purchase` — all purchases
- `http://syntora.local/purchase?status=3&stock=1` — **Active**: status = 3 (Shipped/Completed), with available stock > 0
- `http://syntora.local/purchase?status=2` — Pending (status = 2)
- `http://syntora.local/purchase?status=3&stock=0` — Closed (status = 3, no available stock)

### Route and entry point

| Item | Value |
|------|--------|
| **Route** | `GET /purchase` → `[Order::class, 'purchase']` |
| **Definition** | `routes/web.php`: `Route::get('purchase', [Order::class,'purchase'])->name('view_purchase');` |
| **Component** | Livewire `App\Http\Livewire\Order` |
| **Method** | `purchase()` |

### How the list is built

**Method:** `Order::purchase()` in `app/Http/Livewire/Order.php` (around lines 598–654).

**Query logic:**

1. **Order types included**
   - `order_type_id` **1** (standard Purchase)
   - **Parts Batch Receive** (id from `multi_type` where `table_name = 'orders'` and `name = 'Parts Batch Receive'`)
   - So the purchase list shows both “device” purchases and parts purchases in one list.

2. **Filters (from query string):**
   - `status` → `orders.status = request('status')` (e.g. 2 = Pending, 3 = Shipped/Completed)
   - `status_zero` → when 1, show `orders.status = 0`
   - `start_date` / `end_date` → filter on `orders.created_at`
   - `order_id` → `orders.reference_id LIKE request('order_id')%`
   - `customer_id` → `orders.customer_id = request('customer_id')`
   - **When `status=3` and `stock=1`:** `HAVING available_stock > 0` (Active tab)
   - **When `status=3` and `stock=0`:** `HAVING available_stock = 0` (Closed tab)
   - `deleted=1` → only trashed orders (if permission)

3. **Eager loading / counts**
   - `Order_model::with('order_items', 'order_issues')`
   - `withCount('order_items_available as available_stock')`

4. **Ordering and pagination**
   - `orderBy('orders.reference_id', 'desc')`
   - Paginated; per_page from request (default 10).

**No use of `orders.reference` or slug in this query.** Identification of “purchases” (including parts) is done only via `order_type_id` (1 and Parts Batch Receive).

### Models used (Purchase page)

| Model | Usage |
|-------|--------|
| **Order_model** | Main list; `orders` table. |
| **Order_status_model** | Status labels / dropdown. |
| **Customer_model** | Vendors (customers with `is_vendor` set). |
| **Multi_type_model** | Resolve Parts Batch Receive `order_type_id` to include in the list. |

### View

- **Blade:** `resources/views/livewire/purchase.blade.php`
- **Tabs:** Pending (`status=2`), Active (`status=3&stock=1`), Closed (`status=3&stock=0`), All, (optional) Deleted.
- **Table columns:** No, Order ID (link to detail), Vendor, Reference, Tracking, Cost (if permission), Qty, Issues, Creation Date, Approval Date (when not Pending).
- **Detail link:** `purchase/detail/{id}` → same Livewire Order component, `purchase_detail()`.

### Purchase detail and “parts” behaviour

- **Route:** `GET purchase/detail/{id}` → `Order::purchase_detail($id)`.
- **Parts detection:**  
  `is_parts_order` = (order’s `order_type_id` === Parts Batch Receive id).  
  If true, part batches for this order are loaded:  
  `PartBatch::where('order_id', $order_id)` (and shown as “Parts Batch Receive” section).
- So purchase **list** and **detail** both rely on **order_type_id**, not on `orders.reference` or slug.

---

## 2. RMA page

**URLs (examples):**
- `http://syntora.local/rma` — all RMA orders
- `http://syntora.local/rma?status=2` — **Submitted** (status = 2)

### Route and entry point

| Item | Value |
|------|--------|
| **Route** | `GET /rma` → Livewire full-page component |
| **Definition** | `routes/web.php`: `Route::get('rma', RMA::class)->name('view_rma');` |
| **Component** | Livewire `App\Http\Livewire\RMA` |
| **Method** | `render()` (no separate “rma” method; list is built in `render()`). |

### How the list is built

**Method:** `RMA::render()` in `app/Http/Livewire/RMA.php` (around lines 35–102).

**Query logic:**

1. **Order type**
   - **Only** `order_type_id` **2** (RMA – “sold back to purchaser”).
   - No Parts Batch Receive; RMA is a different flow.

2. **Filters:**
   - `customer_id` → `orders.customer_id`
   - `start_date` / `end_date` → `orders.created_at`
   - `order_id` → `orders.reference_id LIKE ...`
   - **`status`** → `orders.status = request('status')` (e.g. 2 = Submitted)
   - If the logged-in user has restricted customers: `whereIn('orders.customer_id', $admin_customer_ids)`

3. **Eager loading / aggregates**
   - `withCount('order_items')`
   - `withSum('order_items', 'price')`

4. **Ordering and pagination**
   - `orderBy('orders.reference_id', 'desc')`
   - Paginated; per_page from request (default 10).

**No use of `orders.reference` or slug.** RMA is identified only by `order_type_id = 2`.

### Models used (RMA page)

| Model | Usage |
|-------|--------|
| **Order_model** | Main list; `orders` table. |
| **Order_status_model** | Status labels. |
| **Customer_model** | Vendors and (optional) admin customer restriction. |
| **Currency_model** | Currency options. |
| **Order_item_model** | Used in delete/detail flows (not in the list query itself). |
| **Stock_model** | Used when deleting RMA items / returning stock. |

### View

- **Blade:** `resources/views/livewire/rma.blade.php`
- **Tabs:** Pending (`status=1`), Submitted (`status=2`), Approved (`status=3`), All.
- **Table:** Order ID, Vendor, Reference, Cost, Qty, Creation Date, etc., with links to `rma/detail/{id}`.

---

## 3. Order types and statuses (relevant to these pages)

**Order types (`multi_type` where `table_name = 'orders'`):**

| order_type_id | Name | Used on |
|---------------|------|--------|
| 1 | Purchase | Purchase list |
| 2 | RMA (sold back to purchaser) | RMA list |
| 3 | Sales (e.g. B2C) | Order list, not purchase/RMA |
| … | … | … |
| (e.g. 20) | Parts Batch Receive | Purchase list (added in same list as type 1) |

**Order status (e.g. `order_status` table):**

- Used as integer on `orders.status`.
- Purchase tabs: 2 = Pending, 3 = Shipped/Completed (Active/Closed depend on stock count).
- RMA tabs: 1 = Pending, 2 = Submitted, 3 = Approved.

---

## 4. How our “parts purchase series” approach fits

**What we implemented (see PARTS_PURCHASE_ORDER_SERIES_PLAN.md):**

- **Slug in `orders.reference`** (e.g. `parts-purchase`) to identify “parts inventory purchase” orders without using a numeric range on `reference_id`.
- **Order_model scopes:** `scopePartsPurchaseSeries()` (by slug), `scopePartsPurchase()` (by slug or `order_type_id` = Parts Batch Receive).
- When creating/syncing a parts order we set `order.reference` = slug; **order_type_id** is still set to Parts Batch Receive.

**Relationship to these two pages:**

| Page | Uses slug? | Uses order_type_id? | Match? |
|------|------------|---------------------|--------|
| **Purchase** | No. The list query does **not** filter by `reference` or slug. | Yes. Includes `order_type_id` 1 and Parts Batch Receive. | **Yes.** The purchase list already shows parts orders because of `order_type_id`. The slug does not need to be used on this page; it’s additive for reporting/APIs/other features that want “only the parts series” or a stable identifier. |
| **RMA** | No. | Yes. Only `order_type_id` 2. | **Yes.** RMA is unrelated to parts purchase; no change. |

So:

- **Purchase page** behaviour is unchanged: it still shows both standard purchases (type 1) and parts purchases (Parts Batch Receive) in one list, filtered by status/stock/dates/vendor, etc. Our approach does not replace this; it adds a way to identify “parts series” when needed (e.g. reports, exports, or future features).
- **RMA page** is unaffected; it only deals with order_type_id 2.
- If you ever want a view that shows “only orders that are in the parts purchase series” (by slug), you can use `Order_model::partsPurchaseSeries()`; the existing purchase list does not need to use it to keep working as it does today.

---

## 5. Summary table

| Item | Purchase page | RMA page |
|------|----------------|----------|
| **URL (example)** | `/purchase?status=3&stock=1` | `/rma?status=2` |
| **Route** | `GET purchase` → `Order::purchase` | `GET rma` → `RMA::class` (render) |
| **Main model** | Order_model | Order_model |
| **Filter by** | order_type_id in [1, Parts Batch Receive], status, stock, dates, customer_id, order_id | order_type_id = 2, status, dates, customer_id, order_id |
| **Uses orders.reference?** | No (only displayed in table) | No (only displayed if present) |
| **Uses slug for filtering?** | No | No |
| **Compatible with slug approach?** | Yes; list remains by order_type_id | Yes; unchanged |

This document reflects the code paths for the purchase and RMA list/detail behaviour and how they relate to the parts purchase order series (slug in `orders.reference`).
