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
        // Match the app's established Telegram relay config contract. Reading
        // Laravel config (rather than env()) also works with cached config.
        // Prefer the reporting-only namespace so configuring this transport
        // cannot redirect Laravel's native Telegram SDK or notification channel.
        // Keep the previous services key as a compatibility fallback.
        $apiBaseUri=config('app_errors.telegram.base_uri') ?? config('services.telegram-bot-api.base_uri');
        if ($apiBaseUri!==null && (!is_string($apiBaseUri) || $apiBaseUri==='')) throw new \LogicException('Invalid Telegram API base URI configuration');
        $baseParts=null;
        if (is_string($apiBaseUri)) {
            $baseParts=parse_url($apiBaseUri);
            if (!is_array($baseParts) || strtolower((string)($baseParts['scheme']??''))!=='https' || empty($baseParts['host']) || isset($baseParts['user']) || isset($baseParts['pass']) || isset($baseParts['query']) || isset($baseParts['fragment'])) throw new \LogicException('Invalid Telegram HTTPS base URI configuration');
        }
        $relayToken=config('app_errors.telegram.relay_token') ?? config('services.telegram-bot-api.relay_token');
        if ($relayToken!==null && !is_string($relayToken)) throw new \LogicException('Invalid Telegram relay credential configuration');
        $headers=[];
        if (is_string($relayToken) && $relayToken!=='') {
            if (!is_array($baseParts)) throw new \LogicException('Telegram relay credential requires an explicit HTTPS relay base URI');
            if (rtrim(strtolower((string)$baseParts['host']),'.')==='api.telegram.org') throw new \LogicException('Telegram relay credential cannot be sent to the official API origin');
            $headers['X-Service-Token']=$relayToken;
        }
        $client=new $http(['timeout'=>5,'connect_timeout'=>2,'allow_redirects'=>false,'headers'=>$headers]);
        $response=(new $sdk((string)config('app_errors.telegram.bot_token'),$client,$apiBaseUri))->sendMessage($payload);
        return json_decode((string)$response->getBody(),true,512,JSON_THROW_ON_ERROR);
    }
}
