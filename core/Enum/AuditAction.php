<?php

namespace Platform\Enum;

enum AuditAction: string
{
    case LoginSuccess = 'login.success';
    case LoginFailed = 'login.failed';
    case UserCreate = 'user.create';
    case UserUpdate = 'user.update';
    case UserDelete = 'user.delete';
    case AccessUpdate = 'access.update';
    case QuotasUpdate = 'quotas.update';
    case PluginInstall = 'plugin.install';
    case PluginUpdate = 'plugin.update';
    case PluginUninstall = 'plugin.uninstall';
}
