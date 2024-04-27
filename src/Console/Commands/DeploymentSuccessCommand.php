<?php

namespace Leolnid\Common\Console\Commands;

use Illuminate\Console\Command;
use Leolnid\Common\Events\DeploymentSuccessEvent;

class DeploymentSuccessCommand extends Command
{
    protected $signature = 'deployment:success';

    public function handle(): void
    {
        event(new DeploymentSuccessEvent());
    }
}
