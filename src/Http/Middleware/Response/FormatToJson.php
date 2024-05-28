<?php

namespace Leolnid\Common\Http\Middleware\Response;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class FormatToJson
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! ($response instanceof JsonResponse)) {
            return $response;
        }

        $data = $response->getData(true);
        if (is_string($data)) {
            $data = ['data' => $data];
        }
        $content = Arr::get($data, 'data', Arr::except($data, ['success', 'time']));
        if (is_string($content)) {
            $content = ['message' => $content];
        }
        if (! is_array($content)) {
            $content = ['_warning' => 'Unsupported content type was returned', 'value' => $content];
        }

        return $response->setData([
            'success' => Arr::get($data, 'success', $response->isSuccessful()),
            'data' => $content,
            'time' => microtime(true) - LARAVEL_START,
        ]);
    }
}
