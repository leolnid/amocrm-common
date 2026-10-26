<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
final readonly class ErrorReason
{
    private const SERVICES = ['amocrm'=>'amoCRM', 'moysklad'=>'МойСклад', 'flowwow'=>'Flowwow', 'external'=>'внешний сервис', 'internal'=>'приложение', 'mail'=>'почтовый сервер'];
    private const CODES = ['field_mapping','photo_unavailable','data_type','value_error','database_error','internal_error','request_rejected','access_rejected','rate_limited','unavailable','timeout','connection_failed','http_error','object_not_found','multiple_contacts','processing_error','command_failed','mail_connection_failed','mail_configuration_error'];
    public function __construct(public string $code, public string $service = 'internal', public ?int $httpStatus = null, public ?int $exitCode = null, public ?string $safeDetail = null)
    {
        if (!in_array($code, self::CODES, true) || !isset(self::SERVICES[$service]) || ($httpStatus !== null && ($httpStatus < 400 || $httpStatus > 599))) throw new \InvalidArgumentException('Unknown notification reason');
        if ($exitCode !== null && ($code !== 'command_failed' || $exitCode < 1 || $exitCode > 255)) throw new \InvalidArgumentException('Invalid command exit status');
        if ($code === 'command_failed' && $exitCode === null) throw new \InvalidArgumentException('Command exit status required');
        if ($safeDetail !== null) ErrorMessage::text($safeDetail,160);
        if ($httpStatus !== null && !in_array($code,['request_rejected','access_rejected','rate_limited','unavailable','http_error','object_not_found'],true)) throw new \InvalidArgumentException('Reason has no HTTP status');
        if ($httpStatus !== null && (($code==='access_rejected' && !in_array($httpStatus,[401,403],true)) || ($code==='rate_limited' && $httpStatus!==429) || ($code==='object_not_found' && $httpStatus!==404) || ($code==='unavailable' && $httpStatus<500))) throw new \InvalidArgumentException('Reason and HTTP status disagree');
        if (in_array($code, ['request_rejected','access_rejected','rate_limited','unavailable','timeout','connection_failed','http_error','object_not_found'], true) && $service === 'internal') throw new \InvalidArgumentException('External notification service required');
    }
    public function text(): string
    {
        $service = self::SERVICES[$this->service];
        $status = $this->httpStatus === null ? '' : ' ('.$this->httpStatus.')';
        return match ($this->code) {
            'field_mapping'=>'Ошибка сопоставления полей',
            'photo_unavailable'=>'Фото недоступно',
            'data_type'=>'Ошибка формата данных',
            'value_error'=>'Некорректное значение',
            'database_error'=>'Ошибка базы данных',
            'internal_error'=>'Внутренняя ошибка обработки',
            'request_rejected'=>$service.' отклонил'.($this->service === 'amocrm' ? 'а' : '').' запрос'.$status,
            'access_rejected'=>$service.' отклонил'.($this->service === 'amocrm' ? 'а' : '').' доступ'.$status,
            'rate_limited'=>'Превышен лимит запросов к '.$service,
            'unavailable'=>'Нет подтверждения, '.$service.' недоступ'.($this->service === 'amocrm' ? 'на' : 'ен'),
            'timeout'=>'Нет подтверждения, '.$service.' не ответил'.($this->service === 'amocrm' ? 'а' : '').' вовремя',
            'connection_failed'=>'Нет соединения с '.match ($this->service) {'moysklad'=>'МойСкладом','external'=>'внешним сервисом',default=>$service},
            'http_error'=>$service.' вернул'.($this->service === 'amocrm' ? 'а' : '').' ошибку'.$status,
            'object_not_found'=>'Объект не найден в '.$service,
            'multiple_contacts'=>'Найдено несколько контактов с одним телефоном',
            'processing_error'=>$this->safeDetail??'не установлена по безопасным данным',
            'command_failed'=>'Команда завершилась с кодом '.$this->exitCode,
            'mail_connection_failed'=>'Нет соединения с почтовым сервером',
            'mail_configuration_error'=>'Ошибка настройки подключения к почте',
        };
    }
}
