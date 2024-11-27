<?php

namespace Leolnid\Common\Listeners;

class LogDeploymentListener
{
    public function __construct()
    {
    }

    public function handle($event): void
    {
        \App\Services\TelegramLogger::info('Завершили обновление приложения');
        logger()->notice('Завершили обновление приложения');
    }
}
