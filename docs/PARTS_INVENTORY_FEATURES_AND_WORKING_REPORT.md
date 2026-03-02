# Parts Inventory – Features & Working Report

This document describes the **Parts Inventory** module: its features, data flow, models, routes, and how the main workflows operate. It is intended as a single reference for understanding and maintaining the module.

---

## 1. Overview

Parts Inventory is a **V2** sub-module under **V2 → Parts Inventory** in the sidebar. It manages:

- **Part catalog** – part types (SKU, name, product link, batches)
- **Batches** – received stock per part (batch number, quantity, cost, dates)
- **Items to Repair** – stock items assigned to repair with a part; track assignment and “repaired” status
- **Repair workflow** – submit repair (IMEI → part + batch), view repair status
- **Purchases** – batch-based parts purchases (optional lease, set price later)
- **Bulk import** – CSV upload to create parts and receive batches in bulk

**Access:** Users need `view_listing` permission to see the V2 menu and thus Parts Inventory. Sidebar links are under **V2 → Parts Inventory** with: Dashboard, Part Catalog, Batches, Items to Repair.

---

## 2. Sidebar & Navigation

| Menu Item        | URL | Purpose |
|------------------|-----|--------|
| Dashboard        | `v2/parts-inventory/dashboard` | Summary counts and recent part usage |
| Part Catalog     | `v2/parts-inventory/catalog`   | List/edit parts, receive batch, bulk import, attach IMEI |
| Batches          | `v2/parts-inventory/inventory` | List in-stock batches, edit batch, view part batches / broken history |
| Items to Repair  | `v2/parts-inventory/items-to-repair` | List assignments, assign part, mark repaired |

Additional entry points (not in sidebar): **Batch Receive**, **Bulk Import**, **Repair** (by IMEI), **Repair Status**, **Add Purchase**, **Edit Part**, **Attach IMEI**, **Edit Batch**, **Broken History**.

---

## 3. Features & Working

### 3.1 Dashboard

- **Route:** `GET v2/parts-inventory/dashboard`  
  **Controller:** `PartsInventoryController::dashboard`  
  **View:** `v2.parts-inventory.dashboard`

**Behaviour:**

- **Part Types** – count of active parts (`RepairPart::active()`).
- **Batches in stock** – count of batches with `quantity_remaining > 0` (`PartBatch::inStock()`).
- **Total units on hand** – sum of `repair_parts.on_hand` for active parts.
- **Low stock** – count of active parts where `on_hand <= reorder_level`.
- **Recent part usage** – last 5 `RepairPartUsage` rows with part, batch, stock (IMEI link).

Links from the dashboard go to Part Catalog, Batch Receive, and Batches (with optional low-stock filter).

---

### 3.2 Part Catalog

- **Index:** `GET v2/parts-inventory/catalog` → `catalogIndex` → `v2.parts-inventory.catalog.index`
- **Edit:** `GET v2/parts-inventory/catalog/{id}/edit` → `catalogEdit` → form view
- **Update:** `POST v2/parts-inventory/catalog/{id}` → `catalogUpdate`
- **Delete:** `DELETE v2/parts-inventory/catalog/{id}` → `catalogDestroy`
- **Next barcode:** `GET v2/parts-inventory/catalog/next-barcode` → `nextBarcode` (JSON)
- **Attach IMEI:** `GET/POST v2/parts-inventory/catalog/{id}/attach-imei` → `attachImei` / `attachImeiStore`

**Index behaviour:**

- Paginated list of parts with `withCount('batches')`.
- Search by part name or SKU.
- Sorted by latest batch received first (parts with no batches last), then by name.

**Edit/Update:**

- Editable: name, SKU, compatible device (preserved if not in request), on_hand, reorder_level, unit_cost, active.
- Form supports **Scan** (focus SKU for barcode gun) and **Generate** (calls `next-barcode` to fill SKU). Barcode format is from `config/parts_inventory.php` (e.g. `PRT-20250219-A3F2`).

**Delete (`catalogDestroy`):**

- In one transaction: force-delete usages, delete broken records, force-delete batches, delete parts purchases, delete parts repair assignments, then force-delete the part. Ensures no orphaned related records.

**Attach IMEI:**

- Form accepts an IMEI; system finds stock by IMEI, then sets the part’s `product_id` from `stock.variation.product_id`. Used to link a part to a product from existing inventory.

**Next barcode:**

- Reads `config/parts_inventory.php` (prefix, date format, separator, suffix length/chars).
- Builds a unique SKU (e.g. `PRT-20250219-A3F2`), checks uniqueness against `repair_parts.sku` (including soft-deleted). Returns JSON `{ "barcode": "..." }` or 409 with error.

---

### 3.3 Batch Receive (single batch)

