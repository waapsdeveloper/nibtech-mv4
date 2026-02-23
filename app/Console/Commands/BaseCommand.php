<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Base console command that releases DB connections when the command finishes.
 * Extend this instead of Illuminate\Console\Command for scheduled/artisan commands
 * to avoid holding MySQL connections and help stay under connection limits.
 */
abstract class BaseCommand extends Command
{
    /**
     * Run after the command finishes (success or failure).
     * Releases the database connection back to the pool.
     */
    public function terminate($input, $status): void
    {
        try {
            DB::disconnect();
        } catch (\Throwable $e) {
            // Log but do not fail the command exit status
            if (function_exists('report')) {
                report($e);
            }
        }

        parent::terminate($input, $status);
    }
}
