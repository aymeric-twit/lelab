<?php

use Platform\Service\EmailTemplate;

test('il devrait rendre un template email avec des variables', function () {
    $html = EmailTemplate::rendre('verification-email', [
        'username' => 'testuser',
        'lien' => 'https://example.com/verify?token=abc123',
        'expiration' => 24,
    ]);

    expect($html)->toContain('testuser');
    expect($html)->toContain('https://example.com/verify?token=abc123');
    expect($html)->toContain('24');
    expect($html)->toContain('Le lab');
});

test('il devrait rendre le template password-reset', function () {
    $html = EmailTemplate::rendre('password-reset', [
        'username' => 'john',
        'lien' => 'https://example.com/reset?token=xyz',
        'expiration' => 1,
    ]);

    expect($html)->toContain('john');
    expect($html)->toContain('https://example.com/reset?token=xyz');
    expect($html)->toContain('Réinitialisation');
});

test('il devrait lever une exception pour un template inexistant', function () {
    EmailTemplate::rendre('template-inexistant');
})->throws(RuntimeException::class);

test('il devrait échapper les caractères HTML dans les variables', function () {
    $html = EmailTemplate::rendre('verification-email', [
        'username' => '<script>alert("xss")</script>',
        'lien' => 'https://example.com',
        'expiration' => 24,
    ]);

    expect($html)->not->toContain('<script>alert("xss")</script>');
    expect($html)->toContain('&lt;script&gt;');
});
