<?php
/**
 * Viacheslav Rodionov
 * viacheslav@rodionov.top
 * Date: 16.06.2023
 * Time: 0:51
 */

namespace Leolnid\Common\Services;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMMissedTokenException;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use AmoCRM\Exceptions\InvalidArgumentException;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\Customers\CustomerModel;
use AmoCRM\Models\Interfaces\HasIdInterface;
use AmoCRM\Models\LeadModel;
use AmoCRM\Models\NoteModel;
use AmoCRM\Models\NoteType\CommonNote;
use AmoCRM\Models\NoteType\ServiceMessageNote;
use AmoCRM\Models\NoteType\SmsOutNote;
use Throwable;

class Notes
{
    private AmoCRMApiClient $client;

    /**
     * @param AmoCRMApiClient|null $apiClient
     * @throws Throwable
     */
    function __construct(AmoCRMApiClient $apiClient = null)
    {
        $this->client = $apiClient ?? Credentials::getApiClient();
    }

    /**
     * @return SmsOutNote
     * @throws InvalidArgumentException
     * @throws AmoCRMApiException
     * @throws AmoCRMMissedTokenException
     * @throws AmoCRMoAuthApiException
     */
    public function addSmsOutNote(string $entityType, int|HasIdInterface $entityId, string $text, ?string $phone = null): NoteModel
    {
        if (!is_integer($entityId)) {
            $entityId = $entityId->getId();
        }
        return $this->client->notes($entityType)->addOne(
            (new SmsOutNote())
                ->setEntityId($entityId)
                ->setText($text)
                ->setPhone($phone)
        );
    }

    /**
     * @return ServiceMessageNote
     * @throws InvalidArgumentException
     * @throws AmoCRMApiException
     * @throws AmoCRMMissedTokenException
     * @throws AmoCRMoAuthApiException
     */
    public function addToContact(int|ContactModel $contactId, string $text, bool $common = false): NoteModel
    {
        if ($common) return $this->addCommonNote(EntityTypesInterface::CONTACTS, $contactId, $text);
        return $this->addServiceNote(EntityTypesInterface::CONTACTS, $contactId, $text);
    }

    /**
     * @return ServiceMessageNote
     * @throws AmoCRMApiException
     * @throws AmoCRMMissedTokenException
     * @throws AmoCRMoAuthApiException
     * @throws InvalidArgumentException
     */
    public function addServiceNote(string $entityType, int|HasIdInterface $entityId, string $text): NoteModel
    {
        if (!is_integer($entityId)) {
            $entityId = $entityId->getId();
        }

        return $this->client->notes($entityType)->addOne((new ServiceMessageNote())
            ->setEntityId($entityId)
            ->setService(config('amocrm.common.note_prefix') ?? "Интеграция")
            ->setText($text)
        );
    }

    /**
     * @return ServiceMessageNote
     * @throws InvalidArgumentException
     * @throws AmoCRMApiException
     * @throws AmoCRMMissedTokenException
     * @throws AmoCRMoAuthApiException
     */
    public function addToCompany(int|ContactModel $companyId, string $text, bool $common = false): NoteModel
    {
        if ($common) return $this->addCommonNote(EntityTypesInterface::COMPANIES, $companyId, $text);
        return $this->addServiceNote(EntityTypesInterface::COMPANIES, $companyId, $text);
    }

    /**
     * @return ServiceMessageNote
     * @throws InvalidArgumentException
     * @throws AmoCRMApiException
     * @throws AmoCRMMissedTokenException
     * @throws AmoCRMoAuthApiException
     */
    public function addToLead(int|LeadModel $leadId, string $text, bool $common = false): NoteModel
    {
        if ($common) return $this->addCommonNote(EntityTypesInterface::LEADS, $leadId, $text);
        return $this->addServiceNote(EntityTypesInterface::LEADS, $leadId, $text);
    }

    /**
     * @return ServiceMessageNote
     * @throws AmoCRMApiException
     * @throws AmoCRMMissedTokenException
     * @throws AmoCRMoAuthApiException
     * @throws InvalidArgumentException
     */
    public function addCommonNote(string $entityType, int|HasIdInterface $entityId, string $text): NoteModel
    {
        if (!is_integer($entityId)) {
            $entityId = $entityId->getId();
        }

        return $this->client->notes($entityType)->addOne((new CommonNote())
            ->setEntityId($entityId)
            ->setText($text)
        );
    }

    /**
     * @return ServiceMessageNote
     * @throws InvalidArgumentException
     * @throws AmoCRMApiException
     * @throws AmoCRMMissedTokenException
     * @throws AmoCRMoAuthApiException
     */
    public function addToCustomer(int|CustomerModel $customerId, string $text, bool $common = false): NoteModel
    {
        if ($common) return $this->addCommonNote(EntityTypesInterface::CUSTOMERS, $customerId, $text);
        return $this->addServiceNote(EntityTypesInterface::CUSTOMERS, $customerId, $text);
    }

}
