<?php

namespace App\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class ScheduleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(Schedule $schedule): void
    {
        // 每分钟执行一次日志记录任务
        $schedule->command('task:log-minute')->everyMinute();
        
        // 每天凌晨2点执行清理任务
        $schedule->command('task:daily-cleanup')->dailyAt('02:00');
    }
}