- **Form:** `GET v2/parts-inventory/batch-receive` → `batchReceive`  
  **Submit:** `POST v2/parts-inventory/batch-receive` → `batchReceiveStore`

**Behaviour:**

- User enters SKU (or scans), optional name, quantity received, unit cost, received_at, purchase_date, supplier, notes.
- If part with SKU exists: use it. If not: create new part (name required).
- System generates batch number via `PartBatch::generateBatchNumber()` (e.g. `BR-20260219-0001`).
- `RepairPartService::receiveBatch()` creates the `PartBatch` and increments `RepairPart::on_hand`.
- Redirect to catalog with success.

---

### 3.4 Bulk Import

- **Form:** `GET v2/parts-inventory/bulk-import` → `bulkImport`  
  **Submit:** `POST v2/parts-inventory/bulk-import` → `bulkImportStore`  
  **Sample CSV:** `GET v2/parts-inventory/bulk-import/sample` → `bulkImportSample`  
  **Parts reference CSV:** `GET v2/parts-inventory/bulk-import/parts-reference` → `bulkImportPartsReference`

**CSV format (expected columns):**

`sku`, `name`, `quantity_received`, `unit_cost`, `received_at`, `purchase_date`, `supplier`, `notes`

**Behaviour:**

- Each row is one batch. Part is identified by SKU: if SKU exists, use that part; otherwise create new part (name required).
- Batch number is auto-generated per row. `RepairPartService::receiveBatch()` is used per row.
- Errors per row are collected (e.g. missing SKU, qty &lt; 1, missing name for new part). Success count and first N errors are shown after import.
- Sample download provides example rows. Parts reference download lists current parts (SKU, name, product_id, product, compatible_device) for building the import file.

---

### 3.5 Batches (Inventory)

- **List:** `GET v2/parts-inventory/inventory` → `inventory` → `v2.parts-inventory.inventory`
- **Edit batch:** `GET v2/parts-inventory/batches/{id}/edit` → `batchEdit`; `PUT v2/parts-inventory/batches/{id}` → `batchUpdate`
- **Part batches (JSON):** `GET v2/parts-inventory/parts/{id}/batches` → `partBatches` (paginated, for modal)
- **Broken history:** `GET v2/parts-inventory/parts/{id}/broken` → `brokenHistory` → `v2.parts-inventory.part-broken-history`

**List behaviour:**

- Paginated list of **in-stock** batches (`PartBatch::inStock()`), with `repairPart.product`.
- Optional search: batch number, part name, part SKU, product model.
- Optional filter: low stock (batch quantity_remaining ≤ part’s reorder_level).
- Sorted by received_at desc, id desc.

**Edit batch:**

- Edit batch_number, quantity_remaining, unit_cost, received_at, notes. No automatic adjustment of `repair_parts.on_hand` (manual/corrective use).

**Part batches (JSON):**

- Returns part id/name/sku and paginated in-stock batches (id, batch_number, quantity_remaining, received_at). Used by modals (e.g. on inventory page).

**Broken history:**

- Lists `PartBrokenRecord` for the part with filters: batch_id, date_from, date_to. Shows part batch and admin. Used to track damaged/lost parts per batch.

---

### 3.6 Items to Repair

- **List:** `GET v2/parts-inventory/items-to-repair` → `itemsToRepair` → `v2.parts-inventory.items-to-repair`
- **Assign:** `GET v2/parts-inventory/items-to-repair/assign/{id}` → `itemAssignRepair`; `POST .../assign/{id}` → `itemAssignRepairStore`
- **Mark repaired:** `POST v2/parts-inventory/items-to-repair/mark-repaired/{id}` → `itemMarkRepaired`

**List behaviour:**

- Lists `PartsRepairAssignment` with stock (variation, product, sale_order), repairPart, partBatch, customer.
- Filters: status (assigned / repaired), IMEI/serial, reference_id.
- Sorted by assigned_at desc. Used to see which stock items are assigned to a part and whether they are repaired.

**Assign:**

- By stock id: user selects a repair part and optional notes. Creates or updates open assignment (where `repaired_at` is null). One open assignment per stock.

**Mark repaired:**

- For the given stock: sets `repaired_at` on all open assignments and sets `stock.status = 1` (e.g. available). Item is considered repaired and back in inventory.

---

### 3.7 Repair (by IMEI) & Repair Status

- **Repair form:** `GET v2/parts-inventory/repair?imei=...` → `repair`  
  **Submit:** `POST v2/parts-inventory/repair` → `repairSubmit`
- **Repair status:** `GET v2/parts-inventory/repair-status/{id}` → `repairStatus` (stock id)

**Repair form:**

