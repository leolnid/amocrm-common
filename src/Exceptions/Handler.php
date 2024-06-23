<?php

namespace Leolnid\Common\Exceptions;

use AmoCRM\Exceptions\AmoCRMApiErrorResponseException;
use AmoCRM\Exceptions\AmoCRMApiException;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelIgnition\Facades\Flare;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use function Sentry\addBreadcrumb;
use function Sentry\captureException;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->reportable(function (Throwable|Exception $e) {
//            if (App::isLocal()) dump($e);
//            if (App::isProduction()) logger()->error(
//                $e->getMessage(),
//                array_merge(
//                    $this->exceptionContext($e),
//                    $this->context(),
//                )
//            );
        });

        $this->reportable(function (Throwable $e) {
            try {
                $this->addSentryBreadcrumbs($e);
                captureException($e);
            } catch (Throwable) {

            }
        });

        $this->renderable(function (HttpException $e) {
            if (Request::wantsJson()) {
                return response()->json([
                    'success' => false,
                    'data' => $this->convertExceptionToArray($e),
                    'time' => microtime(true) - LARAVEL_START,
                ], $e->getStatusCode());
            }

            return null;
        });

        $this->renderable(function (ValidationException $e) {
            if (Request::wantsJson()) {
                return response()->json([
                    'success' => false,
                    'data' => $this->convertExceptionToArray($e),
                    'time' => microtime(true) - LARAVEL_START,
                ], 422);
            }

            return null;
        });
    }

    public function exceptionContext(Throwable $e): array
    {
        return ['exception' => $this->convertExceptionToArray($e)];
    }

    public function convertExceptionToArray(Throwable $e): array
    {
        return [
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile().':'.$e->getLine(),
            ...(is_null($e->getPrevious()) ? [] : ['previous' => $this->convertExceptionToArray($e->getPrevious())]),
            ...($this->getExceptionContext($e)),
        ];
    }

    public function getExceptionContext(Throwable $e): array
    {
        $result = [];

        if ($e instanceof AmoCRMApiException) {
            $context = Arr::except($e->getLastRequestInfo() ?? [], ['curl_call', 'jquery_call']);
            $result = array_merge($result, ['last_request_info' => $context]);
            $this->addFlareContext('last_request_info', $context);
        }

        if ($e instanceof AmoCRMApiErrorResponseException) {
            $context = Arr::except($e->getValidationErrors() ?? [], []);
            $this->addFlareContext('last_response_info', Arr::except($e->getValidationErrors() ?? [], []));
            $result = array_merge($result, ['last_response_info' => $context]);
        }

        return $result;
    }

    public function addFlareContext(string $key, array $context): void
    {
        try {
            Flare::context($key, $context);
        } catch (Throwable) {

        }
    }

    public function addSentryBreadcrumbs(Throwable $e): void
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
        if (! is_null($previous)) {
            $this->addSentryBreadcrumbs($previous);
        }
    }
}
