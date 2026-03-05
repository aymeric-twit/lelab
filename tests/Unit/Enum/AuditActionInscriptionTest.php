<?php

use Platform\Enum\AuditAction;

test('AuditAction contient les cas inscription et auth', function () {
    expect(AuditAction::Inscription->value)->toBe('inscription');
    expect(AuditAction::PasswordResetRequest->value)->toBe('password_reset.request');
    expect(AuditAction::PasswordResetComplete->value)->toBe('password_reset.complete');
    expect(AuditAction::EmailVerified->value)->toBe('email.verified');
});

test('AuditAction::tryFrom gère les nouvelles valeurs', function () {
    expect(AuditAction::tryFrom('inscription'))->toBe(AuditAction::Inscription);
    expect(AuditAction::tryFrom('password_reset.request'))->toBe(AuditAction::PasswordResetRequest);
    expect(AuditAction::tryFrom('password_reset.complete'))->toBe(AuditAction::PasswordResetComplete);
    expect(AuditAction::tryFrom('email.verified'))->toBe(AuditAction::EmailVerified);
});

test('les nouveaux cas ont un label', function () {
    expect(AuditAction::Inscription->label())->toBe('Inscription');
    expect(AuditAction::PasswordResetRequest->label())->toBe('Demande de réinitialisation');
    expect(AuditAction::PasswordResetComplete->label())->toBe('Mot de passe réinitialisé');
    expect(AuditAction::EmailVerified->label())->toBe('Email vérifié');
});

test('les nouveaux cas ont une icône', function () {
    expect(AuditAction::Inscription->icone())->toContain('bi-');
    expect(AuditAction::PasswordResetRequest->icone())->toContain('bi-');
    expect(AuditAction::PasswordResetComplete->icone())->toContain('bi-');
    expect(AuditAction::EmailVerified->icone())->toContain('bi-');
});
