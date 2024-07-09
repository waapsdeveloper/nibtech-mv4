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
        // $schedule->command('refresh:latest')->everyMinute()->between('8:00', '24:00');
        $schedule->command('refresh:new')->everyTwoMinutes()->between('8:00', '24:00');
        $schedule->command('refresh:orders')->everyFiveMinutes()->between('8:00', '24:00');
        $schedule->command('functions:ten')->everyTenMinutes()->between('8:00', '24:00');
        $schedule->command('functions:thirty')->everyThirtyMinutes()->between('8:00', '24:00');
        $schedule->command('backup:email')->hourly()->between('8:00', '24:00');
        $schedule->command('functions:daily')->daily()->between('8:00', '24:00');
        $schedule->command('fetch:exchange-rates')->hourly()->between('8:00', '24:00');

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
