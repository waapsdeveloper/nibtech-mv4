# Stock Management System Analysis

## Overview
The system maintains stock at three different levels, creating a hierarchical structure for managing inventory across multiple marketplaces.

---

## 1. **Stock Table** (`stock`)
**Purpose**: Tracks individual physical stock items (IMEI/serial number level)

### Key Characteristics:
- **Granularity**: Individual item level (one record per physical unit)
- **Primary Fields**:
  - `id` - Primary key
  - `variation_id` - Links to the variation this stock belongs to
  - `imei` - IMEI number of the device
  - `serial_number` - Serial number
  - `status` - Stock status (1 = Available, 2 = Sold)
  - `order_id` - Purchase order reference
  - `sale_order_id` - Sales order reference
  - `added_by` - Admin who added the stock
  - Soft deletes enabled

### Relationships:
- **Belongs to**: `Variation_model` (via `variation_id`)
- **Has many**: 
  - `Order_item_model` (through `stock_id`)
  - `Process_stock_model` (stock processing history)
  - `Stock_operations_model` (stock operation history)
  - `Stock_movement_model` (physical movement tracking)

### Stock Status Values:
- `1` = Available (in inventory, ready to sell)
- `2` = Sold (already sold, not available)

### Key Methods:
- `mark_sold()` - Marks stock as sold
- `mark_available()` - Marks stock as available
- `availability()` - Calculates and updates stock availability based on order history

---

## 2. **Variation Table** (`variation`)
**Purpose**: Product variations with aggregated stock information (PRIMARY STOCK)

### Key Characteristics:
- **Granularity**: Product variation level (one record per product variant)
- **Primary Stock Field**: `listed_stock` - **This is the "primary stock" field**
  - Represents the total listed stock quantity for this variation
  - Used as the source of truth for total available stock
  - Can be synced from external marketplaces (e.g., BackMarket API)
  - Updated when stock is added/removed

### Key Fields:
- `id` - Primary key
- `product_id` - Links to product
- `reference_id` - External marketplace reference ID
- `sku` - Stock keeping unit
- `color`, `storage`, `grade`, `sub_grade` - Variation attributes
- `listed_stock` - **PRIMARY STOCK QUANTITY** (total listed stock)
- `state` - Publication state

### Relationships:
- **Has many**: 
  - `Stock_model` (all individual stock items for this variation)
  - `MarketplaceStockModel` (marketplace-specific stock distribution)
  - `Listing_model` (marketplace listings)
  - `Order_item_model` (order items)

### Stock-Related Methods:
- `stocks()` - All stock items for this variation
- `sold_stocks()` - Stock items with status = 2
- `all_available_stocks()` - Stock items with status = 1
- `available_stocks()` - Available stocks that are listed and have active orders
- `update_qty()` - Updates `listed_stock` from external API

### Important Notes:
- The `listed_stock` field in the variation table is the **PRIMARY/TOTAL STOCK** for that variation
- This field represents the total quantity available across all marketplaces
- It's used for backward compatibility and as a fallback when marketplace-specific stock isn't available

---

## 3. **Marketplace Stock Table** (`marketplace_stock`)
**Purpose**: Distributes stock across multiple marketplaces (NEWER SYSTEM)

### Key Characteristics:
- **Granularity**: Variation + Marketplace level (one record per variation per marketplace)
- **Purpose**: Allows stock distribution across different marketplaces with formulas
- **Created**: December 2025 (newer addition to the system)

### Key Fields:
- `id` - Primary key
- `variation_id` - Links to variation
- `marketplace_id` - Links to marketplace
- `listed_stock` - Stock quantity allocated to this marketplace
- `formula` - JSON field storing distribution formula:
  ```json
  {
    "type": "percentage|fixed",
    "apply_to": "pushed|total",
    "marketplaces": [
      {"marketplace_id": 2, "value": 30},
      {"marketplace_id": 3, "value": 20}
    ]
  }
  ```
- `reserve_old_value` - Stock value before change (for tracking)
- `reserve_new_value` - Stock value after change (for tracking)
- `admin_id` - Admin who created/updated the record

### Relationships:
- **Belongs to**: 
  - `Variation_model` (via `variation_id`)
  - `Marketplace_model` (via `marketplace_id`)

### Unique Constraint:
- One record per `variation_id` + `marketplace_id` combination

### Stock Distribution Logic:
1. When stock is added to a variation, it can be distributed across marketplaces
2. Distribution uses formulas (percentage or fixed amounts)
3. Remaining stock (after formula distribution) goes to marketplace 1 (primary marketplace)
4. Total of all `marketplace_stock.listed_stock` should equal `variation.listed_stock`

### Service:
- `StockDistributionService` - Handles automatic stock distribution based on formulas

---

## Stock Flow and Relationships

