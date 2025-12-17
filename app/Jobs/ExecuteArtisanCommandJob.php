<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Job to execute artisan commands asynchronously
 * Prevents 504 timeout errors for long-running commands
 */
class ExecuteArtisanCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $command;
    public $options;
    public $tries = 1;
    public $timeout = 3600; // 1 hour timeout

    /**
     * Create a new job instance.
     */
    public function __construct(string $command, array $options = [])
    {
        $this->command = $command;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info("ExecuteArtisanCommandJob: Starting command execution", [
            'command' => $this->command,
            'options' => $this->options,
            'job_id' => $this->job->getJobId()
        ]);

        try {
            // Execute command
            $exitCode = Artisan::call($this->command, $this->options);
            $output = Artisan::output();

            Log::info("ExecuteArtisanCommandJob: Command completed", [
                'command' => $this->command,
                'exit_code' => $exitCode,
                'output_length' => strlen($output),
                'job_id' => $this->job->getJobId()
            ]);

            return [
                'success' => $exitCode === 0,
                'output' => $output,
                'exit_code' => $exitCode
            ];
        } catch (\Exception $e) {
            Log::error("ExecuteArtisanCommandJob: Command failed", [
                'command' => $this->command,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job->getJobId()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        Log::error("ExecuteArtisanCommandJob: Job permanently failed", [
            'command' => $this->command,
            'error' => $exception->getMessage()
        ]);
    }
}

