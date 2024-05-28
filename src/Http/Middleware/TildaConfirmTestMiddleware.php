<?php

namespace Leolnid\Common\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TildaConfirmTestMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->post('test') == 'test') {
            return response()->json([]);
        }

        return $next($request);
    }
}
