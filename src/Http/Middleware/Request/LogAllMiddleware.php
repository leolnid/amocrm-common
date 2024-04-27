<?php

namespace Leolnid\Common\Http\Middleware\Request;

use Closure;
use Illuminate\Http\Request;

class LogAllMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $routeName = $request->route()->getName() ?: 'no-name';
        logger()->debug("Получили запрос $routeName", $request->all());

        return $next($request);
    }
}
