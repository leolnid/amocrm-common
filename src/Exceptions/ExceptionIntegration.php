<?php

namespace Leolnid\Common\Exceptions;

use AmoCRM\Exceptions\AmoCRMApiErrorResponseException;
use AmoCRM\Exceptions\AmoCRMApiException;
use Exception;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelIgnition\Facades\Flare;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use function Sentry\addBreadcrumb;
use function Sentry\captureException;

class ExceptionIntegration
{
    /**
     * Convenience method to register the exception handler with Laravel 11.0 and up.
     */
    public static function handles(Exceptions $exceptions): void
    {
        $exceptions->reportable(function (Throwable|Exception $e) {
            if (App::isLocal()) dump($e);
        });

        $exceptions->reportable(function (Throwable $e) {
            try {
                try {
                    self::addSentryBreadcrumbs($e);
                } catch (Throwable) {
                    // Breadcrumbs не должны блокировать отправку в Sentry
                }
                captureException($e);
            } catch (Throwable $sentryFailure) {
                // Если Sentry не смог принять (сеть, DSN, SDK) — пишем в emergency, чтобы не терять след
                try {
                    \Log::channel('emergency')->emergency('Sentry capture failed', [
                        'sentry_failure' => $sentryFailure->getMessage(),
                        'sentry_failure_class' => get_class($sentryFailure),
                        'original_exception' => $e->getMessage(),
                        'original_class' => get_class($e),
                    ]);
                } catch (Throwable) {
                    // emergency-лог недоступен — ничего не делаем
                }
            }
        });

        $exceptions->renderable(function (HttpException $e) {
            if (Request::wantsJson()) {
                return response()->json([
                    'success' => false,
                    'data' => self::convertExceptionToArray($e),
                    'time' => microtime(true) - LARAVEL_START,
                ], $e->getStatusCode());
            }

            return null;
        });

        $exceptions->renderable(function (ValidationException $e) {
            if (Request::wantsJson()) {
                return response()->json([
                    'success' => false,
                    'data' => self::convertExceptionToArray($e),
                    'time' => microtime(true) - LARAVEL_START,
                ], 422);
            }

            return null;
        });
    }

    public static function addSentryBreadcrumbs(Throwable $e): void
    {
        if ($e instanceof AmoCRMApiException) {
            $context = Arr::except($e->getLastRequestInfo() ?? [], ['curl_call', 'jquery_call']);
            addBreadcrumb('amocrm.request', 'AmoCRMApiException', $context);
        }

        if ($e instanceof AmoCRMApiErrorResponseException) {
            $context = Arr::except($e->getValidationErrors() ?? [], []);
            addBreadcrumb('amocrm.response', 'AmoCRMApiException', $context);
        }

        $previous = $e->getPrevious();
        if (!is_null($previous)) {
            self::addSentryBreadcrumbs($previous);
        }
    }

    public static function convertExceptionToArray(Throwable $e): array
    {
        return [
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile() . ':' . $e->getLine(),
            ...(is_null($e->getPrevious()) ? [] : ['previous' => self::convertExceptionToArray($e->getPrevious())]),
            ...(self::getExceptionContext($e)),
        ];
    }

    public static function getExceptionContext(Throwable $e): array
    {
        $result = [];

        if ($e instanceof AmoCRMApiException) {
            $context = Arr::except($e->getLastRequestInfo() ?? [], ['curl_call', 'jquery_call']);
            $result = array_merge($result, ['last_request_info' => $context]);
            self::addFlareContext('last_request_info', $context);
        }

        if ($e instanceof AmoCRMApiErrorResponseException) {
            $context = Arr::except($e->getValidationErrors() ?? [], []);
            self::addFlareContext('last_response_info', Arr::except($e->getValidationErrors() ?? [], []));
            $result = array_merge($result, ['last_response_info' => $context]);
        }

        return $result;
    }

    public static function addFlareContext(string $key, array $context): void
    {
        try {
            Flare::context($key, $context);
        } catch (Throwable) {

        }
    }

    public static function exceptionContext(Throwable $e): array
    {
        return ['exception' => self::convertExceptionToArray($e)];
    }
}
