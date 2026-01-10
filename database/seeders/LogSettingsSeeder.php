<?php

namespace Database\Seeders;

use App\Models\LogSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LogSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates log settings with sample/placeholder values.
     * Admin should update webhook URLs and channel names manually via the CRUD interface.
     */
    public function run(): void
    {
        // Default log settings to create with sample values
        $logSettings = [
            // Care API logs
            [
                'name' => 'care_api_errors',
                'channel_name' => 'care-api-logs',
                'webhook_url' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL',
                'log_level' => 'error',
                'log_type' => 'care_api',
                'keywords' => null,
                'is_enabled' => false, // Disabled until admin configures webhook
                'description' => 'Care API errors and critical issues. Update webhook URL and channel name, then enable.',
            ],
            [
                'name' => 'care_api_warnings',
                'channel_name' => 'care-api-logs',
                'webhook_url' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL',
                'log_level' => 'warning',
                'log_type' => 'care_api',
                'keywords' => null,
                'is_enabled' => false,
                'description' => 'Care API warnings. Update webhook URL and channel name, then enable if needed.',
            ],
            
            // Order API logs
            [
                'name' => 'order_api_errors',
                'channel_name' => 'order-api-logs',
                'webhook_url' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL',
                'log_level' => 'error',
                'log_type' => 'order_api',
                'keywords' => null,
                'is_enabled' => false,
                'description' => 'Order API errors and critical issues. Update webhook URL and channel name, then enable.',
            ],
            [
                'name' => 'order_sync_errors',
                'channel_name' => 'order-sync-logs',
                'webhook_url' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL',
                'log_level' => 'error',
                'log_type' => 'order_sync',
                'keywords' => null,
                'is_enabled' => false,
                'description' => 'Order synchronization errors. Update webhook URL and channel name, then enable.',
            ],
            [
                'name' => 'order_sync_warnings',
                'channel_name' => 'order-sync-logs',
                'webhook_url' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL',
                'log_level' => 'warning',
                'log_type' => 'order_sync',
                'keywords' => null,
                'is_enabled' => false,
                'description' => 'Order synchronization warnings. Update webhook URL and channel name, then enable if needed.',
            ],
            
            // Listing API logs
            [
                'name' => 'listing_api_errors',
                'channel_name' => 'listing-api-logs',
                'webhook_url' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL',
                'log_level' => 'error',
                'log_type' => 'listing_api',
                'keywords' => null,
                'is_enabled' => false,
                'description' => 'Listing API errors and critical issues. Update webhook URL and channel name, then enable.',
            ],
            [
                'name' => 'listing_api_warnings',
                'channel_name' => 'listing-api-logs',
                'webhook_url' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL',
                'log_level' => 'warning',
                'log_type' => 'listing_api',
                'keywords' => null,
                'is_enabled' => false,
                'description' => 'Listing API warnings. Update webhook URL and channel name, then enable if needed.',
            ],
            
            // Stock sync logs
            [
                'name' => 'stock_sync_errors',
                'channel_name' => 'stock-sync-logs',
                'webhook_url' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL',
                'log_level' => 'error',
                'log_type' => 'stock_sync',
                'keywords' => null,
                'is_enabled' => false,
                'description' => 'Stock synchronization errors. Update webhook URL and channel name, then enable.',
            ],
            [
                'name' => 'stock_sync_warnings',
                'channel_name' => 'stock-sync-logs',
                'webhook_url' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL',
                'log_level' => 'warning',
                'log_type' => 'stock_sync',
                'keywords' => null,
                'is_enabled' => false,
                'description' => 'Stock synchronization warnings. Update webhook URL and channel name, then enable if needed.',
            ],
        ];
        
        $created = 0;
        $skipped = 0;
        
        foreach ($logSettings as $setting) {
            $existing = LogSetting::where('name', $setting['name'])->first();
            
            if ($existing) {
                // Skip if already exists (preserve manual admin changes)
                $skipped++;
                $this->command->line("Skipped (already exists): {$setting['name']}");
            } else {
                // Create new setting with sample values
                LogSetting::create($setting);
                $created++;
                $this->command->info("Created: {$setting['name']} (sample values - update via portal)");
            }
        }
        
        $this->command->newLine();
        $this->command->info("Log settings seeding completed!");
        $this->command->info("Created: {$created} | Skipped: {$skipped}");
        $this->command->newLine();
        $this->command->warn("⚠️  IMPORTANT: Update webhook URLs and channel names via the portal!");
        $this->command->info("Manage settings at: /v2/logs/log-file (Slack Settings tab)");
        $this->command->newLine();
        $this->command->comment("Next steps:");
        $this->command->comment("1. Navigate to /v2/logs/log-file");
        $this->command->comment("2. Click 'Slack Settings' tab");
        $this->command->comment("3. Edit each setting and update:");
        $this->command->comment("   - Webhook URL (from Slack)");
        $this->command->comment("   - Channel name (without #)");
        $this->command->comment("4. Enable the settings you want to use");
    }
}
