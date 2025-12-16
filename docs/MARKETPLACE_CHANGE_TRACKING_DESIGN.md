# Marketplace Listing Change Tracking - Database Design

## Overview
This document outlines the database structure to track changes in marketplace listing data, specifically:
- **Min Handler** (marketplace level)
- **Price Handler** (marketplace level)
- **BuyBox** (listing level)
- **Min Price** (listing level)
- **Price** (listing level)

## Database Structure

### Option 1: Unified History Table (Recommended)

A single comprehensive history table that tracks all changes at both marketplace and listing levels.

#### Table: `listing_marketplace_history`

```sql
CREATE TABLE `listing_marketplace_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `variation_id` INT UNSIGNED NOT NULL,
  `marketplace_id` INT UNSIGNED NOT NULL,
  `listing_id` INT UNSIGNED NULL COMMENT 'NULL for marketplace-level changes, set for listing-level changes',
  `country_id` INT UNSIGNED NULL COMMENT 'For listing-level changes',
  
  -- Tracked Fields (Current Values)
  `min_handler` DECIMAL(10,2) NULL COMMENT 'Min handler value (marketplace level)',
  `price_handler` DECIMAL(10,2) NULL COMMENT 'Price handler value (marketplace level)',
  `buybox` TINYINT(1) NULL COMMENT 'BuyBox status: 0=No, 1=Yes (listing level)',
  `min_price` DECIMAL(10,2) NULL COMMENT 'Min price (listing level)',
  `price` DECIMAL(10,2) NULL COMMENT 'Price (listing level)',
  
  -- Previous Values (for comparison)
  `previous_min_handler` DECIMAL(10,2) NULL,
  `previous_price_handler` DECIMAL(10,2) NULL,
  `previous_buybox` TINYINT(1) NULL,
  `previous_min_price` DECIMAL(10,2) NULL,
  `previous_price` DECIMAL(10,2) NULL,
  
  -- Change Tracking
  `changed_fields` JSON NULL COMMENT 'Array of field names that changed: ["min_handler", "price"]',
  `change_type` ENUM('marketplace', 'listing', 'bulk') NOT NULL DEFAULT 'listing',
  `change_reason` VARCHAR(255) NULL COMMENT 'Optional reason for change',
  
  -- Metadata
  `admin_id` INT UNSIGNED NULL COMMENT 'Who made the change',
  `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  
  PRIMARY KEY (`id`),
  INDEX `idx_variation_marketplace` (`variation_id`, `marketplace_id`),
  INDEX `idx_listing` (`listing_id`),
  INDEX `idx_changed_at` (`changed_at`),
  INDEX `idx_admin` (`admin_id`),
  INDEX `idx_variation_marketplace_date` (`variation_id`, `marketplace_id`, `changed_at`),
  
  FOREIGN KEY (`variation_id`) REFERENCES `variation`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`marketplace_id`) REFERENCES `marketplace`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`listing_id`) REFERENCES `listings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `admin`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Advantages:**
- Single table for all history
- Easy to query all changes for a variation+marketplace
- Can show full snapshots at each point in time
- JSON field for flexible change tracking
- Supports both marketplace-level and listing-level changes

---

### Option 2: Separate Current State + History Tables

#### Table 1: `listing_marketplace_state` (Current State)

```sql
CREATE TABLE `listing_marketplace_state` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `variation_id` INT UNSIGNED NOT NULL,
  `marketplace_id` INT UNSIGNED NOT NULL,
  `listing_id` INT UNSIGNED NULL,
  `country_id` INT UNSIGNED NULL,
  
  -- Current Values
  `min_handler` DECIMAL(10,2) NULL,
  `price_handler` DECIMAL(10,2) NULL,
  `buybox` TINYINT(1) NULL,
  `min_price` DECIMAL(10,2) NULL,
  `price` DECIMAL(10,2) NULL,
  
  -- Metadata
  `last_updated_by` INT UNSIGNED NULL,
  `last_updated_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_listing_state` (`variation_id`, `marketplace_id`, `listing_id`, `country_id`),
  INDEX `idx_variation_marketplace` (`variation_id`, `marketplace_id`),
  
  FOREIGN KEY (`variation_id`) REFERENCES `variation`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`marketplace_id`) REFERENCES `marketplace`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`listing_id`) REFERENCES `listings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table 2: `listing_marketplace_history` (History Log)

