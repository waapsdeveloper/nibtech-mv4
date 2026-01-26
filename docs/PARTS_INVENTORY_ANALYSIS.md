# Parts Inventory System - Analysis

## Overview
The parts inventory system is designed to track and manage repair parts used during device repair processes. It provides inventory management, cost tracking, and usage history for parts consumed during repairs.

## Database Structure

### 1. `repair_parts` Table
**Migration**: `2026_01_21_000000_create_repair_parts_table.php`

**Purpose**: Master inventory table for all repair parts

**Fields**:
- `id` - Primary key (bigInteger)
- `product_id` - Foreign key to `products` table (unsignedInteger)
  - Links part to a product category
- `name` - Part name/description (string)
- `sku` - Part SKU for identification (string, nullable)
- `compatible_device` - Device compatibility info (string, nullable)
- `on_hand` - Current inventory quantity (integer, default: 0)
- `reorder_level` - Minimum stock level before reordering (integer, default: 0)
- `unit_cost` - Cost per unit (decimal 12,2, default: 0)
- `active` - Whether part is active/available (boolean, default: true)
- `created_at`, `updated_at` - Timestamps
- `deleted_at` - Soft delete timestamp

**Indexes**:
- `product_id` + `active` (composite index for filtering)

**Relationships**:
- Belongs to `Products_model` (via product_id)
- Has many `RepairPartUsage` (usages)

### 2. `repair_part_usages` Table
**Migration**: `2026_01_21_000001_create_repair_part_usages_table.php`

**Purpose**: Tracks when and how parts are used in repair processes

**Fields**:
- `id` - Primary key (bigInteger)
- `process_id` - Foreign key to `process` table (integer, nullable)
  - Links to repair process/order
- `process_stock_id` - Foreign key to `process_stock` table (integer, nullable)
  - Links to specific stock item in the process
- `stock_id` - Foreign key to `stock` table (integer, nullable)
  - Direct link to stock item being repaired
- `repair_part_id` - Foreign key to `repair_parts` table (bigInteger)
  - The part that was used
- `technician_id` - Foreign key to `admin` table (integer, nullable)
  - Technician who used the part
- `qty` - Quantity used (integer, default: 1)
- `unit_cost` - Cost per unit at time of use (decimal 12,2, default: 0)
- `total_cost` - Total cost (qty × unit_cost) (decimal 12,2, default: 0)
- `notes` - Additional notes (text, nullable)
- `created_at`, `updated_at` - Timestamps
- `deleted_at` - Soft delete timestamp

**Indexes**:
- `process_id` + `process_stock_id` (composite index)
- `stock_id` + `repair_part_id` (composite index)

**Foreign Keys**:
- `process_id` → `process.id` (nullOnDelete)
- `process_stock_id` → `process_stock.id` (nullOnDelete)
- `stock_id` → `stock.id` (nullOnDelete)
- `repair_part_id` → `repair_parts.id` (cascadeOnDelete, cascadeOnUpdate)
- `technician_id` → `admin.id` (nullOnDelete)

**Relationships**:
- Belongs to `RepairPart` (via repair_part_id)
- Belongs to `Process_model` (via process_id)
- Belongs to `Process_stock_model` (via process_stock_id)
- Belongs to `Stock_model` (via stock_id)
- Belongs to `Admin_model` (via technician_id)

## Models

### 1. `RepairPart` Model
**File**: `app/Models/RepairPart.php`

**Features**:
- Soft deletes support
- Relationships: `product()`, `usages()`
- Scope: `active()` - filters active parts only
- Fillable fields for mass assignment

**Key Methods**:
- `product()` - Get associated product (Products_model)
- `usages()` - Get all usage records
- `scopeActive()` - Filter active parts

**Table**: `repair_parts`

### 2. `RepairPartUsage` Model
**File**: `app/Models/RepairPartUsage.php`

**Features**:
- Soft deletes support
- Relationships: `part()`, `process()`, `processStock()`, `stock()`, `technician()`
- Tracks cost at time of use

**Key Methods**:
- `part()` - Get the repair part used
- `process()` - Get the repair process (Process_model)
- `processStock()` - Get the process stock item (Process_stock_model)
- `stock()` - Get the stock item (Stock_model)
- `technician()` - Get the technician (Admin_model)

