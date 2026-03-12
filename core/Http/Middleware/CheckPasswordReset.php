<?php

namespace Platform\Http\Middleware;

use Platform\Auth\Auth;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\View\Flash;

class CheckPasswordReset implements Middleware
{
    public function handle(Request $request, \Closure $next): void
    {
        $user = Auth::user();

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

        if (
            $user
            && !empty($user['force_password_reset'])
            && !str_starts_with($uri, '/mon-compte')
            && $uri !== '/logout'
        ) {
            Flash::warning('Vous devez changer votre mot de passe.');
            Response::redirect('/mon-compte#mot-de-passe');
        }

        $next($request);
    }
}
