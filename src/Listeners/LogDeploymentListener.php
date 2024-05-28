<?php

namespace Leolnid\Common\Listeners;

class LogDeploymentListener
{
    public function __construct()
    {
    }

    public function handle($event): void
    {
        logger()->channel('telegram')->notice('Завершили обновление приложения');
        logger()->notice('Завершили обновление приложения');
    }
}
