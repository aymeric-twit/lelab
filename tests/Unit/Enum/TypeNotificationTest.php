<?php

use Platform\Enum\TypeNotification;

test('TypeNotification a 9 cas', function () {
    expect(TypeNotification::cases())->toHaveCount(9);
});

test('chaque cas a un label non vide', function () {
    foreach (TypeNotification::cases() as $type) {
        expect($type->label())->toBeString()->not->toBeEmpty();
    }
});

test('chaque cas a un sujet par défaut non vide', function () {
    foreach (TypeNotification::cases() as $type) {
        expect($type->sujetParDefaut())->toBeString()->not->toBeEmpty();
    }
});

test('chaque cas a un template non vide', function () {
    foreach (TypeNotification::cases() as $type) {
        expect($type->template())->toBeString()->not->toBeEmpty();
    }
});

test('chaque cas a une icône non vide', function () {
    foreach (TypeNotification::cases() as $type) {
        expect($type->icone())->toBeString()->toStartWith('bi-');
    }
});

test('chaque cas a des données d\'exemple', function () {
    foreach (TypeNotification::cases() as $type) {
        $donnees = $type->donneesExemple();
        expect($donnees)->toBeArray()->not->toBeEmpty();
        expect($donnees)->toHaveKey('username');
    }
});

test('tryFrom retourne null pour une valeur invalide', function () {
    expect(TypeNotification::tryFrom('inexistant'))->toBeNull();
});

test('tryFrom retourne le bon cas', function () {
    expect(TypeNotification::tryFrom('bienvenue'))->toBe(TypeNotification::Bienvenue);
    expect(TypeNotification::tryFrom('quota_80'))->toBe(TypeNotification::AlerteQuota80);
});
