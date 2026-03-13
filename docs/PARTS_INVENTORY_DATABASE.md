# Parts Inventory – Database Structure

This document describes the database tables and structure for the **Parts Inventory** module (V2), used by the sidebar: **Dashboard**, **Part Catalog**, **Batches**, **Items to Repair**, and **Purchases**.

---

## Overview

| Sidebar / Feature       | Primary tables                                      | Purpose |
|-------------------------|------------------------------------------------------|---------|
| **Dashboard**           | `repair_parts`, `part_batches`, aggregates           | Summary, low stock, quick links |
| **Part Catalog**        | `repair_parts`                                      | Part definitions (name, SKU, cost, on-hand) |
| **Batches**             | `part_batches`, `repair_parts`                       | Received batches, quantity remaining |
| **Items to Repair**     | `parts_repair_assignments`, `stock`, `repair_parts`  | Stock items assigned for repair, repair status |
| **Purchases**           | `parts_purchases`, `parts_purchase_orders`, `parts_purchase_batches` | Purchase records, POs, “add purchase” batches |

Additional tables: **`repair_part_usages`** (usage in processes/repairs), **`part_broken_records`** (broken parts tracking).

---

## Table Dependency Diagram

```
products (external)
    └── repair_parts ............................ Part catalog (SKU, name, cost, on_hand)
            ├── part_batches .................... Batches received (qty, cost, received_at)
            │       ├── parts_purchase_orders ... Optional: PO for the batch
            │       └── order_id (legacy link to orders)
            ├── repair_part_usages .............. Usage in process/repair (process_id, stock_id, batch_id)
            ├── parts_repair_assignments ........ Items to repair (stock_id, repaired_at, part_batch_id)
            ├── parts_purchases ................. Purchase lines (stock or batch_id)
            │       └── parts_purchase_batches .. Optional: “add purchase” batch (system_barcode)
            └── part_broken_records ............ Broken parts log (part_batch_id, responsible_person)

External: stock, process, process_stock, admin, orders, customer (vendor)
```

---

## Tables (Final Schema)

### 1. `repair_parts`

Part catalog: one row per part type (SKU).

| Column            | Type              | Nullable | Description |
|-------------------|-------------------|----------|-------------|
| id                | bigint PK         | No       | |
| product_id        | bigint unsigned   | Yes      | FK → products.id |
| name              | string            | No       | Part name |
| sku               | string            | Yes      | Part SKU / barcode |
| compatible_device | string            | Yes      | |
| on_hand           | integer           | No       | Default 0 (can be derived from batches) |
| reorder_level     | integer           | No       | Default 0 |
| unit_cost         | decimal(12,2)     | No       | Default 0 |
| active            | boolean           | No       | Default true |
| created_at, updated_at | timestamps | No  | |
| deleted_at        | timestamp         | Yes      | Soft deletes |

- **Indexes:** `(product_id, active)`
- **Foreign keys:** `product_id` → `products.id` (cascade update/delete)

---

### 2. `part_batches`

A received batch of a part (e.g. “received 50 units of part X on date Y”).

| Column                   | Type              | Nullable | Description |
|--------------------------|-------------------|----------|-------------|
| id                       | bigint PK         | No       | |
| repair_part_id           | bigint FK         | No       | FK → repair_parts.id |
| order_id                 | integer unsigned  | Yes      | Legacy link to orders.id (Parts Batch Receive) |
| parts_purchase_order_id   | bigint FK         | Yes      | FK → parts_purchase_orders.id |
| batch_number             | string            | No       | Client-facing batch/reference |
| name_label               | string(255)       | Yes      | Name as received in this batch (label only) |
| quantity_received        | integer           | No       | Default 0 |
| quantity_remaining       | integer           | No       | Decremented when used in repairs |
| unit_cost                | decimal(12,2)     | No       | Default 0 |
| total_cost               | decimal(12,2)     | Yes      | quantity_received * unit_cost |
| received_at              | date              | Yes      | |
| supplier                 | string            | Yes      | |
| notes                    | text              | Yes      | |
| created_at, updated_at   | timestamps        | No       | |
| deleted_at               | timestamp         | Yes      | Soft deletes |

- **Indexes:** `repair_part_id`, `received_at`, `(repair_part_id, quantity_remaining)`, `order_id`
- **Foreign keys:** `repair_part_id` → `repair_parts.id` (cascade), `parts_purchase_order_id` → `parts_purchase_orders.id` (null on delete)

---

### 3. `repair_part_usages`

Records usage of a part in a process/repair (links part, batch, process/stock).

