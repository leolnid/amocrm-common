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
use League\OAuth2\Client\Token\AccessTokenInterface;
use RuntimeException;
use Throwable;

class Credentials
{
    /**
     * @throws AmoCRMoAuthApiException
     * @throws Throwable
     */
    public static function getAndSaveToken(string $code, string $domain = null): void
    {
        $apiClient = self::makeRefreshableApiClient($domain);
        $token = $apiClient->getOAuthClient()->getAccessTokenByCode($code);
        CredentialsToken::save($token, $domain);
    }

    /**
     * @throws Throwable
     */
    private static function makeRefreshableApiClient(string $domain = null): AmoCRMApiClient
    {
        $configPath = self::getConfigPath($domain);

        foreach (['client_id', 'client_secret', 'redirect_url', 'domain'] as $param)
            throw_if(is_null(config("$configPath.$param")), "Значение конфига для работы с amoCRM не было инициализировано: $param");


        $clientId = config("$configPath.client_id");
        $clientSecret = config("$configPath.client_secret");
        $redirectUri = config("$configPath.redirect_url");

        if (empty($clientId) || empty($clientSecret) || empty($redirectUri)) {
            throw new RuntimeException("Значение конфига для работы с amoCRM не было инициализировано");
        }

        return (new AmoCRMApiClient($clientId, $clientSecret, $redirectUri))
            ->setAccountBaseDomain(config("$configPath.domain"));
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

        if (!is_null(config("$configPath.token"))) {
            return (new AmoCRMApiClient())
                ->setAccountBaseDomain(config("$configPath.domain"))
                ->setAccessToken(new LongLivedAccessToken(config("$configPath.token")));
        }

        return self::makeRefreshableApiClient($domain)
            ->setAccessToken(CredentialsToken::get())
            ->onAccessTokenRefresh(fn(AccessTokenInterface $accessToken) => CredentialsToken::save($accessToken, $domain));
    }
}