### Hierarchy:
```
Variation (listed_stock = 100)
├── Stock Items (physical units)
│   ├── Stock #1 (IMEI: xxx, status: 1)
│   ├── Stock #2 (IMEI: yyy, status: 1)
│   └── Stock #3 (IMEI: zzz, status: 2) [sold]
│
└── Marketplace Stock Distribution
    ├── Marketplace 1: 50 units (remaining stock)
    ├── Marketplace 2: 30 units (30% formula)
    └── Marketplace 3: 20 units (20% formula)
```

### Stock Calculation Flow:

1. **Physical Stock Count**:
   ```php
   $physicalStock = Variation->stocks()->where('status', 1)->count();
   ```

2. **Total Listed Stock**:
   ```php
   $totalListed = Variation->listed_stock; // Primary stock field
   ```

3. **Marketplace Stock**:
   ```php
   $marketplaceStock = MarketplaceStockModel
       ->where('variation_id', $variationId)
       ->where('marketplace_id', $marketplaceId)
       ->first()
       ->listed_stock;
   ```

4. **Total Marketplace Stock** (should equal variation.listed_stock):
   ```php
   $totalMarketplaceStock = MarketplaceStockModel
       ->where('variation_id', $variationId)
       ->sum('listed_stock');
   ```

---

## Key Differences Between Tables

| Aspect | `stock` Table | `variation` Table | `marketplace_stock` Table |
|--------|---------------|-------------------|---------------------------|
| **Level** | Individual items | Product variation | Variation + Marketplace |
| **Purpose** | Track physical units | Total stock quantity | Marketplace distribution |
| **Stock Field** | `status` (1/2) | `listed_stock` (quantity) | `listed_stock` (quantity) |
| **Granularity** | Per IMEI/serial | Per product variant | Per variant per marketplace |
| **Relationships** | Many-to-one with variation | One-to-many with stock | Many-to-one with variation |
| **When Created** | Original system | Original system | December 2025 |

---

## Stock Update Mechanisms

### 1. **Adding Stock** (Listing/Topup Process):
- Physical stock items are created in `stock` table
- `variation.listed_stock` is updated (total quantity)
- `marketplace_stock.listed_stock` is distributed using formulas
- Stock operations are logged in `stock_operations` table

### 2. **Selling Stock**:
- `stock.status` changes from 1 to 2
- `stock.sale_order_id` is set
- `variation.listed_stock` may be decremented
- `marketplace_stock.listed_stock` may be decremented

### 3. **Stock Verification**:
- `listed_stock_verification` table tracks stock changes
- Records: `qty_from`, `qty_change`, `qty_to`
- Used for audit trail

### 4. **External Sync** (BackMarket API):
- `variation.update_qty()` syncs `listed_stock` from external API
- Updates `variation.listed_stock` with API response

---

## Important Notes

1. **Primary Stock**: The `variation.listed_stock` field is the **PRIMARY STOCK** - it represents the total available stock for a variation.

2. **Stock Distribution**: The `marketplace_stock` table is a newer addition that allows distributing the primary stock across multiple marketplaces using formulas.

3. **Backward Compatibility**: The system maintains `variation.listed_stock` for backward compatibility. When marketplace-specific stock isn't available, it falls back to this field.

4. **Stock Count Mismatch**: There can be a difference between:
   - Physical stock count (`stock` table items with status=1)
   - Listed stock (`variation.listed_stock`)
   - This is normal as listed stock can be updated from external sources

5. **Stock Operations**: All stock changes are tracked in:
   - `stock_operations` - For variation changes
   - `listed_stock_verification` - For listed stock quantity changes
   - `process_stock` - For stock processing workflows

---

## Code Examples

### Get Total Stock for a Variation:
```php
$variation = Variation_model::find($id);
$totalStock = $variation->listed_stock; // Primary stock
```

### Get Physical Stock Count:
```php
$physicalCount = $variation->all_available_stocks()->count();
```

### Get Marketplace Stock:
```php
$marketplaceStock = MarketplaceStockModel::where('variation_id', $id)
    ->where('marketplace_id', $marketplaceId)
    ->first();
$stock = $marketplaceStock ? $marketplaceStock->listed_stock : 0;
```

### Get All Marketplace Stocks:
```php
$allMarketplaceStocks = MarketplaceStockModel::where('variation_id', $id)
    ->get()
    ->pluck('listed_stock', 'marketplace_id');
```

---

## Summary

The stock system uses a **three-tier architecture**:

1. **Physical Level** (`stock` table): Individual items with IMEI/serial numbers
2. **Variation Level** (`variation.listed_stock`): **PRIMARY STOCK** - total quantity per variation
3. **Marketplace Level** (`marketplace_stock`): Distributed stock per marketplace

The `variation.listed_stock` field serves as the **primary stock** source, while `marketplace_stock` provides marketplace-specific distribution. The `stock` table tracks individual physical units for inventory management.

