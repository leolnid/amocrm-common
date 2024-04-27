<?php

namespace Leolnid\Common\Events;

use Illuminate\Foundation\Events\Dispatchable;

class DeploymentSuccessEvent
{
    use Dispatchable;

    public function __construct()
    {
    }
}
