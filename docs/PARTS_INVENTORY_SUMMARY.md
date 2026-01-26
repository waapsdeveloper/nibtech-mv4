# Parts Inventory System - Quick Summary

## Overview
A comprehensive parts inventory management system for tracking repair parts used during device repair operations.

## Database Tables

### 1. `repair_parts` - Master Parts Inventory
**Purpose**: Stores all available repair parts

**Key Fields**:
- `product_id` → Links to products table
- `name` → Part name/description
- `sku` → Part SKU
- `on_hand` → Current inventory quantity
- `reorder_level` → Minimum stock alert level
- `unit_cost` → Cost per unit
- `active` → Active/inactive flag

**Use Case**: Track what parts you have in stock

### 2. `repair_part_usages` - Parts Usage Tracking
**Purpose**: Records when parts are consumed during repairs

**Key Fields**:
- `repair_part_id` → Which part was used
- `process_id` → Which repair process
- `process_stock_id` → Which device in the process
- `stock_id` → Direct link to stock item
- `technician_id` → Who used the part
- `qty` → Quantity used
- `unit_cost` → Cost at time of use
- `total_cost` → Total cost (qty × unit_cost)

**Use Case**: Track which parts were used, when, by whom, and at what cost

## Models

- **RepairPart** (`app/Models/RepairPart.php`)
  - Table: `repair_parts`
  - Relationships: `product()` → Products_model, `usages()` → RepairPartUsage

- **RepairPartUsage** (`app/Models/RepairPartUsage.php`)
  - Table: `repair_part_usages`
  - Relationships: `part()`, `process()`, `processStock()`, `stock()`, `technician()`

## Service

**RepairPartService** (`app/Services/Repair/RepairPartService.php`)

**Methods**:
- `consumePart($partId, $qty, $attributes)` - Consume parts from inventory
- `restockPart($partId, $qty)` - Add parts back to inventory

## Integration

**Connected To**:
- `process` table - Repair processes/orders
- `process_stock` table - Devices in repair
- `stock` table - Individual stock items
- `products` table - Product catalog
- `admin` table - Technicians

## Workflow

1. **Parts Inventory**: Parts are stored in `repair_parts` with quantities
2. **Repair Process**: Device goes into repair (process table)
3. **Part Consumption**: Technician uses parts → `RepairPartService::consumePart()`
   - Reduces `on_hand` quantity
   - Creates usage record in `repair_part_usages`
4. **Tracking**: All usage is tracked with cost, technician, and device info
5. **Restocking**: Parts can be restocked → `RepairPartService::restockPart()`

## Benefits

- ✅ Inventory tracking
- ✅ Cost management
- ✅ Usage history
- ✅ Technician accountability
- ✅ Reorder level alerts
- ✅ Transaction safety (prevents race conditions)

## Status

✅ Migrations created and fixed
✅ Models created and fixed
✅ Service layer implemented
⏳ UI/Controller integration (not yet implemented)