```sql
CREATE TABLE `listing_marketplace_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `state_id` BIGINT UNSIGNED NULL COMMENT 'Reference to listing_marketplace_state',
  `variation_id` INT UNSIGNED NOT NULL,
  `marketplace_id` INT UNSIGNED NOT NULL,
  `listing_id` INT UNSIGNED NULL,
  `country_id` INT UNSIGNED NULL,
  
  -- Field that changed
  `field_name` VARCHAR(50) NOT NULL COMMENT 'min_handler, price_handler, buybox, min_price, price',
  `old_value` TEXT NULL,
  `new_value` TEXT NULL,
  
  -- Change metadata
  `change_type` ENUM('marketplace', 'listing', 'bulk', 'auto') NOT NULL,
  `change_reason` VARCHAR(255) NULL,
  `admin_id` INT UNSIGNED NULL,
  `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) NULL,
  
  PRIMARY KEY (`id`),
  INDEX `idx_state` (`state_id`),
  INDEX `idx_variation_marketplace` (`variation_id`, `marketplace_id`),
  INDEX `idx_listing` (`listing_id`),
  INDEX `idx_field` (`field_name`),
  INDEX `idx_changed_at` (`changed_at`),
  INDEX `idx_variation_marketplace_date` (`variation_id`, `marketplace_id`, `changed_at`),
  
  FOREIGN KEY (`state_id`) REFERENCES `listing_marketplace_state`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`variation_id`) REFERENCES `variation`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`marketplace_id`) REFERENCES `marketplace`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`listing_id`) REFERENCES `listings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `admin`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Advantages:**
- Clear separation of current state vs history
- Efficient queries for current values
- Granular tracking of individual field changes
- Can track multiple field changes in one transaction

---

## Recommended Approach: **Option 1 (Unified History Table)**

### Why Option 1?

1. **Simpler queries**: One table to query for history
2. **Full snapshots**: Each record shows complete state at that moment
3. **Easier reporting**: Can easily show "what changed between date X and Y"
4. **Less complexity**: No need to maintain separate current state table
5. **Flexible**: JSON field allows tracking any combination of changes

### Implementation Strategy

#### 1. Create Migration

```php
// database/migrations/YYYY_MM_DD_HHMMSS_create_listing_marketplace_history_table.php

Schema::create('listing_marketplace_history', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('variation_id');
    $table->unsignedInteger('marketplace_id');
    $table->unsignedBigInteger('listing_id')->nullable();
    $table->unsignedInteger('country_id')->nullable();
    
    // Current values
    $table->decimal('min_handler', 10, 2)->nullable();
    $table->decimal('price_handler', 10, 2)->nullable();
    $table->boolean('buybox')->nullable();
    $table->decimal('min_price', 10, 2)->nullable();
    $table->decimal('price', 10, 2)->nullable();
    
    // Previous values
    $table->decimal('previous_min_handler', 10, 2)->nullable();
    $table->decimal('previous_price_handler', 10, 2)->nullable();
    $table->boolean('previous_buybox')->nullable();
    $table->decimal('previous_min_price', 10, 2)->nullable();
    $table->decimal('previous_price', 10, 2)->nullable();
    
    // Change tracking
    $table->json('changed_fields')->nullable();
    $table->enum('change_type', ['marketplace', 'listing', 'bulk', 'auto'])->default('listing');
    $table->string('change_reason', 255)->nullable();
    
    // Metadata
    $table->unsignedInteger('admin_id')->nullable();
    $table->timestamp('changed_at')->useCurrent();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    
    $table->index(['variation_id', 'marketplace_id']);
    $table->index('listing_id');
    $table->index('changed_at');
    $table->index(['variation_id', 'marketplace_id', 'changed_at']);
    
    $table->foreign('variation_id')->references('id')->on('variation')->onDelete('cascade');
    $table->foreign('marketplace_id')->references('id')->on('marketplace')->onDelete('cascade');
    $table->foreign('listing_id')->references('id')->on('listings')->onDelete('cascade');
    $table->foreign('admin_id')->references('id')->on('admin')->onDelete('set null');
});
```

#### 2. Create Model

```php
// app/Models/ListingMarketplaceHistory.php

class ListingMarketplaceHistory extends Model
{
    protected $table = 'listing_marketplace_history';
    
    protected $fillable = [
        'variation_id',
        'marketplace_id',
        'listing_id',
        'country_id',
        'min_handler',
        'price_handler',
        'buybox',
        'min_price',
        'price',
        'previous_min_handler',
        'previous_price_handler',
        'previous_buybox',
        'previous_min_price',
        'previous_price',
        'changed_fields',
        'change_type',
        'change_reason',
        'admin_id',
        'ip_address',
        'user_agent',
    ];
    
    protected $casts = [
        'min_handler' => 'decimal:2',
        'price_handler' => 'decimal:2',
        'buybox' => 'boolean',
        'min_price' => 'decimal:2',
        'price' => 'decimal:2',
        'previous_min_handler' => 'decimal:2',
        'previous_price_handler' => 'decimal:2',
        'previous_buybox' => 'boolean',
        'previous_min_price' => 'decimal:2',
        'previous_price' => 'decimal:2',
        'changed_fields' => 'array',
        'changed_at' => 'datetime',
    ];
    
    public function variation()
    {
        return $this->belongsTo(Variation_model::class, 'variation_id');
    }
    
    public function marketplace()
    {
        return $this->belongsTo(Marketplace_model::class, 'marketplace_id');
    }
    
    public function listing()
    {
        return $this->belongsTo(Listing_model::class, 'listing_id');
    }
    
    public function admin()
    {
        return $this->belongsTo(Admin_model::class, 'admin_id');
    }
}
```

