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
        $schedule->command('price:handler')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('refresh:latest')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('refresh:new')
            ->everyTwoMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('refresh:orders')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();
        $schedule->command('refurbed:new')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('refurbed:orders')
            ->hourly();
            // ->between('6:00', '02:00')
            // ->withoutOverlapping()
            // ->onOneServer()
            // ->runInBackground();
        $schedule->command('refurbed:link-tickets')
            ->everyTenMinutes()
            ->withoutOverlapping()
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
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();
        $schedule->command('functions:thirty')
            ->hourly();
            // ->withoutOverlapping()
            // ->onOneServer()
            // ->runInBackground();

        $schedule->command('backup:email')
            ->hourly()
            ->between('6:00', '02:00');
            // ->withoutOverlapping()
            // ->onOneServer()
            // ->runInBackground();

        $schedule->command('functions:daily')
            ->everyFourHours();
            // ->between('6:00', '02:00')
            // ->withoutOverlapping()
            // ->onOneServer()
            // ->runInBackground();
        $schedule->command('fetch:exchange-rates')
            ->hourly()
            ->between('6:00', '02:00');
            // ->withoutOverlapping()
            // ->onOneServer()
            // ->runInBackground();

        $schedule->command('api-request:process')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('support:sync')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('bmpro:orders')
            ->everyTenMinutes()
            ->withoutOverlapping()
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

        // Retry failed jobs - every 30 minutes
        $schedule->command('queue:retry all')
            ->everyThirtyMinutes()
            ->runInBackground();
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
