<?php

namespace Platform\Http\Middleware;

use Platform\Auth\Auth;
use Platform\Http\Request;

class RequireAdmin implements Middleware
{
    public function handle(Request $request, \Closure $next): void
    {
        Auth::requireAdmin();
        $next($request);
    }
}
