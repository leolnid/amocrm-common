<?php

namespace Leolnid\Common\Services;

use Exception;
use Illuminate\Support\Facades\File;
use League\OAuth2\Client\Token\AccessToken;

class CredentialsToken
{
    /**
     * @throws Exception
     */
    public static function get(?string $domain = null): AccessToken
    {
        if (! File::exists(self::getPath($domain))) {
            throw new Exception('Авторизационные данные не предоставлены!');
        }

        $token = json_decode(File::get(self::getPath($domain)), true);

        return new AccessToken($token);
    }

    protected static function getPath(?string $domain = null): string
    {
        if (! is_null($domain)) {
            return storage_path("app/tokens/{$domain}.json");
        }

        return storage_path('app/token.json');
    }

    public static function save(AccessToken $token, ?string $domain = null): void
    {
        $array = [
            'access_token' => $token->getToken(),
            'refresh_token' => $token->getRefreshToken(),
            'expires' => $token->getExpires(),
        ];

        File::put(self::getPath($domain), json_encode($array, JSON_PRETTY_PRINT));
    }
}
