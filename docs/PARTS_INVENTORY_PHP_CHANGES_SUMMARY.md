# Parts Inventory – PHP & Related File Changes Summary (for Senior)

Report of all **.php** and related files where **additions or changes** were made (catalog form simplification + SKU/Barcode scan & generate).

---

## 1. **app/Http/Controllers/V2/PartsInventoryController.php**

### Changes (existing code modified)

- **`catalogUpdate()` method**  
  - **What:** When editing a part, the form no longer has IMEI, Compatible device, or Reorder level fields.  
  - **Why:** So we don’t overwrite existing values with empty when those fields are not in the request.  
  - **How:**  
    - `compatible_device`: use `$request->input('compatible_device', $part->compatible_device)` so existing value is kept if not sent.  
    - `reorder_level`: use `$request->input('reorder_level', $part->reorder_level ?? 0)` so existing value is kept if not sent.

### Additions (new code)

- **`nextBarcode()` method (new)**  
  - **Purpose:** Generate a **unique system barcode/SKU** for the parts catalog.  
  - **Behaviour:**  
    - Reads format from `config/parts_inventory.php` (prefix, date, separator, suffix length/chars).  
    - Builds barcode like `PRT-20250219-A3F2`.  
    - Checks uniqueness against `repair_parts.sku` (including soft-deleted).  
    - Retries up to 20 times if collision.  
  - **Returns:** JSON `{ "barcode": "PRT-20250219-XXXX" }` or `{ "error": "..." }` with 409 on failure.

**File path:** `app/Http/Controllers/V2/PartsInventoryController.php`

---

## 2. **config/parts_inventory.php**

### Additions (new file)

- **What:** New config file for **Parts Inventory**, specifically for the **barcode/SKU standard**.  
- **Contents:**  
  - `barcode.prefix` – e.g. `PRT` (env: `PARTS_BARCODE_PREFIX`).  
  - `barcode.date_format` – e.g. `Ymd`.  
  - `barcode.separator` – e.g. `-`.  
  - `barcode.suffix_length` – e.g. `4`.  
  - `barcode.suffix_chars` – alphanumeric set used for random suffix (avoids ambiguous 0/O, 1/I/L).  
- **Purpose:** Single place to define how system-generated part barcodes look; can be changed per environment via `.env`.

**File path:** `config/parts_inventory.php`

---

## 3. **routes/v2.php**

### Additions (new route only)

- **New route:**  
  `GET v2/parts-inventory/catalog/next-barcode`  
  → `PartsInventoryController@nextBarcode`  
  → Named: `v2.parts-inventory.catalog.next-barcode`  
- **Purpose:** Frontend “Generate” button calls this to get a new unique barcode and fill the SKU field.

**File path:** `routes/v2.php`  
**Exact line added:** One new `Route::get('parts-inventory/catalog/next-barcode', ...)` before the `catalog/{id}/attach-imei` route.

---

## 4. **resources/views/v2/parts-inventory/catalog/form.blade.php** (view – contains PHP/Blade)

### Changes (for senior – what changed in “form logic” and structure)

- **Removed from form:**  
  - IMEI field (both Add and Edit).  
  - Compatible device field.  
  - Reorder level field.  
- **Layout:**  
  - Name + On hand on first row.  
  - **SKU / Barcode** on a **separate full-width row** with:  
    - One input for SKU (same as barcode).  
    - Label “Generate or capture” and two buttons:  
      - **Scan** – focuses SKU input for barcode gun.  
      - **Generate** – fetches from `next-barcode` and sets SKU value.  
  - Unit cost and Active below.  
- **New elements:**  
  - `id="part-sku"` on SKU input.  
  - Buttons `#btn-scan-barcode`, `#btn-generate-barcode`.  
  - Hint text “Listening for barcode gun — scan now.” (shown when Scan is clicked).  
- **New script section:**  
  - `@section('scripts')`:  
    - Scan button → focus SKU input + show hint for 4 seconds.  
    - Generate button → `fetch(route('v2.parts-inventory.catalog.next-barcode'))` and set `#part-sku` value from JSON.

**File path:** `resources/views/v2/parts-inventory/catalog/form.blade.php`

---

## Summary table (PHP files only)

| File | Type | Change |
|------|------|--------|
| `app/Http/Controllers/V2/PartsInventoryController.php` | Controller | **Modified** `catalogUpdate()` to preserve `compatible_device` and `reorder_level` when not in request; **Added** `nextBarcode()` method. |
| `config/parts_inventory.php` | Config | **New file** – barcode prefix, date format, separator, suffix length/chars. |
| `routes/v2.php` | Routes | **Added** one GET route: `parts-inventory/catalog/next-barcode` → `nextBarcode`. |
| `resources/views/v2/parts-inventory/catalog/form.blade.php` | View | **Modified** – removed IMEI, compatible device, reorder level; SKU on own row with Scan + Generate; added scripts. |

---

## No changes to these (for reference)

- **Models** – e.g. `RepairPart`, `PartBatch`, etc. – **no changes**.  
- **Other controllers** – e.g. `PartsPurchaseController` – **no changes**.  
- **Database migrations** – **no new migrations**.  
- **.env** – **no required change**; optional `PARTS_BARCODE_PREFIX` if you want to override default `PRT`.

---

**End of report.** Senior ko yeh document de do – isme saari .php (aur form) additions/changes ka batoau diya hua hai.