- Query param `imei` can be IMEI+serial concatenated. Stock is resolved by `CONCAT(imei, serial_number)`.
- Form shows stock (variation, product, order, customer, etc.), part dropdown, optional batch dropdown (loaded via `partBatchesJson` for selected part), unit cost, reference_id, repairer (customer).
- Submit creates or updates `PartsRepairAssignment` (stock_id, repair_part_id, part_batch_id, unit_cost, reference_id, customer_id, admin_id). If no open assignment, sets `assigned_at`. Redirects to Items to Repair.

**Repair status:**

- For one stock, shows all `PartsRepairAssignment` rows (assigned and repaired) with repair part, batch, customer. Used from Internal Repair “Repair status” action.

---

### 3.8 Purchases (batch-based)

- **Add:** `GET v2/parts-inventory/purchases/add` → `PartsPurchaseController::purchaseAdd`  
  **Store:** `POST v2/parts-inventory/purchases` → `purchaseStore`
- **Set price:** `POST v2/parts-inventory/purchases/{id}/set-price` → `purchaseSetPrice`
- **Delete:** `DELETE v2/parts-inventory/purchases/{id}` → `purchaseDestroy` (admin only, role_id === 1)

**Behaviour:**

- **Batch-based:** Each purchase belongs to a batch (`PartsPurchaseBatch`). User can create a new batch (system barcode auto-generated, optional manufacturer barcode) or add to existing batch by system barcode.
- **Add purchase:** Select or create batch; select part; quantity; optional unit price; is_lease; notes. Creates `PartsPurchase` with batch_id, repair_part_id, quantity, unit_price (optional), is_lease, admin_id. If unit_price set, price_set_at is set.
- **Set price:** For a purchase (e.g. lease), set unit_price and price_set_at.
- **Delete:** Only for admin; soft-deletes the purchase record.

Note: There is no separate “Purchase History” page in the sidebar; purchases are managed from Add Purchase and from dashboard/other links as needed. The `PartsPurchase` model supports both legacy `stock_id` and batch-based `batch_id`.

---

## 4. Service Layer

**`App\Services\Repair\RepairPartService`**

- **consumePart($partId, $qty, $attributes)**  
  Consumes quantity from inventory. Uses optional `batch_id` or FIFO (oldest batch with enough stock). Deducts from `part_batches.quantity_remaining` and `repair_parts.on_hand`. Creates `RepairPartUsage` with optional process, stock, technician, unit_cost, etc. Used when recording part usage (e.g. internal repair flow).

- **restockPart($partId, $qty)**  
  Adds qty to `repair_parts.on_hand` only (legacy; for batch receiving use receiveBatch).

- **receiveBatch($partId, $batchNumber, $quantityReceived, $unitCost, $attributes)**  
  Creates `PartBatch` and adds quantity to `repair_parts.on_hand`. Optional attributes: received_at, purchase_date, supplier, notes, name_label. Used by Batch Receive and Bulk Import.

- **getAvailableBatchesForPart($partId)**  
  Returns in-stock batches for the part (for dropdowns).

---

## 5. Models & Tables

| Model | Table | Purpose |
|-------|--------|--------|
| RepairPart | repair_parts | Part catalog (name, sku, product_id, on_hand, reorder_level, unit_cost, active). Soft deletes. |
| PartBatch | part_batches | Batches per part (batch_number, quantity_received/remaining, unit_cost, received_at, purchase_date, supplier, notes). Soft deletes. |
| RepairPartUsage | repair_part_usages | Usage records (part, batch, qty, cost, process, stock, technician). Soft deletes. |
| PartBrokenRecord | part_broken_records | Broken/lost parts per batch (repair_part_id, part_batch_id, quantity, admin_id). |
| PartsRepairAssignment | parts_repair_assignments | Assignment of a part (and optional batch) to a stock item for repair (assigned_at, repaired_at, customer_id, reference_id). |
| PartsPurchase | parts_purchases | Purchase line (batch_id or legacy stock_id, repair_part_id, quantity, unit_price, is_lease, price_set_at, admin_id). |
| PartsPurchaseBatch | parts_purchase_batches | Batches for parts purchases (system_barcode, manufacturer_barcode, notes). |

Relationships (summary):

- **RepairPart** → product (Products_model), batches (PartBatch), usages (RepairPartUsage), partsPurchases (PartsPurchase), brokenRecords (PartBrokenRecord).
- **PartBatch** → repairPart, usages, brokenRecords.
- **PartsRepairAssignment** → stock (Stock_model), repairPart, partBatch, customer (Customer_model), admin.
- **PartsPurchase** → batch (PartsPurchaseBatch), repairPart, stock (optional), admin.

---

## 6. Configuration

**`config/parts_inventory.php`**

