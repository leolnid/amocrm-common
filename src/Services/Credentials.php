<?php
/**
 * Viacheslav Rodionov
 * viacheslav@rodionov.top
 * Date: 06.07.2022
 * Time: 10:56
 */

namespace Leolnid\Common\Services;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Client\LongLivedAccessToken;
use AmoCRM\Exceptions\AmoCRMoAuthApiException;
use Illuminate\Support\Str;
use Throwable;

class Credentials
{
    /**
     * @throws AmoCRMoAuthApiException
     * @throws Throwable
     */
    public static function getAndSaveToken(string $code, string $domain = null): void
    {
        $apiClient = self::makeApiClient($domain);
        $token = $apiClient->getOAuthClient()->getAccessTokenByCode($code);
        CredentialsToken::save($token, $domain);
    }

    /**
     * @throws Throwable
     */
    private static function makeApiClient(string $domain = null): AmoCRMApiClient
    {
        $configPath = self::getConfigPath($domain);
        throw_if(empty(config("$configPath.domain")), "Значение конфига для работы с amoCRM не было инициализировано");

        return (new AmoCRMApiClient(
            config("$configPath.client_id"),
            config("$configPath.client_secret"),
            config("$configPath.redirect_url")
        ))->setAccountBaseDomain(config("$configPath.domain"));
    }

    protected static function getConfigPath(array|string|null $domain): string
    {
        return is_null($domain) ? 'amocrm.common.credentials' : "amocrm.$domain.credentials";
    }

    /**
     * @throws Throwable
     */
    public static function getApiClient(string $domain = null): AmoCRMApiClient
    {
        if (!is_null($domain)) $domain = Str::replace('.', '_', $domain);
        $configPath = self::getConfigPath($domain);

        $apiClient = self::makeApiClient($domain);

        if (!is_null(config("$configPath.token"))) {
            $apiClient->setAccessToken(new LongLivedAccessToken(config("$configPath.token")));
            return $apiClient;
        }

        $apiClient->setAccessToken(CredentialsToken::get());
        $apiClient->onAccessTokenRefresh(fn($accessToken) => CredentialsToken::save($accessToken, $domain));
        return $apiClient;
    }
}
