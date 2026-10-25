<?php
declare(strict_types=1);
namespace Leolnid\Common\Reporting\Capture;
use Leolnid\Common\Reporting\Delivery\{NotificationTransport,TelegramTopicTransport};
abstract class LaravelTelegramTransport implements NotificationTransport
{
    protected const SDK_CLASS='';
    protected const HTTP_CLIENT_CLASS='';
    protected const APPROVED_CHAT_ID='';
    public function send(array $payload): array
    {
        if (static::APPROVED_CHAT_ID==='' || (string)config('app_errors.telegram.chat_id')!==static::APPROVED_CHAT_ID) throw new \RuntimeException('Unapproved technical destination');
        $transport=new TelegramTopicTransport(static::APPROVED_CHAT_ID,(int)config('app_errors.telegram.message_thread_id'),fn(array $body):array=>$this->sdkSend($body),static fn():bool=>(bool)config('app_errors.enabled'),static fn():bool=>is_file(storage_path('app/error-notifications/.delivery-paused')));
        return $transport->send($payload);
    }
    private function sdkSend(array $payload): array
    {
        $sdk=static::SDK_CLASS;$http=static::HTTP_CLIENT_CLASS;
        if (!is_string($sdk)||$sdk===''||!is_string($http)||$http==='') throw new \LogicException('Explicit existing Telegram SDK adapter required');
        $client=new $http(['timeout'=>5,'connect_timeout'=>2,'allow_redirects'=>false]);
        $response=(new $sdk((string)config('app_errors.telegram.bot_token'),$client))->sendMessage($payload);
        return json_decode((string)$response->getBody(),true,512,JSON_THROW_ON_ERROR);
    }
}
