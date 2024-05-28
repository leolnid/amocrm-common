<?php

namespace Leolnid\Common\Services\Finder;

use AmoCRM\Collections\CompaniesCollection;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\EntitiesServices\Companies;
use AmoCRM\EntitiesServices\Contacts;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMApiNoContentException;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use AmoCRM\Filters\ContactsFilter;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\CompanyModel;
use AmoCRM\Models\ContactModel;
use Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Leolnid\Common\Services\Fielder;

class EntityFinder
{
    public function __construct(
        protected readonly Companies|Contacts $service,
    ) {
    }

    /**
     * @throws AmoCRMoAuthApiException
     * @throws AmoCRMApiException
     */
    public function find(string|array $query): CompaniesCollection|ContactsCollection
    {
        $result = $this->getCollection();

        foreach (Arr::wrap($query) as $value) {
            try {
                $entities = $this->service->get(
                    (new ContactsFilter)->setQuery($this->phoneWithoutCode($value)),
                    [EntityTypesInterface::LEADS, EntityTypesInterface::COMPANY]
                );

                /** @var ContactModel|CompanyModel $entity */
                foreach ($entities as $entity) {
                    /** @var Collection $phones */
                    $phones = Fielder::getPhones($entity)?->map(fn ($phone) => $this->formatPhone($phone));
                    $emails = Fielder::getEmails($entity)?->map(fn ($email) => $this->formatEmail($email));

                    // Возвращает индекс найденного элемента, от 0 до N
                    // ВАЖНО: Если не нашел - вернет false. Нужна строгая проверка
                    if ($phones?->search($this->formatPhone($value)) === false &&
                        $emails?->search($this->formatEmail($value)) === false) {
                        continue;
                    }

                    $result->add($entity);
                }
            } catch (AmoCRMApiNoContentException $e) {
            }
        }

        return $result;
    }

    protected function getCollection(): ContactsCollection|CompaniesCollection
    {
        return $this->service instanceof Contacts ? new ContactsCollection() : new CompaniesCollection();
    }

    protected function phoneWithoutCode(mixed $queryItem): mixed
    {
        if (Str::contains($queryItem, '@')) {
            return $queryItem;
        }

        $queryItem = $this->formatPhone($queryItem);

        if (Str::startsWith($queryItem, '8')) {
            return Str::substr($queryItem, 1);
        }
        if (Str::startsWith($queryItem, '+7')) {
            return Str::substr($queryItem, 2);
        }
        if (Str::startsWith($queryItem, '7')) {
            return Str::substr($queryItem, 1);
        }

        return $queryItem;
    }

    private function formatPhone(?string $phone): ?string
    {
        if (is_null($phone)) {
            return null;
        }

        $phone = Str::of($phone)->replaceMatches('/[^0-9]++/', '');

        if ($phone->startsWith('8') || $phone->startsWith('7')) {
            return '+7'.$phone->substr(1);
        }

        return (string) $phone;
    }

    protected function formatEmail(?string $query): ?string
    {
        return $query;
    }
}
