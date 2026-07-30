<?php

namespace Leolnid\Common\Console\Commands;

use Illuminate\Console\Command;
use Leolnid\Common\Events\DeploymentSuccessEvent;

class DeploymentSuccessCommand extends Command
{
    /** Ansible common-deploy вызывает `deploy:success`. */
    protected $signature = 'deploy:success';

    /** @var list<string> */
    protected $aliases = ['deployment:success'];

    public function handle(): void
    {
        event(new DeploymentSuccessEvent());
    }
}