#### 3. Helper Service for Logging Changes

```php
// app/Services/Marketplace/ListingChangeTracker.php

class ListingChangeTracker
{
    public static function logChange(
        $variationId,
        $marketplaceId,
        $data = [],
        $changeType = 'listing',
        $listingId = null,
        $countryId = null,
        $reason = null
    ) {
        $changedFields = [];
        $previousValues = [];
        $currentValues = [];
        
        $fields = ['min_handler', 'price_handler', 'buybox', 'min_price', 'price'];
        
        // Get previous values (latest record for this variation+marketplace+listing)
        $previous = self::getPreviousState($variationId, $marketplaceId, $listingId);
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $currentValues[$field] = $data[$field];
                $previousValues['previous_' . $field] = $previous[$field] ?? null;
                
                // Check if value actually changed
                if (!isset($previous[$field]) || $previous[$field] != $data[$field]) {
                    $changedFields[] = $field;
                }
            }
        }
        
        // Only log if something actually changed
        if (empty($changedFields)) {
            return null;
        }
        
        return ListingMarketplaceHistory::create([
            'variation_id' => $variationId,
            'marketplace_id' => $marketplaceId,
            'listing_id' => $listingId,
            'country_id' => $countryId,
            ...$currentValues,
            ...$previousValues,
            'changed_fields' => $changedFields,
            'change_type' => $changeType,
            'change_reason' => $reason,
            'admin_id' => session('user_id'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
    
    private static function getPreviousState($variationId, $marketplaceId, $listingId = null)
    {
        $query = ListingMarketplaceHistory::where('variation_id', $variationId)
            ->where('marketplace_id', $marketplaceId)
            ->orderBy('changed_at', 'desc');
            
        if ($listingId) {
            $query->where('listing_id', $listingId);
        } else {
            $query->whereNull('listing_id');
        }
        
        $previous = $query->first();
        
        if (!$previous) {
            return [];
        }
        
        return [
            'min_handler' => $previous->min_handler,
            'price_handler' => $previous->price_handler,
            'buybox' => $previous->buybox,
            'min_price' => $previous->min_price,
            'price' => $previous->price,
        ];
    }
}
```

## Usage Examples

### Logging Marketplace-Level Changes (Handlers)

```php
// When changing min_handler and price_handler for all listings in a marketplace
ListingChangeTracker::logChange(
    variationId: $variationId,
    marketplaceId: $marketplaceId,
    data: [
        'min_handler' => 540.00,
        'price_handler' => 508.00,
    ],
    changeType: 'marketplace',
    reason: 'Bulk update from marketplace bar'
);
```

### Logging Listing-Level Changes (Price/BuyBox)

```php
// When updating a specific listing's price
ListingChangeTracker::logChange(
    variationId: $variationId,
    marketplaceId: $marketplaceId,
    listingId: $listingId,
    countryId: $countryId,
    data: [
        'min_price' => 556.79,
        'price' => 523.20,
        'buybox' => 1,
    ],
    changeType: 'listing',
    reason: 'Price handler auto-update'
);
```

## Querying History for Reports

### Get All Changes for a Variation + Marketplace

```php
$history = ListingMarketplaceHistory::where('variation_id', $variationId)
    ->where('marketplace_id', $marketplaceId)
    ->orderBy('changed_at', 'desc')
    ->get();
```

### Get Changes for a Specific Date Range

```php
$history = ListingMarketplaceHistory::where('variation_id', $variationId)
    ->where('marketplace_id', $marketplaceId)
    ->whereBetween('changed_at', [$startDate, $endDate])
    ->orderBy('changed_at', 'desc')
    ->get();
```

### Get Changes by Field

```php
$priceChanges = ListingMarketplaceHistory::where('variation_id', $variationId)
    ->where('marketplace_id', $marketplaceId)
    ->whereJsonContains('changed_fields', 'price')
    ->orderBy('changed_at', 'desc')
    ->get();
```

### Get Daily Summary

```php
$dailySummary = ListingMarketplaceHistory::where('variation_id', $variationId)
    ->where('marketplace_id', $marketplaceId)
    ->selectRaw('DATE(changed_at) as date, COUNT(*) as change_count')
    ->groupBy('date')
    ->orderBy('date', 'desc')
    ->get();
```

## UI Display Structure

The history table should display:

| Date | Min Handler | Price Handler | BuyBox | Min Price | Price | Changed By | Action |
|------|-------------|---------------|--------|-----------|-------|------------|--------|
| 26/11/2025 8:15:43 | 540.00 | 508.00 | Yes | 556.79 | 523.20 | Admin Name | View Details |

- Highlight changed fields in different colors
- Show previous → new values
- Filter by date range, field, or admin
- Export to CSV/Excel

## Next Steps

1. Create migration file
2. Create model
3. Create service class for logging
4. Integrate logging into existing update methods
5. Create API endpoint to fetch history
6. Create UI component to display history table
7. Add filtering and export functionality