| Column           | Type              | Nullable | Description |
|------------------|-------------------|----------|-------------|
| id               | bigint PK         | No       | |
| process_id       | bigint unsigned   | Yes      | FK → process.id |
| process_stock_id | bigint unsigned   | Yes      | FK → process_stock.id |
| stock_id         | bigint unsigned   | Yes      | FK → stock.id |
| repair_part_id   | bigint FK         | No       | FK → repair_parts.id |
| batch_id         | bigint FK         | Yes      | FK → part_batches.id |
| technician_id   | bigint unsigned   | Yes      | FK → admin.id |
| qty              | integer           | No       | Default 1 |
| unit_cost        | decimal(12,2)     | No       | Default 0 |
| total_cost       | decimal(12,2)     | No       | Default 0 |
| notes            | text              | Yes      | |
| created_at, updated_at | timestamps | No  | |
| deleted_at       | timestamp         | Yes      | Soft deletes |

- **Indexes:** `(process_id, process_stock_id)`, `(stock_id, repair_part_id)`
- **Foreign keys:** `repair_part_id` → repair_parts (cascade), `batch_id` → part_batches (null on delete), process/process_stock/stock/technician (null on delete)

---

### 4. `parts_repair_assignments`

**Items to repair:** assigns a stock item to a repair part; tracks when it was assigned and when repaired.

| Column           | Type              | Nullable | Description |
|------------------|-------------------|----------|-------------|
| id               | bigint PK         | No       | |
| stock_id         | bigint unsigned   | No       | FK → stock.id |
| repair_part_id   | bigint FK         | No       | FK → repair_parts.id |
| part_batch_id    | bigint FK         | Yes      | FK → part_batches.id (batch used for this repair) |
| unit_cost        | decimal(12,2)     | Yes      | |
| reference_id     | string(64)        | Yes      | Repair reference ID |
| customer_id      | bigint unsigned   | Yes      | Repairer (customer) |
| assigned_at      | timestamp         | No       | When assigned |
| repaired_at      | timestamp         | Yes      | When marked repaired |
| notes            | text              | Yes      | |
| admin_id         | bigint unsigned   | Yes      | |
| created_at, updated_at | timestamps | No  | |

- **Indexes:** `stock_id`, `(stock_id, repaired_at)`
- **Foreign keys:** `stock_id` → stock.id (cascade), `repair_part_id` → repair_parts (cascade), `part_batch_id` → part_batches (null on delete)

---

### 5. `parts_purchases`

Individual purchase lines: either tied to a **stock** item (IMEI) or to a **parts_purchase_batches** batch (e.g. “add purchase” flow).

| Column       | Type              | Nullable | Description |
|--------------|-------------------|----------|-------------|
| id           | bigint PK         | No       | |
| stock_id     | bigint unsigned   | Yes      | Stock/IMEI this purchase is attached to |
| batch_id     | bigint FK         | Yes      | FK → parts_purchase_batches.id |
| repair_part_id | bigint FK        | No       | FK → repair_parts.id |
| quantity     | integer unsigned  | No       | Default 1 |
| unit_price   | decimal(12,2)     | Yes      | Null when on lease |
| is_lease     | boolean           | No       | Part on lease; price decided later |
| price_set_at | timestamp        | Yes      | When price was set if was lease |
| notes        | text              | Yes      | |
| admin_id     | bigint unsigned   | Yes      | |
| created_at, updated_at | timestamps | No  | |

- **Indexes:** `stock_id`
- **Foreign keys:** `repair_part_id` → repair_parts (cascade), `batch_id` → parts_purchase_batches (null on delete)

---

### 6. `parts_purchase_batches`

Batch for the “add purchase” flow: one system barcode per batch of received items.

| Column              | Type         | Nullable | Description |
|---------------------|--------------|----------|-------------|
| id                  | bigint PK    | No       | |
| system_barcode      | string(64)   | No       | Unique, system-generated |
| manufacturer_barcode| string(255)  | Yes      | Optional manufacturer/supplier barcode |
| notes               | text         | Yes      | |
| created_at, updated_at | timestamps | No    | |

- **Indexes:** unique on `system_barcode`, index on `manufacturer_barcode`

---

### 7. `part_broken_records`

Log of parts that were broken (or received broken).

| Column           | Type              | Nullable | Description |
|------------------|-------------------|----------|-------------|
| id               | bigint PK         | No       | |
| repair_part_id   | bigint FK         | No       | FK → repair_parts.id |
| part_batch_id    | bigint FK         | Yes      | FK → part_batches.id |
| quantity         | integer unsigned  | No       | Default 1 |
| notes            | text              | Yes      | |
| responsible_person | string(255)     | Yes      | Person who broke / received broken |
| admin_id         | bigint unsigned   | Yes      | |
| created_at, updated_at | timestamps | No  | |

