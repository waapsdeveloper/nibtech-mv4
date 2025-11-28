# Refurbed Marketplace Integration

## Overview
Added Refurbed marketplace listing synchronization to the `FunctionsThirty` command, following the same pattern as the existing BackMarket integration.

## Changes Made

### 1. Updated FunctionsThirty.php
- Added `RefurbedAPIController` import
- Added `get_refurbed_listings()` method call in `handle()` method
- Implemented `get_refurbed_listings()` method for syncing Refurbed offers

### 2. Marketplace ID Assignment
- **BackMarket**: marketplace_id = 1
- **BMPRO EUR**: marketplace_id = 2
- **BMPRO GBP**: marketplace_id = 3
- **Refurbed**: marketplace_id = 4 (newly assigned)

## Implementation Details

### Method: `get_refurbed_listings()`

**Features:**
- Fetches all active offers from Refurbed API using `listOffers()` method
- Pagination support with page_size=100 (similar to BackMarket optimization)
- Creates/updates `Variation_model` records based on SKU
- Creates/updates `Listing_model` records with marketplace_id=4
- Comprehensive error handling and logging
- Progress tracking with console output
- Order fulfillment endpoints (`batchUpdateOrderItems`, `batchUpdateOrderItemsState`) chunk payloads automatically (50 items per API call) so tracking + state updates can be applied in bulk once packing is complete.

**Field Mapping:**

| Refurbed API Field | Database Field | Notes |
|-------------------|----------------|-------|
| `id` or `offer_id` | Variation.reference_id | Offer identifier |
| `sku` or `merchant_sku` | Variation.sku | Primary lookup key |
| `product_id` | Variation.reference_uuid | Product reference |
| `title` or `product_title` | Variation.name, Listing.name | Display name |
| `state` | Variation.state | ACTIVE=1, INACTIVE/PAUSED=2, OUT_OF_STOCK=3 |
| `condition` or `grade` | Variation.grade | NEW=1, EXCELLENT=2, VERY_GOOD=3, GOOD=4, FAIR=5 |
| `quantity` or `stock` | Variation.listed_stock | Available quantity |
| `price.amount` or `price` | Listing.price | Price value |
| `price.currency` or `currency` | Listing.currency_id | Currency code (EUR, USD, etc.) |
| `country` or `region` | Listing.country | Defaults to 'DE' if not provided |
| `min_price` | Listing.min_price | Optional |
| `max_price` | Listing.max_price | Optional |

### Packing Workflow Hooks

- The Livewire packing screen (`app/Http/Livewire/Order.php`) now gathers every Refurbed order item ID for the order being shipped and calls `RefurbedAPIController::batchUpdateOrderItemsState()` right after a tracking number is captured.
- Each payload marks the line as `SHIPPED` and includes the tracking number when available; helper chunking (≤50 updates/request) ensures large orders stay within Refurbed limits.
- Failures are logged with order references so ops can retry without guessing which parcels were missed.
- When manual intervention is required, the internal endpoint `POST /api/refurbed/orders/{order_id}/ship-lines` can now be used to push every `ACCEPTED` order line to `SHIPPED`. Optional body fields:
    - `tracking_number` and `carrier` (strings) propagate tracking data to Refurbed.
    - `order_item_ids` (array of Refurbed order item IDs) restricts the update to a subset; leave empty to update every accepted line.
    - `force` (boolean) bypasses the accepted-state guard and ships every provided ID.
    - Response payload returns `updated`, `skipped`, and aggregate API batch info for quick auditing.
- Ops can also trigger the same workflow from the CLI via `php artisan refurbed:ship-lines {order_id}` with the same optional flags (`--order-item-id=*`, `--tracking-number=`, `--carrier=`, `--force`). Useful for quick smoke-tests or shipping a stuck order without touching the UI.

## Configuration Required

### 1. Database Setup
Ensure the Refurbed API key is stored in the `marketplace` table:

```sql
INSERT INTO marketplace (name, api_key, created_at, updated_at)
VALUES ('Refurbed', 'your-refurbed-api-key-here', NOW(), NOW());
```

Or update existing record:
```sql
UPDATE marketplace SET api_key = 'your-refurbed-api-key-here' WHERE name = 'Refurbed';
```

### 2. Environment Variables (Fallback)
If the database is unavailable, set in `.env`:

```env
REFURBED_API_KEY=your-api-key-here
REFURBED_API_BASE_URL=https://api.refurbed.com
REFURBED_AUTH_SCHEME=Bearer
```

## Testing

### 1. Test the Refurbed API Connection
```bash
# Test the listings endpoint
curl "http://your-domain/api/refurbed/listings/active?per_page=5"

# Or use the test endpoint
curl "http://your-domain/api/refurbed/listings/test?per_page=5"
```

