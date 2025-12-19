# Client Stock Sync Implementation Plan

## Executive Summary

**Client Requirements:**
1. Maintain local stock table (our record of marketplace stock) - **NOT** ask marketplaces what their stock is
2. Update stock based on **ORDER EVENTS** (not hourly polling):
   - When order placed → **Lock stock**
   - When order successful → **Reduce stock**
3. Change hourly sync to **6 hours** (instead of 1 hour)
4. When sending stock to Back Market/any marketplace → Send **5-10% LESS** than actual available stock
   - This keeps buffer/reserve stock
   - Can be based on stock formula

---

## Current System Analysis

### Current Stock Management

**Current Flow:**
1. **Page Load:** Fetches stock from Back Market API (`getOneListing`)
2. **Hourly Sync:** `FunctionsThirty` command fetches ALL listings from Back Market
3. **Stock Updates:** When adding stock, sends exact quantity to Back Market
4. **Order Processing:** Currently reduces `variation.listed_stock` directly (Line 208 in `Order_item_model.php`)

**Current Issues:**
- ❌ Pulls stock from Back Market (not maintaining our own record)
- ❌ Hourly sync (needs to be 6 hours)
- ❌ Sends exact stock to marketplaces (no buffer)
- ❌ No stock locking mechanism
- ❌ Stock reduction happens immediately, not based on order status

---

## Proposed Architecture

### Core Concept

```
┌─────────────────────────────────────────────────────────────┐
│                    Order Created Event                       │
│  (order_type_id = 3, status = 1 or 2)                       │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│              Lock Stock in Marketplace Stock                 │
│  - Decrease available_stock                                 │
│  - Increase locked_stock                                    │
│  - Don't update Back Market API yet                         │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│              Order Status Changed to Success                │
│  (status = 3 = Shipped/Completed)                          │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│              Reduce Stock & Update Marketplace              │
│  - Decrease locked_stock                                    │
│  - Decrease listed_stock (permanent reduction)              │
│  - Calculate available stock (with buffer)                  │
│  - Update Back Market API with reduced stock                │
└─────────────────────────────────────────────────────────────┘
```

---

## Database Schema Changes

### 1. Enhance `marketplace_stock` Table

