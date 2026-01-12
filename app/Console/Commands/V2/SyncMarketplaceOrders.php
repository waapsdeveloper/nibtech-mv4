<?php

namespace App\Console\Commands\V2;

use Illuminate\Console\Command;
use App\Services\V2\MarketplaceOrderSyncService;
use App\Services\V2\SlackLogService;
use Illuminate\Support\Facades\Log;

/**
 * V2 Unified Marketplace Order Sync Command
 * Replaces RefreshLatest, RefreshNew, and RefreshOrders commands
 * 
 * Usage:
 *   php artisan v2:sync-orders --type=new
 *   php artisan v2:sync-orders --type=modified
 *   php artisan v2:sync-orders --type=care
 *   php artisan v2:sync-orders --type=incomplete
 *   php artisan v2:sync-orders --type=all
 *   php artisan v2:sync-orders --marketplace=1
 */
class SyncMarketplaceOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'v2:sync-orders 
                            {--type=all : Sync type: new, modified, care, incomplete, or all}
                            {--marketplace= : Specific marketplace ID to sync}
                            {--page-size=50 : Page size for API requests}
                            {--days-back=2 : Days back for incomplete orders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync orders from marketplace APIs (V2 - Unified command)';

    protected $syncService;

    /**
     * Create a new command instance.
     */
    public function __construct(MarketplaceOrderSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // CRITICAL: Log immediately when handle() is called to verify execution
        Log::info("=== SyncMarketplaceOrders::handle() CALLED ===", [
            'timestamp' => now()->toDateTimeString(),
            'memory_usage' => memory_get_usage(true),
            'options_received' => $this->options()
        ]);
        
        $type = $this->option('type');
        $marketplaceId = $this->option('marketplace') ? (int) $this->option('marketplace') : null;
        $pageSize = (int) $this->option('page-size');
        $daysBack = (int) $this->option('days-back');

        $this->info("🔄 Starting V2 Marketplace Order Sync");
        $this->info("Type: {$type}");
        if ($marketplaceId) {
            $this->info("Marketplace ID: {$marketplaceId}");
        }
        $this->newLine();

        // Log to file (for queue execution visibility)
        Log::info("🔄 Starting V2 Marketplace Order Sync", [
            'command' => 'v2:sync-orders',
            'type' => $type,
            'marketplace_id' => $marketplaceId,
            'page_size' => $pageSize,
            'days_back' => $daysBack,
            'parsed_options' => [
                'type' => $type,
                'marketplace' => $marketplaceId,
                'page-size' => $pageSize,
                'days-back' => $daysBack
            ]
        ]);

        // Log sync start to Slack
        $marketplaceInfo = $marketplaceId ? "Marketplace ID: {$marketplaceId}" : "All marketplaces";
        SlackLogService::post('order_sync', 'info', "🔄 V2 Marketplace Order Sync Started", [
            'command' => 'v2:sync-orders',
            'type' => $type,
            'marketplace_id' => $marketplaceId,
            'marketplace_info' => $marketplaceInfo,
            'page_size' => $pageSize,
            'days_back' => $daysBack
        ], true);

        $startTime = microtime(true);
        $results = [];

        try {
            switch ($type) {
                case 'new':
                    $results['new'] = $this->syncNewOrders($marketplaceId, ['page-size' => $pageSize]);
                    break;

                case 'modified':
                    $results['modified'] = $this->syncModifiedOrders($marketplaceId, ['page-size' => $pageSize]);
                    break;

                case 'care':
                    $results['care'] = $this->syncCareRecords($marketplaceId, ['page-size' => $pageSize]);
                    break;

                case 'incomplete':
                    $results['incomplete'] = $this->syncIncompleteOrders($marketplaceId, $daysBack);
                    break;

                case 'all':
                    $this->info("📦 Syncing new orders...");
                    Log::info("📦 Syncing new orders...", ['command' => 'v2:sync-orders', 'type' => 'all']);
                    $results['new'] = $this->syncNewOrders($marketplaceId, ['page-size' => $pageSize]);
                    $this->newLine();

                    $this->info("🔄 Syncing modified orders...");
                    Log::info("🔄 Syncing modified orders...", ['command' => 'v2:sync-orders', 'type' => 'all']);
                    $results['modified'] = $this->syncModifiedOrders($marketplaceId, ['page-size' => $pageSize]);
                    $this->newLine();

                    $this->info("🔧 Syncing care records...");
                    Log::info("🔧 Syncing care records...", ['command' => 'v2:sync-orders', 'type' => 'all']);
                    $results['care'] = $this->syncCareRecords($marketplaceId, ['page-size' => $pageSize]);
                    $this->newLine();

                    $this->info("⚠️  Syncing incomplete orders...");
                    Log::info("⚠️  Syncing incomplete orders...", ['command' => 'v2:sync-orders', 'type' => 'all']);
                    $results['incomplete'] = $this->syncIncompleteOrders($marketplaceId, $daysBack);
                    break;

                default:
                    $this->error("Invalid sync type: {$type}");
                    $this->info("Valid types: new, modified, care, incomplete, all");
                    return 1;
            }

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $this->displayResults($results, $duration);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error during sync: " . $e->getMessage());
            Log::error('SyncMarketplaceOrders: Command failed', [
                'type' => $type,
                'marketplace_id' => $marketplaceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Send error to Slack
            SlackLogService::post('order_sync', 'error', "V2 Sync Command Failed: {$e->getMessage()}", [
                'command' => 'v2:sync-orders',
                'type' => $type,
                'marketplace_id' => $marketplaceId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], true);
            
            return 1;
        }
    }

    /**
     * Sync new orders
     */
    protected function syncNewOrders($marketplaceId, $params)
    {
        $this->info("📦 Fetching new orders...");
        Log::info("📦 Fetching new orders...", ['command' => 'v2:sync-orders', 'type' => 'new', 'marketplace_id' => $marketplaceId]);
        
        $result = $this->syncService->syncNewOrders($marketplaceId, $params);
        
        $this->info("✅ Synced: {$result['synced']} orders");
        Log::info("✅ Synced new orders", [
            'command' => 'v2:sync-orders',
            'type' => 'new',
            'synced' => $result['synced'],
            'errors' => $result['errors'],
            'marketplace_id' => $marketplaceId
        ]);
        
        if ($result['errors'] > 0) {
            $this->warn("⚠️  Errors: {$result['errors']}");
            
            // Send error summary to Slack
            SlackLogService::post('order_sync', 'warning', "V2 Sync New Orders: {$result['errors']} error(s) occurred", [
                'command' => 'v2:sync-orders',
                'type' => 'new',
                'synced' => $result['synced'],
                'errors' => $result['errors']
            ], true);
        }

        return $result;
    }

    /**
     * Sync modified orders
     */
    protected function syncModifiedOrders($marketplaceId, $params)
    {
        $this->info("🔄 Fetching modified orders...");
        Log::info("🔄 Fetching modified orders...", ['command' => 'v2:sync-orders', 'type' => 'modified', 'marketplace_id' => $marketplaceId]);
        
        $result = $this->syncService->syncModifiedOrders($marketplaceId, $params);
        
        $this->info("✅ Synced: {$result['synced']} orders");
        Log::info("✅ Synced modified orders", [
            'command' => 'v2:sync-orders',
            'type' => 'modified',
            'synced' => $result['synced'],
            'errors' => $result['errors'],
            'marketplace_id' => $marketplaceId
        ]);
        
        if ($result['errors'] > 0) {
            $this->warn("⚠️  Errors: {$result['errors']}");
            
            // Send error summary to Slack
            SlackLogService::post('order_sync', 'warning', "V2 Sync Modified Orders: {$result['errors']} error(s) occurred", [
                'command' => 'v2:sync-orders',
                'type' => 'modified',
                'synced' => $result['synced'],
                'errors' => $result['errors']
            ], true);
        }

        return $result;
    }

    /**
     * Sync care records
     */
    protected function syncCareRecords($marketplaceId, $params)
    {
        $this->info("🔧 Fetching care records...");
        Log::info("🔧 Fetching care records...", ['command' => 'v2:sync-orders', 'type' => 'care', 'marketplace_id' => $marketplaceId]);
        
        $result = $this->syncService->syncCareRecords($marketplaceId, $params);
        
        $this->info("✅ Synced: {$result['synced']} care records");
        Log::info("✅ Synced care records", [
            'command' => 'v2:sync-orders',
            'type' => 'care',
            'synced' => $result['synced'],
            'errors' => $result['errors'],
            'marketplace_id' => $marketplaceId
        ]);
        
        if ($result['errors'] > 0) {
            $this->warn("⚠️  Errors: {$result['errors']}");
            
            // Send error summary to Slack
            SlackLogService::post('order_sync', 'warning', "V2 Sync Care Records: {$result['errors']} error(s) occurred", [
                'command' => 'v2:sync-orders',
                'type' => 'care',
                'synced' => $result['synced'],
                'errors' => $result['errors']
            ], true);
        }

        return $result;
    }

    /**
     * Sync incomplete orders
     */
    protected function syncIncompleteOrders($marketplaceId, $daysBack)
    {
        $this->info("⚠️  Fetching incomplete orders (last {$daysBack} days)...");
        Log::info("⚠️  Fetching incomplete orders", [
            'command' => 'v2:sync-orders',
            'type' => 'incomplete',
            'marketplace_id' => $marketplaceId,
            'days_back' => $daysBack
        ]);
        
        $result = $this->syncService->syncIncompleteOrders($marketplaceId, $daysBack);
        
        $this->info("✅ Synced: {$result['synced']} orders");
        Log::info("✅ Synced incomplete orders", [
            'command' => 'v2:sync-orders',
            'type' => 'incomplete',
            'synced' => $result['synced'],
            'errors' => $result['errors'],
            'marketplace_id' => $marketplaceId
        ]);
        
        if ($result['errors'] > 0) {
            $this->warn("⚠️  Errors: {$result['errors']}");
            
            // Send error summary to Slack
            SlackLogService::post('order_sync', 'warning', "V2 Sync Incomplete Orders: {$result['errors']} error(s) occurred", [
                'command' => 'v2:sync-orders',
                'type' => 'incomplete',
                'synced' => $result['synced'],
                'errors' => $result['errors']
            ], true);
        }

        return $result;
    }

    /**
     * Display sync results summary
     */
    protected function displayResults($results, $duration)
    {
        $this->newLine();
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 Sync Summary");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $totalSynced = 0;
        $totalErrors = 0;

        foreach ($results as $type => $result) {
            if (isset($result['synced'])) {
                $totalSynced += $result['synced'];
                $totalErrors += $result['errors'] ?? 0;

                $status = $result['errors'] > 0 ? '⚠️' : '✅';
                $this->info("{$status} {$type}: {$result['synced']} synced" . 
                           ($result['errors'] > 0 ? ", {$result['errors']} errors" : ""));
            }
        }

        $this->newLine();
        $this->info("Total: {$totalSynced} synced, {$totalErrors} errors");
        $this->info("Duration: {$duration}s");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        // Log summary to file
        Log::info("📊 V2 Marketplace Order Sync Summary", [
            'command' => 'v2:sync-orders',
            'total_synced' => $totalSynced,
            'total_errors' => $totalErrors,
            'duration_seconds' => $duration,
            'results' => $results
        ]);
    }
}

