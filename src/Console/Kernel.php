<?php

namespace Leolnid\Common\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Commands\DispatchQueueCheckJobsCommand;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Commands\ScheduleCheckHeartbeatCommand;

class Kernel extends BaseKernel
{
    public function schedule(Schedule $schedule): void
    {
        $schedule->command(ScheduleCheckHeartbeatCommand::class)->everyMinute();
        $schedule->command(DispatchQueueCheckJobsCommand::class)->everyMinute();
        $schedule->command(RunHealthChecksCommand::class)->everyMinute();
    }

    public function commands(ServiceProvider $provider): void
    {
        $this->load($provider, 'Leolnid\\Common\\Console', __DIR__);
    }
}
