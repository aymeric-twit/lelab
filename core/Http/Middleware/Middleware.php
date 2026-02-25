<?php

namespace Platform\Http\Middleware;

use Platform\Http\Request;

interface Middleware
{
    /**
     * Exécute le middleware. Appeler $next($request) pour passer au suivant.
     *
     * @param \Closure(Request): void $next
     */
    public function handle(Request $request, \Closure $next): void;
}
