<?php

namespace Leolnid\Common\Services;

use App;
use Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;
use Throwable;

class TelegramLogger
{
    public static function debug(string $message, ?array $context = null)
    {
        return self::log(LogLevel::DEBUG, $message, $context);
    }

    public static function log(string $level, string $message, ?array $context = null): bool
    {
        try {
            Http::createPendingRequest()
                ->timeout(5)
                ->asJson()
                ->baseUrl(config('telegram-logger.api_host') . '/bot' . config('telegram-logger.token'))
                ->post('/sendMessage', [
                    'chat_id' => config('telegram-logger.chat_id'),
                    'text' => view('laravel-telegram-logging::standard', [
                        'level' => $level,
                        'level_name' => $level,
                        'message' => $message,
                        'context' => $context,
                        'appEnv' => App::environment(),
                        'appName' => config('app.name'),
                    ])->render(),
                    'parse_mode' => 'HTML',
                    ...config('telegram-logger.options'),
                ]);
        } catch (Throwable $e) {
            try {
                Log::channel('single')->error('TelegramLogger: '.$e->getMessage(), ['exception' => $e]);
            } catch (Throwable) {
                error_log('TelegramLogger: '.$e->getMessage());
            }
        }

        return true;
    }

    public static function warn(string $message, ?array $context = null)
    {
        return self::log(LogLevel::WARNING, $message, $context);
    }

    public static function error(string $message, ?array $context = null)
    {
        return self::log(LogLevel::ERROR, $message, $context);
    }

    public static function info(string $message, ?array $context = null)
    {
        return self::log(LogLevel::INFO, $message, $context);
    }
}