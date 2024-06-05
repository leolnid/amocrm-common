<?php

namespace Leolnid\Common\Console\Commands\Amocrm;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CustomFields\CustomFieldEnumsCollection;
use AmoCRM\Collections\CustomFields\CustomFieldsCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMMissedTokenException;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\CustomFields\CustomFieldModel;
use AmoCRM\Models\CustomFields\EnumModel;
use AmoCRM\Models\Leads\Pipelines\PipelineModel;
use AmoCRM\Models\Leads\Pipelines\Statuses\StatusModel;
use AmoCRM\Models\UserModel;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Leolnid\Common\Services\Credentials;
use Throwable;

class CollectAccountValuesCommand extends Command
{
    protected $signature = 'amocrm:collect-account-values {domain?}';

    public function handle(): void
    {
        try {
            $this->info('Начали процесс получения данных');

            $result = [
                'account' => $this->getCurrentAccount(),
                'custom_fields' => [
                    'leads' => $this->getCustomFieldsAsArray(EntityTypesInterface::LEADS),
                    'contacts' => $this->getCustomFieldsAsArray(EntityTypesInterface::CONTACTS),
                    'companies' => $this->getCustomFieldsAsArray(EntityTypesInterface::COMPANIES),
                    'customers' => $this->getCustomFieldsAsArray(EntityTypesInterface::CUSTOMERS),
                ],
                'bots' => $this->getBots(),
                'users' => $this->getUsers(),
                'pipelines' => $this->getPipelines(),
            ];

            $export = $this->toPhpFile($result);

            if (is_null($this->argument('domain')))
                File::put(base_path("config/amocrm/values.php"), "<?php\n\n\nreturn $export;");
            else {
                $domain = Str::replace('.', '_', $this->argument('domain'));
                File::put(base_path("config/amocrm/$domain/values.php"), "<?php\n\n\nreturn $export;");
            }
            $this->info('Успешно получили и сохранили данные аккаунта: ' . data_get($result, 'account.name'));
        } catch (Throwable $e) {
            dd($e);
            $this->error('Произошла ошибка при получении данных аккаунта: ' . $e->getMessage());
        }
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

    /**
     * @throws Throwable
     */
    protected function client(): AmoCRMApiClient
    {
        return Credentials::getApiClient($this->argument('domain'));
    }

    protected function slug(string $name): string
    {
        return Str::slug(strtolower($name), '_');
    }

    protected function getCustomFieldsAsArray(string $entity): array
    {
        $result = [];

        /** @var CustomFieldModel $value */
        foreach ($this->getCustomFields($entity) as $value) {
            $slug = $this->slug($value->getCode() ?: $value->getName());

            $resultValue = [
                'id' => $value->getId(),
                'name' => $value->getName(),
                'code' => $value->getCode(),
                'type' => $value->getType(),
            ];

            if (method_exists($value, 'getEnums') && $value->getEnums() instanceof CustomFieldEnumsCollection)
                $resultValue['options'] = collect($value->getEnums()->getIterator())
                    ->mapWithKeys(fn(EnumModel $model) => [Str::slug($model->getValue(), '_') => $model->getId()])
                    ->toArray();

            $result[$slug] = $resultValue;
        }

        return $result;
    }

    protected function getCustomFields(string $entity): CustomFieldsCollection
    {
        try {
            return $this->client()->customFields($entity)->get();
        } catch (Throwable $e) {
            $this->warn("Ошибка при получении полей $entity - {$e->getMessage()}");
            return new CustomFieldsCollection;
        }
    }

    protected function getBots()
    {

        try {
            $result = $this->client()->getRequest()->get('private/ajax/v2/json/helpbot/',
                ['count' => 100],
                ['X-Requested-With' => 'XMLHttpRequest']);
        } catch (Throwable $e) {
            return [];
        }

        return collect(Arr::get($result, '_embedded.salesbots'))
            ->map(fn($arr) => [
                'id' => Arr::get($arr, 'id'),
                'name' => Arr::get($arr, 'name'),
            ])
            ->values()
            ->toArray();
    }

    /**
     * @throws AmoCRMoAuthApiException
     * @throws AmoCRMApiException
     * @throws AmoCRMMissedTokenException
     */
    private function getUsers(): array
    {
        $result = [];

        /** @var UserModel $user */
        foreach ($this->client()->users()->get() as $user) {
            $result[$this->slug($user->getEmail())] = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'lang' => $user->getLang(),
                'amojo' => $user->getAmojoId(),
                'uuid' => $user->getUuid(),
            ];
        }

        return $result;
    }

    /**
     * @throws AmoCRMApiException
     * @throws AmoCRMoAuthApiException
     * @throws AmoCRMMissedTokenException
     */
    private function getPipelines(): array
    {
        $result = [];

        /** @var PipelineModel $pipeline */
        foreach ($this->client()->pipelines()->get() as $pipeline) {
            $statuses = [];

            /** @var StatusModel $status */
            foreach ($pipeline->getStatuses() as $status) {
                $statuses[$this->slug($status->getName())] = [
                    'id' => $status->getId(),
                    'name' => $status->getName(),
                ];
            }

            $result[$this->slug($pipeline->getName())] = [
                'id' => $pipeline->getId(),
                'name' => $pipeline->getName(),
                'is_archive' => $pipeline->getIsArchive(),
                'is_main' => $pipeline->getIsMain(),

                'statuses' => $statuses
            ];
        }

        return $result;
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
        $export = preg_replace(array_keys($patterns), array_values($patterns), $export);
        return $export;
    }
}
