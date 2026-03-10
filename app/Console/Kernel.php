<?php

namespace App\Console;

use App\Console\Commands\BMProSyncOrders;
use App\Console\Commands\SupportSyncCommand;
use App\Models\Marketplace_model;
use App\Jobs\ExecuteArtisanCommandJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        SupportSyncCommand::class,
        BMProSyncOrders::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * When QUEUE_CONNECTION=redis we run the same codebase as the "scheduler" instance:
     * we do not run commands directly; we push all scheduled work as Redis jobs so the
     * Horizon instance (other server) runs them. When queue is not redis we run commands
     * directly as usual.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        // $schedule->command('tenant:cron')->everyMinute();
        // Staggered schedule to avoid connection spikes (see docs/DB_CONNECTION_ANALYSIS_LAST_WEEK.md).
        // Every-5-min commands at :01–:04; every-10-min at :05–:09.
        $this->scheduleCommandOrJob($schedule, 'price:handler')
            ->cron('1,11,21,31,41,51 * * * *') // ~every 10 min at :01, :11, …
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $this->scheduleCommandOrJob($schedule, 'refresh:latest')
            ->cron('2,7,12,17,22,27,32,37,42,47,52,57 * * * *') // every 5 min at :02, :07, …
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // Critical: new/pending orders sync – every 2 min (uses getNewOrders response directly, no per-order getOneOrder).
        $this->scheduleCommandOrJob($schedule, 'refresh:new')
            ->cron('*/2 * * * *')
            ->before(function () {
                echo '[' . now()->format('Y-m-d H:i:s') . "] 🔄 FIRING: refresh:new\n";
            })
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $this->scheduleCommandOrJob($schedule, 'refresh:orders')
            ->cron('3,8,13,18,23,28,33,38,43,48,53,58 * * * *') // every 5 min at :03, :08, …
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        if ($this->hasRefurbedMarketplace()) {
            $this->scheduleCommandOrJob($schedule, 'refurbed:new')
                ->cron('4,9,14,19,24,29,34,39,44,49,54,59 * * * *') // every 5 min at :04, :09, …
                ->withoutOverlapping()
                ->onOneServer()
                ->runInBackground();

            $this->scheduleCommandOrJob($schedule, 'refurbed:orders')
                ->hourly()
                ->withoutOverlapping()
                ->onOneServer();

            $this->scheduleCommandOrJob($schedule, 'refurbed:link-tickets')
                ->cron('5,15,25,35,45,55 * * * *') // ~every 10 min at :05, :15, …
                ->withoutOverlapping()
                ->onOneServer()
                ->runInBackground();
        }
        // $schedule->command('refurbed:update-stock')
        //     ->everyThirtyMinutes()
        //     ->withoutOverlapping()
        //     ->onOneServer()
        //     ->runInBackground();
        // $schedule->command('refurbed:create-labels')
        //     ->everyTenMinutes()
        //     ->between('6:00', '02:00')
        //     ->withoutOverlapping()
        //     ->onOneServer()
        //     ->runInBackground();
        $this->scheduleCommandOrJob($schedule, 'functions:ten')
            ->cron('6,16,26,36,46,56 * * * *') // ~every 10 min at :06, :16, …
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // Critical: BackMarket listings sync – run via queue so scheduler stays light; dedicated listings-sync worker runs it
        $schedule->job(
            (new ExecuteArtisanCommandJob('functions:thirty', []))->onQueue('listings-sync')
        )
            ->hourly()
            ->before(function () {
                echo '[' . now()->format('Y-m-d H:i:s') . "] 🔄 QUEUED: functions:thirty (listings-sync)\n";
            })
            ->withoutOverlapping()
            ->onOneServer();

        // $schedule->command('backup:email')
        //     ->hourly()
        //     ->between('6:00', '02:00');

        $this->scheduleCommandOrJob($schedule, 'functions:daily')
            ->everyFourHours();

        $this->scheduleCommandOrJob($schedule, 'fetch:exchange-rates')
            ->hourly()
            ->between('6:00', '02:00');

        $this->scheduleCommandOrJob($schedule, 'api-request:process')
            ->cron('0,5,10,15,20,25,30,35,40,45,50,55 * * * *') // every 5 min at :00, :05, … (offset from others)
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $this->scheduleCommandOrJob($schedule, 'support:sync')
            ->cron('7,17,27,37,47,57 * * * *') // ~every 10 min at :07, :17, …
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $this->scheduleCommandOrJob($schedule, 'bmpro:orders')
            ->cron('8,18,28,38,48,58 * * * *') // ~every 10 min at :08, :18, …
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // V2: Marketplace Stock Sync (6-hour interval per marketplace with staggered scheduling)
        // Using optimized bulk sync for BackMarket (95% fewer API calls)
        $this->scheduleCommandOrJob($schedule, 'v2:marketplace:sync-stock-bulk', ['--marketplace' => '1'])
            ->everySixHours()
            ->at('00:00') // Back Market at midnight
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // Keep old command for other marketplaces (deprecated - will be replaced)
        // $schedule->command('v2:marketplace:sync-stock --marketplace=1')
        //     ->everySixHours()
        //     ->at('00:00')
        //     ->withoutOverlapping()
        //     ->onOneServer()
        //     ->runInBackground();

        if ($this->hasRefurbedMarketplace()) {
            $this->scheduleCommandOrJob($schedule, 'v2:marketplace:sync-stock', ['--marketplace' => '4'])
                ->everySixHours()
                ->at('03:00') // Refurbed at 3 AM (staggered)
                ->withoutOverlapping()
                ->onOneServer()
                ->runInBackground();
        }

        // Sync all other marketplaces every 6 hours starting at 6 AM
        $this->scheduleCommandOrJob($schedule, 'v2:marketplace:sync-stock')
            ->everySixHours()
            ->at('06:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // V2: Unified Order Sync – DISABLED (V1 is single source of truth for orders; v2 can override order data)
        // Re-enable when V2 order sync is trusted. Manual run: php artisan v2:sync-orders --type=new|modified|care|incomplete
        // $schedule->command('v2:sync-orders --type=new')
        //     ->everyTwoHours()
        //     ->between('06:00', '22:00')
        //     ->withoutOverlapping()
        //     ->onOneServer()
        //     ->runInBackground();

        // $schedule->command('v2:sync-orders --type=modified')
        //     ->dailyAt('02:00')
        //     ->withoutOverlapping()
        //     ->onOneServer()
        //     ->runInBackground();

        // $schedule->command('v2:sync-orders --type=care')
        //     ->dailyAt('04:00')
        //     ->withoutOverlapping()
        //     ->onOneServer()
        //     ->runInBackground();

        // $schedule->command('v2:sync-orders --type=incomplete')
        //     ->everyFourHours()
        //     ->withoutOverlapping()
        //     ->onOneServer()
        //     ->runInBackground();

        // Listed stock verification: calculate orders_in_between (orders between previous and this verification)
        $this->scheduleCommandOrJob($schedule, 'listed-stock:orders-in-between', ['--days' => '30'])
            ->everySixHours()
            ->withoutOverlapping(90)
            ->onOneServer()
            ->runInBackground();

        // queue:retry all removed from schedule (see docs/DB_CONNECTION_ANALYSIS_LAST_WEEK.md).
        // Retrying all failed jobs at once can spike connections. Run manually when needed:
        //   php artisan queue:retry all
        // or retry specific job IDs: php artisan queue:retry <id>
    }

    /**
     * Schedule a command to run directly or as a Redis job depending on queue connection.
     * When QUEUE_CONNECTION=redis we push ExecuteArtisanCommandJob so Horizon runs it;
     * otherwise we schedule the artisan command to run directly on this instance.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @param  string  $command  Artisan command signature (e.g. 'refresh:new' or 'v2:marketplace:sync-stock-bulk')
     * @param  array  $options  Command options (e.g. ['--marketplace' => '1'])
     * @param  string|null  $queue  Queue name when using Redis (e.g. 'listings-sync'); null = default queue
     * @return \Illuminate\Console\Scheduling\Event
     */
    protected function scheduleCommandOrJob(Schedule $schedule, string $command, array $options = [], ?string $queue = null)
    {
        if (config('queue.default') === 'redis') {
            $job = new ExecuteArtisanCommandJob($command, $options);
            if ($queue !== null) {
                $job->onQueue($queue);
            }
            return $schedule->job($job);
        }
        return $schedule->command($command, $options);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    private function hasRefurbedMarketplace(): bool
    {
        return Marketplace_model::where('id', 4)
            ->orWhere('name', 'like', '%refurbed%')
            ->exists();
    }
}
