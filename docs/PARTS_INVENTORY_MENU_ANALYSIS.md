# Parts Inventory Menu – Analysis (Routes, Controllers, Models, Views)

This document maps each sidebar menu item under **Parts Inventory** to its route, controller method, view, and models/tables. Use it as reference when implementing tasks for this section.

---

## Menu → Route → Controller → View Summary

| Menu Item        | URL/Route                                      | Controller Method   | View |
|------------------|------------------------------------------------|---------------------|------|
| Dashboard        | `GET v2/parts-inventory/dashboard`            | `dashboard()`        | `v2.parts-inventory.dashboard` |
| Part Catalog     | `GET v2/parts-inventory/catalog`              | `catalogIndex()`     | `v2.parts-inventory.catalog.index` |
| Inventory        | `GET v2/parts-inventory/inventory`            | `inventory()`        | `v2.parts-inventory.inventory` |
| Usage History    | `GET v2/parts-inventory/usage`                | `usage()`            | `v2.parts-inventory.usage` |
| Items to Repair  | `GET v2/parts-inventory/items-to-repair`      | `itemsToRepair()`    | `v2.parts-inventory.items-to-repair` |
| Purchase History | `GET v2/parts-inventory/purchase-history`     | `purchaseHistory()`  | `v2.parts-inventory.purchase-history` |

---

## 1. Dashboard

- **Route:** `v2/parts-inventory/dashboard`  
  Named: `v2.parts-inventory.dashboard`
- **Controller:** `App\Http\Controllers\V2\PartsInventoryController::dashboard`
- **View:** `resources/views/v2/parts-inventory/dashboard.blade.php`

**Behaviour:**
- Counts: active parts, in-stock batches, total on-hand, low-stock parts.
- Shows last 5 part usages (part, batch, stock).

**Models / Tables:**
- `RepairPart` → `repair_parts` (scopes: `active()`)
- `PartBatch` → `part_batches` (scope: `inStock()`)
- `RepairPartUsage` → `repair_part_usages` (with part, batch, stock)

---

## 2. Part Catalog

- **Route:** `v2/parts-inventory/catalog`  
  Named: `v2.parts-inventory.catalog`
- **Controller:** `App\Http\Controllers\V2\PartsInventoryController::catalogIndex`
- **View:** `resources/views/v2/parts-inventory/catalog/index.blade.php`

**Behaviour:**
- Paginated list of parts with search (name, sku, compatible_device, product model) and active filter.
- Each part has `withCount('batches')`.

**Models / Tables:**
- `RepairPart` → `repair_parts` (relation: `product` → `Products_model` / products table)

**Related routes (same controller):**
- Create: `catalogCreate` → `catalog/form`
- Store: `catalogStore`
- Edit: `catalogEdit` → `catalog/form`
- Update: `catalogUpdate`
- Attach IMEI: `attachImei` / `attachImeiStore` → `catalog/attach-imei`

---

## 3. Inventory

- **Route:** `v2/parts-inventory/inventory`  
  Named: `v2.parts-inventory.inventory`
- **Controller:** `App\Http\Controllers\V2\PartsInventoryController::inventory`
- **View:** `resources/views/v2/parts-inventory/inventory.blade.php`

**Behaviour:**
- Paginated list of parts with product; optional search and “low stock” filter.
- Optional “recent purchases first” (uses `withMax('partsPurchases', 'created_at')`).
- Page can open a modal with batches for a part (AJAX to `partBatches`).

**Models / Tables:**
- `RepairPart` → `repair_parts` (relation: `product`; relation/count: `partsPurchases`)

**Related controller methods:**
- `partBatches($id)` – JSON, paginated in-stock batches for a part.
- `partBatchesPage($id)` – Full page: `v2.parts-inventory.part-batches` (part + batches).
- `brokenHistory($id)` → `part-broken-history`.
- `brokenAdd($id)` → `part-broken-add`.
- `brokenStore($id)` – creates `PartBrokenRecord`, optionally decrements batch `quantity_remaining`.

**Models used in inventory flow:**
- `PartBatch` → `part_batches`
- `PartBrokenRecord` → `part_broken_records` (relation: `partBatch`, `admin`)

---

## 4. Usage History

- **Route:** `v2/parts-inventory/usage`  
  Named: `v2.parts-inventory.usage`
- **Controller:** `App\Http\Controllers\V2\PartsInventoryController::usage`
- **View:** `resources/views/v2/parts-inventory/usage.blade.php`