- **Indexes:** `(repair_part_id, created_at)`
- **Foreign keys:** `repair_part_id` → repair_parts (cascade), `part_batch_id` → part_batches (null on delete)

---

### 8. `parts_purchase_orders`

Purchase orders for parts (reference_id, status, vendor, etc.). Batches can be linked via `part_batches.parts_purchase_order_id`.

| Column        | Type             | Nullable | Description |
|---------------|------------------|----------|-------------|
| id            | bigint PK        | No       | |
| reference_id   | string(64)       | No       | Batch number or PO reference (e.g. BR-20260302-0001) |
| reference     | string(255)      | Yes      | Vendor reference |
| status        | tinyint unsigned | No       | Default 2; 2=pending, 3=approved |
| currency      | int unsigned     | Yes      | |
| processed_by  | bigint unsigned   | Yes      | admin id |
| customer_id   | bigint unsigned   | Yes      | Vendor/supplier (customer.id where is_vendor) |
| notes         | text             | Yes      | |
| created_at, updated_at | timestamps | No  | |
| deleted_at    | timestamp        | Yes      | Soft deletes |

- **Indexes:** `reference_id`, `status`, `created_at`
- **Foreign keys:** none defined in migrations (customer_id / processed_by are logical FKs to customer and admin)

---

## External Tables Referenced

| Table         | Used by                          | Purpose |
|---------------|-----------------------------------|---------|
| products      | repair_parts                     | Product (device) the part belongs to |
| stock         | repair_part_usages, parts_repair_assignments, parts_purchases (optional) | Device/IMEI being repaired or linked to purchase |
| process       | repair_part_usages              | Process (e.g. repair/topup) |
| process_stock | repair_part_usages              | Process-stock join |
| admin         | repair_part_usages (technician_id), parts_repair_assignments (admin_id), part_broken_records (admin_id), parts_purchases (admin_id) | User/admin |
| orders        | part_batches.order_id           | Legacy “Parts Batch Receive” order type |
| multi_type    | (order type “Parts Batch Receive”) | Order type registration for parts batch receive |
| customer      | parts_repair_assignments (customer_id = repairer), parts_purchase_orders (customer_id = vendor) | Repairer / vendor |

---

## Migrations (Run Order by Filename)

| Migration | Action |
|-----------|--------|
| 2026_01_21_000000 | create `repair_parts` |
| 2026_01_21_000001 | create `part_batches` |
| 2026_01_21_000002 | create `repair_part_usages` |
| 2026_02_11_000000 | add `name_label` to `part_batches` |
| 2026_02_11_100000 | add `part_batch_id`, `unit_cost`, `reference_id`, `customer_id` to `parts_repair_assignments` (table must exist; created in 2026_02_15) |
| 2026_02_15_000000 | create `parts_repair_assignments` |
| 2026_02_17_000000 | create `parts_purchases` |
| 2026_02_17_100000 | create `part_broken_records` |
| 2026_02_17_110000 | add `responsible_person` to `part_broken_records` |
| 2026_02_18_000000 | create `parts_purchase_batches` |
| 2026_02_18_000001 | make `parts_purchases.stock_id` nullable; add `batch_id` → parts_purchase_batches |
| 2026_03_02_100000 | add `order_id` to `part_batches`; register “Parts Batch Receive” in multi_type |
| 2026_03_02_120000 | create `parts_purchase_orders`; add `parts_purchase_order_id` to `part_batches`; backfill from orders |
| 2026_03_02_140000 | add `customer_id` and `reference` to `parts_purchase_orders` |

Note: By filename order, `2026_02_11_100000` runs before `2026_02_15_000000`. If running on a fresh DB, ensure `parts_repair_assignments` exists before the add-column migration (e.g. run 2026_02_15 first if needed).

---

## Eloquent Models

| Model                   | Table                   |
|-------------------------|-------------------------|
| `App\Models\RepairPart` | repair_parts            |
| `App\Models\PartBatch`  | part_batches            |
| `App\Models\RepairPartUsage` | repair_part_usages |
| `App\Models\PartsRepairAssignment` | parts_repair_assignments |
| `App\Models\PartsPurchase` | parts_purchases      |
| `App\Models\PartsPurchaseBatch` | parts_purchase_batches |
| `App\Models\PartBrokenRecord` | part_broken_records   |
| `App\Models\PartsPurchaseOrder` | parts_purchase_orders |

---

*Generated from `database/migrations` and Parts Inventory sidebar/routes. Ask follow-up questions and we can extend this doc.*
