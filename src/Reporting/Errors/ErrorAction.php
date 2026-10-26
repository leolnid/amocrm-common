<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Errors;
final readonly class ErrorAction
{
    private const ACTIONS = ['lead_create'=>'Сделка не создана','photo_upload'=>'Фото не загружено','lead_process'=>'Обработка сделки не завершена','module_process'=>'Обработка модуля не завершена','frame_open'=>'Карточка бонусов не открыта','contact_update'=>'Контакт не обновлён','expiry_notification'=>'Уведомление об истекающих бонусах не обработано','customer_order_create'=>'Заказ покупателя не создан','lead_load'=>'Сделка не получена','missing_delivery_date_note'=>'Комментарий об отсутствии даты доставки не добавлен','next_sale_date_update'=>'Дата следующей покупки не обновлена','next_sale_note'=>'Комментарий об обновлении даты не добавлен','move_leads_load'=>'Сделки для перемещения не получены','lead_move'=>'Сделка не перемещена','test'=>'Проверка оповещений','unknown_operation'=>'Операция приложения не завершена'];
    private function __construct(public string $code, private string $label, private ?string $registeredLabel = null) {}
    public static function known(string $code): self
    {
        if (!isset(self::ACTIONS[$code])) throw new \InvalidArgumentException('Unknown notification action');
        return new self($code, self::ACTIONS[$code]);
    }
    public static function unknown(): self { return new self('unknown_operation',self::ACTIONS['unknown_operation']); }
    /** The application supplies its fixed source-registered operation catalog, never exception text. */
    public static function registered(string $code, array $catalog): self
    {
        $label = $catalog[$code] ?? null;
        if (!preg_match('~^[A-Za-z0-9_\\\\:@./(){} -]{1,200}$~D', $code) || !is_string($label) || !preg_match('/^[\p{L}\p{N} ,().\/-]{1,100}$/uD', $label)) throw new \InvalidArgumentException('Unregistered notification action');
        return new self($code, 'Не удалось выполнить действие «'.$label.'»',$label);
    }
    public function text(?ErrorReason $reason = null): string
    {
        if ($reason !== null && (in_array($reason->code,['timeout','connection_failed','unavailable'],true) || ($reason->httpStatus !== null && $reason->httpStatus >= 500))) {
            if ($this->registeredLabel !== null) return 'Результат действия «'.$this->registeredLabel.'» не подтверждён';
            return match ($this->code) {
                'lead_create'=>'Создание сделки не подтверждено',
                'customer_order_create'=>'Создание заказа покупателя не подтверждено',
                'photo_upload'=>'Загрузка фото не подтверждена',
                'contact_update'=>'Обновление контакта не подтверждено',
                'next_sale_date_update'=>'Обновление даты следующей покупки не подтверждено',
                'next_sale_note','missing_delivery_date_note'=>'Добавление комментария не подтверждено',
                'lead_move'=>'Перемещение сделки не подтверждено',
                'module_process'=>'Результат обработки модуля не подтверждён',
                'lead_process'=>'Результат обработки сделки не подтверждён',
                'expiry_notification'=>'Результат обработки уведомления не подтверждён',
                default=>$this->label,
            };
        }
        return $this->label;
    }
}
