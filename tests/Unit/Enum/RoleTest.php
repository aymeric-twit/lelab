<?php

use Platform\Enum\Role;

test('Role admin a la valeur "admin"', function () {
    expect(Role::Admin->value)->toBe('admin');
});

test('Role user a la valeur "user"', function () {
    expect(Role::User->value)->toBe('user');
});

test('Role::tryFrom retourne le bon enum', function () {
    expect(Role::tryFrom('admin'))->toBe(Role::Admin);
    expect(Role::tryFrom('user'))->toBe(Role::User);
    expect(Role::tryFrom('inconnu'))->toBeNull();
});

test('Role::label retourne un libellé français', function () {
    expect(Role::Admin->label())->toBe('Administrateur');
    expect(Role::User->label())->toBe('Utilisateur');
});
