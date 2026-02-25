<?php

use Platform\Enum\AuditAction;

test('AuditAction contient les cas plugin', function () {
    expect(AuditAction::PluginInstall->value)->toBe('plugin.install');
    expect(AuditAction::PluginUpdate->value)->toBe('plugin.update');
    expect(AuditAction::PluginUninstall->value)->toBe('plugin.uninstall');
});

test('AuditAction::tryFrom gère les valeurs plugin', function () {
    expect(AuditAction::tryFrom('plugin.install'))->toBe(AuditAction::PluginInstall);
    expect(AuditAction::tryFrom('plugin.update'))->toBe(AuditAction::PluginUpdate);
    expect(AuditAction::tryFrom('plugin.uninstall'))->toBe(AuditAction::PluginUninstall);
});
