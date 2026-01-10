# Slack Logging Strategy Implementation

**Date:** 2024-12-19  
**Status:** ✅ Complete

---

## Overview

Implemented a strategic Slack logging system that moves Slack channel configuration from `.env` to a database table, allowing flexible management through a CRUD interface. Only specific logs passed through the `SlackLogService` are posted to Slack channels, reducing noise and load.

---

## Components

### 1. Database Table: `log_settings`

**Migration:** `database/migrations/2026_01_10_200106_create_log_settings_table.php`

**Fields:**
- `id` - Primary key
- `name` - Unique identifier (e.g., 'care_api_errors')
- `channel_name` - Slack channel name (without #)
- `webhook_url` - Slack webhook URL for the channel
- `log_level` - Minimum log level ('error', 'warning', 'info', 'debug')
- `log_type` - Log category (e.g., 'care_api', 'order_sync', 'listing_api')
- `keywords` - Optional JSON array of keywords to match in messages
- `is_enabled` - Boolean flag to enable/disable the setting
- `description` - Description of what logs this setting handles
- `admin_id` - Admin who created the setting
- `created_at`, `updated_at` - Timestamps

**Indexes:**
- `name` (unique)
- `log_type`, `is_enabled` (composite)
- `log_level`

---

### 2. Model: `LogSetting`

**File:** `app/Models/LogSetting.php`

**Key Methods:**
- `getActiveForType($logType, $logLevel)` - Get active setting for a specific log type and level
- `getActiveForKeywords($message, $logLevel)` - Get active setting matching keywords in message

---

### 3. Service: `SlackLogService`

**File:** `app/Services/V2/SlackLogService.php`

**Main Method:**
```php
SlackLogService::post($logType, $level, $message, $context = [])
```

**How It Works:**
1. Searches for active log setting matching the log type and level
2. If no type match, searches for keyword-based matches
3. If no matching setting found or setting disabled, logs to file only (default Laravel log)
4. If setting found and enabled, posts to Slack webhook URL
5. Always logs to file as backup, even if Slack post succeeds

**Parameters:**
- `$logType` (string) - Log category (e.g., 'care_api', 'order_sync', 'listing_api', 'stock_sync')
- `$level` (string) - Log level ('error', 'warning', 'info', 'debug')
- `$message` (string) - Log message
- `$context` (array, optional) - Additional context data

**Returns:** `bool` - Success status

**Features:**
- Strategic routing: Only posts if matching setting exists and is enabled
- Fallback: Always logs to file even if Slack post fails
- Error handling: Catches exceptions and logs errors
- Formatting: Formats messages for Slack with emojis and context
- Level filtering: Respects minimum log level setting

---

### 4. Controller: `LogFileController`

**File:** `app/Http/Controllers/V2/LogFileController.php`

**Methods:**
- `index()` - Display log file and log settings
- `clear()` - Clear log file
- `storeLogSetting()` - Create new log setting
- `updateLogSetting($id)` - Update existing log setting
- `deleteLogSetting($id)` - Delete log setting
- `getLogSetting($id)` - Get single log setting (for editing)

**Routes:**
- `GET /v2/logs/log-file` - View logs and settings
- `DELETE /v2/logs/log-file` - Clear log file
- `POST /v2/logs/log-settings` - Create setting
- `PUT /v2/logs/log-settings/{id}` - Update setting
- `DELETE /v2/logs/log-settings/{id}` - Delete setting
- `GET /v2/logs/log-settings/{id}` - Get setting

---

### 5. View: Log File & Settings Interface

**File:** `resources/views/v2/logs/log-file/index.blade.php`

**Features:**
- **Tabbed Interface:**
  - Tab 1: Log File Viewer (existing functionality)
  - Tab 2: Slack Settings (CRUD interface)

- **Log Settings Table:**
  - Displays all log settings
  - Shows: Name, Channel, Log Type, Level, Keywords, Status, Description
  - Edit and Delete actions per row

- **Modal Form:**
  - Add/Edit log setting
  - Fields: Name, Channel, Webhook URL, Log Type, Log Level, Keywords, Description, Enabled
  - Validation and error handling

---

## Usage Examples

### Example 1: Post Care API Log to Slack

```php
use App\Services\V2\SlackLogService;

// Post only if 'care_api' log type is configured and enabled
SlackLogService::post('care_api', 'info', "Care API: Response received", [
    'endpoint' => 'sav',
    'response_code' => 200
]);
```

### Example 2: Post Order Sync Error

```php
SlackLogService::post('order_sync', 'error', "Failed to sync order", [
    'order_id' => 12345,
    'marketplace_id' => 1,
    'error' => $e->getMessage()
]);
```

### Example 3: Post Listing API Warning

```php
SlackLogService::post('listing_api', 'warning', "Listing API: Missing results", [
    'endpoint' => 'listings',
    'response' => json_encode($result)
]);
```

### Example 4: Keyword-Based Logging

```php
// This will match any setting with keywords containing "critical" or "payment"
SlackLogService::post('payment_processing', 'error', "Critical payment error occurred");
```

---

## Log Types (Recommended Categories)

| Log Type | Description | Example Use Cases |
|----------|-------------|-------------------|
| `care_api` | Care API operations | Care record fetching, updates |
| `order_api` | Order API operations | Order fetching, syncing |
| `order_sync` | Order synchronization | Order sync errors, warnings |
| `listing_api` | Listing API operations | Listing fetching, updates |
| `stock_sync` | Stock synchronization | Stock sync operations, errors |
| `stock_locks` | Stock locking operations | Lock releases, errors |
| `marketplace_api` | Marketplace API calls | API failures, timeouts |
| `price_updates` | Price update operations | Price sync, buybox updates |

---

## Migration Steps

### 1. Run Migration

```bash
php artisan migrate
```

This creates the `log_settings` table.

### 2. Create Log Settings via Interface

1. Navigate to: `http://your-domain.com/v2/logs/log-file`
2. Click on "Slack Settings" tab
3. Click "Add New Setting"
4. Fill in the form:
   - **Name**: `care_api_errors` (unique identifier)
   - **Channel**: `care-api-logs` (Slack channel without #)
   - **Webhook URL**: `https://hooks.slack.com/services/YOUR/WEBHOOK/URL`
   - **Log Type**: `care_api`
   - **Log Level**: `warning` (minimum level)
   - **Keywords**: (optional) `error, failed, exception`
   - **Description**: "Care API errors and warnings"
   - **Enabled**: ✓

5. Click "Save Setting"

### 3. Use in Code

Replace existing Slack logging calls:

**Before:**
```php
Log::channel('slack')->info("Care API: " . json_encode($result));
```

**After:**
```php
\App\Services\V2\SlackLogService::post('care_api', 'info', "Care API: " . json_encode($result), [
    'endpoint' => 'sav',
    'response' => $result
]);
```

---

## Updated Files

### Already Updated

✅ **BackMarketAPIController.php** - Updated 5 Slack logging calls:
- Care API logs (2 occurrences) → `care_api` type
- Order API logs (1 occurrence) → `order_api` type  
- Listing API logs (2 occurrences) → `listing_api` type

### Files Using Slack Logging (To Update)

The following files still use `Log::channel('slack')` and should be updated:

- Check for any other files using `Log::channel('slack')`:
  ```bash
  grep -r "Log::channel('slack')" app/
  ```

---

## Benefits

1. **Flexible Configuration**: No need to modify `.env` or deploy code to change Slack channels
2. **Strategic Posting**: Only posts logs that are explicitly configured
3. **Load Reduction**: Reduces Slack noise by posting only important logs to specific channels
4. **Easy Management**: CRUD interface makes it easy to add/edit/remove settings
5. **Type-Based Routing**: Different log types can go to different channels
6. **Keyword Matching**: Additional flexibility with keyword-based matching
7. **Level Filtering**: Respects minimum log level settings
8. **Backup Logging**: Always logs to file as backup, even if Slack fails
9. **Error Handling**: Graceful error handling - never crashes if Slack is down

---

## Best Practices

1. **Use Descriptive Log Types**: Use clear, consistent log type names (e.g., `care_api`, `order_sync`)
2. **Set Appropriate Levels**: Use `error` and `warning` for Slack, keep `info` and `debug` for file logs
3. **Include Context**: Always pass context array with relevant data for debugging
4. **Monitor Settings**: Regularly review and clean up unused settings
5. **Test Webhooks**: Verify webhook URLs work before enabling settings
6. **Use Keywords Sparingly**: Only use keywords for specific matching needs
7. **Document Settings**: Always add descriptions to explain what each setting handles

---

## Troubleshooting

### Logs Not Posting to Slack

1. Check if setting exists and is enabled
2. Verify webhook URL is correct and active
3. Check log level matches (e.g., setting level is 'error' but posting 'info')
4. Check log type matches exactly
5. Check Laravel log file for error messages from SlackLogService

### All Logs Going to File Only

- This is expected behavior if no matching log setting exists or is disabled
- Only logs explicitly passed through `SlackLogService::post()` with matching settings go to Slack
- This is by design to reduce Slack noise

### Migration Issues

If migration fails:
```bash
php artisan migrate:rollback --step=1
# Fix issues
php artisan migrate
```

---

## Future Enhancements

Potential improvements:
- [ ] Rate limiting per channel
- [ ] Log aggregation (batch multiple logs before posting)
- [ ] Alert thresholds (only alert after X occurrences)
- [ ] Scheduled reports (daily/weekly summaries)
- [ ] Multiple webhook support per setting
- [ ] Test webhook button in UI
- [ ] Log statistics dashboard

---

**End of Documentation**
