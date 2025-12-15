# Stock Formula Distribution Implementation Plan

## Overview
This document outlines the implementation plan for automatic stock distribution across marketplaces based on configurable formulas when stock is updated in the parent variation table.

---

## Database Changes

### Migration: `add_formula_and_reserve_columns_to_marketplace_stock_table`

**New Columns Added:**
1. **`formula`** (JSON, nullable)
   - Stores formula configuration for stock distribution
   - Structure:
     ```json
     {
       "type": "percentage" | "fixed",
       "marketplaces": [
         {"marketplace_id": 1, "value": 50},
         {"marketplace_id": 2, "value": 30},
         {"marketplace_id": 3, "value": 20}
       ],
       "remaining_to_marketplace_1": true
     }
     ```

2. **`reserve_old_value`** (Integer, nullable)
   - Stores stock value before change
   - Used for tracking and audit purposes

3. **`reserve_new_value`** (Integer, nullable)
   - Stores stock value after change
   - Used for tracking and audit purposes

---

## Formula Structure

### Formula Types

#### 1. Percentage-Based Distribution
```json
{
  "type": "percentage",
  "marketplaces": [
    {"marketplace_id": 1, "value": 50},   // 50% of new stock
    {"marketplace_id": 2, "value": 30},   // 30% of new stock
    {"marketplace_id": 3, "value": 20}    // 20% of new stock
  ],
  "remaining_to_marketplace_1": true
}
```

#### 2. Fixed Number Distribution
```json
{
  "type": "fixed",
  "marketplaces": [
    {"marketplace_id": 1, "value": 10},   // 10 units
    {"marketplace_id": 2, "value": 5},    // 5 units
    {"marketplace_id": 3, "value": 3}     // 3 units
  ],
  "remaining_to_marketplace_1": true
}
```

### Formula Rules
- Formula applies to the **incremented/pushed number**, not total stock
- Existing stock in marketplaces is considered as already stored
- Remaining stock (after formula distribution) goes to marketplace 1
- If `remaining_to_marketplace_1` is false, remaining stock is not distributed

---

## Implementation Components

### 1. Model Updates ✅
- **File**: `app/Models/MarketplaceStockModel.php`
- Added new fields to `$fillable`
- Added `$casts` for JSON and integer fields

### 2. UI Components (To Be Created)

#### A. Marketplace Stock Formula Management Page
**Route**: `/v2/marketplace/stock-formula` or `/v2/marketplace/{variation_id}/formula`

**Features**:
- Search and select variation
- Display current stock distribution formulas for all marketplaces
- Add/Edit/Delete formulas per marketplace
- Support both percentage and fixed number inputs
- Toggle for "remaining to marketplace 1"

**Components Needed**:
- `app/Http/Livewire/V2/Marketplace/StockFormula.php` (Livewire component)
- `resources/views/livewire/v2/marketplace/stock-formula.blade.php` (View)

#### B. Formula Input UI
- Dropdown/Radio: Select formula type (Percentage/Fixed)
- Dynamic form fields for each marketplace
- Input validation
- Preview of distribution before saving

### 3. Event & Listener (To Be Created)

#### A. Variation Stock Update Event
**File**: `app/Events/VariationStockUpdated.php`

**Triggers When**:
- Stock is updated in the `variation` table (or related stock table)
- Stock is incremented/decremented

**Event Data**:
```php
[
    'variation_id' => 123,
    'old_stock' => 100,
    'new_stock' => 150,
    'stock_change' => 50,  // The increment/decrement amount
    'admin_id' => 1
]
```

#### B. Stock Distribution Listener
**File**: `app/Listeners/DistributeStockToMarketplaces.php`

**Logic**:
1. Get all marketplace_stock records for the variation
2. For each marketplace with a formula:
   - Calculate distribution based on formula type
   - Apply formula to the **stock_change** (not total)
   - Update `listed_stock` in marketplace_stock table
   - Store old/new values in reserve columns
3. Distribute remaining stock to marketplace 1 (if enabled)
4. Log the distribution

**Distribution Algorithm**:
```php
// Pseudo-code
foreach ($marketplaceStocks as $marketplaceStock) {
    $formula = $marketplaceStock->formula;
    
    if ($formula && isset($formula['marketplaces'])) {
        foreach ($formula['marketplaces'] as $marketplaceConfig) {
            if ($marketplaceConfig['marketplace_id'] == $marketplaceStock->marketplace_id) {
                $oldValue = $marketplaceStock->listed_stock;
                
                if ($formula['type'] == 'percentage') {
                    $distribution = ($stockChange * $marketplaceConfig['value']) / 100;
                } else { // fixed
                    $distribution = $marketplaceConfig['value'];
                }
                
                $newValue = $oldValue + $distribution;
                
                $marketplaceStock->reserve_old_value = $oldValue;
                $marketplaceStock->reserve_new_value = $newValue;
                $marketplaceStock->listed_stock = $newValue;
                $marketplaceStock->save();
                
                $remainingStock -= $distribution;
            }
        }
    }
}

// Add remaining to marketplace 1
if ($formula['remaining_to_marketplace_1'] && $remainingStock > 0) {
    $marketplace1Stock = MarketplaceStockModel::where('variation_id', $variationId)
        ->where('marketplace_id', 1)
        ->first();
    
    if ($marketplace1Stock) {
        $marketplace1Stock->reserve_old_value = $marketplace1Stock->listed_stock;
        $marketplace1Stock->listed_stock += $remainingStock;
        $marketplace1Stock->reserve_new_value = $marketplace1Stock->listed_stock;
        $marketplace1Stock->save();
    }
}
```

### 4. Service Class (To Be Created)

**File**: `app/Services/Marketplace/StockDistributionService.php`

