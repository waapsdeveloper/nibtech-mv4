<?php

namespace App\Console;

use App\Console\Commands\BMProSyncOrders;
use App\Console\Commands\SupportSyncCommand;
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
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        // $schedule->command('tenant:cron')->everyMinute();
        // Staggered schedule to avoid connection spikes (see docs/DB_CONNECTION_ANALYSIS_LAST_WEEK.md).
        // Every-5-min commands at :01–:04; every-10-min at :05–:09.
        $schedule->command('price:handler')
            ->cron('1,11,21,31,41,51 * * * *') // ~every 10 min at :01, :11, …
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('refresh:latest')
            ->cron('2,7,12,17,22,27,32,37,42,47,52,57 * * * *') // every 5 min at :02, :07, …
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // Critical: new orders sync – no compromise (every 2 min)
        $schedule->command('refresh:new')
            ->everyTwoMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('refresh:orders')
            ->cron('3,8,13,18,23,28,33,38,43,48,53,58 * * * *') // every 5 min at :03, :08, …
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('refurbed:new')
            ->cron('4,9,14,19,24,29,34,39,44,49,54,59 * * * *') // every 5 min at :04, :09, …
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('refurbed:orders')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('refurbed:link-tickets')
            ->cron('5,15,25,35,45,55 * * * *') // ~every 10 min at :05, :15, …
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();
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
        $schedule->command('functions:ten')
            ->cron('6,16,26,36,46,56 * * * *') // ~every 10 min at :06, :16, …
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // Critical: BackMarket listings sync (runs refresh:new first then get_listings) – no compromise; revert to every 30 min
        $schedule->command('functions:thirty')
            ->everyThirtyMinutes()
            ->before(function () {
                echo '[' . now()->format('Y-m-d H:i:s') . "] 🔄 FIRING: functions:thirty\n";
            })
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // $schedule->command('backup:email')
        //     ->hourly()
        //     ->between('6:00', '02:00');

        $schedule->command('functions:daily')
            ->everyFourHours();

        $schedule->command('fetch:exchange-rates')
            ->hourly()
            ->between('6:00', '02:00');

        $schedule->command('api-request:process')
            ->cron('0,5,10,15,20,25,30,35,40,45,50,55 * * * *') // every 5 min at :00, :05, … (offset from others)
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('support:sync')
            ->cron('7,17,27,37,47,57 * * * *') // ~every 10 min at :07, :17, …
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('bmpro:orders')
            ->cron('8,18,28,38,48,58 * * * *') // ~every 10 min at :08, :18, …
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // V2: Marketplace Stock Sync (6-hour interval per marketplace with staggered scheduling)
        // Using optimized bulk sync for BackMarket (95% fewer API calls)
        $schedule->command('v2:marketplace:sync-stock-bulk --marketplace=1')
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

        $schedule->command('v2:marketplace:sync-stock --marketplace=4')
            ->everySixHours()
            ->at('03:00') // Refurbed at 3 AM (staggered)
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // Sync all other marketplaces every 6 hours starting at 6 AM
        $schedule->command('v2:marketplace:sync-stock')
            ->everySixHours()
            ->at('06:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // V2: Unified Order Sync - Daily Schedule
        // New orders sync - every 2 hours during business hours
        $schedule->command('v2:sync-orders --type=new')
            ->everyTwoHours()
            ->between('06:00', '22:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // Modified orders sync - daily at 2 AM
        $schedule->command('v2:sync-orders --type=modified')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // Care/replacement records sync - daily at 4 AM
        $schedule->command('v2:sync-orders --type=care')
            ->dailyAt('04:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // Incomplete orders sync - every 4 hours
        $schedule->command('v2:sync-orders --type=incomplete')
            ->everyFourHours()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        // queue:retry all removed from schedule (see docs/DB_CONNECTION_ANALYSIS_LAST_WEEK.md).
        // Retrying all failed jobs at once can spike connections. Run manually when needed:
        //   php artisan queue:retry all
        // or retry specific job IDs: php artisan queue:retry <id>
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
}