- **Barcode/SKU:** prefix (e.g. `PRT`), date_format (`Ymd`), separator (`-`), suffix_length (4), suffix_chars (alphanumeric without ambiguous chars). Used by `nextBarcode()`. Optional env: `PARTS_BARCODE_PREFIX`.

---

## 7. Routes Summary (Parts Inventory)

All under prefix `v2`. Only parts-inventory related routes are listed.

| Method | URI | Controller method |
|--------|-----|-------------------|
| GET | parts-inventory/dashboard | PartsInventoryController@dashboard |
| GET | parts-inventory/catalog | PartsInventoryController@catalogIndex |
| GET | parts-inventory/catalog/{id}/edit | PartsInventoryController@catalogEdit |
| POST | parts-inventory/catalog/{id} | PartsInventoryController@catalogUpdate |
| GET | parts-inventory/catalog/next-barcode | PartsInventoryController@nextBarcode |
| GET | parts-inventory/catalog/{id}/attach-imei | PartsInventoryController@attachImei |
| POST | parts-inventory/catalog/{id}/attach-imei | PartsInventoryController@attachImeiStore |
| DELETE | parts-inventory/catalog/{id} | PartsInventoryController@catalogDestroy |
| GET | parts-inventory/batch-receive | PartsInventoryController@batchReceive |
| POST | parts-inventory/batch-receive | PartsInventoryController@batchReceiveStore |
| GET | parts-inventory/bulk-import | PartsInventoryController@bulkImport |
| POST | parts-inventory/bulk-import | PartsInventoryController@bulkImportStore |
| GET | parts-inventory/bulk-import/sample | PartsInventoryController@bulkImportSample |
| GET | parts-inventory/bulk-import/parts-reference | PartsInventoryController@bulkImportPartsReference |
| GET | parts-inventory/inventory | PartsInventoryController@inventory |
| GET | parts-inventory/batches/{id}/edit | PartsInventoryController@batchEdit |
| PUT | parts-inventory/batches/{id} | PartsInventoryController@batchUpdate |
| GET | parts-inventory/parts/{id}/batches | PartsInventoryController@partBatches |
| GET | parts-inventory/parts/{id}/batches-json | PartsInventoryController@partBatchesJson |
| GET | parts-inventory/parts/{id}/broken | PartsInventoryController@brokenHistory |
| GET | parts-inventory/items-to-repair | PartsInventoryController@itemsToRepair |
| GET | parts-inventory/items-to-repair/assign/{id} | PartsInventoryController@itemAssignRepair |
| POST | parts-inventory/items-to-repair/assign/{id} | PartsInventoryController@itemAssignRepairStore |
| POST | parts-inventory/items-to-repair/mark-repaired/{id} | PartsInventoryController@itemMarkRepaired |
| GET | parts-inventory/repair | PartsInventoryController@repair |
| POST | parts-inventory/repair | PartsInventoryController@repairSubmit |
| GET | parts-inventory/repair-status/{id} | PartsInventoryController@repairStatus |
| GET | parts-inventory/purchases/add | PartsPurchaseController@purchaseAdd |
| POST | parts-inventory/purchases | PartsPurchaseController@purchaseStore |
| POST | parts-inventory/purchases/{id}/set-price | PartsPurchaseController@purchaseSetPrice |
| DELETE | parts-inventory/purchases/{id} | PartsPurchaseController@purchaseDestroy |

---

## 8. Views (Blade)

Located under `resources/views/v2/parts-inventory/`:

- `dashboard.blade.php`
- `catalog/index.blade.php`, `catalog/form.blade.php`, `catalog/attach-imei.blade.php`
- `batch-receive.blade.php`, `batch-edit.blade.php`
- `bulk-import.blade.php`
- `inventory.blade.php`
- `part-broken-history.blade.php`
- `items-to-repair.blade.php`, `assign-repair.blade.php`
- `repair.blade.php`, `repair-status.blade.php`
- `purchases/add.blade.php`

---

## 9. Integration Points

- **Internal Repair:** “Repair” action can link to `v2/parts-inventory/repair?imei=...`; “Repair status” links to `v2/parts-inventory/repair-status/{stock_id}`.
- **IMEI search:** Dashboard recent usage links to `imei?imei=...` for stock lookup.
- **Permissions:** V2 and Parts Inventory visibility depend on `view_listing`. Purchase delete restricted to admin (role_id === 1).
- **RepairPartService::consumePart** is used by the main repair/usage flow (e.g. when recording part usage against a process/stock); Parts Inventory provides the catalog, batches, and Items to Repair workflow around that.

---

*Report generated from codebase analysis. For menu-to-route mapping see also `docs/PARTS_INVENTORY_MENU_ANALYSIS.md`; for barcode/form changes see `docs/PARTS_INVENTORY_PHP_CHANGES_SUMMARY.md`.*