**Methods**:
- `calculateDistribution($variationId, $stockChange, $formula)`
- `applyFormula($marketplaceStock, $formula, $stockChange)`
- `distributeRemainingStock($variationId, $remainingStock)`
- `validateFormula($formula)`

### 5. Real-time Updates (To Be Implemented)

**For Listings Page**:
- Use Laravel Broadcasting or Livewire events
- When stock is updated, trigger real-time update of marketplace stocks
- Display updated stocks without page refresh

**Options**:
- Laravel Echo + Pusher/Broadcasting
- Livewire events
- AJAX polling
- WebSockets

---

## Workflow

### Setting Up Formula
1. Navigate to Marketplace Stock Formula page
2. Search and select variation
3. Configure formula for each marketplace:
   - Select type (Percentage/Fixed)
   - Enter values
   - Enable/disable "remaining to marketplace 1"
4. Save formula

### Stock Update Flow
1. User updates stock in parent table (variation)
2. `VariationStockUpdated` event is fired
3. `DistributeStockToMarketplaces` listener is triggered
4. Service calculates distribution based on formulas
5. Marketplace stocks are updated
6. Reserve values are stored (old/new)
7. Real-time update is sent to listings page
8. UI reflects changes immediately

---

## Files to Create/Modify

### ✅ Completed
- [x] Migration: `add_formula_and_reserve_columns_to_marketplace_stock_table.php`
- [x] Model: Updated `MarketplaceStockModel.php`

### 📝 To Be Created

#### Controllers/Components
- [ ] `app/Http/Livewire/V2/Marketplace/StockFormula.php`
- [ ] `app/Http/Controllers/V2/MarketplaceStockFormulaController.php` (if not using Livewire)

#### Views
- [ ] `resources/views/livewire/v2/marketplace/stock-formula.blade.php`
- [ ] `resources/views/livewire/v2/marketplace/partials/formula-form.blade.php`

#### Events & Listeners
- [ ] `app/Events/VariationStockUpdated.php`
- [ ] `app/Listeners/DistributeStockToMarketplaces.php`

#### Services
- [ ] `app/Services/Marketplace/StockDistributionService.php`

#### Routes
- [ ] Add routes to `routes/v2.php`:
  ```php
  Route::get('marketplace/stock-formula', [StockFormula::class, 'index'])->name('v2.marketplace.stock_formula');
  Route::get('marketplace/stock-formula/{variation_id}', [StockFormula::class, 'show'])->name('v2.marketplace.stock_formula.show');
  Route::post('marketplace/stock-formula/{variation_id}', [StockFormula::class, 'store'])->name('v2.marketplace.stock_formula.store');
  ```

#### Event Registration
- [ ] Register event/listener in `app/Providers/EventServiceProvider.php`

---

## Example Scenarios

### Scenario 1: Percentage Distribution
**Initial State:**
- Variation stock: 100 units
- Marketplace 1 stock: 50 units
- Marketplace 2 stock: 30 units
- Marketplace 3 stock: 20 units

**Formula:**
```json
{
  "type": "percentage",
  "marketplaces": [
    {"marketplace_id": 1, "value": 50},
    {"marketplace_id": 2, "value": 30},
    {"marketplace_id": 3, "value": 20}
  ],
  "remaining_to_marketplace_1": true
}
```

**Stock Update:**
- New stock added: 100 units (total becomes 200)
- Stock change: +100 units

**Distribution:**
- Marketplace 1: 50 + (100 * 50%) = 50 + 50 = **100 units**
- Marketplace 2: 30 + (100 * 30%) = 30 + 30 = **60 units**
- Marketplace 3: 20 + (100 * 20%) = 20 + 20 = **40 units**
- Remaining: 0 (exactly 100%)

### Scenario 2: Fixed Distribution
**Initial State:**
- Variation stock: 100 units
- Marketplace 1 stock: 50 units
- Marketplace 2 stock: 30 units
- Marketplace 3 stock: 20 units

**Formula:**
```json
{
  "type": "fixed",
  "marketplaces": [
    {"marketplace_id": 1, "value": 10},
    {"marketplace_id": 2, "value": 5},
    {"marketplace_id": 3, "value": 3}
  ],
  "remaining_to_marketplace_1": true
}
```

**Stock Update:**
- New stock added: 100 units
- Stock change: +100 units

**Distribution:**
- Marketplace 1: 50 + 10 = **60 units** (+ remaining 82 = **142 units**)
- Marketplace 2: 30 + 5 = **35 units**
- Marketplace 3: 20 + 3 = **23 units**
- Remaining: 100 - (10+5+3) = 82 units → goes to Marketplace 1

---

## Testing Checklist

- [ ] Migration runs successfully
- [ ] Formula can be saved/retrieved correctly
- [ ] Percentage distribution calculates correctly
- [ ] Fixed distribution calculates correctly
- [ ] Remaining stock goes to marketplace 1 when enabled
- [ ] Reserve values are stored correctly
- [ ] Event fires when stock is updated
- [ ] Listener distributes stock correctly
- [ ] Real-time updates work on listings page
- [ ] Formula validation works
- [ ] Edge cases handled (negative stock, zero stock, etc.)

---

## Next Steps

1. Run migration: `php artisan migrate`
2. Create Event & Listener
3. Create Service class
4. Create UI components
5. Register event in EventServiceProvider
6. Implement real-time updates
7. Test thoroughly
8. Deploy

---

## Notes

- Formula applies to **incremented amount only**, not total stock
- Existing stock in marketplaces is preserved
- Reserve columns track changes for audit trail
- Real-time updates ensure UI reflects changes immediately
- Formula can be updated anytime, affecting future distributions only

