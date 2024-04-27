<?php
/**
 * Viacheslav Rodionov
 * viacheslav@rodionov.top
 * Date: 20.05.2022
 * Time: 19:45
 */

namespace Leolnid\Common\Services;

use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Exceptions\InvalidArgumentException;
use AmoCRM\Models\BaseApiModel;
use AmoCRM\Models\CompanyModel;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\CustomFieldsValues\BaseCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\CheckboxCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\DateTimeCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\MultitextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\SelectCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\TextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\UrlCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\CheckboxCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\DateTimeCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\SelectCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\TextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\UrlCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\CheckboxCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\DateTimeCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\SelectCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\TextCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\UrlCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Fielder
{
    public static function addIfNotNull(
        $value,
        BaseCustomFieldValuesModel $cf,
        CustomFieldsValuesCollection &$collection
    ): bool {
        if (is_null($value)) {
            return false;
        }
        $collection->add($cf);
        return true;
    }

    /**
     * @param  BaseApiModel|null  $entity
     * @return array|bool|float|int|object|string|null
     * @deprecated
     */
    public static function getPhone(?BaseApiModel $entity)
    {
        if (!$entity) {
            return null;
        }
        /** @var CompanyModel|ContactModel|LeadModel $entity */
        $customFields = $entity->getCustomFieldsValues();
        if (!$customFields) {
            return null;
        }
        $field = $customFields->getBy('fieldCode', 'PHONE');
        return self::getFromField($field);
    }

    public static function getFromField(?BaseCustomFieldValuesModel $field, $isEnum = false)
    {
        if (!$field) {
            return null;
        }
        $fieldValue = $field->getValues()->first();
        if ($isEnum) {
            return $fieldValue->getEnumId();
        }
        $value = $fieldValue->getValue();
        if ($field instanceof NumericCustomFieldValuesModel) {
            return +$value;
        }
        return $value;
    }

    private static function getValue(?BaseCustomFieldValuesModel $cf)
    {
        if (!$cf) {
            return null;
        }
        $values = $cf->getValues();
        $value = $values->first()->getValue();
        if (is_null($value)) {
            return null;
        }
        if ($cf instanceof NumericCustomFieldValuesModel) {
            return +$value;
        }
        return $value;
    }

    public static function getFromEntity(?BaseApiModel $entity, int $fieldId, bool $isEnum = false)
    {
        //Проверить наличие коллекции у энтитити??
        if (!$entity) {
            return null;
        }
        /** @var CompanyModel|ContactModel|LeadModel $entity */
        return Fielder::getFromCollection($entity->getCustomFieldsValues(), $fieldId, $isEnum);
    }

    public static function getFromCollection(
        ?CustomFieldsValuesCollection $collection,
        int $fieldId,
        bool $isEnum = false
    ) {
        if (!$collection) {
            return null;
        }
        $filed = $collection->getBy('fieldId', $fieldId);
        if (!$filed) {
            return null;
        }
        return Fielder::getFromField($filed, $isEnum);
    }

    /** @deprecated */
    public static function createNumeric(int $id, $value): NumericCustomFieldValuesModel
    {
        return (new NumericCustomFieldValuesModel())->setFieldId(+$id)->setValues((new NumericCustomFieldValueCollection())->add((new NumericCustomFieldValueModel())->setValue($value)));
    }

    /** @deprecated */
    public static function createText(int $id, ?string $value): TextCustomFieldValuesModel
    {
        return (new TextCustomFieldValuesModel())->setFieldId(+$id)->setValues((new TextCustomFieldValueCollection())->add((new TextCustomFieldValueModel())->setValue($value)));
    }

    /** @throws InvalidArgumentException
     * @deprecated
     */
    public static function createDateTime(int $id, ?int $value): DateTimeCustomFieldValuesModel
    {
        return (new DateTimeCustomFieldValuesModel())->setFieldId(+$id)->setValues((new DateTimeCustomFieldValueCollection())->add((new DateTimeCustomFieldValueModel())->setValue($value)));
    }

    public static function getCustomFieldValueFromEntity(BaseApiModel $entity, int $fieldId)
    {
        /** @var CompanyModel|ContactModel|LeadModel $entity */
        $cfCollection = $entity->getCustomFieldsValues();
        if (!$cfCollection) {
            return null;
        }
        $cf = $cfCollection->getBy('fieldId', $fieldId);
        return Fielder::getValue($cf);
    }

    public static function makeNumeric(?int $value, int $fieldId): NumericCustomFieldValuesModel
    {
        return (new NumericCustomFieldValuesModel())->setFieldId($fieldId)->setValues((new NumericCustomFieldValueCollection())->add((new NumericCustomFieldValueModel)->setValue($value)));
    }

    public static function makeText(?string $value, int $fieldId): TextCustomFieldValuesModel
    {
        return (new TextCustomFieldValuesModel())->setFieldId($fieldId)->setValues((new TextCustomFieldValueCollection())->add((new TextCustomFieldValueModel)->setValue($value)));
    }

    public static function makeUrl(?string $value, int $fieldId): UrlCustomFieldValuesModel
    {
        return (new UrlCustomFieldValuesModel())->setFieldId($fieldId)->setValues((new UrlCustomFieldValueCollection())->add((new UrlCustomFieldValueModel)->setValue($value)));
    }

    public static function makeDateTime(?Carbon $value, int $fieldId): DateTimeCustomFieldValuesModel
    {
        return (new DateTimeCustomFieldValuesModel())->setFieldId($fieldId)->setValues((new DateTimeCustomFieldValueCollection())->add((new DateTimeCustomFieldValueModel)->setValue($value ? $value->getTimestamp() : 0)));
    }

    public static function makeCheckbox(?bool $value, int $fieldId): CheckboxCustomFieldValuesModel
    {
        return (new CheckboxCustomFieldValuesModel())->setFieldId($fieldId)->setValues((new CheckboxCustomFieldValueCollection())->add((new CheckboxCustomFieldValueModel)->setValue($value)));
    }

    public static function makeSelect(int $enumId, int $fieldId): SelectCustomFieldValuesModel
    {
        return (new SelectCustomFieldValuesModel())->setFieldId($fieldId)->setValues((new SelectCustomFieldValueCollection())->add((new SelectCustomFieldValueModel)->setEnumId($enumId)));
    }

    public static function getCollectValuesFromMultitextField(MultitextCustomFieldValuesModel $field)
    {

    }

    /**
     * @param  array  $phones
     * @param  ContactModel|CompanyModel|null  $entity
     * @return MultitextCustomFieldValuesModel
     */
    public static function makePhoneField(array $phones, ?BaseApiModel $entity = null): MultitextCustomFieldValuesModel
    {
        //TODO Менять 8 в начале на 7 если номер российский
        $phones = collect($phones)->map(fn($phone) => (string) Str::of($phone)->replaceMatches('/[^0-9]++/', ''));
        if ($entity) {
            $phones = $phones->merge(Fielder::getPhones($entity))->unique();
        }

        $values = (new MultitextCustomFieldValueCollection());

        foreach ($phones as $phone) {
            if ($phone) {
                $values->add((new MultitextCustomFieldValueModel())->setValue($phone));
            }
        }

        return (new MultitextCustomFieldValuesModel())->setFieldCode('PHONE')->setValues($values);
    }

    /**
     * @param  CompanyModel|ContactModel  $contact
     */
    public static function getPhones(BaseApiModel $contact): ?Collection
    {
        $cf = $contact->getCustomFieldsValues();
        if (!$cf) {
            return null;
        }
        $phoneField = $cf->getBy('fieldCode', 'PHONE');
        if (!$phoneField) {
            return null;
        }
        $values = $phoneField->getValues();
        $phones = collect();
        /** @var MultitextCustomFieldValueModel $value */
        foreach ($values as $value) {
            $phones->add((string) Str::of($value->getValue())->replaceMatches('/[^0-9]++/', ''));
        }
        return $phones;
    }

    public static function makeEmailField(array $emails, ?BaseApiModel $entity = null): MultitextCustomFieldValuesModel
    {
        if ($entity) {
            $emails = collect($emails)->merge(Fielder::getEmails($entity))->unique();
        }

        $values = (new MultitextCustomFieldValueCollection());

        foreach ($emails as $email) {
            if ($email) {
                $values->add((new MultitextCustomFieldValueModel())->setValue($email));
            }
        }

        return (new MultitextCustomFieldValuesModel())->setFieldCode('EMAIL')->setValues($values);
    }

    public static function getEmails($contact): ?Collection
    {
        $cf = $contact->getCustomFieldsValues();
        if (!$cf) {
            return null;
        }
        $phoneField = $cf->getBy('fieldCode', 'EMAIL');
        if (!$phoneField) {
            return null;
        }
        $values = $phoneField->getValues();
        $emails = collect();
        /** @var MultitextCustomFieldValueModel $value */
        foreach ($values as $value) {
            $emails->add($value->getValue());
        }
        return $emails;
    }

}

