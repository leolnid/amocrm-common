<?php

namespace Leolnid\Common\Http\Middleware\Request;

use Closure;
use Illuminate\Http\Request;
use Throwable;

class AddToContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            logger()->withContext([
                'route' => [
                    'name' => $request->route()?->getName(),
                    'path' => $request->path(),
                ]
            ]);
        } catch (Throwable) {
        }

        return $next($request);
    }
}
