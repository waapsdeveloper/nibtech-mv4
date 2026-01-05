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
    public $timeout = 7200; // 2 hours timeout (increased for bulk operations)

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
        $commandStartTime = microtime(true);
        
        Log::info("ExecuteArtisanCommandJob: Starting command execution", [
            'command' => $this->command,
            'options' => $this->options,
            'job_id' => $this->job->getJobId(),
            'started_at' => now()->toDateTimeString()
        ]);

        try {
            // Log progress for long-running commands
            if (strpos($this->command, 'sync') !== false || strpos($this->command, 'bulk') !== false) {
                Log::info("ExecuteArtisanCommandJob: Executing long-running command", [
                    'command' => $this->command,
                    'note' => 'This may take several minutes. Check logs for progress.'
                ]);
            }
            
            // Execute command
            $exitCode = Artisan::call($this->command, $this->options);
            $output = Artisan::output();
            
            $commandDuration = round(microtime(true) - $commandStartTime, 2);

            // Log detailed output for debugging
            Log::info("ExecuteArtisanCommandJob: Command completed", [
                'command' => $this->command,
                'exit_code' => $exitCode,
                'duration_seconds' => $commandDuration,
                'output_length' => strlen($output),
                'job_id' => $this->job->getJobId(),
                'output_preview' => substr($output, 0, 500) // First 500 chars for preview
            ]);
            
            // Log full output if it's not too long (for debugging)
            if (strlen($output) > 0 && strlen($output) < 10000) {
                Log::debug("ExecuteArtisanCommandJob: Full command output", [
                    'command' => $this->command,
                    'output' => $output
                ]);
            } elseif (strlen($output) > 0) {
                // For very long output, log in chunks
                $chunks = str_split($output, 5000);
                foreach ($chunks as $index => $chunk) {
                    Log::debug("ExecuteArtisanCommandJob: Command output chunk " . ($index + 1), [
                        'command' => $this->command,
                        'chunk' => $index + 1,
                        'output' => $chunk
                    ]);
                }
            }

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

