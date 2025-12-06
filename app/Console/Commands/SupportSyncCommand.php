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
    protected $signature = <<<'SIG'
support:sync
    {--since= : Override the default lookback timestamp (Y-m-d-H-i)}
    {--source=* : Limit to a specific channel (backmarket, refurbed)}
    {--care-state= : Filter Back Market Care cases by state}
    {--care-priority= : Filter Back Market Care cases by priority}
    {--care-topic= : Filter Back Market Care cases by topic}
    {--care-orderline= : Filter Back Market Care cases by orderline reference}
    {--care-order-id= : Filter Back Market Care cases by order id}
    {--care-last-id= : Resume Care sync from this cursor id}
    {--care-page-size= : Override Care API page size (default 50)}
    {--care-extra= : Additional query string appended to the Care API request}
SIG;

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
        $careParams = $this->buildCareParams();

        $synced = 0;

        if (in_array('backmarket', $sources, true)) {
            $synced += $this->runSync(function () use ($since, $careParams) {
                $options = [];
                if ($since) {
                    $options['since'] = $since;
                }

                if (! empty($careParams)) {
                    $options['params'] = $careParams;
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

    protected function buildCareParams(): array
    {
        $params = [];
        $mapping = [
            'care-state' => 'state',
            'care-priority' => 'priority',
            'care-topic' => 'topic',
            'care-orderline' => 'orderline',
            'care-order-id' => 'order_id',
            'care-last-id' => 'last_id',
        ];

        foreach ($mapping as $option => $key) {
            $value = $this->option($option);
            if ($value === null || $value === '') {
                continue;
            }

            $params[$key] = $value;
        }

        $pageSize = $this->option('care-page-size');
        if ($pageSize !== null && $pageSize !== '') {
            $size = (int) $pageSize;
            $size = max(1, min(200, $size));
            $params['page_size'] = $size;
        }

        $extra = $this->option('care-extra');
        if ($extra) {
            parse_str(ltrim($extra, '?&'), $extraParams);
            $extraParams = array_filter($extraParams, fn ($value) => $value !== null && $value !== '');
            if (! empty($extraParams)) {
                $params = array_merge($params, $extraParams);
            }
        }

        return $params;
    }
}
