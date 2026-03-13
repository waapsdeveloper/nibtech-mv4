# Plan: Identify parts inventory purchase orders via slug in `reference` column

## Goal
- Identify which orders in `orders` belong to **parts inventory purchase** without using a numeric range (ranges can be exhausted quickly).
- Use the **`reference`** column (not `reference_id`) with a **slug** so we can reliably filter "parts purchase" orders no matter how many records exist.

---

## 1. Current state

| Flow | Table | reference_id source |
|------|--------|----------------------|
| **Legacy purchases** | `orders` | User-entered or `latest_reference + 1` (order_type_id = 1). |
| **Parts Batch Receive (legacy)** | `orders` | From multi_type "Parts Batch Receive"; part_batches have `order_id` → orders. |
| **Parts v2 (batch-receive)** | `parts_purchase_orders` | No row in `orders`; PO uses `reference_id` = batch number (e.g. BR-xxx). |

So today:
- Parts v2 does **not** create rows in `orders`.
- Legacy parts orders in `orders` use `order_type_id` = "Parts Batch Receive" and can have any `reference_id`.

---

## 2. Use a slug in the `reference` column (not reference_id)

- **Do not** use a numeric range for `reference_id` — records can grow quickly and the range would be ineffective.
- **Do** use the existing **`reference`** column to store a **slug** (or prefix) that marks the order as belonging to the parts inventory purchase series.
- **Rule:** Any order whose `reference` matches the slug (e.g. equals a constant or starts with a prefix) is treated as **parts purchase**. **reference_id** stays as-is (sequential, user-entered, or batch number); no reserved range.

**Slug options:**

| Option | Example value in `reference` | Query to identify |
|--------|-----------------------------|--------------------|
| **Fixed slug** | `parts-purchase` | `WHERE reference = 'parts-purchase'` |
| **Prefix + optional suffix** | `parts-purchase` or `parts-purchase-BR-20260311-0001` | `WHERE reference = 'parts-purchase' OR reference LIKE 'parts-purchase-%'` |

**Recommendation:** One fixed slug in config. When creating a parts order in `orders`, set `order.reference` = that slug. No range limit; identification is by slug only.

```php
// config/parts.php (or in existing config)
return [
    'purchase_order_reference_slug' => 'parts-purchase',
];
```

---

## 3. How to identify "parts purchase" orders

Two complementary mechanisms:

1. **By slug in `reference` (primary)**  
   - `reference` = configured slug (e.g. `'parts-purchase'`) or `reference LIKE slug . '%'` if using a prefix.  
   - Use this for "all orders in the parts purchase series" (reports, lists, exports). No upper limit on how many.

2. **By order_type_id (secondary)**  
   - `order_type_id` = id of "Parts Batch Receive" in `multi_type` (table_name = 'orders').  
   - Use for legacy rows that may not have the slug set yet.

**Recommended:** Treat as "parts purchase" when **either**:
- `reference` equals (or starts with) the configured slug, **or**
- `order_type_id` = Parts Batch Receive.

So:
- New parts orders in `orders` get `reference` = slug and `order_type_id` = Parts Batch Receive.
- Legacy parts orders can be identified by order_type_id even if `reference` is null or different.
- Queries that need "only the dedicated series" filter by `reference` = slug (or LIKE slug%).

---

## 4. No "next reference_id" for series

- **reference_id** is not driven by a parts-specific range. It can stay as existing logic (e.g. `latest_reference + 1` for purchases, or batch number BR-xxx when syncing from parts_purchase_orders).
- The **series** is defined only by the slug in `reference`; no generation method is needed for reference_id.

---

## 5. Where to set the slug (when does an order get `reference` = slug?)

**Option A – Only when creating an order in `orders` for parts**
- Whenever code creates an `Order` that is a parts purchase (e.g. order_type_id = Parts Batch Receive), set `order.reference` = config slug (e.g. `'parts-purchase'`). Set `order_type_id` = Parts Batch Receive. `reference_id` unchanged from current logic.

**Option B – Sync parts_purchase_orders → orders**
- When creating a PO in `parts_purchase_orders` (e.g. from batch-receive), also create a row in `orders` with:
  - `reference` = slug (e.g. `'parts-purchase'`),
  - `reference_id` = e.g. batch number (BR-xxx) or existing PO reference_id,
  - `order_type_id` = Parts Batch Receive,
  - `batch_reference` = e.g. SKU from form,
  - and link part_batches to that order if desired.
- Identification of "parts series" is then via `reference` = slug; no range on reference_id.

**Option C – Legacy only**
- Only set the slug for **new** parts orders created in `orders`. Leave legacy as-is; identify them by order_type_id.

**Recommendation:** Use the slug whenever an `Order` is created or synced for parts (Option A or B). No numeric series to maintain.

---

## 6. Implementation checklist

1. **Config**  
   - [ ] Add `config/parts.php` (or extend existing config) with `purchase_order_reference_slug` (e.g. `'parts-purchase'`).

2. **Order model**  
   - [ ] Add `scopePartsPurchaseSeries($query)`: `reference` = config slug (or `reference LIKE slug . '%'` if prefix).  
   - [ ] Add `scopePartsPurchase($query)`: parts series **or** order_type_id = Parts Batch Receive (so "all parts purchase" is one scope).

3. **Creation path**  
   - [ ] Wherever an `Order` is created for parts: set `order.reference` = config slug and `order_type_id` = Parts Batch Receive. Leave reference_id as per current logic.

4. **Optional: v2 batch-receive → orders**  
   - [ ] If choosing Option B: when creating a parts PO from batch-receive, also create an `Order` with `reference` = slug, set batch_reference (e.g. SKU), and link part_batches if needed.

5. **Purchase list / reports**  
   - [ ] Where the app lists "purchases", include parts orders identified by slug (and/or order_type_id) using the new scope.

6. **Backfill (optional)**  
   - [ ] Optionally set `reference` = slug for existing Parts Batch Receive orders that have `reference` null or different, so all parts orders are identifiable by slug.

---

## 7. Summary

| Item | Action |
|------|--------|
| **Series** | **Not** a numeric range. Use a **slug** in `orders.reference` (e.g. `'parts-purchase'`) so the "series" never runs out. |
| **Identify** | Scope: `reference` = slug (or LIKE slug%) and/or order_type_id = Parts Batch Receive. |
| **reference_id** | Unchanged; no dedicated range or next-id method for parts. |
| **Assign** | When creating/syncing an `Order` for parts, set `reference` = slug. |

This gives a dedicated, scalable way to identify parts inventory purchase orders in the orders table without depending on a numeric reference_id range.
