<?php

namespace App\Console\Commands;

use App\Jobs\CalculateOrdersInBetweenJob;
use App\Console\Commands\BaseCommand;

class CalculateOrdersInBetween extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'listed-stock:orders-in-between
                            {--days=30 : Only verifications created in the last N days (0 = no limit)}
                            {--all : Recalc all in scope, not only backfill (orders_in_between 0/null)}
                            {--dispatch : Dispatch as queue job instead of running inline}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate orders_in_between for listed_stock_verification records (orders between previous and this verification).';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $daysBack = $days <= 0 ? null : $days;
        $backfillOnly = !$this->option('all');
        $dispatch = $this->option('dispatch');

        if ($dispatch) {
            CalculateOrdersInBetweenJob::dispatch($daysBack, $backfillOnly);
            $this->info('Dispatched CalculateOrdersInBetweenJob to the queue.');
            return 0;
        }

        $this->info('Running orders_in_between calculation...');
        $this->info('Days back: ' . ($daysBack === null ? 'all' : $daysBack));
        $this->info('Backfill only: ' . ($backfillOnly ? 'yes' : 'no'));

        $job = new CalculateOrdersInBetweenJob($daysBack, $backfillOnly);
        $job->handle();

        $this->info('Done. Check logs for updated count.');
        return 0;
    }
}
