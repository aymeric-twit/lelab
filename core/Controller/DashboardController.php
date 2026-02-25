<?php

namespace Platform\Controller;

use Platform\Auth\Auth;
use Platform\Module\Quota;
use Platform\User\AccessControl;
use Platform\View\Layout;

class DashboardController
{
    public function index(): void
    {
        $user = Auth::user();
        $ac = new AccessControl();
        $modules = $ac->getAccessibleModules($user['id']);
        $quotaSummary = Quota::getUserQuotaSummary($user['id']);

        Layout::render('layout', [
            'template'          => 'dashboard',
            'pageTitle'         => 'Dashboard',
            'currentUser'       => $user,
            'accessibleModules' => $modules,
            'activeModule'      => '',
            'quotaSummary'      => $quotaSummary,
        ]);
    }
}
