# Slack Log Settings Seeder

**Date:** 2024-12-19  
**Status:** ✅ Complete

---

## Overview

The `LogSettingsSeeder` reads Slack configuration from `.env` file and creates initial log settings in the database. This allows you to:

1. **Initial Setup**: Seed log settings from `.env` during deployment
2. **Easy Updates**: Change values in `.env` and re-run seeder
3. **Flexible Management**: Update settings later through CRUD interface

---

## Environment Variables

### Required Variables

Add these to your `.env` file:

```env
# Default Slack Webhook (used if specific webhooks not set)
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL

# Default Channel Name (used if specific channels not set)
LOG_SLACK_CHANNEL_NAME=system-logs
```

### Optional: Per-Log-Type Configuration

For more granular control, you can set specific webhooks and channels per log type:

```env
# Care API
SLACK_WEBHOOK_CARE_API=https://hooks.slack.com/services/CARE/WEBHOOK/URL
SLACK_CHANNEL_CARE_API=care-api-logs

# Order API
SLACK_WEBHOOK_ORDER_API=https://hooks.slack.com/services/ORDER/WEBHOOK/URL
SLACK_CHANNEL_ORDER_API=order-api-logs

# Order Sync
SLACK_WEBHOOK_ORDER_SYNC=https://hooks.slack.com/services/ORDER_SYNC/WEBHOOK/URL
SLACK_CHANNEL_ORDER_SYNC=order-sync-logs

# Listing API
SLACK_WEBHOOK_LISTING_API=https://hooks.slack.com/services/LISTING/WEBHOOK/URL
SLACK_CHANNEL_LISTING_API=listing-api-logs

# Stock Sync
SLACK_WEBHOOK_STOCK_SYNC=https://hooks.slack.com/services/STOCK_SYNC/WEBHOOK/URL
SLACK_CHANNEL_STOCK_SYNC=stock-sync-logs
```

---

## Running the Seeder

### Run Seeder Only

```bash
php artisan db:seed --class=LogSettingsSeeder
```

### Run All Seeders (including LogSettingsSeeder)

```bash
php artisan db:seed
```

### Run After Migration

```bash
php artisan migrate --seed
```

---

## What Gets Created

The seeder creates the following log settings:

### Care API
- ✅ `care_api_errors` - Error level (enabled by default)
- ⚠️ `care_api_warnings` - Warning level (disabled by default)

### Order API
- ✅ `order_api_errors` - Error level (enabled by default)

### Order Sync
- ✅ `order_sync_errors` - Error level (enabled by default)
- ⚠️ `order_sync_warnings` - Warning level (disabled by default)

### Listing API
- ✅ `listing_api_errors` - Error level (enabled by default)
- ⚠️ `listing_api_warnings` - Warning level (disabled by default)

### Stock Sync
- ✅ `stock_sync_errors` - Error level (enabled by default)
- ⚠️ `stock_sync_warnings` - Warning level (disabled by default)

**Note:** Warnings are disabled by default to reduce noise. Enable them through the CRUD interface if needed.

---

## Seeder Behavior

### First Run
- Creates all log settings from `.env` values
- Uses default webhook/channel if specific ones not set

### Subsequent Runs
- **Updates existing settings** if webhook URL changed
- **Skips unchanged settings** (preserves manual edits)
- **Creates missing settings** if new ones added to seeder

### Safe to Re-run
- Won't duplicate existing settings
- Only updates webhook URLs if changed in `.env`
- Preserves manual changes made through CRUD interface

---

## Example .env Configuration

```env
# Lines 16-18 area (or anywhere in .env)
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX
LOG_SLACK_CHANNEL_NAME=system-logs

# Optional: Specific channels per log type
SLACK_WEBHOOK_CARE_API=https://hooks.slack.com/services/T00000000/B00000000/CARE_WEBHOOK
SLACK_CHANNEL_CARE_API=care-api-alerts

SLACK_WEBHOOK_ORDER_API=https://hooks.slack.com/services/T00000000/B00000000/ORDER_WEBHOOK
SLACK_CHANNEL_ORDER_API=order-alerts

SLACK_WEBHOOK_LISTING_API=https://hooks.slack.com/services/T00000000/B00000000/LISTING_WEBHOOK
SLACK_CHANNEL_LISTING_API=listing-alerts
```

---

## Updating Settings

### Method 1: Update .env and Re-run Seeder

1. Update values in `.env`:
   ```env
   LOG_SLACK_WEBHOOK_URL=https://new-webhook-url
   ```

2. Re-run seeder:
   ```bash
   php artisan db:seed --class=LogSettingsSeeder
   ```

3. Settings will be updated automatically

### Method 2: Use CRUD Interface

1. Navigate to: `/v2/logs/log-file`
2. Click "Slack Settings" tab
3. Edit any setting directly
4. Changes are saved immediately

**Note:** Manual changes through CRUD interface are preserved when re-running seeder (unless webhook URL changed in `.env`)

---

## Seeder Output

When you run the seeder, you'll see output like:

```
Created: care_api_errors
Created: care_api_warnings
Skipped (unchanged): order_api_errors
Updated: listing_api_errors
Created: stock_sync_errors

Log settings seeding completed!
Created: 5 | Updated: 1

You can now manage these settings via:
URL: /v2/logs/log-file (Slack Settings tab)

Note: To use different channels/webhooks per log type, add to .env:
  SLACK_WEBHOOK_CARE_API=https://hooks.slack.com/services/...
  SLACK_CHANNEL_CARE_API=care-api-logs
  ...
```

---

## Troubleshooting

### "LOG_SLACK_WEBHOOK_URL not found in .env"

**Solution:** Add `LOG_SLACK_WEBHOOK_URL` to your `.env` file:
```env
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

### Settings Not Created

**Check:**
1. Is `LOG_SLACK_WEBHOOK_URL` set in `.env`?
2. Did you run `php artisan config:clear` after updating `.env`?
3. Are there any errors in the seeder output?

### Settings Not Updating

**Solution:** The seeder only updates if webhook URL changed. To force update:
1. Delete settings through CRUD interface
2. Re-run seeder

Or manually update through CRUD interface.

---

## Integration with DatabaseSeeder

The seeder is automatically called when running:
```bash
php artisan db:seed
```

It's included in `DatabaseSeeder.php`:
```php
$this->call(LogSettingsSeeder::class);
```

---

## Best Practices

1. **Initial Setup**: Run seeder after migration to populate initial settings
2. **Environment-Specific**: Use different `.env` files for dev/staging/prod
3. **Version Control**: Don't commit `.env` with real webhook URLs
4. **Updates**: Use CRUD interface for quick changes, `.env` + seeder for bulk updates
5. **Testing**: Test webhook URLs before enabling settings

---

## Migration Path

### From .env to Database

**Before:**
- Slack webhook in `config/logging.php` using `env('LOG_SLACK_WEBHOOK_URL')`
- Hard to manage multiple channels
- Requires code changes to update

**After:**
- Webhook URLs stored in database
- Multiple channels per log type
- Easy updates through CRUD interface
- Seeder allows initial setup from `.env`

---

## Files

- **Seeder**: `database/seeders/LogSettingsSeeder.php`
- **Model**: `app/Models/LogSetting.php`
- **Migration**: `database/migrations/2026_01_10_200106_create_log_settings_table.php`
- **Controller**: `app/Http/Controllers/V2/LogFileController.php`
- **View**: `resources/views/v2/logs/log-file/index.blade.php`

---

**End of Documentation**
