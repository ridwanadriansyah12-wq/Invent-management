<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Hitung ulang ML (SS, ROP, Max) setiap hari jam 00:00
        $schedule->command('inventory:recalculate')->dailyAt('00:00');

        // Pemeriksaan berkala ROP dan pemicu email alert setiap jam
        $schedule->command('inventory:check-reorder')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