### 2. Run the Command Manually
```bash
php artisan functions:thirty
```

Watch the console output for:
- "Refurbed: Processing X offers"
- "Refurbed: Processed page, total: X"
- "Refurbed sync complete: X offers processed"

### 3. Check Logs
```bash
# Check Laravel logs for detailed information
tail -f storage/logs/laravel.log | grep Refurbed
```

### 4. Verify Database Records
```sql
-- Check variations created from Refurbed
SELECT COUNT(*) FROM variations WHERE sku IN (
    SELECT DISTINCT sku FROM listings WHERE marketplace_id = 4
);

-- Check listings with marketplace_id = 4
SELECT COUNT(*) FROM listings WHERE marketplace_id = 4;

-- View sample Refurbed listings
SELECT v.sku, v.name, l.price, c.code as currency, co.name as country
FROM listings l
JOIN variations v ON l.variation_id = v.id
JOIN currencies c ON l.currency_id = c.id
JOIN countries co ON l.country = co.id
WHERE l.marketplace_id = 4
LIMIT 10;
```

## Potential Adjustments Needed

Since the exact Refurbed API response structure isn't documented in the codebase, you may need to adjust:

### 1. Field Name Mapping
The implementation uses common field names with fallbacks:
```php
$sku = $offer['sku'] ?? $offer['merchant_sku'] ?? null;
$productId = $offer['product_id'] ?? null;
$title = $offer['title'] ?? $offer['product_title'] ?? null;
```

**Action:** Run the test endpoint to see actual field names and update accordingly.

### 2. Country/Region Handling
Currently defaults to 'DE' (Germany):
```php
$countryCode = $offer['country'] ?? $offer['region'] ?? 'DE';
```

**Action:** Check if Refurbed supports multiple countries like BackMarket. If not, you might want to:
- Use a single default country for all Refurbed listings
- Remove country-specific logic entirely if Refurbed is region-agnostic

### 3. Grade/Condition Mapping
Current mapping:
```php
$gradeMap = [
    'NEW' => 1,
    'EXCELLENT' => 2,
    'VERY_GOOD' => 3,
    'GOOD' => 4,
    'FAIR' => 5,
];
```

**Action:** Verify Refurbed's condition values match this mapping.

### 4. State Mapping
Current mapping:
```php
$stateMap = [
    'ACTIVE' => 1,
    'INACTIVE' => 2,
    'PAUSED' => 2,
    'OUT_OF_STOCK' => 3,
];
```

**Action:** Verify Refurbed's state values and their meaning.

### 5. Pagination Structure
Uses `next_page_token` for pagination:
```php
$pageToken = $response['next_page_token'] ?? null;
```

**Action:** Verify the pagination response structure from Refurbed API.

## Troubleshooting

### Issue: No offers found
**Check:**
1. API key is valid and has proper permissions
2. Test the API directly: `curl -H "Authorization: Bearer YOUR_KEY" https://api.refurbed.com/refb.merchant.v1.OfferService/ListOffers`
3. Check if there are any active offers in your Refurbed account

### Issue: Currency not found
**Solution:** Add missing currencies to the `currencies` table:
```sql
INSERT INTO currencies (code, sign, name, created_at, updated_at)
VALUES ('EUR', '€', 'Euro', NOW(), NOW());
```

### Issue: Country not found
**Solution:** Add missing countries to the `countries` table or update the default country logic.

### Issue: SKU conflicts with BackMarket
**Note:** If the same SKU exists in both BackMarket and Refurbed:
- They will share the same `Variation_model` record
- Each marketplace will have its own `Listing_model` record (differentiated by marketplace_id)
- This is by design to allow multi-marketplace inventory management

## Schedule Setup

To run automatically via cron (like BackMarket sync):

```bash
# Edit crontab
crontab -e

# Add entry (example: run every hour)
0 * * * * cd /xampp/htdocs/nibritaintech && php artisan functions:thirty >> /dev/null 2>&1
```

Or in Laravel's `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('functions:thirty')->hourly();
}
```

## Next Steps

1. **Test the integration** with actual Refurbed API
2. **Adjust field mappings** based on actual API response
3. **Verify country/currency handling** matches your business logic
4. **Add monitoring** for sync failures
5. **Consider adding** a separate BI data sync method (like `get_listingsBi()` for BackMarket) if Refurbed provides buybox/competitive data

## API Documentation Reference

For detailed Refurbed API documentation, see:
- GitLab: https://gitlab.com/refurbed-community/public-apis
- Internal docs: README.md (Refurbed Merchant API integration section)
