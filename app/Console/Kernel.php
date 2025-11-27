<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
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
            ->between('6:00', '24:00')
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
            ->between('6:00', '24:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();
        $schedule->command('refurbed:new')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('refurbed:orders')
            ->hourly()
            ->between('6:00', '24:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('refurbed:update-stock')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();
        $schedule->command('refurbed:create-labels')
            ->everyTenMinutes()
            ->between('6:00', '24:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();
        $schedule->command('functions:ten')
            ->everyTenMinutes()
            ->between('6:00', '24:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('functions:thirty')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('backup:email')
            ->hourly()
            ->between('6:00', '24:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('functions:daily')
            ->everyFourHours()
            ->between('6:00', '24:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground();

        $schedule->command('fetch:exchange-rates')
            ->hourly()
            ->between('6:00', '24:00')
            ->withoutOverlapping()
            ->onOneServer()
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
