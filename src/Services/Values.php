<?php

namespace Leolnid\Common\Services;

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
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class Values
{
    public function __construct(protected AmoCRMApiClient $client)
    {
    }

    /**
     * @throws AmoCRMoAuthApiException
     * @throws AmoCRMApiException
     * @throws Throwable
     * @throws AmoCRMMissedTokenException
     */
    public static function get(AmoCRMApiClient $client): array
    {
        $self = new static($client);

        return [
            'account' => $self->getCurrentAccount(),
            'custom_fields' => [
                'leads' => $self->getCustomFieldsAsArray(EntityTypesInterface::LEADS),
                'contacts' => $self->getCustomFieldsAsArray(EntityTypesInterface::CONTACTS),
                'companies' => $self->getCustomFieldsAsArray(EntityTypesInterface::COMPANIES),
                'customers' => $self->getCustomFieldsAsArray(EntityTypesInterface::CUSTOMERS),
            ],
            'bots' => $self->getBots(),
            'users' => $self->getUsers(),
            'pipelines' => $self->getPipelines(),
        ];
    }


    /**
     * @throws AmoCRMApiException
     * @throws AmoCRMoAuthApiException
     * @throws AmoCRMMissedTokenException
     * @throws Throwable
     */
    public function getCurrentAccount(): array
    {
        $account = $this->client->account()->getCurrent(['task_types']);

        return [
            ...Arr::except($account->toArray(), ['task_types', 'bots']),
            'task_types' => collect($account->getTaskTypes()->toArray())
                ->mapWithKeys(fn($el) => [$this->slug(data_get($el, 'code') ?: data_get($el, 'name')) => $el])
                ->toArray(),
        ];
    }

    protected function slug(string $name): string
    {
        return Str::slug(strtolower($name), '_');
    }

    public function getCustomFieldsAsArray(string $entity): array
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

    public function getCustomFields(string $entity): CustomFieldsCollection
    {
        try {
            return $this->client->customFields($entity)->get();
        } catch (Throwable $e) {
            logger()->warning("Ошибка при получении полей $entity - {$e->getMessage()}");
            return new CustomFieldsCollection;
        }
    }

    public function getBots()
    {

        try {
            $result = $this->client->getRequest()->get('private/ajax/v2/json/helpbot/',
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
    public function getUsers(): array
    {
        $result = [];

        /** @var UserModel $user */
        foreach ($this->client->users()->get() as $user) {
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
    public function getPipelines(): array
    {
        $result = [];

        /** @var PipelineModel $pipeline */
        foreach ($this->client->pipelines()->get() as $pipeline) {
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
}
