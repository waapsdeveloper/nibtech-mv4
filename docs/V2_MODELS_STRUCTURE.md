# V2 Models Structure Implementation

## Overview

This document describes the V2 structure implementation for models, providing enhanced methods and better integration with V2 services.

## What Was Created

### 1. V2 Models (`app/Models/V2/`)

#### `MarketplaceStock.php`
- **Extends**: `App\Models\MarketplaceStockModel`
- **Purpose**: Enhanced marketplace stock model with V2-specific methods
- **Key Features**:
  - `syncWithMarketplace()` - Syncs stock using MarketplaceAPIService
  - `lockStockForOrder()` - Locks stock with validation
  - `releaseStockLock()` - Releases stock lock
  - `consumeStockLock()` - Consumes stock lock (order completed)
  - `getStockSummary()` - Returns formatted stock summary
  - Enhanced `getAvailableStockWithBuffer()` with logging

#### `MarketplaceStockLock.php`
- **Extends**: `App\Models\MarketplaceStockLock`
- **Purpose**: Enhanced stock lock model with helper methods
- **Key Features**:
  - `isActive()` - Check if lock is active
  - `isConsumed()` - Check if lock is consumed
  - `isCancelled()` - Check if lock is cancelled
  - `getLockDurationMinutes()` - Get lock duration
  - `getLockSummary()` - Returns formatted lock summary

#### `MarketplaceStockHistory.php`
- **Extends**: `App\Models\MarketplaceStockHistory`
- **Purpose**: Enhanced history model with query helpers
- **Key Features**:
  - `getHistoryForVariation()` - Get history for specific variation
  - `getHistoryByType()` - Get history by change type
  - `getHistoryForOrder()` - Get history for specific order
  - `getChangeSummary()` - Returns formatted change summary
  - `isIncrease()` / `isDecrease()` - Check change direction
  - `getFormattedChange()` - Get human-readable change description

## Architecture Benefits

### 1. **Backward Compatibility**
- V2 models extend original models
- All original methods still work
- No breaking changes

### 2. **Enhanced Functionality**
- Additional helper methods
- Better integration with V2 services
- Improved logging and debugging

### 3. **Type Safety**
- Better return types
- More explicit method signatures
- Enhanced IDE support

### 4. **Code Organization**
- V2-specific logic separated
- Clear namespace structure
- Easy to identify V2 code

## Usage Examples

### Using V2 MarketplaceStock

```php
use App\Models\V2\MarketplaceStock;

// Get marketplace stock
$stock = MarketplaceStock::where([
    'variation_id' => 123,
    'marketplace_id' => 1
])->first();

// Lock stock for order
$lock = $stock->lockStockForOrder($orderId, $orderItemId, $quantity);

// Get stock summary
$summary = $stock->getStockSummary();
// Returns: ['variation_id', 'marketplace_id', 'listed_stock', 'locked_stock', ...]

// Sync with marketplace API
$stock->syncWithMarketplace($quantity);

// Consume lock (order completed)
$stock->consumeStockLock($orderId, $orderItemId, $quantity);
```

### Using V2 MarketplaceStockLock

```php
use App\Models\V2\MarketplaceStockLock;

$lock = MarketplaceStockLock::find($lockId);

// Check lock status
if ($lock->isActive()) {
    // Lock is still active
}

// Get lock duration
$duration = $lock->getLockDurationMinutes();

// Get lock summary
$summary = $lock->getLockSummary();
```

### Using V2 MarketplaceStockHistory

```php
use App\Models\V2\MarketplaceStockHistory;

// Get history for variation
$history = MarketplaceStockHistory::getHistoryForVariation($variationId, $marketplaceId);

// Get history by type
$orderHistory = MarketplaceStockHistory::getHistoryByType('order_completed');

// Get history for order
$orderHistory = MarketplaceStockHistory::getHistoryForOrder($orderId);

// Get change summary
$summary = $history->first()->getChangeSummary();

// Check if increase/decrease
if ($history->first()->isIncrease()) {
    // Stock was increased
}

// Get formatted change
$description = $history->first()->getFormattedChange();
// Returns: "Stock increased by 10 (topup)"
```

## File Structure

```
app/
├── Models/
│   ├── MarketplaceStockModel.php          # V1 (original)
│   ├── MarketplaceStockLock.php          # V1 (original)
│   ├── MarketplaceStockHistory.php       # V1 (original)
│   └── V2/
│       ├── MarketplaceStock.php           # V2 (extends V1)
│       ├── MarketplaceStockLock.php       # V2 (extends V1)
│       └── MarketplaceStockHistory.php   # V2 (extends V1)
```

