<?php

namespace Platform\Http\Middleware;

use Platform\Auth\Auth;
use Platform\Http\Request;

class RequireAuth implements Middleware
{
    public function handle(Request $request, \Closure $next): void
    {
        Auth::requireAuth();
        $next($request);
    }
}
