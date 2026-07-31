<?php

namespace Leolnid\Common\Services;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CustomFields\CustomFieldEnumsCollection;
use AmoCRM\Collections\CustomFields\CustomFieldsCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMApiPageNotAvailableException;
use AmoCRM\Exceptions\AmoCRMMissedTokenException;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use AmoCRM\Filters\CustomFieldsFilter;
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
            // Одинаковые имена → один slug; не затираем поля (в т.ч. type=file)
            if (isset($result[$slug])) {
                $slug = $slug . '_' . $value->getId();
            }

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

    /**
     * Все CF сущности с пагинацией (API: max 250/стр.; без nextPage теряются поздние поля, напр. type=file).
     */
    public function getCustomFields(string $entity): CustomFieldsCollection
    {
        try {
            $service = $this->client->customFields($entity);
            $page = $service->get((new CustomFieldsFilter())->setLimit(250)->setPage(1));
            $result = new CustomFieldsCollection();

            while ($page !== null) {
                foreach ($page as $field) {
                    $result->add($field);
                }

                if ($page->getNextPageLink() === null) {
                    break;
                }

                try {
                    $page = $service->nextPage($page);
                } catch (AmoCRMApiPageNotAvailableException) {
                    break;
                }
            }

            return $result;
        } catch (Throwable $e) {
            logger()->warning("Ошибка при получении полей $entity - {$e->getMessage()}");
            return new CustomFieldsCollection;
        }
    }

    public function getBots(): array
    {
        $bots = $this->getBotsViaV4Api();
        if ($bots !== []) {
            return $bots;
        }

        // Fallback: старый private endpoint (лимит ~100)
        try {
            $result = $this->client->getRequest()->get(
                'private/ajax/v2/json/helpbot/',
                ['count' => 500],
                ['X-Requested-With' => 'XMLHttpRequest']
            );
        } catch (Throwable) {
            return [];
        }

        return collect(Arr::get($result, '_embedded.salesbots'))
            ->map(fn ($arr) => [
                'id' => Arr::get($arr, 'id'),
                'name' => Arr::get($arr, 'name'),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Все salesbot через GET /api/v4/bots (пагинация до 250 на страницу).
     *
     * @return list<array{id: mixed, name: mixed}>
     */
    protected function getBotsViaV4Api(): array
    {
        $bots = [];
        $page = 1;

        try {
            do {
                $result = $this->client->getRequest()->get('api/v4/bots', [
                    'page' => $page,
                    'limit' => 250,
                ]);

                $items = Arr::get($result, '_embedded.items')
                    ?? Arr::get($result, '_embedded.bots')
                    ?? [];

                foreach ($items as $item) {
                    $bots[] = [
                        'id' => Arr::get($item, 'id'),
                        'name' => Arr::get($item, 'name'),
                    ];
                }

                $pageCount = (int) Arr::get($result, '_page_count', $page);
                $page++;
            } while ($page <= $pageCount && $items !== []);
        } catch (Throwable $e) {
            logger()->warning('Values: не удалось загрузить bots через api/v4/bots', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return $bots;
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
                $statuses[(string) $status->getId()] = [
                    'id' => $status->getId(),
                    'name' => $status->getName(),
                ];
            }

            // Системные этапы успеха/отказа — в каждой воронке, API иногда не отдаёт в getStatuses()
            $statuses = $this->ensureSystemStatuses($statuses);

            $result[$this->slug($pipeline->getName())] = [
                'id' => $pipeline->getId(),
                'name' => $pipeline->getName(),
                'is_archive' => $pipeline->getIsArchive(),
                'is_main' => $pipeline->getIsMain(),
                'statuses' => $statuses,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, array{id: int|string, name: string}>  $statuses
     * @return array<string, array{id: int|string, name: string}>
     */
    private function ensureSystemStatuses(array $statuses): array
    {
        $system = [
            '142' => ['id' => 142, 'name' => 'Успешно реализовано'],
            '143' => ['id' => 143, 'name' => 'Закрыто и не реализовано'],
        ];

        foreach ($system as $key => $status) {
            if (! isset($statuses[$key])) {
                $statuses[$key] = $status;
            }
        }

        return $statuses;
    }
}
