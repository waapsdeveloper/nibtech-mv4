<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SyncMarketplaceStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $marketplaceId;
    public $userId;
    public $tries = 1; // Only try once
    public $timeout = 3600; // 1 hour timeout

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($marketplaceId, $userId = null)
    {
        $this->marketplaceId = $marketplaceId;
        $this->userId = $userId; // Pass user ID directly, don't use session
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("SyncMarketplaceStockJob: Job started", [
            'marketplace_id' => $this->marketplaceId,
            'user_id' => $this->userId,
            'job_id' => $this->job->getJobId()
        ]);

        try {
            // Run the V2 sync command (uses generic MarketplaceAPIService)
            $exitCode = Artisan::call('v2:marketplace:sync-stock', [
                '--marketplace' => $this->marketplaceId,
                '--force' => true
            ]);

            $output = trim(Artisan::output());

            Log::info("SyncMarketplaceStockJob: Job completed", [
                'marketplace_id' => $this->marketplaceId,
                'exit_code' => $exitCode,
                'output' => $output,
                'job_id' => $this->job->getJobId()
            ]);

        } catch (\Exception $e) {
            Log::error("SyncMarketplaceStockJob: Job failed", [
                'marketplace_id' => $this->marketplaceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job->getJobId()
            ]);
            
            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error("SyncMarketplaceStockJob: Job permanently failed", [
            'marketplace_id' => $this->marketplaceId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
