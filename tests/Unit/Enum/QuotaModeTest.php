<?php

use Platform\Enum\QuotaMode;

test('QuotaMode a les bonnes valeurs', function () {
    expect(QuotaMode::None->value)->toBe('none');
    expect(QuotaMode::Request->value)->toBe('request');
    expect(QuotaMode::FormSubmit->value)->toBe('form_submit');
    expect(QuotaMode::ApiCall->value)->toBe('api_call');
});

test('QuotaMode::estSuivi retourne false pour None', function () {
    expect(QuotaMode::None->estSuivi())->toBeFalse();
});

test('QuotaMode::estSuivi retourne true pour les modes actifs', function () {
    expect(QuotaMode::Request->estSuivi())->toBeTrue();
    expect(QuotaMode::FormSubmit->estSuivi())->toBeTrue();
    expect(QuotaMode::ApiCall->estSuivi())->toBeTrue();
});

test('QuotaMode::tryFrom gère les valeurs inconnues', function () {
    expect(QuotaMode::tryFrom('none'))->toBe(QuotaMode::None);
    expect(QuotaMode::tryFrom('inexistant'))->toBeNull();
});