**Table**: `repair_part_usages`

## Service Layer

### `RepairPartService`
**File**: `app/Services/Repair/RepairPartService.php`

**Purpose**: Business logic for parts inventory operations

**Key Methods**:

1. **`consumePart($partId, $qty, $attributes)`**
   - Consumes parts from inventory
   - Reduces `on_hand` quantity
   - Creates usage record
   - Uses database transaction for atomicity
   - Locks part record to prevent race conditions
   - Calculates total cost automatically
   - Returns `RepairPartUsage` instance

2. **`restockPart($partId, $qty)`**
   - Adds parts back to inventory
   - Increases `on_hand` quantity
   - Uses database transaction
   - Locks part record
   - Returns updated `RepairPart` instance

## Business Logic Flow

### Part Consumption Flow
```
1. Technician starts repair process
2. Identifies parts needed
3. Calls RepairPartService::consumePart()
4. Service:
   - Locks part record
   - Validates quantity
   - Reduces on_hand quantity
   - Creates usage record with:
     - process_id (repair process)
     - process_stock_id (specific device)
     - stock_id (stock item)
     - technician_id (who used it)
     - qty, unit_cost, total_cost
   - Commits transaction
5. Part inventory updated
6. Usage tracked for reporting
```

### Part Restocking Flow
```
1. Parts received from supplier
2. Call RepairPartService::restockPart()
3. Service:
   - Locks part record
   - Increases on_hand quantity
   - Commits transaction
4. Inventory updated
```

## Integration Points

### With Repair Process
- **Process Table**: Links parts usage to repair processes
- **Process_stock Table**: Links parts to specific devices in repair
- **Stock Table**: Direct link to stock items being repaired

### With Products
- Parts are categorized by product
- Allows filtering parts by device type

### With Admin/Technicians
- Tracks which technician used which parts
- Enables accountability and reporting

## Use Cases

1. **Inventory Management**
   - Track current stock levels (`on_hand`)
   - Set reorder levels for automatic alerts
   - Monitor part availability

2. **Cost Tracking**
   - Track unit costs
   - Calculate total costs per usage
   - Historical cost analysis

3. **Usage Tracking**
   - See which parts were used in which repairs
   - Track parts per device/stock item
   - Technician usage reports

4. **Reporting**
   - Parts consumption reports
   - Cost analysis per repair
   - Inventory levels monitoring
   - Reorder alerts

## Data Integrity

### Constraints
- Foreign keys ensure referential integrity
- Cascade deletes for `repair_part_id` (if part deleted, usages deleted)
- Null on delete for process/stock references (preserve history if process deleted)

### Transactions
- `consumePart()` uses transactions to ensure atomicity
- Prevents race conditions with row locking
- Ensures inventory accuracy

## Potential Enhancements

1. **Reorder Alerts**: Automatic notifications when `on_hand < reorder_level`
2. **Batch Operations**: Consume multiple parts in one transaction
3. **Return/Refund**: Handle parts returned from repairs
4. **Supplier Tracking**: Link parts to suppliers
5. **Location Tracking**: Track where parts are stored
6. **Expiry Dates**: Track parts with expiration dates
7. **Serial Numbers**: Track individual part serial numbers
8. **Warranty Tracking**: Track warranty information for parts

## Related Tables

- `process` - Repair processes/orders
- `process_stock` - Stock items in repair processes
- `stock` - Individual stock items
- `products` - Product catalog
- `admin` - Technicians/admins

## Migration Notes

**Fixed Issues**:
- Changed `foreignId('product_id')` to `unsignedInteger('product_id')` to match `products.id` type (int)
- Changed `unsignedInteger()` to `integer()` for foreign keys matching signed int columns:
  - `process_id` → `process.id` (int)
  - `process_stock_id` → `process_stock.id` (int)
  - `stock_id` → `stock.id` (int)
  - `technician_id` → `admin.id` (int)

## Summary

The parts inventory system provides:
- ✅ Complete inventory tracking
- ✅ Cost management
- ✅ Usage history
- ✅ Integration with repair processes
- ✅ Technician accountability
- ✅ Transaction safety
- ✅ Soft deletes for data retention

This system enables efficient management of repair parts, cost tracking, and provides audit trails for all part usage in repair operations.