**Behaviour:**
- Paginated list of part usages with filters: part_id, imei, date_from, date_to.
- Eager loads: part.product, batch, stock.variation.product, process, technician.
- Page also gets: `partsForFilter`, `partsForRecord`, `processes` (Process_model), `technicians` (Admin_model) for filters and “record usage” form.

**Models / Tables:**
- `RepairPartUsage` → `repair_part_usages` (part, batch, stock, process, technician)
- `RepairPart` → `repair_parts`
- `Process_model` → `process`
- `Admin_model` → `admin`

**Related controller methods:**
- `usageStore` – record new usage (validates IMEI, uses `RepairPartService::consumePart`).
- `usageDetail($id)` – JSON for one usage (modal).
- `usageUpdate($id)` – update IMEI/stock, process_id, technician_id, notes.
- `usageDelete($id)` – soft-delete usage.

**Service:** `App\Services\Repair\RepairPartService` (e.g. `consumePart` for recording usage and deducting batch stock).

---

## 5. Items to Repair

- **Route:** `v2/parts-inventory/items-to-repair`  
  Named: `v2.parts-inventory.items-to-repair`
- **Controller:** `App\Http\Controllers\V2\PartsInventoryController::itemsToRepair`
- **View:** `resources/views/v2/parts-inventory/items-to-repair.blade.php`

**Behaviour:**
- Lists stock items that are “to be repaired”: `stock.status = 2`, with sale_order (order_type_id 3 or reference_id 999), excluding customer_id 3955, and variation grade in [8, 12, 17] (Repair, Hold, Other).
- Optional filters: grade(s), imei.
- Loads current open assignments per stock: `PartsRepairAssignment` where `repaired_at` is null, keyed by `stock_id`.

**Models / Tables:**
- `Stock_model` → `stock` (variation.product, sale_order)
- `Variation_model` → variations (grade)
- `PartsRepairAssignment` → `parts_repair_assignments` (repairPart)
- Grade lookup: `grade` table (ids 8, 12, 17)

**Related controller methods:**
- `itemAssignRepair($id)` → `assign-repair` (assign part to stock).
- `itemAssignRepairStore($id)` – create/update assignment (repair_part_id, notes).
- `itemMarkRepaired($id)` – set `repaired_at` on assignment(s), set `stock.status = 1`.

---

## 6. Purchase History

- **Route:** `v2/parts-inventory/purchase-history`  
  Named: `v2.parts-inventory.purchase-history`
- **Controller:** `App\Http\Controllers\V2\PartsPurchaseController::purchaseHistory`
- **View:** `resources/views/v2/parts-inventory/purchase-history.blade.php`

**Behaviour:**
- Paginated list of parts purchases with filters: imei, stock_id, part_id, date_from, date_to.
- Eager loads: repairPart, stock, admin.
- Passes `partsForFilter` (RepairPart active, name => id) for filter dropdown.

**Models / Tables:**
- `PartsPurchase` → `parts_purchases` (stock, repairPart, admin)
- `RepairPart` → `repair_parts`
- `Stock_model` → `stock` (for imei/serial filter and display)

**Related controller methods (same controller):**
- `purchaseAdd` → `v2.parts-inventory.purchases.add` (optional pre-fill by stock_id or imei).
- `purchaseStore` – create `PartsPurchase` (stock_id, repair_part_id, quantity, unit_price, is_lease, notes, admin_id).
- `purchaseSetPrice($id)` – set unit_price and price_set_at on a purchase.

---

## Models Reference (table names)

| Model                 | Table                   |
|-----------------------|-------------------------|
| RepairPart            | repair_parts            |
| PartBatch             | part_batches            |
| RepairPartUsage       | repair_part_usages      |
| PartBrokenRecord      | part_broken_records     |
| PartsRepairAssignment | parts_repair_assignments|
| PartsPurchase         | parts_purchases         |
| Stock_model           | stock                   |
| Products_model        | products                |
| Variation_model       | (variations)            |
| Process_model         | process                 |
| Admin_model           | admin                   |

---

## Controllers

- **Parts Inventory (main):** `App\Http\Controllers\V2\PartsInventoryController`  
  Handles: dashboard, catalog, batch receive, bulk import, inventory, part batches, broken records, items to repair, usage.
- **Purchases:** `App\Http\Controllers\V2\PartsPurchaseController`  
  Handles: purchase history, add purchase, store, set price.

---

## Routes file

All parts-inventory routes are in **`routes/v2.php`** (prefix `v2`).

You can now give tasks one by one; this doc is the single reference for how each menu item works and which files to touch.
