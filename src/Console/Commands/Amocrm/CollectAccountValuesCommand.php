<?php

namespace Leolnid\Common\Console\Commands\Amocrm;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMMissedTokenException;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Leolnid\Common\Services\Credentials;
use Leolnid\Common\Services\Values;
use Throwable;

class CollectAccountValuesCommand extends Command
{
    protected $signature = 'amocrm:collect-account-values {domain?}';

    public function handle(): void
    {
        try {
            $this->info('Начали процесс получения данных');

            $result = Values::get($this->client());
            $export = $this->toPhpFile($result);

            if (is_null($this->argument('domain')))
                File::put(base_path("config/amocrm/values.php"), "<?php\n\n\nreturn $export;");
            else {
                $domain = Str::replace('.', '_', $this->argument('domain'));
                File::put(base_path("config/amocrm/$domain/values.php"), "<?php\n\n\nreturn $export;");
            }
            $this->info('Успешно получили и сохранили данные аккаунта: ' . data_get($result, 'account.name'));
        } catch (Throwable $e) {
            $this->error('Произошла ошибка при получении данных аккаунта: ' . $e->getMessage());
        }
    }

    /**
     * @throws Throwable
     */
    protected function client(): AmoCRMApiClient
    {
        return Credentials::getApiClient($this->argument('domain'));
    }

    protected function toPhpFile(array $result): string|array|null
    {
        $export = var_export($result, true);
        $patterns = [
            "/array \(/" => '[',
            "/^([ ]*)\)(,?)$/m" => '$1]$2',
            "/=>[ ]?\n[ ]+\[/" => '=> [',
            "/([ ]*)(\'[^\']+\') => ([\[\'])/" => '$1$2 => $3',
        ];
        return preg_replace(array_keys($patterns), array_values($patterns), $export);
    }

    /**
     * @throws AmoCRMApiException
     * @throws AmoCRMoAuthApiException
     * @throws AmoCRMMissedTokenException
     * @throws Throwable
     */
    protected function getCurrentAccount(): array
    {
        $account = $this->client()->account()->getCurrent(['task_types']);

        return [
            ...Arr::except($account->toArray(), ['task_types', 'bots']),
            'task_types' => collect($account->getTaskTypes()->toArray())
                ->mapWithKeys(fn($el) => [$this->slug(data_get($el, 'code') ?: data_get($el, 'name')) => $el])
                ->toArray(),
        ];
    }
}
