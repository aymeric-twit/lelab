<?php

namespace Platform\Http\Middleware;

use Platform\Http\Csrf;
use Platform\Http\Request;

class VerifyCsrf implements Middleware
{
    public function handle(Request $request, \Closure $next): void
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            Csrf::validateOrAbort();
        }
        $next($request);
    }
}
