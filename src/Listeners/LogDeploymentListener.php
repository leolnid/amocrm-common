<?php

namespace Leolnid\Common\Listeners;

use Leolnid\Common\Services\TelegramLogger;

class LogDeploymentListener
{
    public function __construct()
    {
    }

    public function handle($event): void
    {
        TelegramLogger::info('Завершили обновление приложения');
        logger()->notice('Завершили обновление приложения');
    }
}
