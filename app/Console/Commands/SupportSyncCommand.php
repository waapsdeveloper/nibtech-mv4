<?php

namespace App\Console\Commands;

use App\Services\Support\BackMarketCareSyncService;
use App\Services\Support\RefurbedMailboxSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupportSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'support:sync {--since=} {--source=* : Limit to a specific channel (backmarket, refurbed)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize external support channels into the unified support tables.';

    public function __construct(
        private BackMarketCareSyncService $backMarketCare,
        private RefurbedMailboxSyncService $refurbedMailbox
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sources = $this->option('source') ?: ['backmarket', 'refurbed'];
        $since = $this->option('since');

        $synced = 0;

        if (in_array('backmarket', $sources, true)) {
            $synced += $this->runSync(function () use ($since) {
                $options = [];
                if ($since) {
                    $options['since'] = $since;
                }

                return $this->backMarketCare->sync($options);
            }, 'Back Market Care');
        }

        if (in_array('refurbed', $sources, true)) {
            $synced += $this->runSync(fn () => $this->refurbedMailbox->sync(), 'Refurbed mailbox');
        }

        $this->info("Support sync completed. Messages processed: {$synced}");

        return Command::SUCCESS;
    }

    protected function runSync(callable $callback, string $label): int
    {
        try {
            $count = $callback();
            $this->line("- {$label}: {$count} messages");

            return $count;
        } catch (Throwable $e) {
            Log::error('SupportSyncCommand failed channel sync', [
                'channel' => $label,
                'message' => $e->getMessage(),
            ]);

            $this->error("{$label} sync failed: {$e->getMessage()}");

            return 0;
        }
    }
}
