<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Auth\PasswordHasher;
use Platform\Enum\AuditAction;
use Platform\Http\Csrf;
use Platform\Http\Request;
use Platform\Http\Response;
use Platform\Service\AuditLogger;
use Platform\View\Flash;
use Platform\View\Layout;

class AuthController
{
    public function formulaireLogin(): void
    {
        if (Auth::check()) {
            Response::redirect('/');
        }
        Layout::renderStandalone('login');
    }

    public function login(Request $req): void
    {
        Csrf::validateOrAbort();

        $ip = $req->ip();
        $username = trim($req->post('username', ''));
        $password = $req->post('password', '');
        $audit = AuditLogger::instance();

        if (Auth::isRateLimited($ip)) {
            Flash::error('Trop de tentatives. Réessayez dans 15 minutes.');
            Response::redirect('/login');
        }

        if (Auth::attempt($username, $password)) {
            $audit->log(AuditAction::LoginSuccess, $ip, Auth::id(), 'user', null, ['username' => $username]);
            Response::redirect('/');
        }

        $audit->log(AuditAction::LoginFailed, $ip, null, 'user', null, ['username' => $username]);
        Flash::error('Identifiants incorrects.');
        Response::redirect('/login');
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect('/login');
    }
}
