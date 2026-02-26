<?php

namespace App\Console\Commands;

use App\Models\Api_request_model;
use App\Models\CommandRunLog;
use App\Console\Commands\BaseCommand;

class ProcessApiRequests extends BaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api-request:process {--chunk=100 : Number of requests per batch}';

    /**
     * The console command description.
     */
    protected $description = 'Process pending tester API requests in queued-friendly batches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        CommandRunLog::recordStart('api-request-process');
        $chunkSize = (int) $this->option('chunk');
        $chunkSize = max(1, $chunkSize);

        $model = new Api_request_model();
        $model->push_testing($chunkSize);

        $this->info('api-request:process finished.');
        CommandRunLog::recordEnd('api-request-process', $chunkSize, 0, 0, 'Processed chunk: ' . $chunkSize, 'completed');
        return self::SUCCESS;
    }
}