## Integration with V2 Services

### MarketplaceAPIService Integration

V2 models integrate seamlessly with V2 services:

```php
use App\Models\V2\MarketplaceStock;
use App\Services\V2\MarketplaceAPIService;

$stock = MarketplaceStock::find($id);

// Sync automatically uses MarketplaceAPIService
$stock->syncWithMarketplace($quantity);
// Internally calls: app(MarketplaceAPIService::class)->updateStock(...)
```

### Event Integration

V2 models work with V2 events:

```php
use App\Models\V2\MarketplaceStock;
use App\Events\V2\OrderCreated;

$stock = MarketplaceStock::find($id);

// Lock stock (can be called from event listener)
$lock = $stock->lockStockForOrder($orderId, $orderItemId, $quantity);
```

## Migration Path

### Current State
- ✅ V2 models created
- ✅ Extend original models
- ✅ Enhanced methods added
- ✅ Backward compatible

### When to Use V2 Models

**Use V2 models when:**
- Writing new V2 code
- Need enhanced methods
- Want better integration with V2 services
- Need better logging/debugging

**Keep V1 models when:**
- Maintaining existing code
- Need backward compatibility
- Simple queries without enhancements

## Method Reference

### MarketplaceStock (V2)

| Method | Description | Returns |
|--------|-------------|---------|
| `getAvailableStockWithBuffer()` | Get stock with buffer applied | `int` |
| `syncWithMarketplace($quantity)` | Sync stock with marketplace API | `bool` |
| `lockStockForOrder($orderId, $orderItemId, $quantity)` | Lock stock for order | `MarketplaceStockLock\|null` |
| `releaseStockLock($orderId, $orderItemId)` | Release stock lock | `bool` |
| `consumeStockLock($orderId, $orderItemId, $quantity)` | Consume stock lock | `bool` |
| `getStockSummary()` | Get formatted stock summary | `array` |

### MarketplaceStockLock (V2)

| Method | Description | Returns |
|--------|-------------|---------|
| `isActive()` | Check if lock is active | `bool` |
| `isConsumed()` | Check if lock is consumed | `bool` |
| `isCancelled()` | Check if lock is cancelled | `bool` |
| `getLockDurationMinutes()` | Get lock duration | `int\|null` |
| `getLockSummary()` | Get formatted lock summary | `array` |

### MarketplaceStockHistory (V2)

| Method | Description | Returns |
|--------|-------------|---------|
| `getHistoryForVariation($variationId, $marketplaceId, $limit)` | Get history for variation | `Collection` |
| `getHistoryByType($changeType, $limit)` | Get history by type | `Collection` |
| `getHistoryForOrder($orderId)` | Get history for order | `Collection` |
| `getChangeSummary()` | Get formatted change summary | `array` |
| `isIncrease()` | Check if stock increased | `bool` |
| `isDecrease()` | Check if stock decreased | `bool` |
| `getFormattedChange()` | Get human-readable change | `string` |

## Testing

### Test V2 MarketplaceStock

```php
// In tinker or test
use App\Models\V2\MarketplaceStock;

$stock = MarketplaceStock::first();
$summary = $stock->getStockSummary();
$buffered = $stock->getAvailableStockWithBuffer();
```

### Test V2 MarketplaceStockLock

```php
use App\Models\V2\MarketplaceStockLock;

$lock = MarketplaceStockLock::where('lock_status', 'locked')->first();
$isActive = $lock->isActive();
$duration = $lock->getLockDurationMinutes();
```

### Test V2 MarketplaceStockHistory

```php
use App\Models\V2\MarketplaceStockHistory;

$history = MarketplaceStockHistory::getHistoryForVariation(1, 1);
$summary = $history->first()->getChangeSummary();
```

## Notes

- **Backward Compatible**: V2 models extend V1 models, so all V1 methods work
- **Enhanced Methods**: V2 adds new methods without breaking existing code
- **Service Integration**: V2 models integrate with MarketplaceAPIService
- **Better Logging**: V2 methods include enhanced logging
- **Type Safety**: Better return types and method signatures

## Next Steps

1. **Update V2 Listeners**: Use V2 models in listeners
2. **Update V2 Services**: Use V2 models in services
3. **Testing**: Test with real data
4. **Documentation**: Update API documentation