**Current Schema:**
```sql
CREATE TABLE marketplace_stock (
    id BIGINT PRIMARY KEY,
    variation_id BIGINT,
    marketplace_id INT,
    listed_stock INT DEFAULT 0,
    admin_id INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**New Schema (Add Columns):**
```sql
ALTER TABLE marketplace_stock
ADD COLUMN locked_stock INT DEFAULT 0 COMMENT 'Stock locked by pending orders',
ADD COLUMN available_stock INT DEFAULT 0 COMMENT 'listed_stock - locked_stock',
ADD COLUMN buffer_percentage DECIMAL(5,2) DEFAULT 10.00 COMMENT 'Percentage to reduce when sending to marketplace (5-10%)',
ADD COLUMN last_synced_at TIMESTAMP NULL COMMENT 'Last time synced with marketplace API',
ADD COLUMN last_api_quantity INT NULL COMMENT 'Last quantity sent to marketplace API';
```

**Indexes:**
```sql
CREATE INDEX idx_marketplace_stock_variation_marketplace ON marketplace_stock(variation_id, marketplace_id);
CREATE INDEX idx_marketplace_stock_available ON marketplace_stock(available_stock);
```

### 2. Create `marketplace_stock_locks` Table (NEW)

**Purpose:** Track which orders have locked which stock

```sql
CREATE TABLE marketplace_stock_locks (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    marketplace_stock_id BIGINT,
    variation_id BIGINT,
    marketplace_id INT,
    order_id BIGINT,
    order_item_id BIGINT,
    quantity_locked INT,
    lock_status ENUM('locked', 'released', 'consumed') DEFAULT 'locked',
    locked_at TIMESTAMP,
    released_at TIMESTAMP NULL,
    consumed_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_marketplace_stock_id (marketplace_stock_id),
    INDEX idx_variation_marketplace (variation_id, marketplace_id),
    INDEX idx_lock_status (lock_status)
);
```

### 3. Create `marketplace_stock_history` Table (NEW)

**Purpose:** Complete audit trail of all stock changes

```sql
CREATE TABLE marketplace_stock_history (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    marketplace_stock_id BIGINT,
    variation_id BIGINT,
    marketplace_id INT,
    listed_stock_before INT,
    listed_stock_after INT,
    locked_stock_before INT,
    locked_stock_after INT,
    available_stock_before INT,
    available_stock_after INT,
    quantity_change INT,
    change_type ENUM('order_created', 'order_completed', 'order_cancelled', 'topup', 'manual', 'reconciliation', 'api_sync', 'lock', 'unlock'),
    order_id BIGINT NULL,
    order_item_id BIGINT NULL,
    reference_id VARCHAR(255) NULL,
    admin_id INT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP,
    INDEX idx_variation_marketplace (variation_id, marketplace_id),
    INDEX idx_created_at (created_at),
    INDEX idx_order_id (order_id),
    INDEX idx_change_type (change_type)
);
```

---

## Implementation Steps

### Phase 1: Database Setup (Week 1, Days 1-2)

#### Step 1.1: Create Migration Files

**File:** `database/migrations/YYYY_MM_DD_HHMMSS_add_stock_tracking_to_marketplace_stock.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStockTrackingToMarketplaceStock extends Migration
{
    public function up()
    {
        Schema::table('marketplace_stock', function (Blueprint $table) {
            $table->integer('locked_stock')->default(0)->after('listed_stock')->comment('Stock locked by pending orders');
            $table->integer('available_stock')->default(0)->after('locked_stock')->comment('listed_stock - locked_stock');
            $table->decimal('buffer_percentage', 5, 2)->default(10.00)->after('available_stock')->comment('Percentage to reduce when sending to marketplace');
            $table->timestamp('last_synced_at')->nullable()->after('buffer_percentage');
            $table->integer('last_api_quantity')->nullable()->after('last_synced_at');
            
            // Add indexes
            $table->index(['variation_id', 'marketplace_id'], 'idx_variation_marketplace');
            $table->index('available_stock', 'idx_available_stock');
        });
    }

    public function down()
    {
        Schema::table('marketplace_stock', function (Blueprint $table) {
            $table->dropIndex('idx_variation_marketplace');
            $table->dropIndex('idx_available_stock');
            $table->dropColumn(['locked_stock', 'available_stock', 'buffer_percentage', 'last_synced_at', 'last_api_quantity']);
        });
    }
}
```

**File:** `database/migrations/YYYY_MM_DD_HHMMSS_create_marketplace_stock_locks.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMarketplaceStockLocks extends Migration
{
    public function up()
    {
        Schema::create('marketplace_stock_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_stock_id');
            $table->unsignedBigInteger('variation_id');
            $table->unsignedInteger('marketplace_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_item_id');
            $table->integer('quantity_locked');
            $table->enum('lock_status', ['locked', 'released', 'consumed'])->default('locked');
            $table->timestamp('locked_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('marketplace_stock_id');
            $table->index(['variation_id', 'marketplace_id']);
            $table->index('lock_status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('marketplace_stock_locks');
    }
}
```

**File:** `database/migrations/YYYY_MM_DD_HHMMSS_create_marketplace_stock_history.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMarketplaceStockHistory extends Migration
{
    public function up()
    {
        Schema::create('marketplace_stock_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_stock_id')->nullable();
            $table->unsignedBigInteger('variation_id');
            $table->unsignedInteger('marketplace_id');
            $table->integer('listed_stock_before');
            $table->integer('listed_stock_after');
            $table->integer('locked_stock_before')->default(0);
            $table->integer('locked_stock_after')->default(0);
            $table->integer('available_stock_before')->default(0);
            $table->integer('available_stock_after')->default(0);
            $table->integer('quantity_change');
            $table->enum('change_type', [
                'order_created', 'order_completed', 'order_cancelled', 
                'topup', 'manual', 'reconciliation', 'api_sync', 'lock', 'unlock'
            ]);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('reference_id')->nullable();
            $table->unsignedInteger('admin_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['variation_id', 'marketplace_id']);
            $table->index('created_at');
            $table->index('order_id');
            $table->index('change_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('marketplace_stock_history');
    }
}
```

#### Step 1.2: Create Model Files

**File:** `app/Models/MarketplaceStockLock.php`

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceStockLock extends Model
{
    protected $table = 'marketplace_stock_locks';
    
    protected $fillable = [
        'marketplace_stock_id',
        'variation_id',
        'marketplace_id',
        'order_id',
        'order_item_id',
        'quantity_locked',
        'lock_status',
        'locked_at',
        'released_at',
        'consumed_at',
    ];
    
    protected $casts = [
        'locked_at' => 'datetime',
        'released_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
    
    public function marketplaceStock()
    {
        return $this->belongsTo(MarketplaceStockModel::class, 'marketplace_stock_id');
    }
    
    public function order()
    {
        return $this->belongsTo(Order_model::class, 'order_id');
    }
    
    public function orderItem()
    {
        return $this->belongsTo(Order_item_model::class, 'order_item_id');
    }
}
```

**File:** `app/Models/MarketplaceStockHistory.php`

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceStockHistory extends Model
{
    protected $table = 'marketplace_stock_history';
    
    protected $fillable = [
        'marketplace_stock_id',
        'variation_id',
        'marketplace_id',
        'listed_stock_before',
        'listed_stock_after',
        'locked_stock_before',
        'locked_stock_after',
        'available_stock_before',
        'available_stock_after',
        'quantity_change',
        'change_type',
        'order_id',
        'order_item_id',
        'reference_id',
        'admin_id',
        'notes',
    ];
    
    public function marketplaceStock()
    {
        return $this->belongsTo(MarketplaceStockModel::class, 'marketplace_stock_id');
    }
    
    public function order()
    {
        return $this->belongsTo(Order_model::class, 'order_id');
    }
}
```

#### Step 1.3: Update MarketplaceStockModel

**File:** `app/Models/MarketplaceStockModel.php` (Add relationships and methods)

```php
// Add to existing MarketplaceStockModel class

protected $fillable = [
    // ... existing fields ...
    'locked_stock',
    'available_stock',
    'buffer_percentage',
    'last_synced_at',
    'last_api_quantity',
];

protected $casts = [
    'buffer_percentage' => 'decimal:2',
    'last_synced_at' => 'datetime',
];

public function locks()
{
    return $this->hasMany(MarketplaceStockLock::class, 'marketplace_stock_id')
        ->where('lock_status', 'locked');
}

public function allLocks()
{
    return $this->hasMany(MarketplaceStockLock::class, 'marketplace_stock_id');
}

public function history()
{
    return $this->hasMany(MarketplaceStockHistory::class, 'marketplace_stock_id')
        ->orderBy('created_at', 'desc');
}

/**
 * Calculate available stock with buffer
 */
public function getAvailableStockWithBuffer()
{
    $available = $this->available_stock ?? ($this->listed_stock - $this->locked_stock);
    $buffer = $this->buffer_percentage ?? 10.00;
    return max(0, floor($available * (1 - $buffer / 100)));
}

/**
 * Update available stock (recalculate)
 */
public function updateAvailableStock()
{
    $this->available_stock = max(0, $this->listed_stock - $this->locked_stock);
    $this->save();
}
```

---

### Phase 2: Event System Implementation (Week 1, Days 3-5)

#### Step 2.1: Create Events

**File:** `app/Events/OrderCreated.php`

```php
<?php
namespace App\Events;

use App\Models\Order_model;
use Illuminate\Queue\SerializesModels;

class OrderCreated
{
    use SerializesModels;
    
    public $order;
    public $orderItems;
    
    public function __construct(Order_model $order, $orderItems)
    {
        $this->order = $order;
        $this->orderItems = $orderItems;
    }
}
```

**File:** `app/Events/OrderStatusChanged.php`

```php
<?php
namespace App\Events;

use App\Models\Order_model;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged
{
    use SerializesModels;
    
    public $order;
    public $oldStatus;
    public $newStatus;
    public $orderItems;
    
    public function __construct(Order_model $order, $oldStatus, $newStatus, $orderItems)
    {
        $this->order = $order;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->orderItems = $orderItems;
    }
}
```

#### Step 2.2: Create Event Listeners

**File:** `app/Listeners/LockStockOnOrderCreated.php`

```php
<?php
namespace App\Listeners;

use App\Events\OrderCreated;
use App\Models\MarketplaceStockModel;
use App\Models\MarketplaceStockLock;
use App\Models\MarketplaceStockHistory;
use Illuminate\Support\Facades\Log;

class LockStockOnOrderCreated
{
    public function handle(OrderCreated $event)
    {
        $order = $event->order;
        
        // Only process marketplace orders (order_type_id = 3)
        if ($order->order_type_id != 3) {
            return;
        }
        
        // Only lock stock if order is in pending/processing status (status 1 or 2)
        if (!in_array($order->status, [1, 2])) {
            return;
        }
        
        $marketplaceId = $order->marketplace_id ?? 1;
        
        foreach ($event->orderItems as $orderItem) {
            $variationId = $orderItem->variation_id;
            $quantity = $orderItem->quantity ?? 1;
            
            if (!$variationId || $quantity <= 0) {
                continue;
            }
            
            // Get or create marketplace stock record
            $marketplaceStock = MarketplaceStockModel::firstOrCreate(
                [
                    'variation_id' => $variationId,
                    'marketplace_id' => $marketplaceId
                ],
                [
                    'listed_stock' => 0,
                    'locked_stock' => 0,
                    'available_stock' => 0,
                    'buffer_percentage' => 10.00
                ]
            );
            
            // Check if stock is already locked for this order
            $existingLock = MarketplaceStockLock::where([
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'lock_status' => 'locked'
            ])->first();
            
            if ($existingLock) {
                // Already locked, skip
                continue;
            }
            
            // Record before values
            $listedStockBefore = $marketplaceStock->listed_stock;
            $lockedStockBefore = $marketplaceStock->locked_stock;
            $availableStockBefore = $marketplaceStock->available_stock;
            
            // Lock stock
            $marketplaceStock->locked_stock += $quantity;
            $marketplaceStock->available_stock = max(0, $marketplaceStock->listed_stock - $marketplaceStock->locked_stock);
            $marketplaceStock->save();
            
            // Create lock record
            $lock = MarketplaceStockLock::create([
                'marketplace_stock_id' => $marketplaceStock->id,
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'quantity_locked' => $quantity,
                'lock_status' => 'locked',
                'locked_at' => now(),
            ]);
            
            // Log to history
            MarketplaceStockHistory::create([
                'marketplace_stock_id' => $marketplaceStock->id,
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
                'listed_stock_before' => $listedStockBefore,
                'listed_stock_after' => $marketplaceStock->listed_stock,
                'locked_stock_before' => $lockedStockBefore,
                'locked_stock_after' => $marketplaceStock->locked_stock,
                'available_stock_before' => $availableStockBefore,
                'available_stock_after' => $marketplaceStock->available_stock,
                'quantity_change' => -$quantity,
                'change_type' => 'lock',
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'reference_id' => $order->reference_id,
                'notes' => "Stock locked for order: {$order->reference_id}"
            ]);
            
            Log::info("Stock locked for order", [
                'order_id' => $order->id,
                'order_reference' => $order->reference_id,
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
                'quantity_locked' => $quantity,
                'available_stock_after' => $marketplaceStock->available_stock
            ]);
        }
    }
}
```

**File:** `app/Listeners/ReduceStockOnOrderCompleted.php`

```php
<?php
namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Models\MarketplaceStockModel;
use App\Models\MarketplaceStockLock;
use App\Models\MarketplaceStockHistory;
use App\Http\Controllers\BackMarketAPIController;
use Illuminate\Support\Facades\Log;

class ReduceStockOnOrderCompleted
{
    public function handle(OrderStatusChanged $event)
    {
        $order = $event->order;
        
        // Only process marketplace orders (order_type_id = 3)
        if ($order->order_type_id != 3) {
            return;
        }
        
        // Only process when order status changes to completed (status = 3)
        if ($event->newStatus != 3) {
            return;
        }
        
        $marketplaceId = $order->marketplace_id ?? 1;
        
        foreach ($event->orderItems as $orderItem) {
            $variationId = $orderItem->variation_id;
            $quantity = $orderItem->quantity ?? 1;
            
            if (!$variationId || $quantity <= 0) {
                continue;
            }
            
            // Get marketplace stock record
            $marketplaceStock = MarketplaceStockModel::where([
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId
            ])->first();
            
            if (!$marketplaceStock) {
                Log::warning("Marketplace stock not found for completed order", [
                    'order_id' => $order->id,
                    'variation_id' => $variationId,
                    'marketplace_id' => $marketplaceId
                ]);
                continue;
            }
            
            // Find and consume locks for this order
            $locks = MarketplaceStockLock::where([
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'lock_status' => 'locked'
            ])->get();
            
            $totalLocked = $locks->sum('quantity_locked');
            
            // Record before values
            $listedStockBefore = $marketplaceStock->listed_stock;
            $lockedStockBefore = $marketplaceStock->locked_stock;
            $availableStockBefore = $marketplaceStock->available_stock;
            
            // Reduce listed stock and unlock
            $marketplaceStock->listed_stock = max(0, $marketplaceStock->listed_stock - $quantity);
            $marketplaceStock->locked_stock = max(0, $marketplaceStock->locked_stock - $totalLocked);
            $marketplaceStock->available_stock = max(0, $marketplaceStock->listed_stock - $marketplaceStock->locked_stock);
            $marketplaceStock->save();
            
            // Mark locks as consumed
            foreach ($locks as $lock) {
                $lock->lock_status = 'consumed';
                $lock->consumed_at = now();
                $lock->save();
            }
            
            // Log to history
            MarketplaceStockHistory::create([
                'marketplace_stock_id' => $marketplaceStock->id,
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
                'listed_stock_before' => $listedStockBefore,
                'listed_stock_after' => $marketplaceStock->listed_stock,
                'locked_stock_before' => $lockedStockBefore,
                'locked_stock_after' => $marketplaceStock->locked_stock,
                'available_stock_before' => $availableStockBefore,
                'available_stock_after' => $marketplaceStock->available_stock,
                'quantity_change' => -$quantity,
                'change_type' => 'order_completed',
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'reference_id' => $order->reference_id,
                'notes' => "Stock reduced for completed order: {$order->reference_id}"
            ]);
            
            // Update Back Market API with reduced stock (with buffer)
            $this->updateMarketplaceAPI($marketplaceStock, $variationId);
            
            Log::info("Stock reduced for completed order", [
                'order_id' => $order->id,
                'order_reference' => $order->reference_id,
                'variation_id' => $variationId,
                'marketplace_id' => $marketplaceId,
                'quantity_reduced' => $quantity,
                'listed_stock_after' => $marketplaceStock->listed_stock,
                'available_stock_after' => $marketplaceStock->available_stock
            ]);
        }
    }
    
    private function updateMarketplaceAPI($marketplaceStock, $variationId)
    {
        $variation = \App\Models\Variation_model::find($variationId);
        
        if (!$variation || !$variation->reference_id) {
            return;
        }
        
        // Calculate stock with buffer
        $stockWithBuffer = $marketplaceStock->getAvailableStockWithBuffer();
        
        // Only update if marketplace is Back Market (marketplace_id = 1)
        if ($marketplaceStock->marketplace_id == 1) {
            $bm = new BackMarketAPIController();
            $response = $bm->updateOneListing(
                $variation->reference_id,
                json_encode(['quantity' => $stockWithBuffer])
            );
            
            if ($response && is_object($response) && isset($response->quantity)) {
                $marketplaceStock->last_synced_at = now();
                $marketplaceStock->last_api_quantity = $response->quantity;
                $marketplaceStock->save();
            }
        }
    }
}
```

#### Step 2.3: Register Events in EventServiceProvider

**File:** `app/Providers/EventServiceProvider.php`

```php
// Add to existing EventServiceProvider

protected $listen = [
    // ... existing listeners ...
    
    \App\Events\OrderCreated::class => [
        \App\Listeners\LockStockOnOrderCreated::class,
    ],
    
    \App\Events\OrderStatusChanged::class => [
        \App\Listeners\ReduceStockOnOrderCompleted::class,
    ],
];
```

---

### Phase 3: Integration Points (Week 2, Days 1-3)

#### Step 3.1: Fire Events in Order Sync Commands

**File:** `app/Console/Commands/RefreshOrders.php` (Modify)

```php
// After order is saved and order items are updated
$order->save();
$orderItemModel->updateOrderItemsInDB($orderObj, null, $bm);

// Fire OrderCreated event
event(new \App\Events\OrderCreated($order, $order->order_items));
```

**File:** `app/Jobs/UpdateOrderInDB.php` (Modify)

```php
// After order status is updated
$oldStatus = $order->status;
$order->status = $newStatus;
$order->save();

// Fire OrderStatusChanged event
if ($oldStatus != $newStatus) {
    event(new \App\Events\OrderStatusChanged(
        $order,
        $oldStatus,
        $newStatus,
        $order->order_items
    ));
}
```

#### Step 3.2: Modify Stock Update Methods to Use Buffer

**File:** `app/Http/Controllers/BackMarketAPIController.php` (Modify `updateOneListing`)

```php
public function updateOneListing($listing_id, $request_JSON, $code = null) {
    // Parse request to get quantity
    $requestData = json_decode($request_JSON, true);
    
    // If quantity is provided, apply buffer if needed
    if (isset($requestData['quantity'])) {
        $variation = Variation_model::where('reference_id', $listing_id)->first();
        
        if ($variation) {
            // Get marketplace stock (default to marketplace_id = 1 for Back Market)
            $marketplaceStock = MarketplaceStockModel::where([
                'variation_id' => $variation->id,
                'marketplace_id' => 1
            ])->first();
            
            // If marketplace stock exists and has buffer_percentage, apply buffer
            if ($marketplaceStock && $marketplaceStock->buffer_percentage > 0) {
                $originalQuantity = $requestData['quantity'];
                $bufferPercentage = $marketplaceStock->buffer_percentage;
                $bufferedQuantity = max(0, floor($originalQuantity * (1 - $bufferPercentage / 100)));
                
                // Update request with buffered quantity
                $requestData['quantity'] = $bufferedQuantity;
                $request_JSON = json_encode($requestData);
                
                Log::info("Applied buffer to stock update", [
                    'variation_id' => $variation->id,
                    'listing_id' => $listing_id,
                    'original_quantity' => $originalQuantity,
                    'buffer_percentage' => $bufferPercentage,
                    'buffered_quantity' => $bufferedQuantity
                ]);
            }
        }
    }
    
    $end_point = 'listings/' . $listing_id;
    if($code != null){
        $response = $this->apiPost($end_point, $request_JSON, $code);
    }else{
        $response = $this->apiPost($end_point, $request_JSON);
    }

    return $response;
}
```

**File:** `app/Http/Controllers/ListingController.php` (Modify `add_quantity`)

```php
// After calculating $new_quantity, before sending to API
// Get marketplace stock and apply buffer
$marketplaceStock = MarketplaceStockModel::where([
    'variation_id' => $variation->id,
    'marketplace_id' => 1 // Back Market
])->first();

if ($marketplaceStock && $marketplaceStock->buffer_percentage > 0) {
    $stockWithBuffer = max(0, floor($new_quantity * (1 - $marketplaceStock->buffer_percentage / 100)));
    $response = $bm->updateOneListing($variation->reference_id, json_encode(['quantity' => $stockWithBuffer]));
} else {
    $response = $bm->updateOneListing($variation->reference_id, json_encode(['quantity' => $new_quantity]));
}
```

**File:** `app/Http/Controllers/V2/ListingController.php` (Modify `add_quantity`)

```php
// Same modification as above
```

#### Step 3.3: Per-Marketplace 6-Hour Sync System

**Key Requirement:** Each marketplace should sync independently with its own 6-hour interval, not all at once.

**Solution:** Create a unified sync command that handles each marketplace separately with staggered timing.

**File:** `app/Console/Kernel.php`

```php
// Instead of single command, schedule per-marketplace syncs with staggered timing
// Back Market (ID 1): Every 6 hours starting at 00:00
$schedule->command('marketplace:sync-stock --marketplace=1')
    ->cron('0 */6 * * *'); // Every 6 hours at minute 0

// Marketplace 2: Every 6 hours starting at 02:00 (2 hours offset)
$schedule->command('marketplace:sync-stock --marketplace=2')
    ->cron('0 2,8,14,20 * * *'); // Every 6 hours at 02:00, 08:00, 14:00, 20:00

// Marketplace 3: Every 6 hours starting at 04:00 (4 hours offset)
$schedule->command('marketplace:sync-stock --marketplace=3')
    ->cron('0 4,10,16,22 * * *'); // Every 6 hours at 04:00, 10:00, 16:00, 22:00

// Refurbed (ID 4): Every 6 hours starting at 06:00 (6 hours offset)
$schedule->command('marketplace:sync-stock --marketplace=4')
    ->cron('0 6,12,18,0 * * *'); // Every 6 hours at 06:00, 12:00, 18:00, 00:00

// Or use a more flexible approach with a single command that handles all marketplaces
$schedule->command('marketplace:sync-stock-all')
    ->everySixHours()
    ->withoutOverlapping();
```

**File:** `app/Console/Commands/SyncMarketplaceStock.php` (NEW - Unified Sync Command)

```php
<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Marketplace_model;
use App\Models\MarketplaceStockModel;
use App\Models\Variation_model;
use App\Models\Listing_model;
use App\Http\Controllers\BackMarketAPIController;
use App\Http\Controllers\RefurbedAPIController;
use Illuminate\Support\Facades\Log;

class SyncMarketplaceStock extends Command
{
    protected $signature = 'marketplace:sync-stock 
                            {--marketplace= : Specific marketplace ID to sync}
                            {--force : Force sync even if last sync was less than 6 hours ago}';
    
    protected $description = 'Sync stock from marketplace APIs (6-hour interval per marketplace)';
    
    // Sync interval in hours (configurable per marketplace)
    private $syncIntervals = [
        1 => 6, // Back Market: 6 hours
        2 => 6, // Marketplace 2: 6 hours
        3 => 6, // Marketplace 3: 6 hours
        4 => 6, // Refurbed: 6 hours
    ];
    
    public function handle()
    {
        $marketplaceId = $this->option('marketplace');
        $force = $this->option('force');
        
        if ($marketplaceId) {
            // Sync specific marketplace
            $this->syncMarketplace((int)$marketplaceId, $force);
        } else {
            // Sync all marketplaces that need syncing
            $this->syncAllMarketplaces($force);
        }
    }
    
    private function syncAllMarketplaces($force = false)
    {
        $marketplaces = Marketplace_model::where('status', 1)->get();
        
        foreach ($marketplaces as $marketplace) {
            $this->info("Checking marketplace: {$marketplace->name} (ID: {$marketplace->id})");
            $this->syncMarketplace($marketplace->id, $force);
        }
    }
    
    private function syncMarketplace($marketplaceId, $force = false)
    {
        $marketplace = Marketplace_model::find($marketplaceId);
        
        if (!$marketplace) {
            $this->error("Marketplace ID {$marketplaceId} not found");
            return;
        }
        
        $this->info("Syncing marketplace: {$marketplace->name} (ID: {$marketplaceId})");
        
        // Get sync interval for this marketplace
        $syncInterval = $this->syncIntervals[$marketplaceId] ?? 6;
        
        // Get all marketplace stocks that need syncing
        $marketplaceStocks = MarketplaceStockModel::where('marketplace_id', $marketplaceId)
            ->whereHas('variation', function($q) {
                $q->whereNotNull('reference_id')
                  ->orWhereNotNull('sku');
            })
            ->get();
        
        $syncedCount = 0;
        $skippedCount = 0;
        
        foreach ($marketplaceStocks as $marketplaceStock) {
            // Check if sync is needed
            $needsSync = $force || 
                        !$marketplaceStock->last_synced_at || 
                        $marketplaceStock->last_synced_at->diffInHours(now()) >= $syncInterval;
            
            if (!$needsSync) {
                $skippedCount++;
                continue;
            }
            
            // Sync based on marketplace type
            try {
                switch ($marketplaceId) {
                    case 1: // Back Market
                        $this->syncBackMarket($marketplaceStock);
                        break;
                    case 4: // Refurbed
                        $this->syncRefurbed($marketplaceStock);
                        break;
                    default:
                        $this->warn("No sync handler for marketplace ID {$marketplaceId}");
                        continue 2;
                }
                
                $syncedCount++;
            } catch (\Exception $e) {
                Log::error("Error syncing marketplace stock", [
                    'marketplace_id' => $marketplaceId,
                    'marketplace_stock_id' => $marketplaceStock->id,
                    'variation_id' => $marketplaceStock->variation_id,
                    'error' => $e->getMessage()
                ]);
                $this->error("Error syncing variation {$marketplaceStock->variation_id}: {$e->getMessage()}");
            }
        }
        
        $this->info("Sync complete for {$marketplace->name}: {$syncedCount} synced, {$skippedCount} skipped");
    }
    
    private function syncBackMarket($marketplaceStock)
    {
        $variation = $marketplaceStock->variation;
        
        if (!$variation || !$variation->reference_id) {
            return;
        }
        
        $bm = new BackMarketAPIController();
        $apiListing = $bm->getOneListing($variation->reference_id);
        
        if (!$apiListing || !isset($apiListing->quantity)) {
            Log::warning("Back Market API returned invalid response", [
                'variation_id' => $variation->id,
                'reference_id' => $variation->reference_id
            ]);
            return;
        }
        
        $apiQuantity = (int)$apiListing->quantity;
        
        // Update marketplace stock (reconciliation)
        $oldListedStock = $marketplaceStock->listed_stock;
        $marketplaceStock->listed_stock = $apiQuantity;
        $marketplaceStock->available_stock = max(0, $marketplaceStock->listed_stock - $marketplaceStock->locked_stock);
        $marketplaceStock->last_synced_at = now();
        $marketplaceStock->last_api_quantity = $apiQuantity;
        $marketplaceStock->save();
        
        // Log to history if there's a discrepancy
        if ($oldListedStock != $apiQuantity) {
            \App\Models\MarketplaceStockHistory::create([
                'marketplace_stock_id' => $marketplaceStock->id,
                'variation_id' => $variation->id,
                'marketplace_id' => $marketplaceStock->marketplace_id,
                'listed_stock_before' => $oldListedStock,
                'listed_stock_after' => $apiQuantity,
                'locked_stock_before' => $marketplaceStock->locked_stock,
                'locked_stock_after' => $marketplaceStock->locked_stock,
                'available_stock_before' => max(0, $oldListedStock - $marketplaceStock->locked_stock),
                'available_stock_after' => $marketplaceStock->available_stock,
                'quantity_change' => $apiQuantity - $oldListedStock,
                'change_type' => 'reconciliation',
                'notes' => "Reconciliation sync: Local={$oldListedStock}, API={$apiQuantity}"
            ]);
        }
        
        // Update variation.listed_stock for backward compatibility (only if this is the primary marketplace)
        if ($marketplaceStock->marketplace_id == 1) {
            $variation->listed_stock = $apiQuantity;
            $variation->save();
        }
    }
    
    private function syncRefurbed($marketplaceStock)
    {
        $variation = $marketplaceStock->variation;
        
        if (!$variation || !$variation->sku) {
            return;
        }
        
        $refurbed = new RefurbedAPIController();
        
        try {
            // Get offer by SKU
            $offers = $refurbed->getAllOffers(['sku' => $variation->sku], [], 1);
            
            if (empty($offers['offers'])) {
                Log::warning("Refurbed offer not found for SKU", [
                    'variation_id' => $variation->id,
                    'sku' => $variation->sku
                ]);
                return;
            }
            
            $offer = $offers['offers'][0];
            $apiQuantity = (int)($offer['stock'] ?? $offer['quantity'] ?? 0);
            
            // Update marketplace stock (reconciliation)
            $oldListedStock = $marketplaceStock->listed_stock;
            $marketplaceStock->listed_stock = $apiQuantity;
            $marketplaceStock->available_stock = max(0, $marketplaceStock->listed_stock - $marketplaceStock->locked_stock);
            $marketplaceStock->last_synced_at = now();
            $marketplaceStock->last_api_quantity = $apiQuantity;
            $marketplaceStock->save();
            
            // Log to history if there's a discrepancy
            if ($oldListedStock != $apiQuantity) {
                \App\Models\MarketplaceStockHistory::create([
                    'marketplace_stock_id' => $marketplaceStock->id,
                    'variation_id' => $variation->id,
                    'marketplace_id' => $marketplaceStock->marketplace_id,
                    'listed_stock_before' => $oldListedStock,
                    'listed_stock_after' => $apiQuantity,
                    'locked_stock_before' => $marketplaceStock->locked_stock,
                    'locked_stock_after' => $marketplaceStock->locked_stock,
                    'available_stock_before' => max(0, $oldListedStock - $marketplaceStock->locked_stock),
                    'available_stock_after' => $marketplaceStock->available_stock,
                    'quantity_change' => $apiQuantity - $oldListedStock,
                    'change_type' => 'reconciliation',
                    'notes' => "Reconciliation sync: Local={$oldListedStock}, API={$apiQuantity}"
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error syncing Refurbed stock", [
                'variation_id' => $variation->id,
                'sku' => $variation->sku,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
```

**File:** `app/Console/Commands/FunctionsThirty.php` (MODIFY - Keep for backward compatibility but use new sync command)

```php
// Modify handle() method to use new sync command
public function handle()
{
    // Use new unified sync command instead
    $this->call('marketplace:sync-stock', [
        '--marketplace' => 1, // Back Market
        '--force' => false
    ]);
    
    // Keep other functionality (if any) that's not stock-related
    return 0;
}
```

**Alternative: Add sync interval configuration to marketplace table**

**File:** `database/migrations/YYYY_MM_DD_HHMMSS_add_sync_config_to_marketplace.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSyncConfigToMarketplace extends Migration
{
    public function up()
    {
        Schema::table('marketplace', function (Blueprint $table) {
            $table->integer('sync_interval_hours')->default(6)->after('status')
                ->comment('Hours between syncs (default 6)');
            $table->time('sync_start_time')->nullable()->after('sync_interval_hours')
                ->comment('Preferred start time for sync (e.g., 00:00, 02:00)');
            $table->boolean('sync_enabled')->default(true)->after('sync_start_time');
        });
    }

    public function down()
    {
        Schema::table('marketplace', function (Blueprint $table) {
            $table->dropColumn(['sync_interval_hours', 'sync_start_time', 'sync_enabled']);
        });
    }
}
```

**Update SyncMarketplaceStock command to use marketplace config:**

```php
// In syncMarketplace() method, replace hardcoded intervals:
$syncInterval = $marketplace->sync_interval_hours ?? 6;
```

---

### Phase 4: Remove Direct Stock Reduction (Week 2, Days 4-5)

#### Step 4.1: Modify Order Item Model

**File:** `app/Models/Order_item_model.php` (Modify `updateOrderItemsInDB`)

```php
// REMOVE or COMMENT OUT this line (around line 208):
// $variation->listed_stock = ($variation->listed_stock ?? 0) - ($itemObj->quantity ?? 0);

// Stock reduction will now happen via events when order status changes to completed
```

---

### Phase 5: Testing & Validation (Week 3)

#### Step 5.1: Create Test Commands

**File:** `app/Console/Commands/TestStockLocking.php`

```php
<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order_model;
use App\Events\OrderCreated;

class TestStockLocking extends Command
{
    protected $signature = 'test:stock-lock {order_id}';
    
    public function handle()
    {
        $orderId = $this->argument('order_id');
        $order = Order_model::with('order_items')->find($orderId);
        
        if (!$order) {
            $this->error("Order not found");
            return;
        }
        
        $this->info("Firing OrderCreated event for order: {$order->reference_id}");
        event(new OrderCreated($order, $order->order_items));
        $this->info("Event fired successfully");
    }
}
```

#### Step 5.2: Create Validation Reports

**File:** `app/Console/Commands/ValidateStockSync.php`

```php
<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MarketplaceStockModel;
use App\Models\Variation_model;

class ValidateStockSync extends Command
{
    protected $signature = 'stock:validate';
    
    public function handle()
    {
        $this->info("Validating stock sync...");
        
        $issues = [];
        
        // Check for negative available stock
        $negativeStock = MarketplaceStockModel::whereRaw('available_stock < 0')->get();
        if ($negativeStock->count() > 0) {
            $issues[] = "Found {$negativeStock->count()} records with negative available stock";
        }
        
        // Check for locked stock > listed stock
        $invalidLocks = MarketplaceStockModel::whereRaw('locked_stock > listed_stock')->get();
        if ($invalidLocks->count() > 0) {
            $issues[] = "Found {$invalidLocks->count()} records with locked stock > listed stock";
        }
        
        if (empty($issues)) {
            $this->info("✓ All validations passed");
        } else {
            foreach ($issues as $issue) {
                $this->error($issue);
            }
        }
    }
}
```

---

## Files to Modify Summary

### Database Migrations (NEW)
1. `database/migrations/YYYY_MM_DD_HHMMSS_add_stock_tracking_to_marketplace_stock.php`
2. `database/migrations/YYYY_MM_DD_HHMMSS_create_marketplace_stock_locks.php`
3. `database/migrations/YYYY_MM_DD_HHMMSS_create_marketplace_stock_history.php`

### Models (NEW)
4. `app/Models/MarketplaceStockLock.php`
5. `app/Models/MarketplaceStockHistory.php`

### Models (MODIFY)
6. `app/Models/MarketplaceStockModel.php` - Add new fields, relationships, methods

### Events (NEW)
7. `app/Events/OrderCreated.php`
8. `app/Events/OrderStatusChanged.php`

### Listeners (NEW)
9. `app/Listeners/LockStockOnOrderCreated.php`
10. `app/Listeners/ReduceStockOnOrderCompleted.php`

### Controllers (MODIFY)
11. `app/Http/Controllers/BackMarketAPIController.php` - Modify `updateOneListing` to apply buffer
12. `app/Http/Controllers/ListingController.php` - Modify `add_quantity` to use buffer
13. `app/Http/Controllers/V2/ListingController.php` - Modify `add_quantity` to use buffer

### Commands (MODIFY)
14. `app/Console/Commands/RefreshOrders.php` - Fire OrderCreated event
15. `app/Console/Commands/FunctionsThirty.php` - Change to 6 hours, use marketplace_stock
16. `app/Console/Kernel.php` - Change schedule to everySixHours

### Jobs (MODIFY)
17. `app/Jobs/UpdateOrderInDB.php` - Fire OrderStatusChanged event

### Models (MODIFY)
18. `app/Models/Order_item_model.php` - Remove direct stock reduction

### Providers (MODIFY)
19. `app/Providers/EventServiceProvider.php` - Register events

### Test Commands (NEW)
20. `app/Console/Commands/TestStockLocking.php`
21. `app/Console/Commands/ValidateStockSync.php`

---

## Implementation Timeline

### Week 1: Foundation
- **Days 1-2:** Database migrations and models
- **Days 3-5:** Event system implementation

### Week 2: Integration
- **Days 1-3:** Integrate events with order processing
- **Days 4-5:** Modify stock update methods to use buffer

### Week 3: Testing & Refinement
- **Days 1-3:** Testing and bug fixes
- **Days 4-5:** Validation and documentation

---

## Key Changes Summary

### 1. Stock Locking Mechanism
- ✅ Lock stock when order is created (status 1 or 2)
- ✅ Track locks in `marketplace_stock_locks` table
- ✅ Don't update marketplace API until order is completed

### 2. Stock Reduction on Order Completion
- ✅ Reduce stock when order status changes to 3 (completed)
- ✅ Consume locks when order is completed
- ✅ Update marketplace API with reduced stock (with buffer)

### 3. Buffer Percentage
- ✅ Apply 5-10% buffer when sending stock to marketplaces
- ✅ Configurable per marketplace via `buffer_percentage` field
- ✅ Can use stock formula for dynamic buffer calculation

### 4. Sync Frequency
- ✅ Change from hourly to 6-hourly sync
- ✅ Only sync if last sync was more than 6 hours ago
- ✅ Maintain local stock records (don't pull from API on every page load)

### 5. Event-Driven Updates
- ✅ Update stock based on order events (not polling)
- ✅ Complete audit trail in `marketplace_stock_history`
- ✅ Real-time stock tracking

---

## Testing Checklist

- [ ] Create test order and verify stock is locked
- [ ] Complete test order and verify stock is reduced
- [ ] Verify buffer is applied when sending to Back Market
- [ ] Verify 6-hour sync works correctly
- [ ] Test order cancellation (should release locks)
- [ ] Test multiple orders for same variation
- [ ] Verify stock history is recorded correctly
- [ ] Test with different buffer percentages
- [ ] Verify no negative stock values
- [ ] Test reconciliation after 6 hours

---

## Risk Mitigation

### Risk 1: Event Not Fired
**Mitigation:** Add logging and monitoring, reconciliation every 6 hours

### Risk 2: Stock Mismatch
**Mitigation:** Daily reconciliation, validation commands

### Risk 3: Buffer Too High/Low
**Mitigation:** Make buffer configurable, add admin interface

### Risk 4: Order Status Changes
**Mitigation:** Handle all status transitions, release locks on cancellation

---

## Success Metrics

- ✅ Stock updates based on order events (not hourly polling)
- ✅ 6-hour sync interval (reduced from hourly)
- ✅ Buffer applied to all marketplace stock updates
- ✅ No negative stock values
- ✅ Complete audit trail
- ✅ Reduced API calls by 75%+

---

## Per-Marketplace Sync Strategy (Multi-Marketplace Handling)

### Problem Statement

With **4+ marketplaces** (and potentially more in the future), we need to:
1. Sync each marketplace **independently** with its own 6-hour interval
2. **Stagger sync times** to avoid rate limits and server load
3. Track `last_synced_at` **per marketplace** (not globally)
4. Allow **different sync intervals** per marketplace if needed

### Solution Architecture

```
┌─────────────────────────────────────────────────────────────┐
│              Per-Marketplace Sync Tracking                   │
│                                                               │
│  marketplace_stock table:                                    │
│  - variation_id + marketplace_id = unique record            │
│  - last_synced_at = tracked PER marketplace                 │
│  - Each marketplace syncs independently                     │
└───────────────────────┬─────────────────────────────────────┘
                        │
        ┌───────────────┼───────────────┐
        │               │               │
        ▼               ▼               ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│ Back Market  │ │ Marketplace 2│ │ Marketplace 3│
│ (ID: 1)      │ │ (ID: 2)       │ │ (ID: 3)      │
│              │ │              │ │              │
│ Sync: 00:00  │ │ Sync: 02:00  │ │ Sync: 04:00  │
│ Every 6 hrs  │ │ Every 6 hrs  │ │ Every 6 hrs  │
└──────────────┘ └──────────────┘ └──────────────┘
        │               │               │
        └───────────────┼───────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│              Refurbed (ID: 4)                                │
│              Sync: 06:00                                     │
│              Every 6 hrs                                     │
└─────────────────────────────────────────────────────────────┘
```

### Key Features

#### 1. Independent Sync Tracking

**Each `marketplace_stock` record has its own `last_synced_at`:**
```sql
SELECT 
    variation_id,
    marketplace_id,
    listed_stock,
    last_synced_at,
    TIMESTAMPDIFF(HOUR, last_synced_at, NOW()) as hours_since_sync
FROM marketplace_stock
WHERE marketplace_id = 1  -- Back Market
  AND (last_synced_at IS NULL 
       OR TIMESTAMPDIFF(HOUR, last_synced_at, NOW()) >= 6);
```

#### 2. Staggered Sync Schedule

**Prevents all marketplaces syncing at once:**

| Marketplace | ID | Sync Times | Offset |
|-------------|----|-----------|--------|
| Back Market | 1  | 00:00, 06:00, 12:00, 18:00 | 0 hours |
| Marketplace 2 | 2  | 02:00, 08:00, 14:00, 20:00 | 2 hours |
| Marketplace 3 | 3  | 04:00, 10:00, 16:00, 22:00 | 4 hours |
| Refurbed | 4  | 06:00, 12:00, 18:00, 00:00 | 6 hours |

**Benefits:**
- ✅ No API rate limit conflicts
- ✅ Reduced server load
- ✅ Better error isolation
- ✅ Easier monitoring per marketplace

#### 3. Flexible Sync Intervals

**Can configure different intervals per marketplace:**

```php
// In marketplace table
sync_interval_hours = 6  // Default: 6 hours
sync_interval_hours = 4  // Fast marketplace: 4 hours
sync_interval_hours = 12 // Slow marketplace: 12 hours
```

#### 4. Per-Marketplace API Handlers

**Each marketplace has its own sync method:**

```php
switch ($marketplaceId) {
    case 1: // Back Market
        $this->syncBackMarket($marketplaceStock);
        break;
    case 4: // Refurbed
        $this->syncRefurbed($marketplaceStock);
        break;
    // Add more marketplaces as needed
}
```

### Sync Command Usage

#### Sync All Marketplaces
```bash
php artisan marketplace:sync-stock-all
```

#### Sync Specific Marketplace
```bash
php artisan marketplace:sync-stock --marketplace=1  # Back Market
php artisan marketplace:sync-stock --marketplace=4  # Refurbed
```

#### Force Sync (Ignore 6-hour rule)
```bash
php artisan marketplace:sync-stock --marketplace=1 --force
```

### Database Structure

**Each variation can have multiple marketplace_stock records:**

```
variation_id: 123
├── marketplace_id: 1 (Back Market)
│   ├── listed_stock: 100
│   ├── locked_stock: 5
│   ├── available_stock: 95
│   └── last_synced_at: 2024-01-15 06:00:00
│
├── marketplace_id: 2 (Marketplace 2)
│   ├── listed_stock: 50
│   ├── locked_stock: 2
│   ├── available_stock: 48
│   └── last_synced_at: 2024-01-15 08:00:00
│
├── marketplace_id: 3 (Marketplace 3)
│   ├── listed_stock: 30
│   ├── locked_stock: 1
│   ├── available_stock: 29
│   └── last_synced_at: 2024-01-15 10:00:00
│
└── marketplace_id: 4 (Refurbed)
    ├── listed_stock: 75
    ├── locked_stock: 3
    ├── available_stock: 72
    └── last_synced_at: 2024-01-15 12:00:00
```

### Adding New Marketplaces

**To add a new marketplace:**

1. **Add to marketplace table:**
```sql
INSERT INTO marketplace (name, sync_interval_hours, sync_start_time, sync_enabled)
VALUES ('New Marketplace', 6, '08:00:00', 1);
```

2. **Add sync handler in SyncMarketplaceStock command:**
```php
case 5: // New Marketplace
    $this->syncNewMarketplace($marketplaceStock);
    break;
```

3. **Add to schedule in Kernel.php:**
```php
$schedule->command('marketplace:sync-stock --marketplace=5')
    ->cron('0 8,14,20,2 * * *'); // Every 6 hours at 08:00, 14:00, 20:00, 02:00
```

### Monitoring & Logging

**Check sync status per marketplace:**
```sql
SELECT 
    m.name as marketplace_name,
    COUNT(ms.id) as total_records,
    COUNT(CASE WHEN ms.last_synced_at IS NULL THEN 1 END) as never_synced,
    COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, ms.last_synced_at, NOW()) >= 6 THEN 1 END) as needs_sync,
    AVG(TIMESTAMPDIFF(HOUR, ms.last_synced_at, NOW())) as avg_hours_since_sync
FROM marketplace m
LEFT JOIN marketplace_stock ms ON m.id = ms.marketplace_id
WHERE m.sync_enabled = 1
GROUP BY m.id, m.name;
```

### Benefits of This Approach

1. ✅ **Scalable:** Easy to add new marketplaces
2. ✅ **Independent:** Each marketplace syncs on its own schedule
3. ✅ **Flexible:** Different sync intervals per marketplace
4. ✅ **Efficient:** Only syncs when needed (6-hour check)
5. ✅ **Isolated:** Errors in one marketplace don't affect others
6. ✅ **Trackable:** Complete audit trail per marketplace

---

**Document Version:** 1.1  
**Created:** [Current Date]  
**Updated:** [Current Date] - Added Per-Marketplace Sync Strategy  
**Status:** Ready for Implementation  
**Estimated Timeline:** 3 weeks

