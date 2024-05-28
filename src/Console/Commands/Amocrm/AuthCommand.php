<?php

namespace Leolnid\Common\Console\Commands\Amocrm;

use Illuminate\Console\Command;
use Leolnid\Common\Services\Credentials;
use Throwable;

class AuthCommand extends Command
{
    protected $signature = 'amocrm:auth {domain?}';

    public function handle(): void
    {
        $code = $this->ask('Введите код авторизации');

        try {
            $this->info('Начали процесс получения токена');
            Credentials::getAndSaveToken($code, $this->argument('domain'));
            $this->info('Успешно получили и сохранили токен');
        } catch (Throwable $e) {
            $this->error('Произошла ошибка при получении токена: '.$e->getMessage());
        }
    }
}
