# V2 Events Structure Implementation

## Overview

This document describes the V2 structure implementation for events and listeners, providing a generic, multi-marketplace solution that uses the `MarketplaceAPIService`.

## What Was Created

### 1. V2 Events (`app/Events/V2/`)

#### `OrderCreated.php`
- **Namespace**: `App\Events\V2`
- **Purpose**: Generic event for order creation across all marketplaces
- **Properties**: 
  - `$order` (Order_model)
  - `$orderItems` (collection)

#### `OrderStatusChanged.php`
- **Namespace**: `App\Events\V2`
- **Purpose**: Generic event for order status changes across all marketplaces
- **Properties**:
  - `$order` (Order_model)
  - `$oldStatus` (int)
  - `$newStatus` (int)
  - `$orderItems` (collection)

### 2. V2 Listeners (`app/Listeners/V2/`)

#### `LockStockOnOrderCreated.php`
- **Namespace**: `App\Listeners\V2`
- **Purpose**: Locks stock when a V2 OrderCreated event is fired
- **Features**:
  - Generic marketplace logic (no marketplace-specific code)
  - Works with all marketplaces
  - Creates lock records and history

#### `ReduceStockOnOrderCompleted.php`
- **Namespace**: `App\Listeners\V2`
- **Purpose**: Reduces stock and updates marketplace API when order is completed
- **Features**:
  - Uses `MarketplaceAPIService` (automatic buffer application)
  - Generic API updates (no marketplace-specific code)
  - Works with all supported marketplaces

### 3. Updated EventServiceProvider

Both V1 and V2 events are registered:
- **V1 Events**: Original events (backward compatibility)
- **V2 Events**: New generic events (uses MarketplaceAPIService)

## Architecture Benefits

### 1. **Separation of Concerns**
- Original events/listeners remain untouched
- V2 code is in separate namespace (`V2`)
- Both can work simultaneously

### 2. **Generic Design**
- No hardcoded marketplace names
- Uses `MarketplaceAPIService` for API calls
- Consistent behavior across all marketplaces

### 3. **Automatic Buffer**
- V2 listeners use `MarketplaceAPIService`
- Buffer is applied automatically
- No manual buffer calculation needed

### 4. **Extensibility**
- Easy to add new marketplaces
- No changes needed to event/listener code
- Just extend `MarketplaceAPIService`

## Usage Examples

### Dispatch V2 OrderCreated Event

```php
use App\Events\V2\OrderCreated;

// When creating a new order
$order = Order_model::create([...]);
$orderItems = $order->order_items;

// Dispatch V2 event
event(new OrderCreated($order, $orderItems));
```

### Dispatch V2 OrderStatusChanged Event

```php
use App\Events\V2\OrderStatusChanged;

// When order status changes
$oldStatus = $order->status;
$order->status = 3; // Completed
$order->save();
$orderItems = $order->order_items;

// Dispatch V2 event
event(new OrderStatusChanged($order, $oldStatus, $order->status, $orderItems));
```

## File Structure

```
app/
├── Events/
│   ├── OrderCreated.php                    # V1 (original)
│   ├── OrderStatusChanged.php              # V1 (original)
│   └── V2/
│       ├── OrderCreated.php                # V2 (generic)
│       └── OrderStatusChanged.php          # V2 (generic)
├── Listeners/
│   ├── LockStockOnOrderCreated.php         # V1 (original)
│   ├── ReduceStockOnOrderCompleted.php     # V1 (original)
│   └── V2/
│       ├── LockStockOnOrderCreated.php     # V2 (uses MarketplaceAPIService)
│       └── ReduceStockOnOrderCompleted.php # V2 (uses MarketplaceAPIService)
└── Providers/
    └── EventServiceProvider.php            # Registers both V1 and V2
```

## Event Flow

### V2 OrderCreated Flow

1. **Order Created** → Dispatch `OrderCreated` (V2)
2. **Listener Triggered** → `LockStockOnOrderCreated` (V2)
3. **Stock Locked** → Updates `marketplace_stock` table
4. **Lock Record Created** → Creates entry in `marketplace_stock_locks`
5. **History Logged** → Creates entry in `marketplace_stock_history`

### V2 OrderStatusChanged Flow

1. **Order Status Changed to Completed** → Dispatch `OrderStatusChanged` (V2)
2. **Listener Triggered** → `ReduceStockOnOrderCompleted` (V2)
3. **Stock Reduced** → Updates `marketplace_stock` table
4. **Locks Consumed** → Updates `marketplace_stock_locks` status
5. **History Logged** → Creates entry in `marketplace_stock_history`
6. **API Updated** → Uses `MarketplaceAPIService` (with buffer)

## Differences: V1 vs V2

### V1 Listeners
- Direct API calls to `BackMarketAPIController`
- Manual buffer calculation
- Marketplace-specific code

### V2 Listeners
- Uses `MarketplaceAPIService`
- Automatic buffer application
- Generic marketplace logic
- Works with all marketplaces

## Migration Path

### Current State
- ✅ V2 events created
- ✅ V2 listeners created
- ✅ EventServiceProvider updated
- ✅ Both V1 and V2 registered

### When to Use V2

**Use V2 events when:**
- Creating new order sync functionality
- Updating existing order processing
- Want automatic buffer application
- Need multi-marketplace support

**Keep V1 events when:**
- Maintaining existing code
- Need backward compatibility
- Specific marketplace logic required

## Testing

### Test V2 OrderCreated Event

```php
// In tinker or test
$order = Order_model::find(1);
$orderItems = $order->order_items;

event(new \App\Events\V2\OrderCreated($order, $orderItems));

// Check logs
// Check marketplace_stock_locks table
// Check marketplace_stock_history table
```

### Test V2 OrderStatusChanged Event

```php
// In tinker or test
$order = Order_model::find(1);
$oldStatus = $order->status;
$order->status = 3; // Completed
$order->save();
$orderItems = $order->order_items;

event(new \App\Events\V2\OrderStatusChanged($order, $oldStatus, $order->status, $orderItems));

// Check logs
// Check marketplace_stock table (stock reduced)
// Check marketplace_stock_locks (locks consumed)
// Check marketplace API (updated with buffer)
```

## Integration Points

### Where to Fire V2 Events

1. **Order Sync Commands** (`RefreshOrders`, `UpdateOrderInDB`)
   ```php
   event(new \App\Events\V2\OrderCreated($order, $order->order_items));
   ```

2. **Order Status Updates**
   ```php
   event(new \App\Events\V2\OrderStatusChanged($order, $oldStatus, $newStatus, $order->order_items));
   ```

3. **Webhook Handlers** (Refurbed, Back Market)
   ```php
   // After creating/updating order from webhook
   event(new \App\Events\V2\OrderCreated($order, $order->order_items));
   ```

## Notes

- **Both V1 and V2 work**: You can use both simultaneously
- **V2 is recommended**: For new code, use V2 events
- **Automatic buffer**: V2 listeners apply buffer automatically
- **Generic design**: V2 works with all marketplaces
- **Backward compatible**: V1 events still work

## Next Steps

1. **Update Order Sync Commands**: Fire V2 events in `RefreshOrders` and `UpdateOrderInDB`
2. **Update Webhook Handlers**: Use V2 events in Refurbed/Back Market webhooks
3. **Testing**: Test with real orders
4. **Documentation**: Update API documentation

