# V1 Order Refresh → Stock Allocation / Reduction (Analysis)

This document summarizes what happens in the **V1 order refresh commands** when orders come in, with a focus on whether the system:

- uses existing stock vs creates new stock, and
- reduces stock (and where that reduction happens).

## Scope

Files reviewed:

- `app/Console/Commands/RefreshLatest.php`
- `app/Console/Commands/RefreshNew.php`
- `app/Console/Commands/RefreshOrders.php`
- `app/Models/Order_model.php` (method: `updateOrderInDB`)
- `app/Models/Order_item_model.php` (method: `updateOrderItemsInDB`)

## High-level conclusion

The client’s description is **mostly correct**, but the key logic is **not in `Order_model`**.

- **Order header creation/update** happens in `Order_model->updateOrderInDB(...)`.
- The “**use stock if exists else create stock and link it**” behavior happens in **`Order_item_model->updateOrderItemsInDB(...)`**.
- The “**reduce stock**” behavior in this flow is primarily a decrement to **`Variation_model.listed_stock`** when a new order item is created (see details below).

## Command-by-command behavior

### 1) `Refresh:latest` (`app/Console/Commands/RefreshLatest.php`)

Purpose:

- Not a new-order ingestion path. It updates **care** information.

What it calls:

- `Order_item_model->get_latest_care($bm)`

Stock allocation / reduction:

- None directly related to sales stock assignment in this command.

### 2) `Refresh:new` (`app/Console/Commands/RefreshNew.php`)

Purpose:

- Fetch new orders from BackMarket and store them locally.

What it calls per order:

- `Order_model->updateOrderInDB($orderObj, ...)`
- `Order_item_model->updateOrderItemsInDB($orderObj, ...)`

Stock allocation / reduction:

- Happens downstream in `Order_item_model->updateOrderItemsInDB(...)`.

### 3) `Refresh:orders` (`app/Console/Commands/RefreshOrders.php`)

Purpose:

- Validate new orderlines, then pull modified orders and store them locally.

What it calls per order:

- `Order_model->updateOrderInDB($orderObj, ...)`
- `Order_item_model->updateOrderItemsInDB($orderObj, ...)`

Stock allocation / reduction:

- Happens downstream in `Order_item_model->updateOrderItemsInDB(...)`.

## Where the order is stored (no stock allocation here)

### `Order_model->updateOrderInDB(...)`

This method stores/updates the order header record:

- creates/updates `Order_model` (by `reference_id` and `marketplace_id`)
- ensures customer exists (via `Customer_model->updateCustomerInDB(...)`)
- sets status/price/currency/payment_method/label urls/tracking/timestamps
- saves the order

Important:

- This method **does not** assign a `Stock_model` to order items.
- This method **does not** decrement stock by itself in the reviewed section.

## Where stock is assigned/created AND stock is reduced

### `Order_item_model->updateOrderItemsInDB(...)`

This is where the client-described behavior exists.

#### A) Variation lookup / creation

For each marketplace orderline:

- resolves a `Variation_model` by `listing_id` or `sku`
- if variation does not exist, it may be created based on marketplace listing payload

#### B) “Reduce stock” = decrement variation listed stock when a new order item is created

When the order item is new (first time seen locally), the code reduces the variation’s `listed_stock`:

- condition: `elseif ($orderItem->id == null)` (i.e., this is a brand new `Order_item_model`)
- action: `variation->listed_stock = listed_stock - quantity`

So, **stock reduction occurs at the variation/listed_stock level** in this refresh path.

#### C) “Use existing stock else create stock” = `Stock_model::firstOrNew(...)` by IMEI/serial

If the orderline contains `imei` and/or `serial_number`, and the local order item has no stock assigned yet (`stock_id == null`), then:

- it tries to find an existing stock record **including soft-deleted**:
  - `Stock_model::withTrashed()->firstOrNew(['imei' => ...])` or `firstOrNew(['serial_number' => ...])`
- if an existing stock record is found (`$stock->id != null`):
  - it sets `status = 2` (meaning: stock is treated as allocated/used in this context)
  - it links to the last item (`$orderItem->linked_id = $last_item->id`) when possible
- it sets:
  - `stock->variation_id = $variation->id`
  - `orderItem->stock_id = $stock->id`

This matches the client’s “reuse if exists, else create and use it” statement.

## Nuance / important notes

- The refresh flow does **not** “pick a free stock unit from inventory” for sales orders unless the marketplace sends IMEI/serial in the payload.
  - Without IMEI/serial, the only “reduction” observed in this path is the decrement to `Variation_model.listed_stock`.
- The term “reduce stock” in this flow is not a single central service call; it’s split into:
  - a decrement of `variation.listed_stock`, and
  - a stock unit association (create/reuse `Stock_model`) **only when** IMEI/serial exists.

## Quick checklist to validate the client’s claim

To validate on a real incoming order:

1. Run `php artisan Refresh:new` (or `Refresh:orders`).
2. Confirm `Order_model` was created/updated via `updateOrderInDB`.
3. Confirm each `Order_item_model` was created/updated via `updateOrderItemsInDB`.
4. For an orderline with IMEI/serial:
   - confirm `order_items.stock_id` is filled
   - confirm `stocks` record was reused/created and has `variation_id` set
5. Confirm `variations.listed_stock` was decremented when the `Order_item_model` was newly created.


